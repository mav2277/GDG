# Instructions for AI coding assistants

## Source of truth

- Read `README.md` and relevant files in `docs/` before planning or changing the application.
- Treat the project files and team-approved decisions as authoritative. Update the documentation when decisions are confirmed.
- Keep documentation in sync with implementation: whenever a change affects code structure, UI design or behavior, architecture, workflows, or a confirmed decision, update the relevant Markdown file in `docs/` (and `README.md` when appropriate) in the same task. Keep `docs/code-structure.md`, `docs/ui-design.md`, requirements, and decisions accurate; label preview-only behavior and unresolved choices clearly.
- Admin login, account invitations/setup and reset links, Admin password changes and account management, Staff login and check-in, event create/read/update/delete, event-scoped check-in, participant list/search/filter and CSV import, and name-tag/certificate print queue records are implemented against the local `db_gdgevents` database using booth-aligned shared tables (`attendees`, `events`, and `event_attendees`) and registration-specific tables. Shared booth columns such as `createdAt` are retained; registration data uses `attendees.nickname`. Initial Admin setup is a one-time localhost-only flow. Setup/reset tokens are single-use, expire in 24 hours, and are stored hashed; no email is sent because an email service is not configured. Admins cannot deactivate their own account or the last active Admin. Event status is derived from date, and description is not persisted. Deletion relies on foreign-key protection to preserve linked registrations and records. Check-in uses an atomic conditional update; first check-in queues a separate initial name-tag print job and repeat scans do not add another job. Certificate eligibility and queue actions require check-in. CSV import is Admin-only, event-scoped, requires explicit delimiter and field mapping, and logs aggregate counts without retaining the file. Physical printer dispatch is not configured. Browser/device behavior remains unverified; do not claim it was tested.

## Before implementation

- Inspect the existing project, schema, attendee/participant table, and organizer-supplied participant export before proposing database changes or import mappings.
- Compare actual fields and sample formats; do not invent QR formats, identifiers, CSV columns, participant records, event templates, or official branding.
- Record unresolved requirements and explain material assumptions. Ask for clarification when a decision blocks safe implementation.
- Before designing or changing any feature, map its full user interaction flow: actor/scope, eligibility, client and server validation, state changes, side effects, loading/success/error feedback, duplicate/concurrent requests, completion/history/removal, retries, and refresh behavior. Keep list visibility, individual actions, bulk actions, server authorization, and any external worker's eligibility rules consistent. Use `docs/interaction-review.md` and add scenario checks to `docs/testing.md`.
- Preserve existing data and database contents. Never automatically drop, recreate, or replace a database.
- Do not start application implementation until the user/team has moved past the documentation and review stages.

## Security and correctness

- Use password hashing, parameterized SQL, server-side authorization and validation, protected sessions, and protection for state-changing requests.
- Enforce event membership on the server. Prevent duplicate check-ins with suitable database constraints and transaction-safe logic, including concurrent scans.
- Keep attendance/check-in outcome separate from printing outcome. A printing failure must not undo a successful check-in.
- Do not automatically reprint for an already checked-in participant. Reprints must be a distinct authorized action.
- Certificate eligibility is based on successful check-in, subject to organizer-confirmed rules. Certificate issuance is separate from check-in.
- Never report an operation as successful unless it actually succeeded. Do not claim tests passed unless they were run.

## Scope and stack

- Keep the proposed PHP, HTML, CSS, JavaScript, and MySQL/MariaDB stack unless a change is explained and approved by the team.
- Printing method, QR library, import rules, and certificate workflow are open decisions; do not silently choose them.
- Use only clearly labeled sample data when examples are necessary. Do not fabricate real attendees or present placeholder marks as official GDG branding.
