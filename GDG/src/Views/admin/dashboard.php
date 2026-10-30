<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#11165f">
    <title>Admin Dashboard | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=6">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css?v=3">
    <link rel="stylesheet" href="assets/css/event-workspace.css?v=1">
    <link rel="stylesheet" href="assets/css/participants.css?v=5">
    <link rel="stylesheet" href="assets/css/nametags.css?v=7">
    <link rel="stylesheet" href="assets/css/certificates.css?v=16">
    <link rel="stylesheet" href="assets/css/checkin-scanner.css?v=14">
    <script src="assets/js/ui-feedback.js" defer></script>
    <script src="assets/js/admin-sidebar.js" defer></script>
    <script src="assets/js/event-workspace.js?v=4" defer></script>
    <script src="assets/js/participants.js?v=1" defer></script>
    <script src="assets/js/participant-import.js?v=2" defer></script>
    <script src="assets/js/nametags.js?v=7" defer></script>
    <script src="assets/js/name-tag-layout.js?v=2" defer></script>
    <script src="assets/js/certificates.js?v=8" defer></script>
    <script src="assets/js/certificate-layout.js?v=7" defer></script>
    <script src="assets/vendor/html5-qrcode-2.3.8.min.js" defer></script>
    <script src="assets/js/checkin-scanner.js?v=18" defer></script>
    <script src="assets/js/admin-dashboard.js?v=8" defer></script>
</head>
<body>
    <div class="app-shell">
        <?php require dirname(__DIR__) . '/components/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="content-wrap" id="dashboard-view" data-checkin-endpoint="check-in.php" data-events-endpoint="admin-events.php" data-participants-endpoint="event-participants.php" data-participant-import-endpoint="event-participant-import.php" data-nametags-endpoint="event-nametags.php" data-nametag-layout-endpoint="event-nametag-layout.php" data-certificates-endpoint="event-certificates.php" data-certificate-layout-endpoint="event-certificate-layout.php" data-csrf-token="<?= escape_html($csrfToken) ?>" data-initial-events="<?= escape_html(json_encode($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)) ?>">
                <section class="dashboard-hero" aria-labelledby="page-title">
                    <div class="hero-topline">
                        <div>
                            <div class="brand-dots" aria-hidden="true">
                                <span class="brand-dot brand-dot-red"></span>
                                <span class="brand-dot brand-dot-yellow"></span>
                                <span class="brand-dot brand-dot-blue"></span>
                                <span class="brand-dot brand-dot-green"></span>
                            </div>
                            <h1 id="page-title">Admin Dashboard</h1>
                            <p class="hero-description">Manage events, participants, attendance, and printing for GDG Bacolod.</p>
                        </div>
                        <button class="button button-yellow add-event-button" id="add-event-button" type="button">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
                            <span>Add Event</span>
                        </button>
                    </div>

                    <div class="summary-list" aria-label="Event summary">
                        <div class="summary-card">
                            <span class="summary-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3.5v3M16 3.5v3M4 9h16M8 12h2v2H8z"></path></svg>
                            </span>
                            <span class="summary-text"><strong id="total-events">0</strong><span>Total Events</span></span>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"></circle><path d="M12 7v5l3.2 2"></path></svg>
                            </span>
                            <span class="summary-text"><strong id="upcoming-events">0</strong><span>Upcoming</span></span>
                        </div>
                    </div>
                </section>

                <section class="events-section" aria-label="Events">
                    <div class="event-toolbar">
                        <label class="search-field" for="event-search">
                            <span class="visually-hidden">Search events</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                            <input id="event-search" type="search" placeholder="Search events..." autocomplete="off">
                        </label>
                        <div class="event-filters" role="group" aria-label="Filter events by status">
                            <button class="filter-button is-selected" type="button" data-event-filter="all" aria-pressed="true">All</button>
                            <button class="filter-button" type="button" data-event-filter="active" aria-pressed="false">Active</button>
                            <button class="filter-button" type="button" data-event-filter="upcoming" aria-pressed="false">Upcoming</button>
                            <button class="filter-button" type="button" data-event-filter="completed" aria-pressed="false">Completed</button>
                        </div>
                        <span class="event-count" id="event-count" aria-live="polite">0 events</span>
                    </div>

                    <?php if ($eventLoadError !== null): ?>
                        <p class="event-load-error" role="alert"><?= escape_html($eventLoadError) ?></p>
                    <?php endif; ?>
                    <div class="event-grid" id="event-grid" aria-live="polite"></div>

                    <div class="empty-state" id="empty-state">
                        <span class="empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3.5v3M16 3.5v3M4 9h16M12 12v5M9.5 14.5h5"></path></svg>
                        </span>
                        <h2 id="empty-title">No events yet</h2>
                        <p id="empty-description">Add an event to start setting up your GDG Bacolod workspace.</p>
                        <button class="button button-primary" id="empty-add-button" type="button">Add your first event</button>
                    </div>
                </section>
            </div>

            <dialog class="participant-import-dialog" id="participant-import-dialog" aria-labelledby="participant-import-title">
                <form class="participant-import-form" id="participant-import-form">
                    <header class="participant-import-heading">
                        <div>
                            <h2 id="participant-import-title">Import participants</h2>
                            <p>Follow the steps below, then review the column matches before importing.</p>
                        </div>
                        <button class="participant-import-close" type="button" data-close-participant-import aria-label="Close">×</button>
                    </header>

                    <div class="participant-import-source">
                        <label class="participant-import-field" for="participant-csv-file">
                            <span class="participant-import-step-title"><span class="participant-import-step-number">1</span>Choose CSV file</span>
                            <input id="participant-csv-file" type="file" accept=".csv,text/csv" required>
                            <small>UTF-8 CSV, up to 5 MB and 20,000 participant rows.</small>
                        </label>

                        <label class="participant-import-field participant-import-delimiter" for="participant-csv-delimiter">
                            <span class="participant-import-step-title"><span class="participant-import-step-number">2</span>Choose separator</span>
                            <select id="participant-csv-delimiter">
                                <option value="comma">Comma (,)</option>
                                <option value="semicolon">Semicolon (;)</option>
                                <option value="tab">Tab</option>
                            </select>
                            <small>Use the character between values in your file.</small>
                        </label>
                    </div>

                    <section class="participant-import-mapping" data-import-mapping hidden aria-labelledby="participant-import-mapping-title">
                        <h3 id="participant-import-mapping-title"><span class="participant-import-step-number">3</span>Match CSV columns</h3>
                        <p class="participant-import-row-count" data-import-row-count aria-live="polite"></p>
                        <p>Select the CSV column that contains each participant field.</p>
                        <div class="participant-import-fields">
                            <label class="participant-import-field" for="import-map-ticket"><span>Ticket number / QR value</span><select id="import-map-ticket" data-import-field="ticket" required></select></label>
                            <label class="participant-import-field" for="import-map-first"><span>First name</span><select id="import-map-first" data-import-field="first" required></select></label>
                            <label class="participant-import-field" for="import-map-last"><span>Last name</span><select id="import-map-last" data-import-field="last" required></select></label>
                            <label class="participant-import-field" for="import-map-email"><span>Email</span><select id="import-map-email" data-import-field="email" required></select></label>
                            <label class="participant-import-field" for="import-map-nickname"><span>Nickname</span><select id="import-map-nickname" data-import-field="nickname" required></select></label>
                        </div>
                    </section>

                    <p class="participant-import-feedback" data-import-feedback role="status" aria-live="polite" hidden></p>
                    <ul class="participant-import-issues" data-import-issues hidden></ul>
                    <footer class="participant-import-actions">
                        <button class="button button-secondary" type="button" data-close-participant-import>Cancel</button>
                        <button class="button button-primary" type="submit" data-import-submit disabled>Import participants</button>
                    </footer>
                </form>
            </dialog>

            <?php require __DIR__ . '/event-workspace.php'; ?>
        </main>
    </div>

    <dialog class="event-dialog" id="event-dialog" aria-labelledby="event-dialog-title">
        <form id="event-form" class="event-form" novalidate>
            <div class="dialog-heading">
                <div>
                    <h2 id="event-dialog-title">Add New Event</h2>
                </div>
                <button class="icon-button dialog-close" type="button" id="dialog-close" aria-label="Close dialog">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                </button>
            </div>

            <label class="form-field" for="event-name">
                <span>Event name <span class="required-mark" aria-hidden="true">*</span></span>
                <input id="event-name" name="eventName" type="text" maxlength="255" required autocomplete="off" placeholder="e.g. GDG Bacolod DevFest 2026" aria-describedby="event-name-error">
                <small class="field-error" id="event-name-error"></small>
            </label>

            <div class="form-row">
                <label class="form-field" for="event-date">
                    <span>Event date <span class="required-mark" aria-hidden="true">*</span></span>
                    <input id="event-date" name="eventDate" type="date" required aria-describedby="event-date-error">
                    <small class="field-error" id="event-date-error"></small>
                </label>
                <div class="form-field event-status-help">
                    <span>Status</span>
                    <output id="event-status-preview">Upcoming</output>
                    <small>Status is based on the event date.</small>
                </div>
            </div>

            <label class="form-field" for="event-venue">
                <span>Location <span class="required-mark" aria-hidden="true">*</span></span>
                <input id="event-venue" name="eventVenue" type="text" maxlength="255" required autocomplete="off" placeholder="Venue" aria-describedby="event-venue-error">
                <small class="field-error" id="event-venue-error"></small>
            </label>

            <div class="dialog-actions">
                <button class="button button-secondary" type="button" id="cancel-event">Cancel</button>
                <button class="button button-primary" type="submit" id="save-event">Create Event</button>
            </div>
        </form>
    </dialog>

    <dialog class="confirmation-dialog" id="delete-dialog" aria-labelledby="delete-dialog-title" aria-describedby="delete-dialog-description">
        <div class="confirmation-content">
            <span class="confirmation-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4.5 7h15M9 7V4.5h6V7m-8.5 0 .8 13h9.4l.8-13M10 10.5v6M14 10.5v6"></path></svg>
            </span>
            <h2 id="delete-dialog-title">Delete this event?</h2>
            <p id="delete-dialog-description">This deletes the event from the database. Events with linked registrations or records cannot be deleted.</p>
            <div class="dialog-actions">
                <button class="button button-secondary" type="button" id="cancel-delete">Cancel</button>
                <button class="button button-danger" type="button" id="confirm-delete">Delete Event</button>
            </div>
        </div>
    </dialog>

    <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
