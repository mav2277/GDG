<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#11165f">
    <title>Staff Check-In | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=6">
    <link rel="stylesheet" href="assets/css/event-workspace.css?v=1">
    <link rel="stylesheet" href="assets/css/checkin-scanner.css?v=14">
    <link rel="stylesheet" href="assets/css/staff-checkin.css?v=14">
    <script src="assets/vendor/html5-qrcode-2.3.8.min.js" defer></script>
    <script src="assets/js/checkin-scanner.js?v=20" defer></script>
    <script src="assets/js/staff-checkin.js?v=7" defer></script>
</head>
<body>
    <div class="staff-shell" id="dashboard-view" data-checkin-endpoint="check-in.php" data-csrf-token="<?= escape_html($csrfToken) ?>">
        <header class="staff-header">
            <a class="staff-brand" href="staff.php" aria-label="GDG Bacolod Staff Check-In">
                <img src="assets/images/gdg-logo.svg" alt="">
                <span><strong>GDG Bacolod</strong><small>Staff Check-In</small></span>
            </a>
            <div class="staff-event-controls">
                <div class="staff-event-select-row">
                    <label class="staff-event-label" for="staff-event-select">Event</label>
                    <select id="staff-event-select"<?= isset($eventLoadError) || $events === [] ? ' disabled' : '' ?><?= isset($eventLoadError) || $events !== [] ? ' aria-describedby="staff-event-select-help"' : '' ?>>
                        <?php if (isset($eventLoadError)): ?>
                            <option value="">Events unavailable</option>
                        <?php elseif ($events === []): ?>
                            <option value="">No events scheduled today</option>
                        <?php else: ?>
                            <option value="">Choose an event</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= (int) $event['id'] ?>" data-name="<?= escape_html($event['name']) ?>" data-date="<?= escape_html($event['date']) ?>" data-venue="<?= escape_html($event['venue']) ?>"><?= escape_html($event['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <button class="button button-primary" id="staff-start-event" type="button" disabled>Start Check-In</button>
                </div>
                <?php if (isset($eventLoadError) || $events !== []): ?>
                    <small id="staff-event-select-help" class="staff-event-help"<?= !isset($eventLoadError) ? ' hidden' : '' ?>>
                        <?php if (isset($eventLoadError)): ?>
                            <?= escape_html($eventLoadError) ?>
                        <?php else: ?>
                            Choose an event to begin.
                        <?php endif; ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="staff-account">
                <div class="staff-account-identity">
                    <span class="staff-account-avatar" aria-hidden="true"><?= escape_html(mb_strtoupper(mb_substr(trim((string) ($user['display_name'] ?? 'Staff')), 0, 1))) ?></span>
                    <span class="staff-account-name"><?= escape_html($user['display_name'] ?? 'Staff') ?></span>
                </div>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
                    <button class="staff-signout-button" type="submit" aria-label="Sign out" title="Sign out">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17 7 15.6 8.4 18.2 11H8v2h10.2l-2.6 2.6L17 17l5-5-5-5ZM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5Z"></path></svg>
                    </button>
                </form>
            </div>
        </header>

        <main class="staff-main">
            <section class="staff-event-empty" id="staff-event-empty">
                <h1>Ready to check in?</h1>
                <p>Choose an event from the header to start scanning participants.</p>
            </section>

            <section class="staff-checkin-panel workspace-panel checkin-panel" id="staff-checkin-panel" data-workspace-panel="staff-checkin" hidden>
                <h2>Scan QR Code</h2>
                <p class="workspace-helper">Scan the participant’s QR code or enter its ticket number. Check-in is recorded for this event.</p>
                <div class="camera-preview" aria-live="polite">
                    <div class="qr-reader" id="qr-reader" aria-label="QR scanner camera preview"></div>
                    <button class="staff-camera-toggle" id="staff-camera-toggle" type="button" role="switch" aria-checked="false" aria-label="Camera off">
                        <span class="staff-camera-switch-track" aria-hidden="true"><span class="staff-camera-switch-thumb"></span></span>
                        <span data-camera-toggle-label>Camera off</span>
                    </button>
                    <button class="staff-fullscreen-toggle" id="staff-fullscreen-toggle" type="button" aria-pressed="false" aria-label="Enter scanner full screen">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4H5a1 1 0 0 0-1 1v3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"></path></svg>
                        <span>Full screen</span>
                    </button>
                    <div class="scanner-frame" aria-hidden="true">
                        <span class="scanner-corner scanner-corner-blue"></span>
                        <span class="scanner-corner scanner-corner-red"></span>
                        <span class="scanner-corner scanner-corner-yellow"></span>
                        <span class="scanner-corner scanner-corner-green"></span>
                    </div>
                    <div class="camera-state" aria-live="polite">
                        <span class="camera-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2l1.3-2h4.4l1.3 2h2A2.5 2.5 0 0 1 20 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-9Z"></path><circle cx="12" cy="13" r="3.2"></circle></svg></span>
                        <span id="camera-state-message">Camera is off.</span>
                    </div>
                    <div class="checkin-result" id="checkin-result" role="status" aria-live="polite" hidden></div>
                </div>
                <button class="button button-primary camera-start" id="start-camera" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4H5a1 1 0 0 0-1 1v3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"></path><path d="M8 12h8"></path></svg><span>Start Camera</span>
                </button>
                <div class="manual-entry">
                    <button class="manual-entry-toggle" id="manual-entry-toggle" type="button" aria-expanded="false" aria-controls="manual-entry-form">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"></rect><path d="M7 9h2m3 0h2m3 0h.1M7 13h2m3 0h2m3 0h.1M8 16h8"></path></svg><span>Enter ticket manually</span><svg class="manual-entry-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>
                    <form class="manual-entry-form" id="manual-entry-form" hidden>
                        <label for="manual-ticket-id">Ticket number / QR value</label>
                        <div class="manual-entry-controls"><input id="manual-ticket-id" type="text" maxlength="100" required autocomplete="off" placeholder="Enter ticket number"><button class="button button-primary" type="submit">Check In</button></div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
