-- Standalone team handoff: merge the event app's `users` accounts into the
-- booth team's `admins` table, then remove the `users` table.
-- Use this when the target database still has both original tables. Do not run
-- it on the database that already completed this merge.
-- Target: MariaDB/MySQL db_gdgevents matching database/gdg_bacolod_synced_schema.sql.
-- Make a database backup first. This migration is one-time and performs DDL.
-- Existing booth Admin credentials are retained and become active event-app Admins.
-- Existing event-app users are copied with their role/status/password hash intact.
-- Their old IDs are remapped to avoid collisions; dependent history/token references
-- are remapped too. The temporary ID map and source `users` table are dropped after
-- a successful merge, leaving `admins` as the only shared account table.
--
-- Before running: inspect the booth application's login/write code. It must filter
-- `admins.role = 'admin' AND is_active = 1` and use named INSERT columns.
-- The supplied SQL dump contains schema only, so this migration cannot verify that app.
-- This script aborts before modifying account tables if usernames collide or an
-- old `users_legacy` archive exists from an earlier migration revision.

USE `db_gdgevents`;

DROP PROCEDURE IF EXISTS `gdg_merge_users_into_admins_20261030`;
DELIMITER $$
CREATE PROCEDURE `gdg_merge_users_into_admins_20261030`()
BEGIN
  DECLARE conflict_count INT DEFAULT 0;
  DECLARE archive_exists INT DEFAULT 0;
  DECLARE id_base BIGINT UNSIGNED DEFAULT 0;
  DECLARE max_user_id BIGINT UNSIGNED DEFAULT 0;

  SELECT COUNT(*) INTO archive_exists
    FROM information_schema.tables
   WHERE table_schema = DATABASE() AND table_name = 'users_legacy';
  IF archive_exists > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'users_legacy already exists; migration may already have run.';
  END IF;
  SELECT COUNT(*) INTO conflict_count
    FROM users AS u
    INNER JOIN admins AS a ON a.username = u.username;
  IF conflict_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Username collision between users and admins. Resolve collisions before migration.';
  END IF;

  SET id_base = (SELECT COALESCE(MAX(id), 0) + 1 FROM admins);
  SELECT COALESCE(MAX(id), 0) INTO max_user_id FROM users;
  IF id_base + max_user_id > 2147483647 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account ID space would exceed admins.id capacity.';
  END IF;

  -- Keep the booth columns and add the fields required by the event app.
  ALTER TABLE admins
    MODIFY COLUMN username VARCHAR(100) NOT NULL,
    ADD COLUMN display_name VARCHAR(150) NOT NULL DEFAULT '' AFTER id,
    ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER username,
    ADD COLUMN role ENUM('admin','staff') NOT NULL DEFAULT 'admin' AFTER password_hash,
    ADD COLUMN is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN created_by_user_id INT(11) DEFAULT NULL AFTER is_active,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER createdAt,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_admins_email (email),
    ADD KEY idx_admins_created_by (created_by_user_id);

  UPDATE admins SET display_name = username, created_at = COALESCE(createdAt, CURRENT_TIMESTAMP);

  CREATE TEMPORARY TABLE gdg_account_id_map (
    old_user_id INT(11) NOT NULL PRIMARY KEY,
    new_admin_id INT(11) NOT NULL UNIQUE
  ) ENGINE=InnoDB;
  INSERT INTO gdg_account_id_map (old_user_id, new_admin_id)
    SELECT id, id_base + id FROM users;

  -- Remove old account foreign keys before remapping their referenced IDs.
  ALTER TABLE users DROP FOREIGN KEY fk_users_created_by;
  ALTER TABLE checkin_corrections DROP FOREIGN KEY fk_checkin_corrections_user;
  ALTER TABLE event_print_templates DROP FOREIGN KEY fk_event_print_templates_user;
  ALTER TABLE print_jobs DROP FOREIGN KEY fk_print_jobs_user;
  ALTER TABLE import_batches DROP FOREIGN KEY fk_import_batches_user;
  ALTER TABLE password_reset_tokens DROP FOREIGN KEY fk_password_reset_tokens_user;

  INSERT INTO admins
    (id, display_name, username, email, password_hash, role, is_active,
     created_by_user_id, createdAt, created_at, updated_at)
  SELECT m.new_admin_id, u.display_name, u.username, u.email, u.password_hash,
         u.role, u.is_active, creator.new_admin_id, u.created_at, u.created_at, u.updated_at
    FROM users AS u
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = u.id
    LEFT JOIN gdg_account_id_map AS creator ON creator.old_user_id = u.created_by_user_id;

  UPDATE checkin_corrections AS x
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = x.corrected_by_user_id
     SET x.corrected_by_user_id = m.new_admin_id;
  UPDATE event_print_templates AS x
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = x.uploaded_by_user_id
     SET x.uploaded_by_user_id = m.new_admin_id;
  UPDATE print_jobs AS x
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = x.requested_by_user_id
     SET x.requested_by_user_id = m.new_admin_id;
  UPDATE import_batches AS x
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = x.imported_by_user_id
     SET x.imported_by_user_id = m.new_admin_id;
  UPDATE password_reset_tokens AS x
    INNER JOIN gdg_account_id_map AS m ON m.old_user_id = x.user_id
     SET x.user_id = m.new_admin_id;

  ALTER TABLE admins
    ADD CONSTRAINT fk_admins_created_by
      FOREIGN KEY (created_by_user_id) REFERENCES admins (id),
    MODIFY COLUMN display_name VARCHAR(150) NOT NULL DEFAULT '';
  ALTER TABLE checkin_corrections
    DROP INDEX idx_checkin_corrections_user,
    ADD KEY idx_checkin_corrections_admin (corrected_by_user_id),
    ADD CONSTRAINT fk_checkin_corrections_admin
      FOREIGN KEY (corrected_by_user_id) REFERENCES admins (id);
  ALTER TABLE event_print_templates
    ADD KEY idx_event_print_templates_admin (uploaded_by_user_id),
    ADD CONSTRAINT fk_event_print_templates_admin
      FOREIGN KEY (uploaded_by_user_id) REFERENCES admins (id);
  ALTER TABLE print_jobs
    ADD KEY idx_print_jobs_admin (requested_by_user_id),
    ADD CONSTRAINT fk_print_jobs_admin
      FOREIGN KEY (requested_by_user_id) REFERENCES admins (id);
  ALTER TABLE import_batches
    ADD KEY idx_import_batches_admin (imported_by_user_id),
    ADD CONSTRAINT fk_import_batches_admin
      FOREIGN KEY (imported_by_user_id) REFERENCES admins (id);
  ALTER TABLE password_reset_tokens
    ADD CONSTRAINT fk_password_reset_tokens_admin
      FOREIGN KEY (user_id) REFERENCES admins (id);

  DROP TEMPORARY TABLE gdg_account_id_map;
  DROP TABLE users;
END$$
DELIMITER ;

CALL `gdg_merge_users_into_admins_20261030`();
DROP PROCEDURE `gdg_merge_users_into_admins_20261030`;

