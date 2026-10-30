(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const panel = document.querySelector('[data-workspace-panel="participants"]');
    if (!dashboard || !panel) return;

    const body = panel.querySelector('[data-participants-body]');
    const total = panel.querySelector('[data-participant-total]');
    const checkedInTotal = panel.querySelector('[data-checked-in-total]');
    const searchInput = panel.querySelector('#participant-search');
    const filterButtons = [...panel.querySelectorAll('[data-participant-filter]')];
    const pagination = panel.querySelector('[data-participants-pagination]');
    const pageLabel = panel.querySelector('[data-participants-page]');
    const previousButton = panel.querySelector('[data-participants-previous]');
    const nextButton = panel.querySelector('[data-participants-next]');
    const errorMessage = panel.querySelector('[data-participants-error]');

    let eventId = null;
    let page = 1;
    let filter = 'all';
    let requestNumber = 0;
    let debounceTimer = null;

    function setEmptyMessage(message) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.className = 'participants-empty';
        cell.colSpan = 5;
        cell.textContent = message;
        row.append(cell);
        body.replaceChildren(row);
    }

    function formatTime(value) {
        if (!value) return '—';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(date);
    }

    function addCell(row, value, className = '') {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = value || '—';
        row.append(cell);
    }

    function renderRows(rows) {
        if (!rows.length) {
            setEmptyMessage(searchInput.value.trim() || filter !== 'all'
                ? 'No participants match this search or filter.'
                : 'No registrations are linked to this event yet.');
            return;
        }

        const fragment = document.createDocumentFragment();
        rows.forEach((participant) => {
            const row = document.createElement('tr');
            addCell(row, participant.fullName);
            addCell(row, participant.nickname);
            addCell(row, participant.ticketNumber, 'participant-ticket');
            const status = document.createElement('td');
            status.className = `participant-status ${participant.checkedIn ? 'is-checked-in' : 'is-not-checked-in'}`;
            status.textContent = participant.checkedIn ? '✓ Checked in' : '○ Not checked in';
            row.append(status);
            addCell(row, participant.checkedIn ? formatTime(participant.checkedInAt) : '—');
            fragment.append(row);
        });
        body.replaceChildren(fragment);
    }

    async function loadParticipants() {
        if (!eventId || panel.hidden) return;
        const thisRequest = ++requestNumber;
        errorMessage.hidden = true;
        setEmptyMessage('Loading participants…');
        const params = new URLSearchParams({
            eventId: String(eventId),
            page: String(page),
            search: searchInput.value.trim(),
            status: filter,
        });
        try {
            const response = await fetch(`${dashboard.dataset.participantsEndpoint}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (thisRequest !== requestNumber) return;
            if (!response.ok || !result.success) throw new Error(result.message || 'Participant records could not be loaded.');

            total.textContent = String(result.registered);
            checkedInTotal.textContent = String(result.checkedIn);
            page = result.page;
            renderRows(result.rows);
            const pageCount = Math.max(1, Math.ceil(result.totalRows / result.pageSize));
            pagination.hidden = result.totalRows <= result.pageSize;
            pageLabel.textContent = `Page ${result.page} of ${pageCount}`;
            previousButton.disabled = result.page <= 1;
            nextButton.disabled = result.page >= pageCount;
        } catch (error) {
            if (thisRequest !== requestNumber) return;
            setEmptyMessage('Participant records could not be loaded.');
            errorMessage.textContent = error.message || 'Participant records could not be loaded. Refresh and try again.';
            errorMessage.hidden = false;
            pagination.hidden = true;
        }
    }

    window.addEventListener('gdg:workspace:open', (event) => {
        eventId = Number(event.detail?.id) || null;
        page = 1;
        searchInput.value = '';
        filter = 'all';
        filterButtons.forEach((button) => {
            const selected = button.dataset.participantFilter === filter;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        total.textContent = '0';
        checkedInTotal.textContent = '0';
        setEmptyMessage('Open Participants to load event registrations.');
    });

    window.addEventListener('gdg:workspace:close', () => {
        eventId = null;
        requestNumber += 1;
    });
    window.addEventListener('gdg:participants:load', () => { void loadParticipants(); });

    searchInput.addEventListener('input', () => {
        page = 1;
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => { void loadParticipants(); }, 250);
    });

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filter = button.dataset.participantFilter;
            page = 1;
            filterButtons.forEach((item) => {
                const selected = item === button;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', String(selected));
            });
            void loadParticipants();
        });
    });

    previousButton.addEventListener('click', () => { page = Math.max(1, page - 1); void loadParticipants(); });
    nextButton.addEventListener('click', () => { page += 1; void loadParticipants(); });
})();
