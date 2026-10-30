# Testing checklist

This is a planned checklist, not a record of completed tests. Read-only metadata inspection confirms the local account schema now uses `admins`; Admin login, event CRUD, and check-in have not been exercised in a browser. Do not mark a box complete until the behavior has been verified.

For every new or changed feature, use the [system interaction and workflow review](interaction-review.md) to trace actor, eligibility, validation, state changes, side effects, feedback, repeat/concurrent requests, completion/history, and refresh behavior across the UI and server. Add concrete scenarios below and leave them unchecked until exercised.

## Authentication and authorization

- [ ] The initial Admin setup works only from localhost and becomes unavailable after the first account exists.
- [ ] After account migration, existing booth Admins sign in to this app with their preserved username/password hash and receive Admin role; this depends on compatible hash formats.
- [x] Isolated migration fixture preserved Staff role and inactive status.
- [x] Duplicate-username preflight stopped before changing account tables; original account rows remained intact in the isolated fixture.
- [x] Isolated migration fixture remapped correction, print-template, print-job, import-batch, token, and creator references to the correct `admins.id`.
- [x] Revised merge migration used a temporary ID map, dropped `users` after remapping references, and left `admins` as the only shared account table in the isolated fixture.
- [ ] Cleanup migration for databases already migrated by the earlier revision removes only `users_legacy` and `users_admin_id_map_20261030`, leaving `admins` and its foreign keys intact.
- [ ] Existing event-app sessions are rejected after the session-name version bump and require sign-in; no old session can resolve to a different account after ID remapping.
- [ ] Booth app login accepts only `role = 'admin' AND is_active = 1`; booth writes use named columns and still work with the expanded `admins` table.
- [ ] Admin can sign in using email or username; inactive users and Staff cannot enter the Admin dashboard.
- [ ] Staff can sign in and are sent to the Staff event picker; Admin-only Settings, event, participant, and print routes reject Staff requests.
- [ ] Admin invitation creates an inactive account and exposes a copyable link without sending email; the recipient can choose a display name, unique username, and password.
- [ ] Staff invitation setup asks for one username only and stores that value as both username and display name; invalid or duplicate usernames are rejected server-side.
- [ ] Team Accounts lists roles and account states without exposing password hashes or tokens.
- [ ] Admin can renew a pending invitation and only the newest link remains usable.
- [ ] Admin can create a one-time password-reset link for an active account; the recipient can change the password, and the old password stops working.
- [ ] Admin can deactivate another account; its next request is rejected, unused links are invalidated, and the account can be restored only through a fresh setup link.
- [ ] An Admin cannot deactivate their own account or the last active Admin account.
- [ ] Invitation token expires after 24 hours, is single-use, and is not stored in plaintext; invalid, expired, or reused links cannot activate an account.
- [ ] Existing active accounts cannot be silently replaced by an invitation; pending invitation renewal invalidates the previous link.
- [ ] Admin can change password only after entering the correct current password; invalid current password leaves the stored credential unchanged.
- [ ] Passwords are stored using a secure password-hashing API; login rejects invalid credentials.
- [ ] Passwords are stored using a secure password-hashing API; login rejects invalid credentials.
- [ ] Session handling and logout work safely.
- [ ] Admin pages redirect signed-out users to login and reject a revoked/inactive account.
- [ ] Staff cannot access admin pages/actions by direct URL or modified requests.
- [ ] Unauthorized event/participant IDs are rejected server-side.
- [ ] State-changing requests have appropriate CSRF protection and validation.

## Events and data isolation

- [ ] Admin can create/edit valid event details; invalid required values are rejected and changes persist after refresh.
- [ ] Event list and summary totals load from the database; date-derived status filters work.
- [ ] Non-Admin and requests without a valid CSRF token cannot create, edit, or delete events.
- [ ] An event with linked registrations or records cannot be deleted; its history remains intact.
- [ ] Editing an event preserves related registrations and attendance.

## Participant CSV import

- [ ] Admin can preview a UTF-8 CSV locally, select comma/semicolon/tab delimiter, and map the five fields without uploading before confirmation.
- [ ] Staff and signed-out users cannot import; invalid CSRF, missing event, malformed mapping, non-CSV, oversized file, and over-limit row count are rejected.
- [ ] Valid rows create registrations only for the selected event; an existing attendee is reused by email without overwriting their current profile.
- [ ] Repeated attendee/event registrations are explicitly reported as already registered and skipped; ticket conflicts and invalid rows are rejected with row numbers and reasons.
- [ ] The summary counts match the inserted/skipped/rejected rows and are recorded in `import_batches`; the original file is not retained.
- [ ] Re-importing the same file does not duplicate attendees or registrations.
- [ ] Staff can select an event and the active event remains visible.
- [ ] Staff event selection appears in the header before and during check-in; there is no separate main-content picker card.
- [ ] Staff can enter and exit scanner full screen, including with Escape; the Admin scanner has no Full screen control.
- [ ] QR detection continues after entering and leaving Staff scanner full screen.
- [ ] Staff camera switch is hidden outside full screen, slides right when on and left when off, and starts/stops the camera without leaving the event workspace.
- [ ] Staff sees only events dated today in Asia/Manila; past/future events are absent, and the empty state explains when none are scheduled today.
- [ ] A modified or stale Staff request for an event not dated today is rejected server-side; Admin check-in behavior is unchanged.
- [ ] After an event is selected, the Staff scan area has no active-event banner/card; scanner and manual entry remain visible.
- [ ] The Staff header stays compact at desktop and mobile widths; its active status chip, account identity, event selector, and sign-out remain readable and usable.
- [ ] Staff sign-out is visibly recognizable, has a 44px hit area without a persistent square border, and shows hover and keyboard-focus feedback.
- [ ] “Scan QR Code” and its instruction text sit close together without crowding the scanner controls.
- [ ] Switching events asks for confirmation, stops the prior camera session, and cannot be triggered while verification is pending.
- [ ] Empty event lists and event-load failures are explained next to a disabled header selector.
- [ ] Participants from another event cannot be checked in for the selected event.
- [ ] Dashboard totals match the underlying records.

## Participant list

- [ ] Admin-only participant endpoint returns only registrations for the selected event and rejects invalid event IDs.
- [ ] Registered and checked-in totals match database records, including after a successful check-in.
- [ ] Search finds by full name, nickname, and exact/partial ticket text without treating `%` or `_` as user-supplied wildcards.
- [ ] All, checked-in, and not-checked-in filters return the expected rows.
- [ ] Pagination has no missing or duplicated rows across pages, including while filtering or searching.
- [ ] Empty, loading, and database error states are clear; no email is exposed in the participant list response.

## Import

- [ ] Import mapping matches the actual organizer file, not assumed column names.
- [ ] Valid, missing, malformed, duplicate, and unexpected values are handled and reported.
- [ ] Re-import behavior is safe and does not silently duplicate or overwrite records.
- [ ] Import errors do not leave partial/corrupt data; existing data is preserved.

## QR and check-in

- [ ] Valid registered participant, camera scan and manual entry: exactly one timestamped check-in for the selected event and clear success result.
- [ ] Already checked-in participant: clear result, no duplicate, no automatic reprint.
- [ ] Unknown/invalid code: no attendance record and no print request.
- [ ] Participant registered only for a different event: rejected for the active event.
- [ ] Two simultaneous scans of the same ticket create exactly one check-in.
- [ ] Camera permission, stop/restart, rear-camera selection, and camera shutdown after leaving workspace work on the intended browsers/devices.
- [ ] After a camera check-in result, the camera remains on, pauses new verification for one second, ignores a QR while it remains in view, and can scan it again after it leaves and returns.
- [ ] Camera and manual check-in outcomes play their short sound cues after user interaction in browsers that support audio; visual status remains clear when audio is unavailable or muted.
- [ ] QR decoded value matches the organizer's actual `ticket_number` payload.
- [ ] Manual lookup uses an approved identifier and applies the same event validation.
- [ ] Database/system errors are not presented as successful check-in and can be retried safely.

## Name-tag printing

- [ ] Admin can upload the accepted template format, enter positive tag width/height, and save one draggable/resizable name area whose bounds stay inside the tag.
- [ ] Admin can save and reload font size, font family, text alignment, and text color with the selected template; invalid values are rejected.
- [ ] Reopening the template preview restores the saved dimensions and name-area position/size; layout is reused for multiple attendees without entering names one by one.
- [ ] Print output uses the attendee name field specified by the approved name-tag rule and respects the saved area; font sizing, alignment, and overflow behavior match the confirmed choices.
- [ ] If no active name-tag layout exists, check-in still records attendance and queues the separate name-tag job with a null template reference; physical printer dispatch must remain unavailable until a renderer can handle it.
- [ ] First successful check-in creates exactly one separate queued name-tag job; repeated check-in creates no additional job and does not change attendance.
- [ ] If saving a name-tag job fails after check-in, attendance remains successful and the UI reports queue failure separately.
- [ ] Name Tags lists only checked-in registrations for the selected event and shows latest job status and total name-tag attempts.
- [ ] Name-tag status filter options show accurate counts for all, not queued, queued, printing, printed, failed, and cancelled jobs; selecting each status shows only matching rows and a clear empty state when none match.
- [ ] Changing the visible status filter does not change the checked-in, no-history count or the records included by bulk queueing.
- [ ] Name-tag layout editor saves/loads artwork and geometry per event, and changing a layout preserves earlier template rows referenced by print history.
- [ ] Certificate and name-tag previews show the example name directly over artwork without a white label, border, or box shadow; configured text color and saved layout values remain unchanged.
- [ ] Name-tag initial print/retry/reprint endpoints reject registrations that have not checked in, even if the client submits their registration ID.
- [ ] Bulk initial name-tag queue includes checked-in registrations only, skips every registration with any name-tag job history, and reports the actual queued/skipped counts. Run concurrent individual and bulk requests to verify one first-time job is created per eligible registration.
- [ ] A completed/printed name-tag job remains visible with Printed status and a separate reprint action; it is not silently removed from history.
- [ ] Admin queue actions require CSRF, reject registration IDs from another event, and prevent duplicate requests while a job is queued/printing.
- [ ] Retry/reprint is stored with `is_reprint` and the acting Admin; it does not alter check-in data.
- [ ] Queued status is never displayed as printed; physical dispatch is verified only after a printer integration exists.
- [ ] Output uses the supplied event-specific layout and actual printer/stock configuration.
- [ ] Printing starts only after a successful first check-in.
- [ ] Print failure is reported while attendance remains successful.
- [ ] Retrying print does not create another check-in.
- [ ] Authorized reprint is recorded and is distinct from automatic first print.
- [ ] Browser/printer behavior is verified on the actual event workstation.
- [ ] The local printer service securely claims each queued job once, prints silently to the selected installed printer, and records success/failure without changing check-in status.
- [ ] When the printer computer is offline, jobs remain queued and are processed after it reconnects; retries do not create duplicate attendance or concurrent duplicate print attempts.
- [ ] The service uses only protected server-side credentials; no Supabase privileged key or signing secret is exposed to browser code.
- [ ] A clearly labeled sample name tag prints at the approved dimensions on the actual printer and driver; changing to a replacement printer is verified on that workstation.

## Certificates

- [ ] Certificate list contains only checked-in registrations for the selected event.
- [ ] Certificate queue endpoint rejects unchecked participants, registrations from other events, invalid actions, and requests without valid Admin session/CSRF.
- [ ] Check-in does not automatically queue a certificate. Individual certificate queueing is available only after check-in and after a layout is saved; explicit retries/reprints are recorded with the correct print type, active template, requester, and reprint flag.
- [ ] Bulk queueing adds one first-time certificate job for each checked-in participant without existing certificate history, skips anyone with any prior certificate job, reports queued/skipped counts, and requires confirmation.
- [ ] Individual and bulk certificate queueing are disabled in the UI and rejected by the server until a layout is saved; after saving, every certificate job links to the active event template.
- [ ] Duplicate requests are blocked while a certificate job is queued/printing; check-in state is unchanged by certificate actions.
- [ ] Certificate queue failure leaves successful check-in intact, and certificate queue state remains separate from attendance.
- [ ] Certificate layout editor accepts PNG/JPG/WebP artwork up to 10 MB, updates inch dimensions and text styles, and supports pointer/keyboard name-area positioning/resizing.
- [ ] Saving requires valid artwork, dimensions, name-area bounds, font, alignment, and color; successful saves persist per selected event and reopening that event restores the saved image and settings.
- [ ] Saving a revised layout preserves older template records referenced by print-job history; unauthorized users and invalid CSRF tokens cannot load or save layouts.
- [ ] Invalid image types, oversized images, non-positive dimensions/font sizes, malformed text colors, and name areas outside the preview are rejected clearly.
- [ ] Non-positive dimensions/font sizes and malformed text colors are rejected clearly; dragging/resizing never moves the name area outside the certificate preview.
- [ ] Font dropdown selection updates the preview using the current device's available font or its stated fallback.
- [ ] Certificate preview values reflect the saved template and do not claim that physical printing has happened.
- [ ] Certificate queue shows checked-in participants, check-in time, status, individual queue actions, and the bulk queue action with the current first-time eligible count.
- [ ] Queued status is never shown as printed; physical output is verified only after template/printer integration exists.

## Interface and accessibility

- [ ] Active event and participant identity are clear throughout check-in.
- [ ] Success, already-checked-in, invalid, error, and print-failure states are distinguishable without color alone.
- [ ] Keyboard focus, labels, contrast, and practical keyboard operation are checked.
- [ ] Layout is readable at the target laptop/desktop size and supports loading/empty/error states.

Record environment, steps, expected result, actual result, and outcome for each executed test. Do not mark a box complete until it has actually been verified.
