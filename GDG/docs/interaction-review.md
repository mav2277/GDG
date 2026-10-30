# System interaction and workflow review

Use this checklist when proposing, implementing, or reviewing a feature. Trace the full workflow across the UI, server, database, and any external service. Review related screens and actions too; a rule is incomplete if only one button or page enforces it.

## Map each user action

For every button, form, scan, import, or other user-triggered action, record:

- **Actor and scope:** which role may act, and which event, account, participant, or record the action applies to.
- **Preconditions and eligibility:** what must already be true. Use the same rule in the visible list, individual action, bulk action, and server endpoint.
- **Validation:** validate format and required values in the UI for quick feedback, then repeat authoritative validation on the server. Check authorization, ownership/event membership, and CSRF for state changes.
- **State transition:** identify the starting state, allowed next states, persisted changes, and whether the operation is atomic or can be retried safely.
- **Side effects:** identify linked records, notifications, queued work, files, or external calls. Keep separate outcomes separate; for example, a successful check-in must not be undone by a print-queue failure.
- **Feedback:** define loading, success, partial success, empty, validation error, permission error, conflict, and service-failure states. Never show success until the operation actually succeeded.
- **Repeat and concurrency behavior:** decide what a second click, duplicate submission, stale form, or simultaneous request does. Prevent duplicate records and explain conflicts clearly.
- **Completion and history:** decide what users see after completion. Preserve useful history, show the final state, and make retry, reprint, undo, cancel, or removal an explicit action with clear rules.
- **Refresh and navigation:** after a state change, refresh affected counts, lists, and related sections. Ignore late responses from a previous event/page so they cannot update the current view.
- **External outcome:** distinguish a database queue request from the external work actually finishing. State how the user sees later results and what happens when the external service is unavailable.

## Trace the full flow

Write and review a short sequence for each feature:

1. User opens the relevant screen and sees the correct scope and current state.
2. User submits valid, invalid, unauthorized, stale, duplicate, and out-of-scope actions.
3. The server checks authorization, eligibility, and request data before changing state.
4. The database change and dependent side effects either complete safely or report their separate failure.
5. The UI shows the real result and refreshes affected information.
6. A later reload shows the same persisted state; completed records remain or disappear only according to a documented rule.
7. Retry, reprint, cancellation, correction, or reversal follows the intended authorization and history rules.

## Example: check-in and name-tag queue

- Only a successful first check-in creates the automatic initial name-tag request; a repeat scan adds no second check-in or automatic request.
- Only checked-in participants appear in the Name Tags queue view or may receive an initial request, retry, or reprint. Enforce this in list queries, individual endpoints, bulk endpoints, and any future print-service job claim.
- A queue failure does not undo attendance. Show the queue failure separately and leave an Admin retry path.
- Queued/printing, printed, failed, and cancelled states remain understandable. Printed jobs stay in history; a reprint is an explicit new request. Refresh the list when another service changes a job state.
- A saved name-tag layout is optional for queueing in the current project; no queue state means physical printing has occurred.

## Keep this review reusable

For a feature change, update the relevant requirements, UI, database, decision, and testing docs in the same task. Add scenario checks to [the testing checklist](testing.md), but mark them complete only after they have actually been exercised. Record unresolved choices and the source of confirmed rules. Do not infer permissions, eligibility, retention, retry, or removal behavior from a mockup alone.

## Shared account table consolidation

This account change is approved: `admins` becomes the single shared account source for the booth and event app. Existing booth Admin rows remain active and become event-app Admins; migrated event-app accounts retain role, active state, credentials, timestamps, and related token/audit/print/import history. User IDs are remapped to avoid collisions and dependent references are updated. The merge uses a temporary ID map and drops `users` after references are remapped. The earlier imported revision's archive and ID map have since been removed; read-only metadata inspection confirms all six account foreign keys now reference `admins.id`. Because IDs may change, the event app uses a new PHP session name so every prior event-app session must sign in again.

Before running the migration, inspect username collisions and the booth application's login/write code. The booth app must restrict access to rows with `role = 'admin' AND is_active = 1`; otherwise event-app Staff accounts can authenticate there. It must use named INSERT columns because the shared table gains fields. Verify that its stored password hashes are compatible with PHP `password_verify()` before relying on existing booth credentials in the event app.

- Valid/no collision: the migration adds account fields to `admins`, copies rows, maps every actor/token reference, adds the new foreign keys, and removes `users` plus the temporary map. For a database already migrated by the earlier revision, use the cleanup file instead; do not rerun the merge.
- Username collision or existing `users_legacy`: migration signals an error before DDL; resolve the collision or inspect the prior migration before rerunning.
- After migration: Admin and Staff login reads active rows from `admins`; role-based server checks continue to separate event-app access. Password changes and deactivation affect the shared row; the booth app must enforce both role and active state.
- Concurrent booth access: pause writes from both apps while applying the migration and deploy the updated event app only after the migration succeeds.
- Refresh/history: old event-app sessions are invalidated; users sign in again. Existing links/history remain associated with the remapped account IDs.
