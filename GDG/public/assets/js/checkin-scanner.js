(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const startCamera = document.getElementById('start-camera');
    const cameraStateMessage = document.getElementById('camera-state-message');
    const cameraState = document.querySelector('.camera-state');
    const cameraPreview = document.querySelector('.camera-preview');
    const manualEntryToggle = document.getElementById('manual-entry-toggle');
    const manualEntryForm = document.getElementById('manual-entry-form');
    const ticketInput = document.getElementById('manual-ticket-id');
    const resultPanel = document.getElementById('checkin-result');
    const staffCameraToggle = document.getElementById('staff-camera-toggle');

    let activeEventId = null;
    let scanner = null;
    let cameraRunning = false;
    let cameraStarting = false;
    let verificationPending = false;
    let cameraGeneration = 0;
    let lastCameraPayload = null;
    let resultTimeout = null;
    let scanCooldown = false;
    let scanCooldownTimeout = null;
    let notificationAudio = null;
    let layoutRestartPending = false;
    let layoutRestarting = false;
    const notificationClips = {
        success: new Audio('assets/audio/checkin-success.mp3.mp3'),
        warning: new Audio('assets/audio/checkin-already.mp3.mp3'),
        error: new Audio('assets/audio/checkin-error.mp3.mp3'),
    };

    Object.values(notificationClips).forEach((clip) => {
        clip.preload = 'auto';
        clip.volume = 0.55;
    });

    function prepareNotificationAudio() {
        const AudioContextType = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextType) return;
        try {
            if (!notificationAudio || notificationAudio.state === 'closed') {
                notificationAudio = new AudioContextType();
            }
            if (notificationAudio.state === 'suspended') {
                void notificationAudio.resume().catch(() => {});
            }
        } catch {
            notificationAudio = null;
        }
    }

    function playGeneratedTone(kind) {
        if (!notificationAudio || kind === 'pending') return;
        const playTone = () => {
            if (notificationAudio?.state !== 'running') return;
            try {
                const oscillator = notificationAudio.createOscillator();
                const gain = notificationAudio.createGain();
                const frequency = kind === 'success' ? 880 : kind === 'warning' ? 560 : 360;
                const now = notificationAudio.currentTime;
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, now);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.045, now + 0.012);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.14);
                oscillator.connect(gain);
                gain.connect(notificationAudio.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.15);
            } catch {
                // Keep audio failures from interrupting check-in feedback.
            }
        };
        if (notificationAudio.state === 'suspended') {
            void notificationAudio.resume().then(playTone).catch(() => {});
        } else {
            playTone();
        }
    }

    function playNotificationSound(kind) {
        if (kind === 'pending') return;
        const clip = notificationClips[kind];
        if (!clip) {
            playGeneratedTone(kind);
            return;
        }
        try {
            clip.currentTime = 0;
            const playback = clip.play();
            if (playback && typeof playback.catch === 'function') {
                playback.catch(() => playGeneratedTone(kind));
            }
        } catch {
            playGeneratedTone(kind);
        }
    }

    function setResult(kind, message, detail = '') {
        window.clearTimeout(resultTimeout);
        resultPanel.className = `checkin-result is-${kind}`;
        resultPanel.replaceChildren();

        const icon = document.createElement('span');
        icon.className = 'checkin-result-icon';
        icon.setAttribute('aria-hidden', 'true');
        const logo = document.createElement('img');
        logo.src = 'assets/images/gdg-logo.svg';
        logo.alt = '';
        icon.append(logo);

        const content = document.createElement('span');
        content.className = 'checkin-result-content';
        const meta = document.createElement('span');
        meta.className = 'checkin-result-meta';
        const brand = document.createElement('span');
        brand.className = 'checkin-result-brand';
        brand.textContent = 'GDG BACOLOD';
        const time = document.createElement('span');
        time.className = 'checkin-result-time';
        time.textContent = 'now';
        meta.append(brand, time);

        const heading = document.createElement('strong');
        heading.className = 'checkin-result-heading';
        heading.textContent = message;
        content.append(meta, heading);
        if (detail) {
            const copy = document.createElement('span');
            copy.className = 'checkin-result-detail';
            copy.textContent = detail;
            content.append(copy);
        }
        resultPanel.append(icon, content);
        resultPanel.hidden = false;
        if (kind !== 'pending') {
            playNotificationSound(kind);
            resultTimeout = window.setTimeout(clearResult, 3000);
        }
    }

    function clearResult() {
        window.clearTimeout(resultTimeout);
        resultTimeout = null;
        resultPanel.hidden = true;
        resultPanel.replaceChildren();
        resultPanel.className = 'checkin-result';
    }

    function updateStaffCameraToggle() {
        if (!staffCameraToggle) return;
        const isOn = cameraRunning;
        staffCameraToggle.setAttribute('aria-checked', String(isOn));
        staffCameraToggle.setAttribute('aria-label', isOn ? 'Camera on' : 'Camera off');
        staffCameraToggle.querySelector('[data-camera-toggle-label]').textContent = cameraStarting ? 'Starting…' : isOn ? 'Camera on' : 'Camera off';
        staffCameraToggle.disabled = cameraStarting || verificationPending;
    }

    async function stopCamera() {
        cameraGeneration += 1;
        window.clearTimeout(scanCooldownTimeout);
        scanCooldownTimeout = null;
        scanCooldown = false;
        lastCameraPayload = null;
        const currentScanner = scanner;
        scanner = null;
        cameraRunning = false;
        updateStaffCameraToggle();
        if (currentScanner) {
            try {
                if (currentScanner.isScanning) await currentScanner.stop();
            } catch (error) {
                console.warn('Could not stop QR scanner cleanly.', error);
            }
            try { await currentScanner.clear(); } catch (error) {
                console.warn('Could not clear QR scanner view.', error);
            }
        }
        cameraPreview.classList.remove('is-scanning');
        cameraState.hidden = false;
        cameraStateMessage.textContent = 'Camera is off.';
        startCamera.querySelector('span').textContent = 'Start Camera';
    }

    async function restartCameraAfterLayoutChange() {
        if (cameraStarting) {
            layoutRestartPending = true;
            return;
        }
        if (!cameraRunning || verificationPending || layoutRestarting) return;

        layoutRestarting = true;
        try {
            await stopCamera();
            if (activeEventId) await startCameraScan();
        } finally {
            layoutRestarting = false;
        }
    }

    async function verifyTicket(ticketValue, source = 'manual') {
        const ticketNumber = String(ticketValue || '').trim();
        if (!ticketNumber) {
            setResult('error', 'Enter a ticket number.');
            return;
        }
        if (!activeEventId) {
            setResult('error', 'Select an event first.');
            return;
        }
        if (verificationPending) return;

        verificationPending = true;
        updateStaffCameraToggle();
        window.dispatchEvent(new CustomEvent('gdg:checkin:pending', { detail: { pending: true } }));
        clearResult();
        setResult('pending', 'Verifying ticket…');
        if (source === 'manual') manualEntryForm.querySelector('button[type="submit"]').disabled = true;

        try {
            const response = await fetch(dashboard.dataset.checkinEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    eventId: activeEventId,
                    ticketNumber,
                    csrfToken: dashboard.dataset.csrfToken,
                }),
            });
            let result;
            try { result = await response.json(); } catch {
                throw new Error('The server returned an unreadable response. Refresh the page and try again.');
            }

            if (result.outcome === 'checked_in') {
                window.dispatchEvent(new CustomEvent('gdg:checkin:recorded'));
                const printMessage = result.nameTagQueue === 'queued'
                    ? 'Name tag queued.'
                    : result.nameTagQueue === 'already_queued' || result.nameTagQueue === 'already_requested'
                        ? 'Name tag already queued.'
                    : 'Name tag needs Admin help.';
                setResult('success', 'Check-in successful', `${result.fullName} · ${printMessage}`);
                if (source === 'manual') ticketInput.value = '';
            } else if (result.outcome === 'already_checked_in') {
                window.dispatchEvent(new CustomEvent('gdg:checkin:recorded'));
                setResult('warning', 'Already checked in', `${result.fullName} · No duplicate added`);
                if (source === 'manual') ticketInput.value = '';
            } else if (result.outcome === 'not_registered') {
                setResult('error', 'Not registered', 'No check-in recorded for this event.');
            } else if (result.outcome === 'event_unavailable') {
                setResult('error', 'Event unavailable', 'Choose an event scheduled today.');
            } else if (!response.ok || !result.success) {
                const isSessionError = result.message?.toLowerCase().includes('session token');
                setResult('error', isSessionError ? 'Session expired' : 'Check-in failed', isSessionError ? 'Refresh and try again.' : 'Check the ticket and try again.');
            } else {
                setResult('error', 'Check-in failed', 'Check the ticket and try again.');
            }
        } catch {
            setResult('error', 'Check-in unavailable', 'Try again or enter the ticket manually.');
        } finally {
            if (source === 'camera' && cameraRunning) {
                scanCooldown = true;
                cameraStateMessage.textContent = 'Ready for next scan…';
                window.clearTimeout(scanCooldownTimeout);
                scanCooldownTimeout = window.setTimeout(() => {
                    scanCooldown = false;
                    if (cameraRunning) cameraStateMessage.textContent = 'Looking for a QR code…';
                }, 1000);
            }
            verificationPending = false;
            updateStaffCameraToggle();
            window.dispatchEvent(new CustomEvent('gdg:checkin:pending', { detail: { pending: false } }));
            if (source === 'manual') manualEntryForm.querySelector('button[type="submit"]').disabled = false;
        }
    }

    async function startCameraScan() {
        if (cameraStarting) return;
        if (cameraRunning) {
            await stopCamera();
            return;
        }
        prepareNotificationAudio();
        if (!window.Html5Qrcode) {
            setResult('error', 'Scanner unavailable', 'Use manual ticket entry.');
            return;
        }
        if (!activeEventId) {
            setResult('error', 'Select an event first.');
            return;
        }

        cameraStarting = true;
        updateStaffCameraToggle();
        const thisGeneration = ++cameraGeneration;
        lastCameraPayload = null;
        startCamera.disabled = true;
        clearResult();
        cameraStateMessage.textContent = 'Requesting camera access…';
        const config = {
            fps: 10,
            videoConstraints: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            formatsToSupport: [window.Html5QrcodeSupportedFormats.QR_CODE],
            verbose: false,
        };
        let instance = null;
        try {
            instance = new window.Html5Qrcode('qr-reader', config);
            scanner = instance;
            await instance.start(
                { facingMode: 'environment' },
                config,
                (decodedText) => {
                    if (verificationPending || scanCooldown || decodedText === lastCameraPayload) return;
                    lastCameraPayload = decodedText;
                    cameraState.hidden = false;
                    cameraStateMessage.textContent = 'QR detected. Checking ticket…';
                    void verifyTicket(decodedText, 'camera');
                },
                () => {
                    if (!verificationPending && !scanCooldown) {
                        lastCameraPayload = null;
                    }
                },
            );
            if (thisGeneration !== cameraGeneration) {
                try { if (instance.isScanning) await instance.stop(); } catch {}
                try { await instance.clear(); } catch {}
                return;
            }
            cameraRunning = true;
            updateStaffCameraToggle();
            cameraPreview.classList.add('is-scanning');
            cameraStateMessage.textContent = 'Looking for a QR code…';
            cameraState.hidden = false;
            startCamera.querySelector('span').textContent = 'Stop Camera';
        } catch (error) {
            scanner = null;
            try { if (instance) await instance.clear(); } catch {}
            if (thisGeneration !== cameraGeneration) return;
            cameraState.hidden = false;
            cameraStateMessage.textContent = 'Camera is off.';
            const reason = error && error.name === 'NotAllowedError'
                ? 'Allow camera access in browser settings.'
                : 'Check camera access or use localhost/HTTPS.';
            setResult('error', 'Camera unavailable', reason);
        } finally {
            cameraStarting = false;
            updateStaffCameraToggle();
            startCamera.disabled = false;
            if (layoutRestartPending) {
                layoutRestartPending = false;
                if (cameraRunning) void restartCameraAfterLayoutChange();
            }
        }
    }

    window.addEventListener('gdg:workspace:open', (event) => {
        void stopCamera();
        activeEventId = Number(event.detail?.id) || null;
        manualEntryToggle.setAttribute('aria-expanded', 'false');
        manualEntryForm.hidden = true;
        ticketInput.value = '';
        clearResult();
    });

    window.addEventListener('gdg:workspace:close', () => {
        void stopCamera();
        activeEventId = null;
    });

    startCamera.addEventListener('click', () => { void startCameraScan(); });
    staffCameraToggle?.addEventListener('click', () => { void startCameraScan(); });
    window.addEventListener('gdg:scanner:layoutchange', () => { void restartCameraAfterLayoutChange(); });

    manualEntryToggle.addEventListener('click', () => {
        const expanded = manualEntryToggle.getAttribute('aria-expanded') === 'true';
        manualEntryToggle.setAttribute('aria-expanded', String(!expanded));
        manualEntryForm.hidden = expanded;
        if (!expanded) ticketInput.focus();
    });

    ticketInput.addEventListener('input', clearResult);
    manualEntryForm.addEventListener('submit', (event) => {
        event.preventDefault();
        prepareNotificationAudio();
        void verifyTicket(ticketInput.value, 'manual');
    });
})();
