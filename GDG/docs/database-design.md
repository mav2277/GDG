# Database design and review status

## Current evidence

**Current XAMPP database (updated 2026-10-30):** The imported `db_gdgevents` database contains the booth tables plus registration tables, `attendees.nickname`, booth-aligned shared columns, and the foreign keys/indexes needed by current models. Model queries use `attendees` in place of the earlier proposed `attendees_new`. On 2026-10-30, the additive certificate-layout migration added eight layout fields to `event_print_templates`; existing template/job records were preserved. No attendee data was read or changed by the migration. Earlier references below to `gdg_bacolod_checkin` and the `attendees_new` proposal describe a previous schema state and are superseded.

Read-only metadata inspection on 2026-10-30 confirms the local `db_gdgevents` now has `admins` and no `users`, `users_legacy`, or `users_admin_id_map_20261030` table. The six account foreign keys for creator, correction, template, print, import, and token records all reference `admins.id`. The earlier cleanup migration has been applied. The standalone [merge handoff SQL](../database/merge_users_and_admins.sql) is for the other team's unmerged database; on success it leaves `admins` as the shared account table and does not remove booth-specific `booth_users`.

Reviewed the supplied static dump [event_registration (1).sql](../event_registration%20(1).sql) and separately inspected the local `gdg_bacolod_checkin` database read-only. The original dump identifies MariaDB 10.4.32 and contains three tables with sample attendee/event rows. The local imported database contains the tables from [the project schema](../database/gdg_bacolod_schema.sql); its `events` table currently has `id`, `event_name`, `event_date`, `venue`, `created_at`, and `updated_at`, and had zero event rows at inspection time. The sample rows contain personal information, so this document does not repeat it. No existing data was changed during inspection.

## Structure observed

- `attendees`: attendee ID, first/last name, required email, and optional Twitter, company, and title fields. `id` is the primary key and auto-increments. There is no nickname or explicit QR-code column.
- `events`: event ID, required name, optional date and venue. `id` is the primary key and auto-increments. A sample event has no date.
- `event_attendees`: registration/link ID, attendee and event IDs, optional order number, required ticket number, optional ticket title/venue, check-in flag defaulting to 0, and optional check-in time. `id` is the primary key and auto-increments.
- `event_attendees.attendee_id` references `attendees.id`; `event_attendees.event_id` references `events.id`. Both foreign keys are present.
- `event_attendees` has a unique `(attendee_id, event_id)` key, a unique `ticket_number` key, and an index on `event_id`.

## Initial review

The attendee/event/link-table separation is a sensible relational starting point for representing people registered to events. The foreign keys protect against links to missing attendees or events. The unique `(attendee_id, event_id)` key prevents duplicate attendee-event links and supports at most one registration row for a person at an event.

Items to verify before recommending schema changes:

- **Meaning of identifiers:** User expects ticket numbers to be globally unique and believes the QR may contain that value, but has not verified the QR payload. The current global unique key matches the stated expectation; compare it with a real QR/export before relying on it.
- **Registration cardinality:** User confirms one ticket per person per event and allows a person to register for multiple events. The unique `(attendee_id, event_id)` key matches this rule.
- **Participant identity and import:** User states email is guaranteed and unique, but this must be validated against the real organizer export. The schema has no unique email constraint. User expects nickname in the export for name tags; it is not currently represented. Existing registrations should be skipped/reported during re-import, not inserted again.
- **Check-in consistency:** `checkin_status` is nullable even though it defaults to 0, and `checked_in_at` is nullable. The dump does not enforce that status and timestamp agree. Application logic should perform a conditional state change atomically; any constraint or representation change should be proposed after confirming MariaDB version and team preference.
- **Names and fields:** First and last names are nullable; confirm whether that matches real exports and certificate/tag needs. `twitter`, `company`, and `title` should be retained only if the workflow needs them and organizers supply them.
- **Event data:** `event_date` is nullable and events have no start/end time or per-event timezone. Staff event availability uses the Asia/Manila calendar date; Admins can select and manage events regardless of date.
- **Naming:** User confirms `attendees` is intended as the attendee table. The dump remains an initial, non-final design.
- **Printing and certificates:** There are no print-job, name-tag status, certificate, user/account, or role tables in this dump. These may not all require tables, but their tracking and authorization needs remain unmodeled here.
- **Correction audit:** Admin-only mistaken check-in corrections must be recorded. The dump has no audit/correction history structure; agree on required audit details before schema design.
- **Check-in races:** The unique attendee-event registration does not alone prevent two concurrent check-in requests from both attempting to act on the same unchecked row. The eventual application must use a transaction-safe conditional update/locking strategy and report the winning result correctly.

No physical schema change is proposed yet. The supplied SQL is an initial schema artifact, not confirmation that it is the currently installed database or the final agreed design. The Bevy export, QR payload, and software environment are still needed before designing an import or migration.

## Implemented accounts and Settings

The Admin Settings page reads the current active account from `admins`. Admin and Staff rows share that table; `role` controls authorization. Password changes verify the current password and store a `password_hash()` result. Invitations and Admin-generated password-reset links use `password_reset_tokens`: only a SHA-256 token hash is stored, links expire after 24 hours, and links are marked used on completion. Account activation/password update and token consumption run in one transaction. Email delivery is not configured, so an Admin copies and shares each link. Invitees choose their display name and username; reset recipients choose a new password. Self-service recovery is not configured.

Active Admin and Staff roles are checked server-side. Staff can access the event selector and check-in endpoint; Admin-only event, participant, Settings, account management, and print-queue actions remain restricted. The Team Accounts list reads `admins`; deactivation revokes unused account links, prevents self-deactivation, and refuses to deactivate the last active Admin. A disabled account can be restored only by an Admin creating a fresh setup link. Password reset links for active accounts are single-use and hashed in the same table; recipients choose their own password. Links expire after 24 hours and are copied/shared manually because email is not configured.

The one-time account migration [20261030_merge_users_into_admins.sql](../database/migrations/20261030_merge_users_into_admins.sql) expands the booth `admins` table, copies registration-app accounts into it, remaps foreign-key references in audit/print/import/token records, and drops `users` after a successful copy. Its ID map is temporary, so a fresh run leaves no archive or map table. The standalone handoff copy is [merge_users_and_admins.sql](../database/merge_users_and_admins.sql); send that to the other team only if their database still has both original tables. The user imported an earlier revision that created archive artifacts; the follow-up [cleanup migration](../database/migrations/20261030_remove_account_migration_artifacts.sql) is only for that already-migrated database. Existing booth Admins become active event-app Admins with their existing username and password hash. A preflight aborts on username collisions. Back up before either merge. The supplied booth SQL dump contains no login code, so verify that the booth application accepts only `role = 'admin' AND is_active = 1` and uses named-column inserts. Otherwise Staff accounts may also authenticate to the booth system, deactivated accounts may remain usable there, or extra columns may break positional inserts. Password changes/deactivation in either app affect the shared account. Compatibility of existing booth password hashes with PHP `password_verify()` remains unverified.

## Implemented event check-in

The current Admin Check-In panel uses the imported `event_attendees` and `attendees` tables without schema changes. It finds a registration by selected `event_id` plus exact `ticket_number`, then conditionally updates `checkin_status` and `checked_in_at` only when not already checked in. The conditional update is the concurrency guard; repeat scans return an already-checked-in result. Participants outside the selected event cannot be checked in. The actual QR payload is not confirmed; current behavior assumes decoded QR text is the ticket number.

The Participants tab reads the same tables without a schema change. The Admin-only `event-participants.php` endpoint returns event registration/check-in totals and pages of participant name, nickname, ticket number, check-in state, and timestamp. Search matches full name, nickname, or ticket number; status filters are applied in the database. Email is not returned to the browser. CSV import is available through the Admin-only, CSRF-protected `event-participant-import.php` endpoint. Since the organizer's actual header row is not available, the Admin previews the UTF-8 CSV locally, chooses comma/semicolon/tab, and explicitly maps the five expected fields. The server validates the mapping and rows, imports within the selected event, reuses attendees by email without overwriting them, skips an already existing attendee-event registration, rejects conflicting tickets, and writes aggregate outcomes to the existing `import_batches` table. The uploaded file and row data are not persisted. No schema change was made.

The Name Tags tab reads only checked-in registrations and their name-tag history from `attendees`, `event_attendees`, and `print_jobs`. The Admin-only `event-nametags.php` endpoint lists those participants and supports CSRF-protected queue actions. A first successful check-in queues one initial job even if no layout exists; repeat check-ins do not enqueue another. The server rejects initial print, retry, or reprint requests for registrations that are not checked in. Admin actions lock the registration and prevent duplicate requests while a job is queued or printing. The active `name_tag` template is linked when present. Bulk initial queueing locks checked-in event registrations, creates one first-time job per registration with no name-tag job history, and reports how many prior jobs were skipped. Printed, failed, and cancelled jobs remain in the Name Tags list as history; retry/reprint is explicit. Shared `event_print_templates` layout columns store dimensions and name-area geometry in millimeters, plus font size, font family, alignment, and `#RRGGBB` color for either print type. `NameTagLayout` and the Admin-only `event-nametag-layout.php` endpoint save/load versioned per-event layouts with private artwork at `storage/uploads/name-tags/`. The editor accepts the confirmed PNG/JPG/WebP formats up to the current 10 MB implementation limit and displays dimensions in millimeters; the font dropdown is also an implementation choice. The queue is not connected to a printer, so `queued` is not reported as physically printed. Supabase PostgreSQL is the user's intended deployment data service, but this project schema and query layer currently target MySQL/MariaDB; migration design and production data changes remain pending review.

The Certificates tab uses `event_attendees` and `attendees` to select only participants checked in for the selected event. The Admin-only `event-certificates.php` endpoint returns full names, check-in times, layout availability, and certificate-specific attempt status, and accepts CSRF-protected individual and bulk queue/reprint requests. The model rechecks event membership, current check-in eligibility, and the presence of an active event layout inside transactions, locks registration rows, prevents duplicate queued/printing requests, and links each job to the active certificate template. Bulk queueing creates first-time jobs only and skips any registration with certificate history. The Admin-only `event-certificate-layout.php` endpoint saves and loads the event's active certificate template. Artwork is kept in private local storage; dimensions and name-area geometry are stored in millimeters using the shared print-template fields, while the interface displays inches. A new template row is created for each save to preserve prior print-job references. Existing imported databases can add the shared fields with `20261030_add_shared_print_template_layout_fields.sql`; the current local database already has them. Automatic initial queueing remains unimplemented. Certificate jobs remain separate from attendance; a queued certificate is not reported as printed, and printer integration remains outstanding.

## Proposed logical structure (for team review)

A pasteable draft implementing this proposal is available at [database/gdg_bacolod_schema.sql](../database/gdg_bacolod_schema.sql). It creates a separate empty `gdg_bacolod_checkin` database and does not modify the existing `event_registration` database. Review the notes at the top of that SQL file before using it.

The user has now confirmed the expected CSV fields: ticket number/QR, first name, last name, email, and nickname. One person may attend multiple events, with one ticket per event; ticket numbers are expected to be globally unique; email is stated to be unique. There is no fixed limit on event count. This is enough to propose the logical model below. It is **not a migration or final SQL**. First compare it with the actual CSV and confirm whether the decoded QR value exactly equals the ticket number.

### Core tables

| Table | Purpose and main fields | Important constraints/relationships |
|---|---|---|
| `events` | One row per event: `id`, `event_name`, `event_date`, `venue` and any later-confirmed event details. | Existing primary key is suitable as a starting point. No fixed maximum number of event rows is required. |
| `attendees` (retain existing name for now) | One person: `id`, `first_name`, `last_name`, `email`, `nickname`. | Primary key `id`; email required and unique after verifying the real export. Nickname is added because it is needed on name tags. |
| `event_attendees` | One registration/ticket for one attendee at one event: `id`, `attendee_id`, `event_id`, `ticket_number`, ticket metadata only if confirmed, current check-in status/time. | Foreign keys to attendee and event; unique `(attendee_id, event_id)` (one ticket per person/event); unique `ticket_number` globally (matches the stated rule). |

The relationship is `attendees` 1-to-many `event_attendees` and `events` 1-to-many `event_attendees`. This lets one attendee register for multiple events without duplicating their name/email, while each event registration has its own ticket number and check-in state. It matches the existing table layout and stated rules, subject to validation against the CSV.

### Supporting tables needed by confirmed workflows

- **`admins`:** Shared booth and event-app account table. It retains booth-compatible `username`, `password_hash`, and `createdAt` fields, and adds display name, optional email, Admin/Staff role, active status, and account metadata. The initial event-app Admin is set up locally; later accounts are invited by an Admin and activated when the invitee sets a password. `users_legacy` is an archive after migration, not an active account source.
- **Check-in correction audit:** Record registration, acting Admin, old and new check-in state/time, reason, and correction timestamp. The current check-in fields hold the current state; the audit preserves the correction history. Confirm whether a correction means clearing a mistaken check-in and whether it allows a later new check-in.
- **Print jobs:** Record each name-tag or certificate print attempt against an event registration, print type, status, requester, timestamps, failure details, and whether it is a reprint. A successful first check-in creates a name-tag job; retry/reprint creates another job, never another check-in. Certificate eligibility is derived from successful check-in. Define cancel/"stop" behavior before finalizing status values.
- **Event print templates/settings:** Store each event's uploaded template reference/name, overall dimensions, one attendee-name area rectangle, font size in points, font family, text alignment, and color in `#RRGGBB` format. Name-tag coordinates use millimeters; certificate coordinates use percentages of artwork dimensions. Store file paths/metadata, not uploaded binary contents, in the relational table. Certificate artwork is stored privately under `storage/uploads/certificate-templates/` and accepts PNG, JPG, or WebP up to 10 MB.

These supporting tables are logical proposals; exact columns and whether all print attempts need persistence should be confirmed with the team and hardware workflow. The SQL allows nullable nicknames because a supplied column does not guarantee every participant fills it in; confirm the fallback name-tag behavior.

### Check-in and import rules for this model

- Import is scoped to the Admin-selected event. Map only the supplied fields: ticket number, first name, last name, email, nickname. Do not expect any other CSV columns until the actual file is inspected.
- Reuse an attendee by unique email when importing them for another event, then create that event's registration. If the `(attendee_id, event_id)` registration already exists, skip/report it rather than insert another. Do not silently overwrite existing attendee details when an import conflicts; report for review.
- Reject/report a repeated ticket number (global unique rule) or conflicting duplicate email for review. Provide an import summary and avoid partial/unclear outcomes.
- Check in by resolving the ticket/QR to an `event_attendees` row for the active event. Record a first check-in with an atomic conditional update so simultaneous scans cannot both succeed. A registration for another event is not sufficient.
- Keep the current check-in state separate from print-job status. If a print fails, leave attendance checked in and allow Admin-authorized retry.

### Schema decisions still needed

- Confirm that QR decoding returns exactly the CSV ticket number; the user currently believes this but is unsure.
- Inspect the real CSV to verify headers, encoding, whitespace/case conventions, required values, and that emails/ticket numbers meet the uniqueness claims.
- Agree on check-in correction semantics and audit fields, password reset method, print cancellation meaning, template upload formats, and the exact printer integration.

The live database has not been changed. Schema reference files now include proposed name-area and typography fields, and a one-time additive migration is supplied for review; do not run it until the live table/version is checked and a backup is available. Remaining print-editor decisions include accepted file format, available font families, actual initial values, and overflow behavior.

## Required review when materials are available

Review the supplied schema and actual organizer-supplied participant export together before making changes:

- Tables, columns, data types, nullability, defaults, and normalization.
- Primary keys, foreign keys, relationships, uniqueness, indexes, and delete/update behavior.
- How registrations across multiple events are represented and whether participant identity can be shared between them.
- Actual participant fields, duplicate records, QR values, character encoding, and missing/invalid values.
- How check-in is recorded and how concurrent/repeated scans are made idempotent.
- How printing status and certificate issuance are represented separately from attendance.
- Database engine/version and compatibility with local PHP/XAMPP development. For production, the user intends to use Supabase PostgreSQL; the PHP host and a data-preserving MySQL-to-Postgres migration need review before deployment.

Do not assume specific CSV columns or QR payloads. Obtain a representative, appropriately protected sample/export and confirm whether this dump matches the live database before proposing migrations. Admins may create any number of events; do not add a hard event-count cap.

## Design principles pending review

- Preserve the existing database and data; do not automatically recreate, drop, or replace it.
- Keep event registration and attendance associated with the correct event.
- Use appropriate primary and foreign keys, unique constraints, and indexes.
- Enforce one successful check-in per registration/event using a database-backed strategy that handles concurrent requests.
- Keep check-in result independent from print result; printing retries must not duplicate check-in.
- Base certificate eligibility on successful attendance and confirmed organizer rules.
- Record Admin check-in corrections and Admin print/reprint actions in an audit trail appropriate to confirmed requirements.
- Use parameterized SQL and server-side validation/authorization.

## Change control

## Shared booth/check-in schema review draft

The user confirmed the intended direction: use the booth team's supplied `database/db_gdgevents.sql` names for shared tables and fields, and add registration-only fields/tables when needed. The booth-aligned schema reference is available at [gdg_bacolod_synced_schema.sql](../database/gdg_bacolod_synced_schema.sql). It retains the booth tables (`admins`, `attendees`, `booths`, `booth_users`, `events`, `event_attendees`, and `scan_logs`), adds `nickname` to `attendees`, and includes account, import, correction, template, print-job, and password-token fields/tables. Existing booth columns such as `createdAt`, and the booth's signed integer IDs for shared entities, are preserved. Account consolidation has been approved: existing booth Admin rows should be active Admins in the event app.

This is a schema design artifact for review, not a migration. It targets `db_gdgevents` and does not copy or migrate existing data. `CREATE TABLE IF NOT EXISTS` leaves existing tables untouched. The imported database already includes `attendees.nickname`, so the code update required no database alteration. The PHP application now queries `attendees`. Do not run the schema reference over the shared database; actual participant export and QR payload review remain outstanding.

Document each proposed schema change, its reason, affected data, migration/rollback approach, and compatibility impact. Confirm proposed changes with the team before implementation. Back up and preserve existing data before an approved migration. The synced SQL file is a schema reference, not an executable migration. The imported database schema is now confirmed by read-only metadata inspection; the actual participant export and QR payload remain unreviewed.
