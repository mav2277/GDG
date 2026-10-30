(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const workspace = document.getElementById('event-workspace');
    const eventName = document.getElementById('workspace-event-name');
    const eventDate = document.getElementById('workspace-event-date');
    const venue = document.getElementById('workspace-event-venue');
    const venueRow = document.getElementById('workspace-venue-row');
    const tabs = [...document.querySelectorAll('[data-workspace-tab]')];
    const panels = [...document.querySelectorAll('[data-workspace-panel]')];
    let activeEventId = null;

    function formatDate(value) {
        if (!value) return 'Date not set';
        const date = new Date(`${value}T00:00:00`);
        return new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }).format(date);
    }

    function selectTab(tabName) {
        tabs.forEach((tab) => {
            const selected = tab.dataset.workspaceTab === tabName;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.workspacePanel !== tabName;
        });
        if (tabName === 'participants') {
            window.dispatchEvent(new CustomEvent('gdg:participants:load'));
        }
        if (tabName === 'name-tags') {
            window.dispatchEvent(new CustomEvent('gdg:nametags:load'));
        }
        if (tabName === 'certificates') {
            window.dispatchEvent(new CustomEvent('gdg:certificates:load'));
        }
    }

    function openWorkspace(eventRecord) {
        if (!eventRecord || !eventRecord.name) {
            window.GDGPreviewUI.toast('This event is no longer available in the preview.');
            return;
        }

        eventName.textContent = eventRecord.name;
        activeEventId = Number(eventRecord.id) || null;
        eventDate.textContent = formatDate(eventRecord.date);
        venue.textContent = eventRecord.venue || '';
        venueRow.hidden = !eventRecord.venue;
        selectTab('checkin');
        dashboard.hidden = true;
        workspace.hidden = false;
        window.scrollTo({ top: 0, behavior: 'auto' });
    }

    function showDashboard() {
        window.dispatchEvent(new CustomEvent('gdg:workspace:close'));
        activeEventId = null;
        workspace.hidden = true;
        dashboard.hidden = false;
        window.scrollTo({ top: 0, behavior: 'auto' });
    }

    window.addEventListener('gdg:workspace:open', (event) => openWorkspace(event.detail));
    window.addEventListener('gdg:checkin:recorded', () => {
        if (!panels.find((panel) => panel.dataset.workspacePanel === 'participants')?.hidden) {
            window.dispatchEvent(new CustomEvent('gdg:participants:load'));
        }
        if (!panels.find((panel) => panel.dataset.workspacePanel === 'name-tags')?.hidden) {
            window.dispatchEvent(new CustomEvent('gdg:nametags:load'));
        }
        if (!panels.find((panel) => panel.dataset.workspacePanel === 'certificates')?.hidden) {
            window.dispatchEvent(new CustomEvent('gdg:certificates:load'));
        }
    });
    document.getElementById('back-to-dashboard').addEventListener('click', showDashboard);
    document.querySelector('[data-dashboard-link]').addEventListener('click', (event) => {
        event.preventDefault();
        showDashboard();
    });

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab.dataset.workspaceTab));
        tab.addEventListener('keydown', (event) => {
            let nextIndex = index;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') nextIndex = 0;
            else if (event.key === 'End') nextIndex = tabs.length - 1;
            else return;

            event.preventDefault();
            const nextTab = tabs[nextIndex];
            selectTab(nextTab.dataset.workspaceTab);
            nextTab.focus();
        });
    });
})();
