(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const panel = document.querySelector('[data-workspace-panel="name-tags"]');
    if (!dashboard || !panel) return;

    const body = panel.querySelector('[data-nametag-body]');
    const feedback = panel.querySelector('[data-nametag-feedback]');
    const queueAllButton = panel.querySelector('[data-nametag-queue-all]');
    const statusFilter = panel.querySelector('[data-nametag-status-filter]');
    const statusOptions = [
        ['all', 'All statuses'],
        ['not_printed', 'Not queued'],
        ['queued', 'Queued'],
        ['printing', 'Printing'],
        ['printed', 'Printed'],
        ['failed', 'Failed'],
        ['cancelled', 'Cancelled'],
    ];
    let eventId = null;
    let requestNumber = 0;
    let currentRows = [];
    let bulkBusy = false;

    function showMessage(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.hidden = !message;
    }

    function setEmpty(message) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        const text = document.createElement('span');
        cell.className = 'nametag-empty';
        cell.colSpan = 5;
        text.textContent = message;
        cell.append(text);
        row.append(cell);
        body.replaceChildren(row);
    }

    function formatStatus(status) {
        switch (status) {
            case 'queued': return ['Queued', 'is-queued'];
            case 'printing': return ['Printing', 'is-queued'];
            case 'printed': return ['Printed', 'is-printed'];
            case 'failed': return ['Print failed', 'is-failed'];
            case 'cancelled': return ['Cancelled', ''];
            default: return ['Not printed', ''];
        }
    }

    function updateStatusFilterOptions() {
        const selectedValue = statusFilter.value || 'all';
        const counts = new Map(statusOptions.map(([value]) => [value, 0]));
        counts.set('all', currentRows.length);
        currentRows.forEach((row) => counts.set(row.status, (counts.get(row.status) || 0) + 1));
        const options = statusOptions.map(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = `${label} (${counts.get(value) || 0})`;
            return option;
        });
        statusFilter.replaceChildren(...options);
        statusFilter.value = counts.has(selectedValue) ? selectedValue : 'all';
    }

    function render(rows) {
        currentRows = rows;
        updateStatusFilterOptions();
        const firstTimeCount = rows.filter((row) => row.attempts === 0).length;
        queueAllButton.disabled = bulkBusy || firstTimeCount === 0;
        queueAllButton.textContent = firstTimeCount ? `Queue eligible name tags (${firstTimeCount})` : 'No eligible name tags to queue';
        const visibleRows = statusFilter.value === 'all'
            ? rows
            : rows.filter((row) => row.status === statusFilter.value);
        if (!visibleRows.length) {
            if (rows.length) {
                setEmpty('No checked-in participants match this print status.');
                return;
            }
            setEmpty('No checked-in participants yet. Name tags become available after check-in.');
            return;
        }

        const fragment = document.createDocumentFragment();
        visibleRows.forEach((participant) => {
            const row = document.createElement('tr');
            const addCell = (value, className = '') => {
                const cell = document.createElement('td');
                if (className) cell.className = className;
                cell.textContent = value || '—';
                row.append(cell);
            };

            addCell(participant.fullName);
            addCell(participant.nickname);
            const [statusLabel, statusClass] = formatStatus(participant.status);
            addCell(statusLabel, `nametag-status ${statusClass}`.trim());
            addCell(String(participant.attempts));

            const actionCell = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'nametag-action';
            button.dataset.registrationId = String(participant.registrationId);
            button.dataset.action = participant.attempts === 0 ? 'print' : 'reprint';
            if (participant.status === 'queued' || participant.status === 'printing') {
                button.textContent = participant.status === 'printing' ? 'Printing' : 'Queued';
                button.disabled = true;
            } else {
                button.textContent = participant.attempts === 0
                    ? 'Queue Print'
                    : participant.status === 'failed' || participant.status === 'cancelled'
                        ? 'Queue Retry'
                        : 'Queue Reprint';
            }
            actionCell.append(button);
            row.append(actionCell);
            fragment.append(row);
        });
        body.replaceChildren(fragment);
    }

    async function loadRows() {
        if (!eventId || panel.hidden) return false;
        const currentRequest = ++requestNumber;
        showMessage('');
        setEmpty('Loading participant print records…');
        try {
            const params = new URLSearchParams({ eventId: String(eventId) });
            const response = await fetch(`${dashboard.dataset.nametagsEndpoint}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (currentRequest !== requestNumber) return false;
            if (!response.ok || !result.success) throw new Error(result.message || 'Name-tag records could not be loaded.');
            render(result.rows);
            return true;
        } catch (error) {
            if (currentRequest !== requestNumber) return false;
            setEmpty('Name-tag records could not be loaded.');
            showMessage(error.message || 'Name-tag records could not be loaded. Refresh and try again.', true);
            return false;
        }
    }

    async function queueRequest(button) {
        if (!eventId || button.disabled) return;
        const submittedEventId = eventId;
        button.disabled = true;
        showMessage('Saving print request…');
        try {
            const response = await fetch(dashboard.dataset.nametagsEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    eventId: submittedEventId,
                    registrationId: Number(button.dataset.registrationId),
                    action: button.dataset.action,
                    csrfToken: dashboard.dataset.csrfToken,
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The print request could not be saved.');
            if (eventId !== submittedEventId) return;
            if (await loadRows()) showMessage(result.message);
        } catch (error) {
            if (eventId !== submittedEventId) return;
            showMessage(error.message || 'The print request could not be saved. Try again.', true);
            button.disabled = false;
        }
    }

    async function queueAll() {
        if (!eventId || bulkBusy) return;
        const submittedEventId = eventId;
        const count = currentRows.filter((row) => row.attempts === 0).length;
        if (!count || !window.confirm(`Queue one initial name-tag request for each of ${count} checked-in participants with no prior name-tag job? Existing jobs and participants who have not checked in will be excluded.`)) return;
        bulkBusy = true;
        queueAllButton.disabled = true;
        showMessage('Queueing initial name-tag requests…');
        try {
            const response = await fetch(dashboard.dataset.nametagsEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ eventId: submittedEventId, action: 'queue_all', csrfToken: dashboard.dataset.csrfToken }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Name-tag requests could not be queued.');
            if (eventId !== submittedEventId) return;
            if (await loadRows()) showMessage(`${result.queued} initial request(s) queued; ${result.skipped} checked-in participant(s) skipped because a name-tag job already exists. Physical printing is not configured.`);
        } catch (error) {
            if (eventId !== submittedEventId) return;
            showMessage(error.message || 'Name-tag requests could not be queued. Try again.', true);
        } finally {
            if (eventId === submittedEventId) {
                bulkBusy = false;
                render(currentRows);
            }
        }
    }

    async function refreshStatuses() {
        const refreshButton = panel.querySelector('[data-nametag-refresh]');
        if (refreshButton.disabled) return;
        refreshButton.disabled = true;
        refreshButton.setAttribute('aria-busy', 'true');
        try {
            await loadRows();
        } finally {
            refreshButton.disabled = false;
            refreshButton.setAttribute('aria-busy', 'false');
        }
    }

    window.addEventListener('gdg:workspace:open', (event) => {
        requestNumber += 1;
        eventId = Number(event.detail?.id) || null;
        currentRows = [];
        statusFilter.value = 'all';
        updateStatusFilterOptions();
        bulkBusy = false;
        queueAllButton.disabled = true;
        queueAllButton.textContent = 'Queue all eligible name tags';
        showMessage('');
        setEmpty('Open Name Tags to load event participants.');
    });
    window.addEventListener('gdg:workspace:close', () => {
        eventId = null;
        requestNumber += 1;
        currentRows = [];
        statusFilter.value = 'all';
        updateStatusFilterOptions();
        bulkBusy = false;
        queueAllButton.disabled = true;
        queueAllButton.textContent = 'Queue all eligible name tags';
    });
    window.addEventListener('gdg:nametags:load', () => { void loadRows(); });
    statusFilter.addEventListener('change', () => { render(currentRows); });
    panel.addEventListener('click', (event) => {
        if (event.target.closest('[data-nametag-queue-all]')) { void queueAll(); return; }
        if (event.target.closest('[data-nametag-refresh]')) { void refreshStatuses(); return; }
        const button = event.target.closest('button[data-registration-id]');
        if (button) void queueRequest(button);
    });
})();
