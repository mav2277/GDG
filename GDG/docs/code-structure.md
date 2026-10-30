# Code folder structure

## Status

The project uses a PHP MVC structure. Admin/Staff login, one-time local initial-Admin setup, Settings password changes, account invitations and management, Staff check-in, Admin event CRUD, event-scoped check-in, participant list/search/status filters, event-specific name-tag/certificate layout save/load, and name-tag/certificate queue/history are connected to the imported MySQL/MariaDB database. Setup/reset links use hashed single-use tokens that expire after 24 hours; the Admin shares links manually because email delivery is not configured. CSV import and physical printing remain unconnected. Production is intended to use Supabase services, but the PHP application host and MySQL-to-Postgres migration are not selected or implemented. Automatic initial certificate queueing and printer dispatch remain unconnected. The separate local printer-service design is planned in [printing and deployment](printing-deployment.md).

## Folders

```text
GDG/
├── src/
│   ├── Controllers/       # Handle HTTP requests and choose a response/view
│   ├── Models/            # Represent and retrieve/update application data
│   ├── Views/
│   │   ├── admin/         # Admin pages
│   │   ├── auth/          # Login and account recovery pages
│   │   ├── components/    # Reusable PHP interface pieces
│   │   ├── layouts/       # Shared page shells and navigation
│   │   └── staff/         # Staff event and check-in pages
│   ├── Middleware/        # Login, role checks, and request protections
│   ├── Routes/            # Map URLs to controllers
│   ├── Services/          # Multi-step check-in, import, and printing workflows
│   └── Utils/             # Small shared PHP helpers
├── config/                # Local settings; keep secrets out of Git
├── database/
│   ├── migrations/        # Reviewed, ordered schema changes
│   └── gdg_bacolod_schema.sql
├── docs/                  # Requirements, decisions, design, and review notes
├── public/                # Configure Apache's document root to this folder
│   └── assets/
│       ├── css/
│       ├── images/
│       └── js/            # Browser behavior, including QR camera scanning
└── storage/               # Private runtime files; do not expose through Apache
    ├── logs/
    └── uploads/
        ├── certificate-templates/
        └── name-tags/
```

## Frontend file ownership

Keep browser code separated by interface responsibility:

- `public/login.php`, `public/setup-admin.php`, and `public/logout.php` handle Admin/Staff sign-in, first-account setup, and sign-out. `src/Controllers/AuthController.php` validates requests; `src/Models/User.php` reads and creates accounts in the shared `admins` table using parameterized SQL; `src/Utils/session.php`, `security.php`, and `auth.php` handle sessions, CSRF tokens, and role authorization. `.env` holds ignored local database settings; use `.env.example` as the template. Self-service password recovery is not implemented; Admin-generated reset links are managed in Settings.
- `src/Views/auth/login.php`, `src/Views/auth/setup-admin.php`, `public/assets/css/login.css`, and `public/assets/js/login.js` render and style the login/setup screens and password visibility control.
- `src/Views/components/admin-sidebar.php`, `public/assets/css/admin-sidebar.css`, and `public/assets/js/admin-sidebar.js` own the shared Admin sidebar markup, styles, and navigation interactions. The login card and sidebar use the supplied `public/assets/images/gdg-logo.svg` logo asset.
- `src/Views/admin/dashboard.php`, `public/assets/css/admin-dashboard.css`, and `public/assets/js/admin-dashboard.js` own the event dashboard, cards, search/status filters, and add/edit/delete dialogs.
- `src/Models/Event.php` reads events with registration and checked-in totals from `event_attendees`, and creates, updates, and deletes records using the imported `events` table. `public/admin-events.php` is the Admin-only, CSRF-protected JSON endpoint used by the dashboard; event-card totals refresh after check-in and import, and foreign keys block deletion when linked records exist.
- `src/Controllers/CheckinController.php` validates check-in requests and returns results; `src/Models/Checkin.php` performs the event-scoped conditional update against `event_attendees`; `public/check-in.php` applies active Admin-or-Staff authorization before dispatching. Both Admin and Staff page roots provide this endpoint URL to the shared scanner. Requests use CSRF protection. Attendance and the separate initial name-tag queue record are handled independently.
- `src/Views/admin/event-workspace.php`, `public/assets/css/event-workspace.css`, and `public/assets/js/event-workspace.js` own the event header, workspace navigation, tabs, and panels.
- `src/Models/Participant.php`, `src/Controllers/ParticipantController.php`, and `public/event-participants.php` provide the Admin-only, read-only event-scoped participant query endpoint. It returns totals and paginated participant rows, with parameterized search/filtering and no email exposure.
- `src/Models/ParticipantImport.php`, `src/Controllers/ParticipantImportController.php`, and `public/event-participant-import.php` own the Admin-only, CSRF-protected event-scoped CSV import. The endpoint validates the upload and explicit field mapping, reuses attendees by email, skips duplicate registrations, rejects conflicting tickets, and writes aggregate batch counts to `import_batches` without retaining the CSV.
- `public/assets/js/participants.js` loads participant rows when the Participants tab opens, applies the search/status controls, renders loading/empty/error states, pages results, and refreshes after check-in. `public/assets/js/participant-import.js` owns the import dialog, local CSV preview, delimiter and column mapping, upload confirmation, and result rendering. `public/assets/css/participants.css` owns participant totals, search/filter controls, import dialog, responsive table, and paging styles. Organizer header names are not assumed.
- `src/Models/NameTag.php`, `src/Controllers/NameTagController.php`, and `public/event-nametags.php` provide Admin-only name-tag history and individual/bulk queue actions using `event_attendees`, `attendees`, `event_print_templates`, and `print_jobs`. A first successful check-in queues one separate name-tag job, even without a layout. Listing and all queue actions require check-in; bulk initial queueing skips checked-in registrations with prior history. The UI filters visible rows by print status while keeping bulk eligibility based on the full checked-in list. Printed/failed/cancelled jobs remain visible as history, and the UI has an explicit status refresh. `NameTagLayout.php`, `NameTagLayoutController.php`, and `public/event-nametag-layout.php` save/load versioned event artwork and settings under private `storage/uploads/name-tags/`. `public/assets/js/name-tag-layout.js` owns the draggable/resizable layout editor, while `nametags.js` owns queue/history interactions and `nametags.css` owns Name Tags styles. Queue records do not perform physical printing; printer integration remains outstanding.
- `src/Models/Certificate.php`, `src/Controllers/CertificateController.php`, and `public/event-certificates.php` provide Admin-only certificate eligibility/history and CSRF-protected individual/bulk queue and reprint actions using only checked-in registrations for the selected event. Queue actions require an active event layout. Bulk queueing adds first-time jobs only and skips registrations with existing certificate history. `public/assets/js/certificates.js` renders eligible participants, check-in time, layout availability, certificate status, and actions; `public/assets/css/certificates.css` owns its feature styles. `src/Models/CertificateLayout.php`, `src/Controllers/CertificateLayoutController.php`, and `public/event-certificate-layout.php` save/load the selected event's active certificate template with CSRF protection and server-side image/field validation. Artwork is stored privately under `storage/uploads/certificate-templates/`; each save creates a new template version. `public/assets/js/certificate-layout.js` loads and saves this configuration and provides the draggable/resizable name area preview. Automatic initial certificate queueing remains unimplemented. Queue records do not perform physical printing; printer integration remains outstanding.
- `src/Controllers/SettingsController.php`, `src/Views/admin/settings.php`, `public/settings.php`, `public/assets/css/admin-settings.css`, and `public/assets/js/admin-settings.js` own the Admin Settings page, current-password-confirmed password changes, invitations, and Team Accounts list/actions. `public/account-actions.php` is the Admin-only, CSRF-protected JSON endpoint; `public/admin-accounts.php` is the Admin-only account listing endpoint. `src/Models/User.php` reads and writes `admins` and stores only hashes of one-use, 24-hour setup/reset tokens in the existing `password_reset_tokens` table and enforces account deactivation rules.
- `src/Controllers/InvitationController.php`, `src/Views/auth/accept-invitation.php`, and `public/activate-account.php` validate setup/reset links and let recipients choose their own password; invitees also set their display name and username. No email is sent; Settings returns a link for the Admin to share manually until mail delivery is configured.
- `src/Controllers/StaffController.php`, `src/Views/staff/check-in.php`, `public/staff.php`, `public/assets/css/staff-checkin.css`, and `public/assets/js/staff-checkin.js` provide the Staff check-in-only screen. The selector lists only events dated today in Asia/Manila, and the check-in endpoint enforces the same rule server-side for Staff. The compact header contains the event selector/status, account identity, and sign-out; the workspace below contains the tightly spaced scanner heading/instructions, scanner, and manual entry, without a separate active-event card. Staff can expand the camera preview using the Fullscreen API where supported; a sliding camera on/off switch appears only in full-screen mode. Event switching confirms before closing the active camera session; switching is disabled during a pending verification. Admin data-management endpoints continue to require Admin authorization.
- `public/assets/css/nametags.css` owns Name Tags feedback, the print-status table, action controls, and centered empty state.
- `public/assets/css/certificates.css` owns the Certificates layout editor, connected queue card, participant table, and centered empty state. Queue actions are database-backed; physical printing is not connected.
- `public/assets/css/checkin-scanner.css` and `public/assets/js/checkin-scanner.js` own camera scanning, manual ticket entry, camera lifecycle, and accessible result states. Completed check-in notifications use concise outcome text in a compact branded card centered over the scanner, dismiss after three seconds, and play the matching local MP3 from `public/assets/audio/` when browser audio is available; generated tones are the fallback. The camera stays on and pauses accepting scans for one second after each camera verification. A QR can be scanned again after it leaves the camera view. Scanner sizing is recalculated by restarting the camera scan when Staff enters or exits full screen. Verification-in-progress stays visible until a result arrives. The QR decoder is bundled locally at `public/assets/vendor/html5-qrcode-2.3.8.min.js` with its license. Check-in matches decoded text directly to `event_attendees.ticket_number`; confirm that this is the organizer's actual QR payload.
- `public/assets/js/ui-feedback.js` owns shared preview notifications used by the dashboard and workspace.

Load the shared dashboard styles before the component styles so the CSS tokens are available. Load `ui-feedback.js` before feature scripts that display notifications. A feature stylesheet or script should contain that feature's behavior, not unrelated sidebar/dashboard/scanner rules.

## Request flow

```text
Browser → public entry point → bootstrap → Controller → Model
                                           → Utils (session, CSRF, authorization)
                                           → View
```

- **Model:** owns attendee, event, registration, and account data access. Use parameterized SQL.
- **View:** renders HTML. Keep business rules out of templates. Reusable view fragments belong in `Views/components/`.
- **Controller:** validates the request, calls the needed Model or Service, then selects a View/redirect.
- **Service:** holds workflows that involve multiple steps or rules, such as atomic check-in followed by a separate print job.
- **Bootstrap:** loads database settings, shared helpers, and the protected session for each public entry point.
- **Routes:** the current small app uses PHP entry points under `public/` to dispatch directly to controller methods; add a router only when needed.
- **Utils:** session, CSRF, authorization, and other small shared helpers that do not own business workflows.

Keep CSS and browser JavaScript in `public/assets/` because the browser must load them directly. Keep uploaded layouts and logs under `storage/`, outside the public web root. Serve a validated template through an authorized application route rather than exposing arbitrary upload paths.

## XAMPP note

The project currently lives under `htdocs/GDG`; local preview URLs serve `public/` as a subfolder. Opening `http://localhost/GDG/` runs the root `index.php`, which serves the login page while keeping `/public` and `.php` out of the address. The root `.htaccess` maps login assets and the first-admin setup route to their public files and blocks direct requests to internal source/config/database/docs/storage folders and Markdown/SQL files. For deployment, configure Apache's document root to `C:/xampp2/htdocs/GDG/public` so only public files are web-accessible.

## Conventions

- Keep secrets and local database credentials out of committed files; `.gitignore` covers local config, runtime logs, and uploads.
- Keep the local `.env` file out of version control; copy `.env.example` and use the credentials for the installed local database.
- Use `database/migrations/` for future reviewed changes. Do not paste updates over the original database or run destructive reset scripts.
- Keep optional folders out until a feature needs them; add a separate repository layer only if the Models need it.
- Add code one feature at a time after the database and environment decisions are confirmed.
