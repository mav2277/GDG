(() => {
    'use strict';

    const panel = document.getElementById('staff-checkin-panel');
    const emptyState = document.getElementById('staff-event-empty');
    const selector = document.getElementById('staff-event-select');
    const startButton = document.getElementById('staff-start-event');
    const cameraPreview = document.querySelector('.staff-checkin-panel .camera-preview');
    const fullscreenButton = document.getElementById('staff-fullscreen-toggle');
    if (!panel || !emptyState || !selector || !startButton) return;

    let activeEvent = null;
    let verificationPending = false;

    if (cameraPreview && fullscreenButton) {
        if (!document.fullscreenEnabled || typeof cameraPreview.requestFullscreen !== 'function') {
            fullscreenButton.hidden = true;
        } else {
            let wasScannerFullscreen = false;
            const updateFullscreenButton = () => {
                const isFullscreen = document.fullscreenElement === cameraPreview;
                fullscreenButton.setAttribute('aria-pressed', String(isFullscreen));
                fullscreenButton.setAttribute('aria-label', isFullscreen ? 'Exit scanner full screen' : 'Enter scanner full screen');
                fullscreenButton.querySelector('span').textContent = isFullscreen ? 'Exit full screen' : 'Full screen';
                if (isFullscreen !== wasScannerFullscreen) {
                    wasScannerFullscreen = isFullscreen;
                    window.dispatchEvent(new CustomEvent('gdg:scanner:layoutchange'));
                }
            };

            fullscreenButton.addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement === cameraPreview) {
                        await document.exitFullscreen();
                    } else if (!document.fullscreenElement) {
                        await cameraPreview.requestFullscreen();
                    }
                } catch (error) {
                    console.warn('Scanner full screen could not be changed.', error);
                }
            });
            document.addEventListener('fullscreenchange', updateFullscreenButton);
            updateFullscreenButton();
        }
    }

    function updateStartButton() {
        const selectedId = Number(selector.value) || null;
        const sameEvent = activeEvent !== null && selectedId === activeEvent.id;
        const hasEvents = selector.options.length > 1;
        if (hasEvents) selector.disabled = verificationPending;
        startButton.disabled = selectedId === null || sameEvent || verificationPending;
        startButton.classList.toggle('is-active', sameEvent);
        startButton.textContent = activeEvent === null
            ? 'Start Check-In'
            : (sameEvent ? 'Check-In Active' : 'Switch Event');
    }

    function startEvent() {
        const option = selector.selectedOptions[0];
        if (!option || !selector.value) return;

        const eventRecord = {
            id: Number(option.value),
            name: option.dataset.name || option.textContent.trim(),
            date: option.dataset.date || '',
            venue: option.dataset.venue || '',
        };

        if (activeEvent && activeEvent.id !== eventRecord.id) {
            const confirmed = window.confirm(`Switch from "${activeEvent.name}" to "${eventRecord.name}"? The current camera session will stop.`);
            if (!confirmed) {
                selector.value = String(activeEvent.id);
                updateStartButton();
                return;
            }
            window.dispatchEvent(new CustomEvent('gdg:workspace:close'));
        }

        activeEvent = eventRecord;
        emptyState.hidden = true;
        panel.hidden = false;
        updateStartButton();
        window.scrollTo({ top: 0, behavior: 'auto' });
        window.dispatchEvent(new CustomEvent('gdg:workspace:open', { detail: eventRecord }));
    }

    startButton.addEventListener('click', startEvent);
    selector.addEventListener('change', updateStartButton);
    window.addEventListener('gdg:checkin:pending', (event) => {
        verificationPending = Boolean(event.detail?.pending);
        updateStartButton();
    });
    updateStartButton();
})();
