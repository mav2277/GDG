# GDG Bacolod Event Check-In System

## Project status

This repository contains Admin/Staff login, account setup/reset and management in Settings, Staff check-in with short browser-audio result cues, a Staff-only full-screen scanner option, and an event selector limited to events dated today in Asia/Manila; database-backed event management with per-event registration/check-in totals and event-scoped check-in; an Admin participant list with totals/search/filters/pagination and CSV import; and database-backed name-tag and certificate print queues. The current local development app uses the imported `db_gdgevents` MySQL/MariaDB database and booth-aligned shared table names. The user plans to use Supabase for deployment data services; a separate PHP-capable application host and any MySQL-to-Postgres migration have not been selected or configured. The Name Tags tab has an event-specific artwork/layout editor and queue actions for checked-in participants only. Bulk initial queueing skips anyone with prior name-tag history; a saved layout is optional. Printed jobs stay visible as history and can be explicitly reprinted. Artwork is stored privately. Certificates also have an event-specific layout editor; Admins must save a layout before queueing individual certificates or bulk queueing checked-in participants. Existing certificate jobs are skipped by that bulk action. Physical printer dispatch is not connected. Values shown in the reference are examples, not defaults. See [printing and deployment](docs/printing-deployment.md).

## Purpose

Plan a web-based system for GDG Bacolod events with no fixed event limit. Its intended workflows cover staff check-in and QR verification, event and participant administration, name-tag printing, and certificate eligibility and management. See [requirements](docs/requirements.md) for the current scope.

## Proposed stack

- Backend: PHP
- Frontend: HTML5, CSS3, and JavaScript
- Current development database: MariaDB/MySQL database `db_gdgevents` is imported and accessible from the local XAMPP installation
- Planned deployment data service: Supabase (PostgreSQL); the current MySQL schema and PHP queries have not been migrated
- Application hosting: a separate host that supports the PHP MVC application must be selected; Supabase does not host this PHP application
- Local development environment: XAMPP with PHP 8.0.30 and PDO MySQL available
- QR scanning: locally bundled `html5-qrcode` 2.3.8 (MIT license included under `public/assets/vendor/`)
- Printing: Admin configures event-specific certificate templates and manually queues eligible certificates after check-in; physical printer dispatch is planned but not implemented
- Version control: Git and GitHub recommended

Do not replace this stack without explaining the reason and getting team approval.

## Development approach

Work in this order: review project files and database; compare the schema to organizer-supplied participant data; resolve important requirements and decisions; verify the local environment; then implement and verify one feature at a time. Preserve existing data. Do not recreate or replace a database automatically.

Before implementing a feature or changing an existing workflow, trace each user action through eligibility, validation, authorization, persisted state, side effects, feedback, repeat/concurrent requests, completion/history, and refresh behavior. Apply the [system interaction and workflow review](docs/interaction-review.md), then add concrete scenarios to [the testing checklist](docs/testing.md).

## Run the Admin login

With XAMPP Apache and MariaDB running, open `http://localhost/GDG/`. The project root sends visitors straight to the login page, so they do not need to enter `/public` or a PHP filename. After running the account migration, sign in with an existing booth Admin account. Use the first-admin setup link only when `admins` has no Admin account; setup is available only from localhost and disables itself after the first Admin exists. Enter a username, email, and password; the username is also used as the display name. Passwords need at least 6 characters, including one uppercase letter and one number; confirmation must match. Then sign in with the username or email and password you created. Database settings are read from the ignored local `.env` file; `.env.example` shows the variables. The local XAMPP setup uses `db_gdgevents` at `127.0.0.1:3306` and the local `root` account with a blank password; change `.env` if your MySQL credentials differ.

Admin and Staff login use the shared `admins` table, password hashes, protected PHP sessions, and CSRF protection. Its `role` column distinguishes Admin and Staff. The account migration retains existing booth Admin credentials and makes those accounts active Admins here. It remaps account references and drops `users` after a successful merge, leaving no legacy or mapping table. The cleanup migration is only for the earlier version already imported into the current database. Inspect the booth app login/write code before running it: the booth app must filter `role = 'admin' AND is_active = 1` and use named INSERT columns so Staff rows cannot sign in as booth Admins and added columns do not break writes. Password changes and deactivation affect the shared account in both apps. Settings lets Admins invite users, list accounts, renew setup links, issue password-reset links, restore disabled accounts, and deactivate other accounts. Admins cannot deactivate themselves or the last active Admin. Setup and reset links are hashed, single-use, expire in 24 hours, and are copied/shared manually because email delivery is not configured; recipients choose their own passwords. Admins can change their own password after confirming the current password. Staff can sign in and access only the Staff event selector and check-in screen; Admin endpoints remain role-protected. The Admin Dashboard loads and saves event name, date, and venue in the `events` table. Add, edit, and delete requests require an Admin session and CSRF token. Event status is derived from the date; the current schema has no description column. Database foreign keys prevent deleting events that have linked registrations or records. Manage Event opens the event-specific workspace. Admin and Staff check-in support camera QR scans and manual ticket entry against the selected event; an atomic database update prevents duplicate check-ins. The decoded QR text is compared directly with `ticket_number`; verify this against a real QR before the event. The Participants tab reads registrations and check-in status from the selected event, with database totals, server-side name/nickname/ticket search, status filters, and pagination. Admins can import a UTF-8 CSV by choosing its delimiter and mapping columns to ticket number, first name, last name, email, and nickname. Imports are event-scoped, reuse attendees by email, skip existing event registrations, reject conflicting tickets, and log row counts in `import_batches`; the original file and row values are not stored. The organizer's actual headers and delimiter remain unconfirmed, so Admins must map each file explicitly. Name Tags lists checked-in registrations and their database print history, and provides an event-specific artwork/layout editor for the nickname area. A successful first check-in queues one name-tag job even without a saved layout. Admins can queue an initial print or an explicit retry/reprint; bulk queueing applies only to checked-in registrations with no prior name-tag job. Printed jobs remain visible as history and can be explicitly reprinted. Certificates lists eligible checked-in participants and their certificate print history. Admins must save an event certificate layout before queueing an individual certificate or bulk queueing first-time requests for all checked-in participants; prior certificate requests are skipped by the bulk action. The Certificates tab saves the selected event's artwork, dimensions, font settings, color, alignment, and attendee-name area in `event_print_templates`; artwork files are stored under private `storage/uploads/certificate-templates/`. Saving a new version preserves earlier template records for existing print history. Check-in does not automatically queue certificates, and physical printer dispatch is not configured. A queue record does not mean a physical document was printed. Browser/device behavior has not been manually verified. Camera use needs localhost or HTTPS, particularly for phones on the local network.

## Project documents

- [AI assistant instructions](AGENTS.md)
- [Requirements](docs/requirements.md)
- [UI design](docs/ui-design.md)
- [Database design and review status](docs/database-design.md)
- [Draft database SQL](database/gdg_bacolod_schema.sql)
- [Shared booth/check-in schema review draft](database/gdg_bacolod_synced_schema.sql)
- [Standalone account merge SQL for the other team](database/merge_users_and_admins.sql)
- [Account-table consolidation migration](database/migrations/20261030_merge_users_into_admins.sql)
- [Account-migration artifact cleanup](database/migrations/20261030_remove_account_migration_artifacts.sql)
- [Shared print-template layout migration](database/migrations/20261030_add_shared_print_template_layout_fields.sql)
- [Code folder structure](docs/code-structure.md)
- [Decisions and open questions](docs/decisions.md)
- [Testing checklist](docs/testing.md)
- [System interaction and workflow review](docs/interaction-review.md)
- [Printing and deployment plan](docs/printing-deployment.md)

These documents record the information currently provided. Update them as the team confirms decisions and provides real project resources.
