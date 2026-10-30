# Requirements

## Status and scope

This is a working requirements record based on the project brief and user clarifications. Admin/Staff login from the shared `admins` table, first-Admin setup, logout, Admin event management, event-scoped check-in, participant list/search/status filters and CSV import, name-tag/certificate print queues, Admin account invitations/password changes/account management, and Staff login/check-in are implemented against the imported tables. Physical printing remains disconnected. Account setup/reset uses copyable, single-use 24-hour links because email delivery is not configured; recipients choose their own credentials. The organizer export headers/delimiter, approved print templates, and printer setup have not been reviewed. Admins can manage any number of events. Unanswered items remain open.

## Users and access

### Admin

- Sign in securely.
- Create, edit, and delete any number of events; event name, date, and venue persist in the database. Event status is derived from its date. Deletion is rejected if linked records exist, preserving event history. Open an event-specific workspace.
- Search participants by name or QR/ticket value; view participant and attendance information and import data.
- Correct mistaken check-ins; record who made the correction, when, and why.
- Monitor name-tag printing and authorize/record reprints.
- Manage certificate printing for checked-in participants and authorize/record reprints.
- Invite later Admin and Staff accounts in Settings; the first Admin account is created through a one-time localhost-only setup. For the first Admin and invited Staff accounts, display name is the chosen username. Invited Admins choose a separate display name and username. Invitees set a password through a single-use 24-hour link.
- Review all accounts in Settings. Admins can renew setup links, create a password-reset link for an active account, restore a disabled account through a new setup link, or deactivate another account. Admins cannot deactivate themselves or the last active Admin. Links are shared manually until email delivery is configured.
- Change the signed-in Admin password after confirming the current password. An Admin can generate a one-time reset link for another account; self-service password recovery and automatic email delivery are not configured.

### Staff

- Accept a valid one-time account invitation, choose a username and password (the username is also the Staff display name), then sign in.
- Select from events whose Admin-set date is today in the Asia/Manila timezone, then use camera QR scanning or manual ticket entry. The server also rejects Staff check-in requests for events dated before or after today.
- Scan QR codes or use an approved manual lookup identifier.
- See a clear verification result, record valid check-ins, and facilitate name-tag printing.
- See only the participant's full name and QR/ticket identifier during check-in, as currently specified.
- Return to the event dashboard or leave the active event.
- Must not access administrative settings, change event configuration, correct check-ins, import data, or authorize reprints.

Enforce all permissions on the server, not only in the interface.

### Proposed permissions for team review

| Action | Admin | Staff |
|---|---:|---:|
| View/select an event and run check-in | Yes | Yes, for available events |
| Search by participant name or QR/ticket value | Yes | Yes, for check-in only (confirm name search) |
| View full name and QR/ticket identifier in check-in | Yes | Yes |
| Create/edit event details (no fixed event limit) | Yes | No |
| Import participant registrations | Yes | No |
| Correct a mistaken check-in with an audit record | Yes | No |
| Retry/reprint a name tag and record the action | Yes | No |
| Print/reprint an eligible certificate and record the action | Yes | No |
| Invite/manage user accounts, deactivate accounts, issue setup/reset links | Yes | No |
| Change own password | Yes | Yes, through invitation/reset link |

This matrix records the implemented role split. Setup and reset links are Admin-generated and manually shared; the recipient chooses the password.

## Planned areas

- Dashboard/event overview with event context, useful registration and check-in totals, and event actions.
- Event management and event-specific workspace.
- Participant records, attendance, search/filtering as needed, and authorized imports.
- Staff QR check-in with manual lookup fallback and visible active-event context.
- Name-tag printing status, retry, and authorized reprint workflow.
- Certificate eligibility and a separate certificate management workflow.
- Settings and account management as confirmed.

## Check-in outcomes

1. **Registered and not checked in:** validate membership in the selected event, atomically record one successful check-in timestamp, and show the participant's full name and result. Queue one separate initial name-tag job; physical print dispatch remains unconfigured.
2. **Already checked in:** show an explicit message; do not add another attendance record or automatically print another tag.
3. **Unknown/invalid QR:** reject without recording attendance or printing.
4. **Not registered for selected event:** reject without recording attendance or printing.
5. **Registered for another event:** never check in under the wrong event.
6. **Printing failure:** retain the successful check-in and report printing status separately; allow an appropriate retry without duplicating attendance.
7. **Repeated/concurrent scans:** do not create duplicate check-ins.
8. **System/database failure:** do not claim success; provide a safe retry path when possible.

Certificate eligibility is based on successful check-in and organizer-confirmed rules. Certificate queueing is separate from attendance. Admins queue certificates manually from the Certificates tab; a successful check-in does not automatically queue a certificate. Physical printing remains unconfigured.

- A successful check-in is sufficient for certificate eligibility. Certificate name uses attendee full name; name-tag text uses attendee nickname.
- A mistaken check-in can be corrected by Admin only, with the correction recorded as a mistake. Exact audit details remain to be decided.
- A name-tag print job is queued automatically after the first successful check-in. Check-in remains successful even if queueing fails. The queue entry is separate from attendance and does not claim that physical printing completed.
- Admins can save a per-event name-tag layout, but a saved layout is optional for queueing. Only checked-in registrations appear in the Name Tags list or can receive a name-tag request. Bulk initial queueing includes checked-in registrations with no prior name-tag job and skips any with existing history; the action confirms and reports queued/skipped totals. PNG/JPG/WebP artwork formats and this checked-in-only scope were confirmed on 2026-10-30.
- Printed name tags remain visible as completed history; they are not removed from the list. Admins can explicitly queue a reprint. Queued/printing jobs remain visible with their current status, and failed/cancelled jobs remain available for explicit retry/reprint.
- Admins can filter the checked-in Name Tags list by All statuses, Not queued, Queued, Printing, Printed, Failed, and Cancelled. Show a current count for each option. Filtering changes only visible rows; it must not change the eligibility count or scope of bulk queueing.
- Admin invitations create inactive accounts and issue a single-use token valid for 24 hours. The invitee sets their own password; no shared starter password is generated or displayed. Invitation links are copied/shared manually until mail delivery is configured.
- Admin password changes require the current password. Admins can issue one-use reset links for other accounts; self-service recovery and email delivery are not configured.
- Admin can authorize and record name-tag/certificate reprints. The requested ability to "stop" a print needs clarification (cancel a queued job, disable future prints, or another action).
- Admins must save a certificate layout for the selected event before queueing certificates. They can queue an eligible certificate individually or queue first-time requests for all checked-in participants. The bulk action skips anyone with an existing certificate job to avoid silently reprinting; retry/reprint remains an explicit individual action. Check-in does not automatically queue certificates. Physical printing is not configured.

## Data and integration

- Registrations originate in Bevy. Organizers (Manong Ken or Miss Dai) are expected to supply participant data, possibly as CSV.
- The expected fields are ticket number/QR, first name, last name, email, and nickname. The import UI requires Admins to map each field to the actual CSV header and choose comma, semicolon, or tab delimiter; it does not assume organizer header names.
- Import accepts UTF-8 `.csv` files up to 5 MB and 20,000 data rows. It uses a preview and explicit confirmation, validates required data, scopes rows to the selected event, reuses attendees by email without overwriting existing details, skips existing registrations, rejects conflicting ticket assignments, and records aggregate import counts. Uploaded CSV contents are not retained.
- The organizer export is expected to contain a nickname. Email is stated to be guaranteed and unique; validate against the real export.
- Expected participant data fields are ticket number (believed to be the QR value), first name, last name, email, and nickname. Exact CSV headers, format, and encoding are not yet known.
- An already-imported registration should be skipped/reported, not inserted again.
- Admin uploads the organizer-provided name-tag design, sets overall width/height, positions/resizes one attendee-name area on a preview, and configures font size, font family, text alignment, and text color. The attendee's configured name (currently nickname by the recorded name-tag rule) is filled into that area for each job. The layout is configured once per event/template, then reused automatically.
- Name-tag queue and attempt history are stored in `print_jobs`; Admin actions queue initial print/retry/reprint requests. A queued/printing request cannot be duplicated. The event-specific layout editor and bulk initial queue action are implemented; artwork format/size and font options are current implementation choices pending organizer confirmation. Physical printer service and long-name overflow behavior remain unresolved. See [printing and deployment](printing-deployment.md).
- Certificate eligibility is read from checked-in registrations for the selected event. Certificate jobs are stored separately from attendance in `print_jobs`; Admins must save an active event layout before they can queue one eligible certificate or bulk queue first-time requests for all checked-in participants. The bulk action skips participants who already have any certificate job. Each job links to the active template. Check-in does not queue certificates automatically. Physical printer dispatch is not implemented.
- The supplied certificate-layout reference specifies an Admin editor to upload certificate artwork, set physical width and height, drag/resize one attendee-name area on a preview, set font size in points, font family, alignment, and text color, and save the layout. The user requested that the editor display width and height in inches; persisted schema values remain in millimeters and require conversion. Its queue section lists eligible checked-in participants with check-in time and certificate status and offers individual and bulk queue actions. Although the screenshot depicts automatic queueing at check-in, current behavior is manual. The pictured dimensions, font, color, sample name, and sample participant rows are illustrative only; no defaults, artwork, or real participant data are approved by the screenshot.
- Ticket numbers are stated to be globally unique. One person may register for multiple events but may have only one ticket per event.
- The app currently compares the scanner's decoded text directly to `event_attendees.ticket_number`. Confirm the real QR payload before event use; do not strip prefixes or parse URLs without an approved sample.
- The imported shared database uses the booth team's `attendees` table. The application has been aligned to that table; the earlier `attendees_new` proposal is superseded.
- Keep participants associated with the correct event. Do not assume the QR code itself identifies an event.
- Inspect the actual schema and data before choosing fields or relationships.
- Preserve existing records and avoid automatic database recreation.

## Interface direction

- Admin and Staff interfaces should share a consistent sidebar and application identity. A GDG logo asset is now present at `public/assets/images/gdg-logo.svg` and is used in the login card and Admin sidebar.
- Prioritize a clear, fast laptop/desktop check-in workflow, consistent hierarchy, readable status messages, accessible contrast and keyboard focus, and visible active-event context.
- Staff check-in should work on laptop and phone; camera and responsive-layout behavior need verification on actual devices.
- Use clear loading, empty, success, error, and disabled states. Confirm destructive actions.
- A restrained system-font design system is planned; finalize visual tokens when interface work is approved.

## Security and quality

- Hash passwords securely; use parameterized SQL.
- For password setup and changes, require at least 6 characters, one uppercase letter (A-Z), and one number (0-9). Clearly explain that confirmation must match. Enforce the rule server-side as well as in form validation.
- Validate requests and enforce authorization on the server.
- Protect sessions and state-changing requests.
- Use suitable primary/foreign keys, unique constraints, indexes, and transaction-safe duplicate prevention.
- Prevent unauthorized access through modified identifiers or requests.
- Do not invent attendee data, official branding, layouts, or templates.
- Test actual behavior before reporting a feature as working.

## Current and intended technical stack

The existing application is PHP MVC with HTML5, CSS3, JavaScript, and a local MySQL/MariaDB database under XAMPP for development. The user intends to use Supabase for production services, which means PostgreSQL; a separate PHP-capable application host and a reviewed MySQL-to-Postgres migration are required before deployment. Keep the PHP application unless the team approves a different approach. The local printer service and approved print method remain to be implemented. See [printing and deployment](printing-deployment.md).
