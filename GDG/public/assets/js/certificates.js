(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const panel = document.querySelector('[data-workspace-panel="certificates"]');
    if (!dashboard || !panel) return;

    const body = panel.querySelector('[data-certificate-body]');
    const feedback = panel.querySelector('[data-certificate-feedback]');
    const queueAllButton = panel.querySelector('[data-certificate-queue-all]');
    let eventId = null;
    let requestNumber = 0;
    let firstTimeEligible = 0;
    let queueAllBusy = false;
    let layoutConfigured = false;

    function updateQueueAllButton() {
        queueAllButton.disabled = queueAllBusy || !layoutConfigured || firstTimeEligible === 0;
        queueAllButton.textContent = queueAllBusy
            ? 'Queueing…'
            : !layoutConfigured
                ? 'Save layout to enable queueing'
                : firstTimeEligible === 0
                ? 'No new certificates to queue'
                : `Queue all eligible certificates (${firstTimeEligible})`;
        queueAllButton.title = !layoutConfigured
            ? 'Save this event’s certificate layout before queueing certificates.'
            : 'Queue first-time certificate jobs for all checked-in participants. Existing certificate requests are skipped.';
        queueAllButton.setAttribute('aria-label', queueAllButton.title);
        queueAllButton.setAttribute('aria-busy', queueAllBusy ? 'true' : 'false');
    }

    function showMessage(message, isError = false, isSuccess = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.classList.toggle('is-success', isSuccess);
        feedback.hidden = !message;
    }

    function setEmpty(message) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        const text = document.createElement('span');
        cell.className = 'certificate-empty';
        cell.colSpan = 4;
        text.textContent = message;
        cell.append(text);
        row.append(cell);
        body.replaceChildren(row);
    }

    function formatTime(value) {
        if (!value) return '—';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(date);
    }

    function statusDetails(status, attempts) {
        switch (status) {
            case 'queued': return ['Queued', 'is-queued'];
            case 'printing': return ['Printing', 'is-queued'];
            case 'printed': return [`Printed · ${attempts} ${attempts === 1 ? 'attempt' : 'attempts'}`, 'is-printed'];
            case 'failed': return ['Print failed', 'is-failed'];
            case 'cancelled': return ['Cancelled', ''];
            default: return ['', ''];
        }
    }

    function render(rows, hasLayout) {
        layoutConfigured = hasLayout;
        firstTimeEligible = rows.filter((participant) => participant.attempts === 0).length;
        updateQueueAllButton();
        if (!rows.length) {
            setEmpty('No checked-in participants are eligible for certificates yet.');
            return;
        }

        const fragment = document.createDocumentFragment();
        rows.forEach((participant) => {
            const row = document.createElement('tr');
            const nameCell = document.createElement('td');
            nameCell.textContent = participant.fullName || '—';
            row.append(nameCell);

            const checkedInCell = document.createElement('td');
            checkedInCell.textContent = formatTime(participant.checkedInAt);
            row.append(checkedInCell);

            const statusCell = document.createElement('td');
            const [statusLabel, statusClass] = statusDetails(participant.status, participant.attempts);
            statusCell.className = `certificate-status ${statusClass}`.trim();
            statusCell.textContent = statusLabel || 'Not queued';
            row.append(statusCell);

            const actionCell = document.createElement('td');
            const actions = document.createElement('div');
            actions.className = 'certificate-actions';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'certificate-action';
            button.dataset.registrationId = String(participant.registrationId);
            button.dataset.action = participant.attempts === 0 ? 'print' : 'reprint';
            if (participant.status === 'queued' || participant.status === 'printing') {
                button.textContent = participant.status === 'printing' ? 'Printing' : 'Queued';
                button.disabled = true;
            } else if (!layoutConfigured) {
                button.textContent = 'Save layout first';
                button.disabled = true;
            } else {
                button.textContent = participant.attempts === 0
                    ? 'Queue Certificate'
                    : participant.status === 'failed' || participant.status === 'cancelled'
                        ? 'Queue Retry'
                        : 'Queue Reprint';
            }
            actions.append(button);
            actionCell.append(actions);
            row.append(actionCell);
            fragment.append(row);
        });
        body.replaceChildren(fragment);
    }

    async function loadRows() {
        if (!eventId || panel.hidden) return false;
        const currentRequest = ++requestNumber;
        showMessage('');
        setEmpty('Loading eligible participants…');
        try {
            const params = new URLSearchParams({ eventId: String(eventId) });
            const response = await fetch(`${dashboard.dataset.certificatesEndpoint}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (currentRequest !== requestNumber) return false;
            if (!response.ok || !result.success) throw new Error(result.message || 'Certificate records could not be loaded.');
            render(result.rows, result.layoutConfigured === true);
            return true;
        } catch (error) {
            if (currentRequest !== requestNumber) return false;
            setEmpty('Certificate records could not be loaded.');
            showMessage(error.message || 'Certificate records could not be loaded. Refresh and try again.', true);
            return false;
        }
    }

    async function queueRequest(button) {
        if (!eventId || button.disabled) return;
        const requestedEventId = eventId;
        const originalLabel = button.textContent;
        if (!layoutConfigured) {
            showMessage('Save a certificate layout for this event before queueing certificates.', true);
            return;
        }
        button.disabled = true;
        button.textContent = 'Queuing…';
        showMessage('Queuing certificate request…');
        try {
            const response = await fetch(dashboard.dataset.certificatesEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    eventId,
                    registrationId: Number(button.dataset.registrationId),
                    action: button.dataset.action,
                    csrfToken: dashboard.dataset.csrfToken,
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                const error = new Error(result.message || 'The certificate request could not be saved.');
                error.status = response.status;
                throw error;
            }
            if (eventId !== requestedEventId) return;
            if (await loadRows()) showMessage(result.message, false, true);
        } catch (error) {
            if (eventId !== requestedEventId) return;
            if (error.status === 404 || error.status === 409) {
                await loadRows();
                showMessage(error.message, true);
                return;
            }
            showMessage(error.message || 'The certificate request could not be saved. Try again.', true);
            button.textContent = originalLabel;
            button.disabled = false;
        }
    }

    async function queueAllCertificates() {
        if (!eventId || queueAllButton.disabled || firstTimeEligible === 0) return;
        const requestedEventId = eventId;
        const requestedCount = firstTimeEligible;
        if (!layoutConfigured) return;
        const confirmed = window.confirm(
            `Queue first-time certificates for ${requestedCount} checked-in participant${requestedCount === 1 ? '' : 's'}? Existing certificate requests will be skipped.`
        );
        if (!confirmed) return;

        queueAllBusy = true;
        updateQueueAllButton();
        showMessage('Queueing certificates for eligible participants…');
        try {
            const response = await fetch(dashboard.dataset.certificatesEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    eventId: requestedEventId,
                    action: 'queue_all',
                    csrfToken: dashboard.dataset.csrfToken,
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                const error = new Error(result.message || 'Certificate requests could not be queued. Try again.');
                error.status = response.status;
                throw error;
            }
            if (eventId !== requestedEventId) return;
            if (await loadRows()) showMessage(result.message, false, true);
        } catch (error) {
            if (eventId !== requestedEventId) return;
            if (error.status === 404 || error.status === 409) await loadRows();
            showMessage(error.message || 'Certificate requests could not be queued. Try again.', true);
        } finally {
            queueAllBusy = false;
            updateQueueAllButton();
        }
    }

    window.addEventListener('gdg:workspace:open', (event) => {
        requestNumber += 1;
        eventId = Number(event.detail?.id) || null;
        firstTimeEligible = 0;
        layoutConfigured = false;
        updateQueueAllButton();
        showMessage('');
        setEmpty('Open Certificates to load checked-in participants.');
    });
    window.addEventListener('gdg:workspace:close', () => {
        eventId = null;
        firstTimeEligible = 0;
        layoutConfigured = false;
        updateQueueAllButton();
        requestNumber += 1;
    });
    window.addEventListener('gdg:certificates:load', () => { void loadRows(); });
    window.addEventListener('gdg:certificate-layout:saved', (event) => {
        if (Number(event.detail?.eventId) !== eventId) return;
        layoutConfigured = true;
        updateQueueAllButton();
        if (!panel.hidden) void loadRows();
    });
    window.addEventListener('gdg:checkin:recorded', () => {
        if (!panel.hidden) void loadRows();
    });
    panel.addEventListener('click', (event) => {
        if (event.target.closest('[data-certificate-queue-all]')) {
            void queueAllCertificates();
            return;
        }
        const button = event.target.closest('button[data-registration-id]');
        if (button) void queueRequest(button);
    });
})();
