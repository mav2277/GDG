<section class="workspace-view" id="event-workspace" aria-labelledby="workspace-event-name" hidden>
    <div class="workspace-wrap">
        <button class="workspace-back" id="back-to-dashboard" type="button">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"></path></svg>
            <span>Back to dashboard</span>
        </button>

        <header class="workspace-event-header">
            <span class="workspace-event-label">EVENT</span>
            <h1 id="workspace-event-name"></h1>
            <div class="workspace-event-meta">
                <span class="workspace-meta-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3.5v3M16 3.5v3M4 9h16"></path></svg>
                    <span id="workspace-event-date"></span>
                </span>
                <span class="workspace-meta-chip" id="workspace-venue-row">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10.2c0 5.1-7 10.3-7 10.3S5 15.3 5 10.2a7 7 0 1 1 14 0Z"></path><circle cx="12" cy="10" r="2.2"></circle></svg>
                    <span id="workspace-event-venue"></span>
                </span>
            </div>
        </header>

        <div class="workspace-tabs" role="tablist" aria-label="Event workspace sections">
            <button class="workspace-tab is-active" id="tab-checkin" type="button" role="tab" aria-selected="true" aria-controls="panel-checkin" data-workspace-tab="checkin">Check-In</button>
            <button class="workspace-tab" id="tab-participants" type="button" role="tab" aria-selected="false" aria-controls="panel-participants" data-workspace-tab="participants" tabindex="-1">Participants</button>
            <button class="workspace-tab" id="tab-name-tags" type="button" role="tab" aria-selected="false" aria-controls="panel-name-tags" data-workspace-tab="name-tags" tabindex="-1">Name Tags</button>
            <button class="workspace-tab" id="tab-certificates" type="button" role="tab" aria-selected="false" aria-controls="panel-certificates" data-workspace-tab="certificates" tabindex="-1">Certificates</button>
        </div>

        <section class="workspace-panel checkin-panel" id="panel-checkin" role="tabpanel" aria-labelledby="tab-checkin" data-workspace-panel="checkin">
            <h2>Scan QR Code</h2>
            <p class="workspace-helper">Scan the participant’s QR code or enter its ticket number. Check-in is verified for this event.</p>

            <div class="camera-preview" aria-live="polite">
                <div class="qr-reader" id="qr-reader" aria-label="QR scanner camera preview"></div>
                <div class="scanner-frame" aria-hidden="true">
                    <span class="scanner-corner scanner-corner-blue"></span>
                    <span class="scanner-corner scanner-corner-red"></span>
                    <span class="scanner-corner scanner-corner-yellow"></span>
                    <span class="scanner-corner scanner-corner-green"></span>
                </div>
                <div class="camera-state" aria-live="polite">
                    <span class="camera-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2l1.3-2h4.4l1.3 2h2A2.5 2.5 0 0 1 20 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-9Z"></path><circle cx="12" cy="13" r="3.2"></circle></svg>
                    </span>
                    <span id="camera-state-message">Camera is off.</span>
                </div>
                <div class="checkin-result" id="checkin-result" role="status" aria-live="polite" hidden></div>
            </div>

            <button class="button button-primary camera-start" id="start-camera" type="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4H5a1 1 0 0 0-1 1v3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"></path><path d="M8 12h8"></path></svg>
                <span>Start Camera</span>
            </button>

            <div class="manual-entry">
                <button class="manual-entry-toggle" id="manual-entry-toggle" type="button" aria-expanded="false" aria-controls="manual-entry-form">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"></rect><path d="M7 9h2m3 0h2m3 0h.1M7 13h2m3 0h2m3 0h.1M8 16h8"></path></svg>
                    <span>Enter ticket manually</span>
                    <svg class="manual-entry-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <form class="manual-entry-form" id="manual-entry-form" hidden>
                    <label for="manual-ticket-id">Ticket number / QR value</label>
                    <div class="manual-entry-controls">
                        <input id="manual-ticket-id" type="text" maxlength="100" required autocomplete="off" placeholder="Enter ticket number">
                        <button class="button button-primary" type="submit">Find participant</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="workspace-panel participants-panel" id="panel-participants" role="tabpanel" aria-labelledby="tab-participants" data-workspace-panel="participants" hidden>
            <div class="participants-topbar">
                <div class="participants-summary" aria-label="Participant totals">
                    <span><strong data-participant-total>0</strong> registered</span>
                    <span class="participants-summary-dot" aria-hidden="true">·</span>
                    <span><strong data-checked-in-total>0</strong> checked in</span>
                </div>
                <button class="participants-import" type="button" data-open-participant-import>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 14v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5"></path></svg>
                    <span>Import CSV</span>
                </button>
            </div>
            <div class="participants-toolbar">
                <label class="participants-search" for="participant-search">
                    <span class="visually-hidden">Search participants</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                    <input id="participant-search" type="search" placeholder="Search name, nickname, or QR code..." autocomplete="off" maxlength="120">
                </label>

                <div class="participant-filters" role="group" aria-label="Filter participants by check-in status">
                    <button type="button" class="participant-filter is-selected" data-participant-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="participant-filter" data-participant-filter="checked-in" aria-pressed="false">Checked in</button>
                    <button type="button" class="participant-filter" data-participant-filter="not-checked-in" aria-pressed="false">Not checked in</button>
                </div>
            </div>

            <div class="participants-table-wrap">
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Nickname</th>
                            <th scope="col">QR / ID</th>
                            <th scope="col">Status</th>
                            <th scope="col">Check-in time</th>
                        </tr>
                    </thead>
                    <tbody data-participants-body>
                        <tr><td class="participants-empty" colspan="5">Open Participants to load event registrations.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="participants-pagination" data-participants-pagination hidden>
                <button class="button button-secondary" type="button" data-participants-previous>Previous</button>
                <span data-participants-page aria-live="polite"></span>
                <button class="button button-secondary" type="button" data-participants-next>Next</button>
            </div>
            <p class="participants-load-error" data-participants-error role="alert" hidden></p>
        </section>

        <section class="workspace-panel name-tags-panel" id="panel-name-tags" role="tabpanel" aria-labelledby="tab-name-tags" data-workspace-panel="name-tags" hidden>
            <p class="nametag-feedback" data-nametag-feedback role="status" aria-live="polite" hidden></p>
            <section class="certificate-layout-card" aria-labelledby="nametag-layout-title" data-nametag-layout>
                <header class="certificate-layout-heading"><div><h2 id="nametag-layout-title">Name-tag layout</h2></div></header>
                <p class="certificate-layout-status" data-nametag-layout-status role="status" aria-live="polite" hidden></p>
                <div class="certificate-layout-editor">
                    <div class="certificate-layout-preview-wrap">
                        <div class="certificate-layout-preview" data-nametag-preview>
                            <p class="certificate-preview-empty" data-nametag-empty>Upload name-tag artwork to preview it.</p>
                            <img class="certificate-artwork" data-nametag-artwork alt="Uploaded name-tag artwork preview" hidden>
                            <div class="certificate-name-area" data-nametag-name-area tabindex="0" role="group" aria-label="Example nickname area preview"><span>Example nickname</span><span class="certificate-name-resize" data-nametag-resize aria-hidden="true"></span></div>
                        </div>
                    </div>
                    <div class="certificate-layout-controls">
                        <label class="certificate-layout-field" for="nametag-artwork-file"><span>Template image</span><input id="nametag-artwork-file" data-nametag-file type="file" accept="image/png,image/jpeg,image/webp"></label>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="nametag-width"><span>Width (mm)</span><input id="nametag-width" data-nametag-width type="number" min="0.01" step="any" inputmode="decimal" required></label>
                            <label class="certificate-layout-field" for="nametag-height"><span>Height (mm)</span><input id="nametag-height" data-nametag-height type="number" min="0.01" step="any" inputmode="decimal" required></label>
                        </div>
                        <small class="certificate-layout-help">Enter the tag's physical size in millimeters.</small>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="nametag-font-size"><span>Font size (pt)</span><input id="nametag-font-size" data-nametag-font-size type="number" min="1" step="any" inputmode="decimal" required></label>
                            <label class="certificate-layout-field" for="nametag-alignment"><span>Align</span><select id="nametag-alignment" data-nametag-alignment required><option value="left">Left</option><option value="center" selected>Center</option><option value="right">Right</option></select></label>
                        </div>
                        <label class="certificate-layout-field" for="nametag-font-family"><span>Font</span><select id="nametag-font-family" data-nametag-font-family required>
                            <option value="" selected>Choose a font</option><option value="Georgia, serif">Georgia</option><option value="'Times New Roman', serif">Times New Roman</option><option value="Arial, Helvetica, sans-serif">Arial</option><option value="Calibri, Arial, sans-serif">Calibri</option><option value="'Segoe UI', Arial, sans-serif">Segoe UI</option><option value="Verdana, sans-serif">Verdana</option><option value="'Courier New', monospace">Courier New</option>
                        </select></label>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="nametag-text-color"><span>Text colour</span><input id="nametag-text-color" data-nametag-text-color type="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" placeholder="#RRGGBB" required></label>
                            <label class="certificate-layout-field" for="nametag-area-size"><span>Name area</span><input id="nametag-area-size" data-nametag-name-area-size type="text" value="Preview area" readonly></label>
                        </div>
                        <button class="certificate-layout-save" data-nametag-save type="button" disabled>Save layout</button>
                        <div class="certificate-layout-edit-actions" data-nametag-edit-actions hidden><button class="certificate-layout-edit" data-nametag-edit type="button">Edit</button><button class="certificate-layout-cancel" data-nametag-cancel type="button" hidden>Cancel</button></div>
                        <small class="certificate-layout-help">Use the preview to move or resize the nickname area. Saving creates a new template version.</small>
                    </div>
                </div>
            </section>
            <div class="nametag-bulk-actions"><p>Only checked-in participants are listed.</p><div class="nametag-bulk-buttons"><label class="nametag-filter-label" for="nametag-status-filter">Print status<select id="nametag-status-filter" data-nametag-status-filter><option value="all">All statuses</option><option value="not_printed">Not queued</option><option value="queued">Queued</option><option value="printing">Printing</option><option value="printed">Printed</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></label><button class="nametag-action nametag-refresh" data-nametag-refresh type="button" aria-label="Refresh name-tag statuses" title="Refresh statuses"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 7v5h-5M4 17v-5h5"></path><path d="M5.6 9a7 7 0 0 1 11.7-2L20 12M4 12l2.7 5a7 7 0 0 0 11.7-2"></path></svg></button><button class="nametag-action nametag-queue-all" data-nametag-queue-all type="button" disabled>Queue all eligible name tags</button></div></div>
            <div class="nametag-table-wrap">
                <table class="nametag-table">
                    <thead>
                        <tr>
                            <th scope="col">Participant</th>
                            <th scope="col">Nickname</th>
                            <th scope="col">Print status</th>
                            <th scope="col">Attempts</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody data-nametag-body>
                        <tr><td class="nametag-empty" colspan="5"><span>Open Name Tags to load event participants.</span></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="workspace-panel certificates-panel" id="panel-certificates" role="tabpanel" aria-labelledby="tab-certificates" data-workspace-panel="certificates" hidden>
            <section class="certificate-layout-card" aria-labelledby="certificate-layout-title" data-certificate-layout>
                <header class="certificate-layout-heading">
                    <div>
                        <h2 id="certificate-layout-title">Certificate layout</h2>
                    </div>
                </header>
                <p class="certificate-layout-status" data-certificate-layout-status role="status" aria-live="polite" hidden></p>
                <div class="certificate-layout-editor">
                    <div class="certificate-layout-preview-wrap">
                        <div class="certificate-layout-preview" data-certificate-preview>
                            <p class="certificate-preview-empty" data-certificate-empty>Upload certificate artwork to preview it.</p>
                            <img class="certificate-artwork" data-certificate-artwork alt="Uploaded certificate artwork preview" hidden>
                            <div class="certificate-name-area" data-certificate-name-area tabindex="0" role="group" aria-label="Example attendee name area preview">
                                <span>Example attendee name</span>
                                <span class="certificate-name-resize" data-certificate-resize aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>
                    <div class="certificate-layout-controls">
                        <label class="certificate-layout-field" for="certificate-artwork-file">
                            <span>Template image</span>
                            <input id="certificate-artwork-file" data-certificate-file type="file" accept="image/png,image/jpeg,image/webp">
                        </label>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="certificate-width"><span>Width (in)</span><input id="certificate-width" data-certificate-width type="number" min="0.01" step="any" inputmode="decimal" required aria-describedby="certificate-dimensions-help"></label>
                            <label class="certificate-layout-field" for="certificate-height"><span>Height (in)</span><input id="certificate-height" data-certificate-height type="number" min="0.01" step="any" inputmode="decimal" required aria-describedby="certificate-dimensions-help"></label>
                        </div>
                        <small class="certificate-layout-help" id="certificate-dimensions-help">Enter positive decimal inches.</small>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="certificate-font-size"><span>Font size (pt)</span><input id="certificate-font-size" data-certificate-font-size type="number" min="1" step="any" inputmode="decimal" required></label>
                            <label class="certificate-layout-field" for="certificate-alignment"><span>Align</span><select id="certificate-alignment" data-certificate-alignment required><option value="">Choose</option><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
                        </div>
                        <label class="certificate-layout-field" for="certificate-font-family"><span>Font</span><select id="certificate-font-family" data-certificate-font-family required>
                            <option value="" selected>Choose a font</option>
                            <optgroup label="Serif">
                                <option value="Georgia, serif">Georgia</option>
                                <option value="'Times New Roman', serif">Times New Roman</option>
                            <option value="Cambria, Georgia, serif">Cambria</option>
                            <option value="Garamond, Georgia, serif">Garamond</option>
                            <option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino Linotype</option>
                                <option value="'Playfair Display', Georgia, serif">Playfair Display</option>
                            </optgroup>
                            <optgroup label="Sans serif">
                                <option value="Arial, Helvetica, sans-serif">Arial</option>
                                <option value="Calibri, Arial, sans-serif">Calibri</option>
                                <option value="'Segoe UI', Arial, sans-serif">Segoe UI</option>
                                <option value="Tahoma, sans-serif">Tahoma</option>
                                <option value="Verdana, sans-serif">Verdana</option>
                                <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                <option value="'Century Gothic', Arial, sans-serif">Century Gothic</option>
                            </optgroup>
                            <optgroup label="Monospace">
                                <option value="'Courier New', monospace">Courier New</option>
                                <option value="Consolas, 'Courier New', monospace">Consolas</option>
                            </optgroup>
                        </select></label>
                        <small class="certificate-layout-help">The preview uses fonts available on this device.</small>
                        <div class="certificate-layout-dimensions">
                            <label class="certificate-layout-field" for="certificate-text-color"><span>Text colour</span><input id="certificate-text-color" data-certificate-text-color type="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" placeholder="#RRGGBB" required></label>
                            <label class="certificate-layout-field" for="certificate-name-area-size"><span>Name area</span><input id="certificate-name-area-size" data-certificate-name-area-size type="text" value="Preview area" readonly></label>
                        </div>
                        <button class="certificate-layout-save" data-certificate-save type="button" disabled>Save layout</button>
                        <div class="certificate-layout-edit-actions" data-certificate-edit-actions hidden>
                            <button class="certificate-layout-edit" data-certificate-edit type="button">Edit</button>
                            <button class="certificate-layout-cancel" data-certificate-cancel type="button" hidden>Cancel</button>
                        </div>
                        <small class="certificate-layout-help">Choose artwork and complete each setting to save.</small>
                    </div>
                </div>
            </section>
            <div class="certificate-queue-card">
                <div class="certificate-queue-heading">
                    <div>
                        <h2>Certificate queue</h2>
                        <p>Only checked-in participants are eligible.</p>
                    </div>
                    <button class="certificate-queue-all" data-certificate-queue-all type="button" disabled>Queue all certificates</button>
                </div>
                <div class="certificate-table-wrap">
                    <table class="certificate-table">
                        <thead>
                            <tr>
                                <th scope="col">Participant</th>
                                <th scope="col">Checked in</th>
                                <th scope="col">Certificate</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody data-certificate-body>
                            <tr><td class="certificate-empty" colspan="4"><span>Open Certificates to load checked-in participants.</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="certificate-feedback" data-certificate-feedback role="status" aria-live="polite" hidden></p>
            </div>
        </section>
    </div>
</section>
