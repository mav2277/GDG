# Printing and deployment plan

## Queue flow (implemented); physical printing plan (not implemented)

1. Staff scans an attendee. The application records the successful check-in and saves one name-tag job in the database queue. A repeat scan does not create another job.
2. The Admin Name Tags page can save an authorized initial print or reprint request to the same queue. A saved name-tag layout is optional for queueing.
3. Admin can choose **Queue eligible name tags** to queue one job for every checked-in registration that has no name-tag job history. Unchecked-in registrations are excluded; existing jobs, including failed or printed jobs, are skipped. The confirmation and result report queued and skipped counts. Use individual retry/reprint actions for prior jobs.
4. A separate computer connected to the printer runs a local print service. It securely checks for pending jobs from the hosted application, claims a job so another service cannot print it at the same time, and sends the approved name-tag output to the computer's selected printer without showing a browser print dialog.
5. The service reports the print outcome. If the printer computer is offline, queued work waits. A print failure does not undo attendance; an Admin can review the job and authorize a retry/reprint.

This design allows Staff to check in from another device. The printer computer must be on, online, and able to reach both the hosted application and its configured printer for immediate printing. Changing printers means selecting/installing the replacement printer on that computer and checking the final output.

## Confirmed name-tag layout workflow

- Admin uploads the design supplied by the organizer, sets the tag's overall width and height, and uses a preview to position and resize one box for the attendee's name.
- The shared stored coordinates are `name_area_x_mm`, `name_area_y_mm`, `name_area_width_mm`, and `name_area_height_mm`, measured in millimeters from the top-left of the template. Each template record's `print_type` keeps the name-tag and certificate layouts separate. The box must remain within the template dimensions.
- The template record also stores the configurable font size in points, font family, alignment (`left`, `center`, or `right`), and text color (`#RRGGBB`) shown in the initial mockup. No sample values in the mockup are treated as print defaults.
- The image file is stored in protected file/object storage; SQL stores its path and original filename. The queue count and checked-in attendees without a tag are derived from `event_attendees` and `print_jobs`; queueing multiple initial tags creates one job row per attendee.
- The Name Tags editor saves per-event artwork, physical dimensions entered and stored in millimeters, nickname-area geometry, font size/family, alignment, and color. It accepts the confirmed PNG, JPG, or WebP artwork formats up to the current 10 MB implementation limit and stores versioned images under private `storage/uploads/name-tags/`. The bulk queue scope was confirmed on 2026-10-30. The size limit and dropdown font families remain implementation defaults pending printer requirements.
- Configure the layout once and reuse it for every attendee. The existing recorded rule uses the attendee nickname on name tags; the value is filled automatically for each queued job. Admin does not type names individually.
- Only checked-in participants appear in the Name Tags list and are eligible for initial requests, retries, or reprints. A printed job stays visible as history with a Printed status and explicit reprint action; it does not disappear when completed. Admin can refresh statuses to load any updated print-job state.
- Existing print-job history is preserved if it predates the checked-in-only rule. Any future print service must verify the associated registration is checked in at job-claim time before dispatching it; unchecked registrations are hidden in this UI and must not be printed.
- The layout editor and queueing are implemented; browser/device rendering, physical printer layout, and dispatch have not been verified. The organizer's approved artwork, printer-specific font availability, and long-name overflow behavior remain open.

## Requested certificate layout and queue

- The 2026-10-29 supplied reference requests a certificate editor with artwork upload, physical width and height in millimeters, one draggable/resizable attendee-name area, font size in points, font family, alignment, text color, and a Save Layout action. The editor preview displays the configured full name over the uploaded artwork.
- Its certificate queue lists checked-in participants and their check-in times and certificate statuses, offers per-participant queue actions, and offers bulk queueing of first-time certificates with an eligible count.
- Certificate queue actions are manual and Admin-only. The selected event must have a saved active layout before any certificate job can be queued. The server requires each participant to be checked in to the selected event and prevents duplicates; bulk queueing skips participants with any existing certificate job. Check-in does not automatically queue certificates. Queueing is not proof of physical printing.
- Sample artwork, names, participant rows, dimensions, font, color, and other displayed values in the screenshot are illustrative. They are not approved templates, data, or defaults.
- The Certificates tab stores one active, event-specific layout in `event_print_templates`. Width/height entered in inches are converted to millimeters; the name-area position and size also convert to the shared millimeter fields. Font settings, alignment, color, original file name, and a private artwork file are saved together. Each save creates a new template version and deactivates the prior one so existing print-job references remain intact. Artwork accepts PNG, JPG, or WebP up to 10 MB and is stored under `storage/uploads/certificate-templates/`, which is blocked from direct web access. Existing imported databases can add the shared layout fields with `20261030_add_shared_print_template_layout_fields.sql`; those fields are already present in the current local database and the project schema drafts. Physical printer rendering and dispatch remain unimplemented.
- The local preview provides serif, sans-serif, and monospace font choices, including Georgia, Times New Roman, Cambria, Garamond, Palatino Linotype, Playfair Display with Georgia fallback, Arial, Calibri, Segoe UI, Tahoma, Verdana, Trebuchet MS, Century Gothic, Courier New, and Consolas. The browser uses fonts available on the current device; actual printing font availability depends on the printer renderer and remains unconfirmed.
- Certificate artwork accepts PNG, JPG, or WebP; font choices are restricted to the editor's dropdown. Actual printer font availability and name-area overflow behavior remain unconfirmed. Physical printer dispatch remains unimplemented.

## Deployment facts and blockers

- The current PHP MVC application and imported `db_gdgevents` schema use MySQL/MariaDB locally.
- Supabase provides PostgreSQL and related backend services; it does not run this conventional PHP MVC application. A separate PHP-capable application host is required.
- Moving production data to Supabase PostgreSQL requires a reviewed compatibility migration and application database changes. No live database has been replaced or modified for this plan; existing data must be preserved.
- The local print service must authenticate securely to the hosted application. Supabase privileged keys or other server secrets must never be placed in browser JavaScript or distributed in a public page.
- Silent dispatch must be tested on the actual printer computer, driver, and replacement-printer workflow. No printer could be inspected from the current workspace.
- Physical output waits for the organizer's design and actual tag width/height. The shared schema stores overall dimensions, one name-area rectangle, and typography settings per event and print type. The local database has the shared columns; a queued row alone cannot produce a correctly sized printed name tag.

## Decisions needed before implementation

1. Choose the PHP application host and confirm whether production will use Supabase PostgreSQL or another database.
2. Confirm the operating system and location of the computer that will run the printer service. The user has a Brother printer connected by USB, but the specific model and print computer are not yet known.
3. Select a local print-service approach that can securely claim jobs and print through that computer's installed printer driver.
4. Obtain the approved name-tag template and dimensions before final physical layout configuration.
5. Confirm accepted template file types, allowed font families, and long-name overflow behavior before approving physical output.

## Safe rollout

Develop against a separate development project/database, then review and back up data before any production migration. Keep check-in and print-job status separate. Start by printing clearly labeled sample records, verify queue claiming and failure/retry behavior, and only then enable automatic dispatch for live events. Do not report a job as printed until the local service receives a successful print handoff; printer hardware may not confirm that paper physically emerged.
