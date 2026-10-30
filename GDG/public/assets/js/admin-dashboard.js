(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    let events = JSON.parse(dashboard.dataset.initialEvents || '[]');
    const endpoint = dashboard.dataset.eventsEndpoint;
    const csrfToken = dashboard.dataset.csrfToken;
    const elements = {
        addButtons: [document.getElementById('add-event-button'), document.getElementById('empty-add-button')],
        totalEvents: document.getElementById('total-events'),
        upcomingEvents: document.getElementById('upcoming-events'),
        eventGrid: document.getElementById('event-grid'),
        emptyState: document.getElementById('empty-state'),
        emptyTitle: document.getElementById('empty-title'),
        emptyDescription: document.getElementById('empty-description'),
        eventCount: document.getElementById('event-count'),
        search: document.getElementById('event-search'),
        filterButtons: [...document.querySelectorAll('[data-event-filter]')],
        dialog: document.getElementById('event-dialog'),
        deleteDialog: document.getElementById('delete-dialog'),
        form: document.getElementById('event-form'),
        dialogTitle: document.getElementById('event-dialog-title'),
        saveButton: document.getElementById('save-event'),
        name: document.getElementById('event-name'),
        date: document.getElementById('event-date'),
        statusPreview: document.getElementById('event-status-preview'),
        venue: document.getElementById('event-venue'),
        nameError: document.getElementById('event-name-error'),
        dateError: document.getElementById('event-date-error'),
        venueError: document.getElementById('event-venue-error'),
    };

    let editingId = null;
    let deletingId = null;
    let activeFilter = 'all';
    let lastFocusedElement = null;

    const svg = {
        calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3.5v3M16 3.5v3M4 9h16M8 12h2v2H8z"></path></svg>',
        pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10.2c0 5.1-7 10.3-7 10.3S5 15.3 5 10.2a7 7 0 1 1 14 0Z"></path><circle cx="12" cy="10" r="2.2"></circle></svg>',
        people: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H7.4A3.4 3.4 0 0 0 4 18.4V20M10 11.6a3.8 3.8 0 1 0 0-7.6 3.8 3.8 0 0 0 0 7.6ZM16 4.3a3.8 3.8 0 0 1 0 7.3M17 15h.6a3.4 3.4 0 0 1 3.4 3.4V20"></path></svg>',
        check: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"></circle><path d="m8.5 12.2 2.2 2.2 4.8-5"></path></svg>',
        arrow: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"></path></svg>',
        edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z"></path></svg>',
        delete: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 7h15M9 7V4.5h6V7m-8.5 0 .8 13h9.4l.8-13M10 10.5v6M14 10.5v6"></path></svg>',
    };

    function localToday() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return today;
    }

    function eventStatus(event) {
        const statuses = {
            upcoming: { label: 'Upcoming', className: 'status-upcoming' },
            active: { label: 'Active', className: 'status-active' },
            completed: { label: 'Completed', className: 'status-completed' },
        };
        if (!event.date) return statuses.upcoming;
        const date = new Date(`${event.date}T00:00:00`);
        const today = localToday();
        if (date.getTime() === today.getTime()) return statuses.active;
        if (date < today) return statuses.completed;
        return statuses.upcoming;
    }

    function formattedDate(value) {
        if (!value) return 'Date not set';
        return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
            .format(new Date(`${value}T00:00:00`));
    }

    function makeElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    function makeIcon(markup, className = '') {
        const element = document.createElement('span');
        element.className = className;
        element.innerHTML = markup;
        return element;
    }

    function makeMetric(iconMarkup, label, value, checked = false) {
        const metric = makeElement('div', 'event-metric');
        const icon = makeIcon(iconMarkup, `metric-icon${checked ? ' checked' : ''}`);
        const copy = makeElement('span', 'metric-copy');
        copy.append(makeElement('strong', '', String(Number(value) || 0)), makeElement('span', '', label));
        metric.append(icon, copy);
        return metric;
    }

    function makeEventCard(event) {
        const article = makeElement('article', 'event-card');
        const heading = makeElement('div', 'event-card-heading');
        heading.append(makeElement('h2', '', event.name));
        const status = eventStatus(event);
        heading.append(makeElement('span', `status-badge ${status.className}`, status.label));

        const metadata = makeElement('div', 'event-meta');
        const dateRow = makeElement('div', 'event-meta-row');
        dateRow.append(makeIcon(svg.calendar), makeElement('span', '', formattedDate(event.date)));
        metadata.append(dateRow);
        if (event.venue) {
            const venueRow = makeElement('div', 'event-meta-row');
            venueRow.append(makeIcon(svg.pin), makeElement('span', '', event.venue));
            metadata.append(venueRow);
        }

        const metrics = makeElement('div', 'event-summary');
        metrics.setAttribute('aria-label', `${event.registered || 0} registered, ${event.checkedIn || 0} checked in`);
        metrics.append(
            makeMetric(svg.people, 'Registered', event.registered),
            makeMetric(svg.check, 'Checked in', event.checkedIn, true),
        );

        const actions = makeElement('div', 'card-actions');
        const manage = makeElement('button', 'button button-primary manage-button');
        manage.type = 'button';
        manage.append(makeIcon(svg.arrow), makeElement('span', '', 'Manage Event'));
        manage.addEventListener('click', () => window.dispatchEvent(new CustomEvent('gdg:workspace:open', { detail: event })));

        const edit = makeElement('button', 'icon-button');
        edit.type = 'button';
        edit.setAttribute('aria-label', `Edit ${event.name}`);
        edit.title = 'Edit event';
        edit.append(makeIcon(svg.edit));
        edit.addEventListener('click', () => openEventDialog(event.id));

        const remove = makeElement('button', 'icon-button delete-event-button');
        remove.type = 'button';
        remove.setAttribute('aria-label', `Delete ${event.name}`);
        remove.title = 'Delete event';
        remove.append(makeIcon(svg.delete));
        remove.addEventListener('click', () => openDeleteDialog(event.id));

        actions.append(manage, edit, remove);
        article.append(heading, metadata, metrics, actions);
        return article;
    }

    function render() {
        const query = elements.search.value.trim().toLocaleLowerCase();
        const visibleEvents = events.filter((event) => {
            const searchable = `${event.name} ${event.venue} ${formattedDate(event.date)}`.toLocaleLowerCase();
            return searchable.includes(query) && (activeFilter === 'all' || eventStatus(event).className === `status-${activeFilter}`);
        });
        elements.totalEvents.textContent = String(events.length);
        elements.upcomingEvents.textContent = String(events.filter((event) => eventStatus(event).label === 'Upcoming').length);
        elements.eventCount.textContent = query || activeFilter !== 'all'
            ? `Showing ${visibleEvents.length} of ${events.length} events`
            : `${events.length} ${events.length === 1 ? 'event' : 'events'}`;
        elements.eventGrid.replaceChildren(...visibleEvents.map(makeEventCard));
        elements.eventGrid.hidden = visibleEvents.length === 0;
        elements.emptyState.hidden = visibleEvents.length > 0;
        const noEventsExist = events.length === 0;
        elements.emptyTitle.textContent = noEventsExist ? 'No events yet' : 'No matching events';
        elements.emptyDescription.textContent = noEventsExist
            ? 'Add an event to start setting up your GDG Bacolod workspace.'
            : 'Try another search or status filter, or choose All to see every event.';
        document.getElementById('empty-add-button').hidden = !noEventsExist;
    }

    async function refreshEventCounts() {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'list', csrfToken }),
            });
            const result = await response.json();
            if (!response.ok || !result.success || !Array.isArray(result.events)) return;
            events = result.events;
            render();
        } catch (error) {
            console.warn('Dashboard event totals could not be refreshed.', error);
        }
    }

    function clearValidation() {
        [elements.name, elements.date, elements.venue].forEach((input) => input.removeAttribute('aria-invalid'));
        [elements.nameError, elements.dateError, elements.venueError].forEach((error) => { error.textContent = ''; });
    }

    function updateStatusPreview() {
        elements.statusPreview.textContent = elements.date.value
            ? eventStatus({ date: elements.date.value }).label
            : 'Choose a date';
    }

    function openEventDialog(id = null) {
        editingId = id;
        const event = id === null ? null : events.find((item) => item.id === id);
        elements.form.reset();
        clearValidation();
        elements.dialogTitle.textContent = event ? 'Edit Event' : 'Add New Event';
        elements.saveButton.textContent = event ? 'Save Changes' : 'Create Event';
        if (event) {
            elements.name.value = event.name;
            elements.date.value = event.date;
            elements.venue.value = event.venue;
        }
        updateStatusPreview();
        lastFocusedElement = document.activeElement;
        elements.dialog.showModal();
        requestAnimationFrame(() => elements.name.focus());
    }

    function closeEventDialog() {
        elements.dialog.close();
        if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    }

    function openDeleteDialog(id) {
        deletingId = id;
        lastFocusedElement = document.activeElement;
        elements.deleteDialog.showModal();
        document.getElementById('cancel-delete').focus();
    }

    function closeDeleteDialog() {
        elements.deleteDialog.close();
        if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    }

    function showToast(message) {
        window.GDGPreviewUI.toast(message);
    }

    async function saveEvent(payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ...payload, csrfToken }),
        });
        let result;
        try { result = await response.json(); } catch { throw new Error('The server returned an unreadable response. Refresh the page and try again.'); }
        if (!response.ok || !result.success) throw new Error(result.message || 'The event could not be saved.');
        return result;
    }

    elements.addButtons.forEach((button) => button.addEventListener('click', () => openEventDialog()));
    elements.search.addEventListener('input', render);
    elements.filterButtons.forEach((button) => button.addEventListener('click', () => {
        activeFilter = button.dataset.eventFilter;
        elements.filterButtons.forEach((filterButton) => {
            const selected = filterButton === button;
            filterButton.classList.toggle('is-selected', selected);
            filterButton.setAttribute('aria-pressed', String(selected));
        });
        render();
    }));
    document.getElementById('dialog-close').addEventListener('click', closeEventDialog);
    document.getElementById('cancel-event').addEventListener('click', closeEventDialog);
    document.getElementById('cancel-delete').addEventListener('click', closeDeleteDialog);
    elements.date.addEventListener('input', updateStatusPreview);
    elements.dialog.addEventListener('click', (event) => { if (event.target === elements.dialog) closeEventDialog(); });
    elements.deleteDialog.addEventListener('click', (event) => { if (event.target === elements.deleteDialog) closeDeleteDialog(); });
    elements.dialog.addEventListener('close', clearValidation);
    window.addEventListener('gdg:checkin:recorded', () => { void refreshEventCounts(); });
    window.addEventListener('gdg:registrations:changed', () => { void refreshEventCounts(); });

    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearValidation();
        const name = elements.name.value.trim();
        const date = elements.date.value;
        const venue = elements.venue.value.trim();
        if (!name) {
            elements.name.setAttribute('aria-invalid', 'true');
            elements.nameError.textContent = 'Enter an event name.';
            elements.name.focus();
            return;
        }
        if (!date) {
            elements.date.setAttribute('aria-invalid', 'true');
            elements.dateError.textContent = 'Choose an event date.';
            elements.date.focus();
            return;
        }
        if (!venue) {
            elements.venue.setAttribute('aria-invalid', 'true');
            elements.venueError.textContent = 'Enter an event location.';
            elements.venue.focus();
            return;
        }

        elements.saveButton.disabled = true;
        try {
            const result = await saveEvent({
                action: editingId === null ? 'create' : 'update',
                id: editingId,
                name,
                date,
                venue,
            });
            const existingRecord = events.find((item) => item.id === editingId);
            const record = {
                ...existingRecord,
                id: result.id ?? editingId,
                name,
                date,
                venue,
                registered: existingRecord?.registered ?? 0,
                checkedIn: existingRecord?.checkedIn ?? 0,
            };
            if (editingId === null) events.unshift(record);
            else {
                const index = events.findIndex((item) => item.id === editingId);
                if (index !== -1) events[index] = record;
            }
            closeEventDialog();
            elements.search.value = '';
            render();
            showToast(result.message);
        } catch (error) {
            showToast(error.message || 'The event could not be saved.');
        } finally {
            elements.saveButton.disabled = false;
        }
    });

    document.getElementById('confirm-delete').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const result = await saveEvent({ action: 'delete', id: deletingId });
            const index = events.findIndex((item) => item.id === deletingId);
            if (index !== -1) events.splice(index, 1);
            closeDeleteDialog();
            render();
            showToast(result.message);
        } catch (error) {
            closeDeleteDialog();
            showToast(error.message || 'The event could not be deleted.');
        } finally {
            button.disabled = false;
        }
    });

    render();
})();
