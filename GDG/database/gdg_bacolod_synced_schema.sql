
CREATE DATABASE IF NOT EXISTS `db_gdgevents`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_gdgevents`;

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET NAMES utf8mb4;

-- Table structure for table `attendees`
CREATE TABLE IF NOT EXISTS `attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `qr_code_token` varchar(255) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `createdAt` datetime(3) DEFAULT current_timestamp(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `events`
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) NOT NULL,
  `event_date` date DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `booths`
CREATE TABLE IF NOT EXISTS `booths` (
  `id` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sponsorName` varchar(100) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_name` varchar(150) NOT NULL DEFAULT '',
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_created_by` (`created_by_user_id`),
  CONSTRAINT `fk_admins_created_by`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `booth_users`
CREATE TABLE IF NOT EXISTS `booth_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `booth_id` varchar(64) NOT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `unique_booth_id` (`booth_id`),
  KEY `booth_id` (`booth_id`),
  CONSTRAINT `fk_booth_users_booth`
    FOREIGN KEY (`booth_id`) REFERENCES `booths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `event_attendees`
CREATE TABLE IF NOT EXISTS `event_attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `ticket_number` varchar(100) NOT NULL,
  `ticket_title` varchar(255) DEFAULT NULL,
  `ticket_venue` varchar(255) DEFAULT NULL,
  `checkin_status` tinyint(1) DEFAULT 0,
  `checked_in_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  UNIQUE KEY `attendee_event` (`attendee_id`, `event_id`),
  KEY `event_id` (`event_id`),
  KEY `idx_event_attendees_event_checkin` (`event_id`, `checkin_status`),
  CONSTRAINT `fk_event_attendees_attendee`
    FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_attendees_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `scan_logs`
CREATE TABLE IF NOT EXISTS `scan_logs` (
  `id` varchar(64) NOT NULL,
  `attendeeId` int(11) NOT NULL,
  `boothId` varchar(64) NOT NULL,
  `eventId` int(11) NOT NULL,
  `timestamp` datetime(3) DEFAULT current_timestamp(3),
  `points` int(11) DEFAULT 100,
  `category` enum('1-10','11-50','51-90','91+') DEFAULT '1-10',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE_ATTENDEE_BOOTH_EVENT` (`attendeeId`,`boothId`,`eventId`),
  KEY `boothId` (`boothId`),
  KEY `eventId` (`eventId`),
  CONSTRAINT `fk_scan_logs_attendee`
    FOREIGN KEY (`attendeeId`) REFERENCES `attendees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scan_logs_booth`
    FOREIGN KEY (`boothId`) REFERENCES `booths` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scan_logs_event`
    FOREIGN KEY (`eventId`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `checkin_corrections`
CREATE TABLE IF NOT EXISTS `checkin_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_attendee_id` int(11) NOT NULL,
  `corrected_by_user_id` int(11) NOT NULL,
  `previous_checkin_status` tinyint(1) unsigned NOT NULL,
  `previous_checked_in_at` datetime DEFAULT NULL,
  `new_checkin_status` tinyint(1) unsigned NOT NULL,
  `new_checked_in_at` datetime DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `corrected_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_checkin_corrections_registration_time` (`event_attendee_id`, `corrected_at`),
  KEY `idx_checkin_corrections_admin` (`corrected_by_user_id`),
  CONSTRAINT `fk_checkin_corrections_registration`
    FOREIGN KEY (`event_attendee_id`) REFERENCES `event_attendees` (`id`),
  CONSTRAINT `fk_checkin_corrections_admin`
    FOREIGN KEY (`corrected_by_user_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `chk_checkin_corrections_previous_status` CHECK (`previous_checkin_status` IN (0, 1)),
  CONSTRAINT `chk_checkin_corrections_new_status` CHECK (`new_checkin_status` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `event_print_templates`
CREATE TABLE IF NOT EXISTS `event_print_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `print_type` enum('name_tag','certificate') NOT NULL,
  `template_path` varchar(500) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `width_mm` decimal(7,2) DEFAULT NULL,
  `height_mm` decimal(7,2) DEFAULT NULL,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `uploaded_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name_area_x_mm` decimal(7,2) DEFAULT NULL,
  `name_area_y_mm` decimal(7,2) DEFAULT NULL,
  `name_area_width_mm` decimal(7,2) DEFAULT NULL,
  `name_area_height_mm` decimal(7,2) DEFAULT NULL,
  `font_size_pt` decimal(5,2) DEFAULT NULL,
  `font_family` varchar(100) DEFAULT NULL,
  `text_alignment` enum('left','center','right') DEFAULT NULL,
  `text_color_hex` char(7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_print_templates_event_type_active` (`event_id`, `print_type`, `is_active`),
  KEY `idx_event_print_templates_admin` (`uploaded_by_user_id`),
  CONSTRAINT `fk_event_print_templates_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `fk_event_print_templates_admin`
    FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `chk_event_print_templates_dimensions`
    CHECK ((`width_mm` IS NULL AND `height_mm` IS NULL) OR (`width_mm` > 0 AND `height_mm` > 0)),
  CONSTRAINT `chk_event_print_templates_name_area`
    CHECK (
      (`name_area_x_mm` IS NULL AND `name_area_y_mm` IS NULL
        AND `name_area_width_mm` IS NULL AND `name_area_height_mm` IS NULL)
      OR
      (`width_mm` IS NOT NULL AND `height_mm` IS NOT NULL
        AND `name_area_x_mm` IS NOT NULL AND `name_area_y_mm` IS NOT NULL
        AND `name_area_width_mm` IS NOT NULL AND `name_area_height_mm` IS NOT NULL
        AND `name_area_x_mm` >= 0 AND `name_area_y_mm` >= 0
        AND `name_area_width_mm` > 0 AND `name_area_height_mm` > 0
        AND `name_area_x_mm` + `name_area_width_mm` <= `width_mm`
        AND `name_area_y_mm` + `name_area_height_mm` <= `height_mm`)
    ),
  CONSTRAINT `chk_event_print_templates_font_size`
    CHECK (`font_size_pt` IS NULL OR `font_size_pt` > 0),
  CONSTRAINT `chk_event_print_templates_text_color`
    CHECK (`text_color_hex` IS NULL OR `text_color_hex` REGEXP '^#[0-9A-Fa-f]{6}$'),
  CONSTRAINT `chk_event_print_templates_is_active` CHECK (`is_active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `print_jobs`
CREATE TABLE IF NOT EXISTS `print_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_attendee_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `print_type` enum('name_tag','certificate') NOT NULL,
  `status` enum('queued','printing','printed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `is_reprint` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `requested_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `failure_message` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_print_jobs_registration_type_time` (`event_attendee_id`, `print_type`, `created_at`),
  KEY `idx_print_jobs_status` (`status`),
  KEY `idx_print_jobs_admin` (`requested_by_user_id`),
  CONSTRAINT `fk_print_jobs_registration`
    FOREIGN KEY (`event_attendee_id`) REFERENCES `event_attendees` (`id`),
  CONSTRAINT `fk_print_jobs_template`
    FOREIGN KEY (`template_id`) REFERENCES `event_print_templates` (`id`),
  CONSTRAINT `fk_print_jobs_admin`
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `chk_print_jobs_is_reprint` CHECK (`is_reprint` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `import_batches`
CREATE TABLE IF NOT EXISTS `import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `imported_by_user_id` int(11) NOT NULL,
  `source_file_name` varchar(255) NOT NULL,
  `status` enum('previewed','completed','completed_with_errors','failed') NOT NULL,
  `rows_seen` int(10) unsigned NOT NULL DEFAULT 0,
  `rows_imported` int(10) unsigned NOT NULL DEFAULT 0,
  `rows_skipped` int(10) unsigned NOT NULL DEFAULT 0,
  `rows_rejected` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_import_batches_event_time` (`event_id`, `created_at`),
  KEY `idx_import_batches_admin` (`imported_by_user_id`),
  CONSTRAINT `fk_import_batches_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `fk_import_batches_admin`
    FOREIGN KEY (`imported_by_user_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `password_reset_tokens`
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user_expiry` (`user_id`, `expires_at`),
  CONSTRAINT `fk_password_reset_tokens_admin`
    FOREIGN KEY (`user_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
