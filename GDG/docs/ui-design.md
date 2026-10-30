# UI Design

## Status

This is the interface plan derived from `GDG.md`, project requirements, and the supplied Login, Admin Dashboard, Event Workspace, Settings, and Certificate Layout reference screenshots. Admin and Staff sign-in, account setup/reset and management, Admin password changes, event management, event-scoped check-in, participant list/search/status filters, certificate and name-tag layout save/load, and name-tag/certificate print queue/history are connected to the database. CSV import and physical printer dispatch remain unconnected. Name-tag layout is optional for queueing. The Certificates tab saves event-specific layout settings and private artwork; a saved layout is required before individual or bulk first-time certificate queueing is enabled. Automatic certificate queueing and printer dispatch are not connected. Account setup/reset URLs are copied and shared manually because outbound email is not configured. The actual organizer CSV headers, QR payload, and approved print artwork remain unconfirmed. The login background uses `public/assets/images/gdg-login.jpg`; the GDG logo at `public/assets/images/gdg-logo.svg` is used in the login card and Admin sidebar.

## Design approach: Apple HIG + Fluent 2

Use **Apple Human Interface Guidelines (HIG)** for the experience principles: keep the main task clear, preserve user context, provide understandable feedback, support recovery, and consider accessibility from the start. Use **Microsoft Fluent 2** as the practical web design-system foundation: semantic design tokens, consistent control states, clear hierarchy, and reusable components.

These references have different platform origins, so this project should not imitate Apple-native macOS controls while also copying Fluent components. Apply HIG as a set of design principles and Fluent 2 as the web visual/component language. The result should feel coherent as a web application, not claim to be an official Apple or Microsoft interface. For this staff workflow, prioritize a fast, focused scan experience, persistent event context, and explicit success/error/printing feedback.

Reference links:

- [Apple Human Interface Guidelines: Design Principles](https://developer.apple.com/design/human-interface-guidelines/design-principles/)
- [Apple Human Interface Guidelines: Foundations](https://developer.apple.com/design/human-interface-guidelines/foundations)
- [Fluent 2: Design](https://fluent2.microsoft.design/get-started/design)
- [Fluent 2: Design Tokens](https://fluent2.microsoft.design/design-tokens)
- [Fluent 2: Accessibility](https://fluent2.microsoft.design/accessibility)

## Design goals

- Help staff check people in quickly and confidently during a busy event.
- Keep the currently selected event visible wherever an event-specific action is performed.
- Make outcomes understandable from text and layout, not color alone.
- Keep Admin tools available without adding them to the simpler Staff workflow.
- Use consistent spacing, controls, feedback, and navigation across both interfaces.
- Support Admin on a laptop and Staff check-in on both laptop and phone.

## UI consistency rules (source of truth)

Apply these rules to every Admin and Staff screen. Reuse the shared CSS tokens and existing components under `public/assets/css/`; do not add one-off values for a new page when an existing token or component fits. Keep sidebar, dashboard, workspace, and scanner styles in their corresponding feature stylesheets as listed in [code structure](code-structure.md). If approved GDG brand guidance later changes the palette, update the shared tokens and this section together.

For every interaction, design the full state flow: disabled/ineligible, ready, submitting, success, empty, conflict, and failure states; clear next steps; and what persists after leaving and returning to the screen. Decide whether completed items remain as history or are removed, and make retries or reprints explicit. Check that list visibility, individual controls, bulk actions, and server rules use the same eligibility conditions. See the [system interaction and workflow review](interaction-review.md).

### Color and surfaces

- Use `--navy-900` (`#11165F`) for primary actions, active navigation, and the shared dashboard/workspace hero gradient. Keep the gradient definition identical on both hero banners.
- Use `--yellow-400` (`#FFDC68`) for primary accent highlights such as Add Event, active navigation, and the Event badge.
- Use `--page`, `--surface`, `--line`, `--text`, and `--muted` for page background, surfaces, borders, main text, and supporting text. Do not substitute a different gray or navy on a single screen.
- Keep status colors semantic (success, warning, error) and pair each with text or an icon. The dashboard heading dots and the scanner-frame corner marks are the only intentional four-color decorative accents.
- Keep the event date and location icons blue. Do not recolor them to match the yellow badge or add decorative artwork to the right side of the event banner.

### Radius, spacing, and typography

- Dashboard and workspace hero banners share `--radius-dashboard-hero`: 16px above 700px viewport width and 14px at or below 700px. Change the shared token/breakpoint together if the overall design is revised.
- Standard cards use 12px corners, dialogs 16px, form controls 8px, and status/detail chips use pill corners. Do not give the event header a separate radius.
- Use the existing Segoe UI/system font stack and a 4px spacing scale. Prefer 8, 12, 16, 24, and 32px gaps/padding; compact controls may use smaller spacing where the current component requires it.
- Subline/supporting copy is tertiary text: use 16px with a softer muted color than headings and primary content, adjusted for sufficient contrast against its background.
- Keep title, section heading, and card heading hierarchy consistent across pages. Use the same button heights, border, focus ring, and hover/disabled behavior for matching control types.

### Icons and responsive behavior

- Use inline SVGs for the current dashboard icon implementation, with a consistent 24×24 viewBox, simple strokes, and `aria-hidden="true"` for decorative icons. Use `currentColor` unless the icon has a specified semantic color, such as the blue workspace date/location icons.
- Reuse the current responsive breakpoints: 920px for compact content, 700px for the stacked navigation/mobile layout and hero radius change, and 430px for narrow control adjustments. When a shared component changes at a breakpoint, update every screen that uses it.
- Preserve visible keyboard focus and clear hover, pressed, and disabled states. Do not communicate state using color alone.

## Application structure

Both areas use the same left sidebar identity and visual language. The sidebar displays the supplied GDG logo and “GDG Bacolod.” The main area has a page title, optional event context, and one clear primary action.

### Admin navigation

- **Dashboard** — event cards with event name, date, venue, and database-backed registration/check-in totals. Actions: Add Event, Edit Event, Delete Event, Manage Event.
- **Event workspace** — event name/date persistently visible, with sections for Check-In, Participants & Attendance, Name Tags, and Certificates.
- **Settings** — current account details, password change, and invitations for Staff/Admin accounts. Invitation recipients set their own display name, username, and password through a one-use link that expires after 24 hours. Until email delivery is configured, an Admin copies and shares the link manually.

### Staff navigation

- **Staff check-in page** — use a compact horizontal header like the supplied `Screenshot 2026-10-24 232102.png`: small GDG logo/wordmark on the left, one bordered Event selector with its action/status chip centered, and a circular initial plus account name grouped as the identity, followed by a separate accessible icon-only Sign out button on the right. Use a recognizable door-and-right-arrow sign-out symbol, a 44px hit area without a persistent border, and visible hover and keyboard-focus states. On mobile, keep the avatar and sign-out control visible and hide the name only when needed to preserve header space. After Staff starts check-in, show a green dot and “Check-In Active” chip in the selector group. Keep the header short. Staff can select another event from this control; confirm before switching, stop the old camera session, and keep switching disabled while a check-in request is processing.
- **Scan area** — show the “Scan QR Code” heading with the participant instruction directly below it using tight spacing, following `Screenshot 2026-10-24 232203.png`. The scanner and manual ticket entry are the Staff workspace content. Keep selected-event context in the header selector; omit a separate active-event banner/card from the scan area. Give the camera preview a prominent, roomy size on desktop (about 62vh, capped at 640px) and keep it comfortably usable on phones (about 52vh, with a 320px minimum); the workspace may expand to 1040px so the wider camera view is useful. Show the full video image without cropping, mirrored horizontally for easier positioning. QR decoding scans the full camera preview; the colored scanner frame is a visual positioning guide and does not crop decoding. Show “Looking for a QR code” during camera scanning and detected feedback before verification; if the payload is not registered for the selected event, report that it was detected but not matched. Request an ideal 1280×720 camera stream where the device supports it.

Manual entry appears as a clearly outlined button labeled “Enter ticket manually” with a down chevron, hover/pressed feedback, and keyboard focus, making its click-to-expand action obvious in both Admin and Staff check-in.

Results should appear in the scan page rather than forcing navigation away. On a result, present the outcome and participant details where appropriate, then allow staff to continue scanning.

## Screen behavior

### Login

The login screen uses `public/assets/images/gdg-login.jpg` as a full-page background with a dark, rounded sign-in card on the right at desktop widths. On narrow screens, center the card over the background. Center the supplied `gdg-logo.svg` beside the GDG Bacolod name on one line, with the “Event Check-In Portal” heading and concise “Sign in to continue.” supporting text centered below. Keep credential labels and fields left-aligned for readability. Accept an email address or username and password, include an accessible eye-icon visibility toggle and a clear Sign in button, and show server feedback for failures. Provide the same eye-icon toggle for password and confirmation fields during first-Admin setup, invitation/reset, and password change. An initial Admin can be created once through a localhost-only setup page; the setup becomes unavailable after an Admin account exists. The setup form asks for username, email, and password; the display name is set to the username. Authentication uses hashed passwords, a protected PHP session, CSRF tokens, and role checks. Settings lets a signed-in Admin change their password after entering the current password. For other accounts, an Admin can generate a single-use password-reset link; recipients choose the new password. Automatic email delivery and self-service recovery are not configured.

### Settings and invitations

Follow the supplied Settings screenshot: shared Admin sidebar, centered content column, page title/subtitle, account summary, Account Security card, Invite Staff & Admins card, Team Accounts list, and a softly highlighted About this system panel. The account summary shows the signed-in user's name, role, and email. The password dialog requires the current password and matching new password entries. The invitation form accepts an email and Admin or Staff role. On success, show a copyable one-time invitation URL and its 24-hour expiry; do not imply that an email was sent. Staff invitees choose one username that is also saved as their display name; Admin invitees choose a separate display name and username. Both choose a password and are signed in after activation. The token is single-use and stored hashed. Team Accounts lists each account's identity, role, and status. Admin actions renew a pending setup link, restore an inactive account through a new setup link, issue password-reset links, and deactivate other accounts. Do not offer self-deactivation; server logic also protects the last active Admin. All links are shared manually until email delivery is configured. Staff sign in to a focused event selector/check-in screen; they do not receive the Admin sidebar or Admin tools.

### Admin dashboard

Use a page heading and a prominent Add Event action. Each event card shows the event name, date, and basic details; totals are optional and must come from real records. Keep Edit Event visually secondary to Manage Event. Event editing must communicate that existing attendance history is preserved. Destructive or archival actions, if eventually supported, require a clear confirmation.

The dashboard loads event cards and each event's registered and checked-in totals from the database. Totals refresh after check-in and participant import. The heading uses the four colored dots shown in `docs/ui pics/dashboard UI aditional.png`. Search is followed by All, Active, Upcoming, and Completed filters. The Add/Edit Event dialog follows `docs/ui pics/add events card.png`: event name, date, date-derived status, required location, close control, and Cancel/Create Event actions. The imported `events` table has no status or description columns, so status is derived from date and description is not offered. Add/edit/delete actions save through the Admin event endpoint; delete asks for confirmation and the database rejects deletion when linked registrations or records exist.

### Event workspace

The workspace opens from Manage Event and shows the selected event name, date, and venue. Its event header follows the layout in `Screenshot 2026-10-24 161413.png`, using the same navy gradient and yellow accent as the Admin Dashboard, blue date/location icons, compact detail chips, and the shared dashboard hero radius (16 px above 700 px viewport width, 14 px at or below 700 px). Omit the decorative artwork shown on the right side of the reference. The four tabs switch between Check-In, Participants, Name Tags, and Certificates. The Check-In panel follows `Screenshot 2026-10-24 144038.png`, including a dark camera preview, colored scanner corners, camera start/stop, and expandable manual ticket entry. Keep the camera-off icon and label centered as one overlay regardless of scanner-generated DOM changes. Authorized Admin and Staff camera/manual submissions use the event-scoped check-in endpoint; result states distinguish success, already checked in, invalid-for-event, and service errors. QR text is compared directly with `ticket_number`, pending confirmation against a real QR. Camera scanning needs localhost or HTTPS. Check-in records attendance and queues a separate initial name-tag job after the first successful check-in; a queue failure does not undo attendance. The Participants tab follows `Screenshot 2026-10-24 144125.png`: show database-backed registered/checked-in totals, server-side name/nickname/ticket search, check-in status filters, and a five-column participant table for name, nickname, QR/ID, status, and check-in time. Results are event-scoped and paginated; check-in or import refreshes the list when the tab is open. Admins can open an Import CSV dialog, preview a UTF-8 file locally, choose comma/semicolon/tab delimiter, map the five expected fields to actual columns, and explicitly confirm import. Show new-registration, already-registered/skipped, and rejected totals; identify duplicate rows as already registered for the selected event and include their row numbers. The Name Tags tab follows `Screenshot 2026-10-24 144135.png`; it loads event participants and latest print job status/attempt count from the database. Its layout editor uploads event artwork, sets physical width/height in millimeters, and lets the Admin move/resize one nickname area and configure font size, family, alignment, and color. Admins can queue an initial print or an explicit retry/reprint; an active queued request cannot be duplicated. "Queue eligible name tags" includes checked-in registrations only and skips participants with any existing name-tag job; it confirms scope and reports queued/skipped counts. A saved layout is optional for queueing, and a queue record does not mean a physical tag was printed. Physical printer dispatch remains unavailable until artwork and printer setup are supplied. The Certificates tab follows `Screenshot 2026-10-24 144144.png` for its queue and `Screenshot 2026-10-29 231203.png` for its layout editor. The editor provides certificate artwork upload, physical width/height in inches, one draggable/resizable attendee-name area, font size in points, font family, alignment, text color, and Save Layout. The queue lists checked-in participants, check-in time, certificate status, and individual Queue Certificate, Queue Retry, or Queue Reprint actions; a Queue all certificates action queues first-time requests for all checked-in participants and skips any prior certificate job. The active certificate layout must be saved before individual or bulk queue actions become available. Check-in does not automatically queue a certificate. The pictured artwork, participant rows, sample name, and styling values are reference examples, not approved production assets or defaults. Physical printer dispatch remains unavailable.

The CSV import dialog uses three numbered steps: choose a file, select its separator, then match CSV columns to participant fields. Make source-file limits and row count easy to scan, use larger field labels and controls, and keep Cancel/Import actions visible while dialog content scrolls. Announce preview row-count updates to assistive technology.

In the Certificate layout editor, show width and height controls in inches, validate positive dimensions/font sizes and `#RRGGBB` colors, and constrain pointer/keyboard movement and resizing so the name area remains inside the preview. Use a soft neutral settings surface, comfortable field spacing, and readable 12–13px labels and controls. Font-size changes must remain visible even before dimensions are entered. Admins can save and reload artwork and settings for the selected event; dimensions and name-area coordinates convert to millimeters in the shared template fields. Keep the Certificate queue title, eligibility hint, table, and status feedback inside one continuous card so the heading and table do not appear separated.

Show the event name/date in the page header and keep it visible in child sections. Use a compact section navigation or tabs for:

- **Check-In:** opens the same validated staff scanning workflow for authorized users.
- **Participants & Attendance:** searchable, readable table with participant name, nickname, approved identifier, check-in status, and check-in date/time when available. Provide checked-in and not-yet-checked-in filters. Do not expose fields that are not needed by the role.
- **Name Tags:** list checked-in participants only and show print status/history with authorized retry/reprint actions. Provide a status filter for All statuses, Not queued, Queued, Printing, Printed, Failed, and Cancelled, with current counts. Filtering changes visible rows only; it does not change the bulk queue's eligibility count. Printed jobs remain visible; Admin can refresh statuses and explicitly queue another copy. Admin uploads artwork, sets overall width/height in millimeters, positions/resizes one nickname area on a preview, and sets font size, family, alignment, and color. Keep the box within the tag boundary. Bulk initial queueing covers checked-in participants without prior name-tag history. PNG/JPG/WebP formats and the checked-in-only scope were confirmed on 2026-10-30. The 10 MB limit and font dropdown are implementation defaults; overflow and physical printer behavior remain open.
- **Print layout previews:** show the example attendee name directly over the artwork, without a white label, border, or box shadow around the sample text. Keep text legible with its configured color and subtle text shadow; preview styling must not alter saved layout values or physical print output.
- **Certificates:** show checked-in participants and confirmed eligibility; block ineligible issuance, associate issuance with the correct event/person, and show print failures. Keep this workflow separate from check-in.

### Staff event selection

Place the event dropdown and Start Check-In/Switch Event button in the header. Show only events whose Admin-set date is today in the Asia/Manila timezone; past and future events are unavailable to Staff. Do not render a separate event-picker card or active-event card in the main content. Keep the active event selected in the header while scanning; switching requires confirmation and stops the previous camera session. The main check-in area contains the scanner and manual entry. In full-screen mode, Staff can toggle the camera with a sliding switch that moves right when on and left when off. If no event is scheduled today or the event list fails to load, explain the reason beside the disabled header selector.

### Staff scan and result states

Keep the camera preview and scan status as the main focus. Staff has a Full screen control on the camera preview; it expands the preview to the viewport and offers an Exit full screen control, with Escape supported by the browser. Hide the control when the browser does not support the Fullscreen API. Include concise instructions, manual lookup, and a return action. Admin lookup uses name and QR; Staff's exact manual search permission needs confirmation. Request camera permission only when needed. If permission is denied or no camera is available, explain the issue and keep manual lookup available. Never display a “scanned” state unless the scanner actually decoded a value. Verify phone camera access in the intended local network/browser setup.

Present result states in a compact, branded notification card centered over the scanner, with an icon, small GDG Bacolod label, time marker, bold heading, and short explanation. Keep the wording concise. Dismiss each completed result after three seconds and play a brief success, already-checked-in, or error tone when browser audio is available; sound supplements the visual and accessible status. Keep the verification-pending state visible until the request finishes. After a camera verification, keep the camera on and pause accepting scans for one second, then return to the ready state. Ensure the status is announced accessibly:

| Outcome | Message and presentation | Next action |
|---|---|---|
| First successful check-in | Short success heading; show participant name and brief name-tag queue status | Save one initial name-tag job; keep camera running and resume scanning after a one-second pause |
| Already checked in | Short warning heading and participant name; say no duplicate was added | Continue scanning; no automatic print |
| Not registered for this event | Short rejection and event hint | Check event selection or ticket |
| Invalid or unknown QR/ID | Short rejection without claiming attendance was changed | Try again or use manual entry |
| System/database error | Short failure heading and clear retry hint | Retry when available |
| Print failure after successful check-in | Keep successful check-in separate from short name-tag retry guidance | Admin-authorized, recorded print retry; never repeat check-in |

Avoid briefly flashing results that staff may miss. Provide a clear ready state before accepting the next person. Repeated scans must not trigger duplicate writes or automatic reprints.

## Starter visual system (proposed)

These values are derived from the user's dashboard reference screenshot. They are not official GDG brand values and do not claim to reproduce Fluent's complete token set. In implementation, express them through semantic tokens (for example, `colorBrandBackground`, `colorNeutralBackground1`, `colorNeutralForeground1`, and status tokens) rather than scattering raw values through stylesheets. Replace the placeholder palette if the approved brand guidance calls for it. Use a system font stack beginning with Segoe UI.

### Color tokens

| Token | Proposed value | Use |
|---|---|---|
| Primary | `#11165F` | Main actions, links, active navigation |
| Primary hover | `#1B2582` | Hover/pressed primary actions |
| Accent | `#FFDC68` | Add Event action and active navigation highlight |
| App background | `#F3F4F8` | Page background |
| Surface | `#FFFFFF` | Cards, forms, tables, dialogs |
| Border | `#D9DEEA` | Dividers, input and card borders |
| Text | `#161B36` | Main text |
| Muted text | `#66708A` | Supporting text and labels |
| Success | `#16834C` | Successful check-in/print status |
| Warning | `#9B6500` | Already checked in or attention needed |
| Error | `#B42318` | Invalid input, rejected action, system error |
| Focus | `#7164E8` | Visible keyboard focus outline |

Pair status colors with an icon or explicit text label. Check contrast when the actual interface is implemented.

### Type and spacing

- Font: `Segoe UI, system-ui, -apple-system, BlinkMacSystemFont, sans-serif`.
- Body: 16 px / 1.5 line height; compact table text should remain comfortably readable.
- Page title: 28–32 px, semibold; section title: 20–24 px; card title: 16–18 px semibold.
- Use a 4 px base spacing scale: 4, 8, 12, 16, 24, 32, 40, 48 px.
- Standard card padding: 20–24 px; compact control gap: 8–12 px; section gap: 24–32 px.
- Border radius: 8 px for controls, 12 px for standard cards, 16 px for dialogs, and the shared 16/14 px responsive token for dashboard/workspace hero banners. Use subtle shadows only to distinguish raised surfaces.

### Components and interaction states

- **Buttons:** primary for the main action, secondary for alternatives, destructive only for destructive actions. Minimum practical hit area 40–44 px. Include hover, pressed, keyboard focus, disabled, and loading states.
- **Inputs:** persistent visible labels, concise helper/error text, clear focus ring, and validation next to the field. Do not use placeholder text as the only label.
- **Cards:** consistent padding and alignment; put the most important event details first and actions in a predictable location.
- **Tables:** semantic headings, readable row spacing, clear empty state, and accessible search/filter controls.
- **Dialogs:** use for confirmations that prevent data loss or irreversible actions; explain what will happen and provide an explicit cancel action.
- **Notifications:** announce important outcomes accessibly. Keep check-in and print statuses distinct.
- **Icons:** use one consistent, simple icon set if selected during implementation; pair icons with labels for important actions.

### Accessibility and responsive behavior

- Ensure keyboard users can reach navigation, scanning controls, search, and actions in a logical order with a visible focus indicator.
- Use semantic headings, form labels, button names, table headers, and accessible live announcements for verification results.
- Never communicate success, warning, or failure by color alone.
- Maintain readable contrast, avoid unnecessary motion, and respect reduced-motion preferences.
- At narrow widths, collapse the sidebar into an accessible menu, stack cards, and allow tables to scroll or use an approved compact layout without hiding key actions.
- Keep the scanner preview large enough for reliable use on the target laptop and do not let decorative content compete with it.

### Certificate layout save feedback

- On save, change the button label to “Saving…” and announce progress in the layout status area.
- On success, lock and visually mute the saved settings, hide Save, show an enabled Edit button, and announce confirmation. Edit returns the settings to an editable state with Save changes and Cancel actions.
- Any edit returns Save to its normal label and announces that changes are unsaved. Cancel reloads the last saved values and confirms before discarding unsaved edits.
- On failure, show an error and change the button label to “Try again”; retain the current form values so the Admin can retry.
- Do not show a save result for an event after the Admin has switched to another event.

## Decisions to confirm before UI implementation

- Approve or revise the proposed colors, typography, and component sizing.
- Confirm any additional GDG Bacolod brand guidance beyond the supplied logo asset.
- Confirm exact event details and which participant fields staff may view.
- Confirm whether Staff can manually search by name or only scan/enter QR/ticket ID.
- Confirm whether workspace sections use tabs or another navigation pattern.
- Provide approved event-specific name-tag artwork, printer constraints, and certificate templates before relying on physical output.
- Confirm target laptop resolution and whether touch use is expected.
