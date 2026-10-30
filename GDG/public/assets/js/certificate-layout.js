(() => {
    'use strict';

    const editor = document.querySelector('[data-certificate-layout]');
    const dashboard = document.getElementById('dashboard-view');
    if (!editor || !dashboard) return;

    const preview = editor.querySelector('[data-certificate-preview]');
    const fileInput = editor.querySelector('[data-certificate-file]');
    const artwork = editor.querySelector('[data-certificate-artwork]');
    const empty = editor.querySelector('[data-certificate-empty]');
    const area = editor.querySelector('[data-certificate-name-area]');
    const resizeHandle = editor.querySelector('[data-certificate-resize]');
    const widthInput = editor.querySelector('[data-certificate-width]');
    const heightInput = editor.querySelector('[data-certificate-height]');
    const fontSizeInput = editor.querySelector('[data-certificate-font-size]');
    const fontFamilyInput = editor.querySelector('[data-certificate-font-family]');
    const alignmentInput = editor.querySelector('[data-certificate-alignment]');
    const colorInput = editor.querySelector('[data-certificate-text-color]');
    const areaSizeInput = editor.querySelector('[data-certificate-name-area-size]');
    const status = editor.querySelector('[data-certificate-layout-status]');
    const saveButton = editor.querySelector('[data-certificate-save]');
    const editActions = editor.querySelector('[data-certificate-edit-actions]');
    const editButton = editor.querySelector('[data-certificate-edit]');
    const cancelButton = editor.querySelector('[data-certificate-cancel]');
    const editableFields = [fileInput, widthInput, heightInput, fontSizeInput, fontFamilyInput, alignmentInput, colorInput];
    let objectUrl = null;
    let hasArtwork = false;
    let eventId = null;
    let saving = false;
    let saveState = 'idle';
    let savedLayout = false;
    let editing = true;
    let layoutLoading = false;
    let hasUnsavedChanges = false;
    let bounds = { x: 16, y: 41, width: 68, height: 17 };
    let pointerState = null;

    function setStatus(message, isError = false, isSuccess = false) {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        status.classList.toggle('is-success', isSuccess);
        status.hidden = !message;
    }

    function updateAreaSize() {
        const widthInches = Number(widthInput.value);
        const heightInches = Number(heightInput.value);
        if (widthInches > 0 && heightInches > 0) {
            areaSizeInput.value = `${(widthInches * bounds.width / 100).toFixed(2)} × ${(heightInches * bounds.height / 100).toFixed(2)} in`;
        } else {
            areaSizeInput.value = 'Preview area';
        }
    }

    function canSave() {
        return Boolean(eventId && hasArtwork
            && Number(widthInput.value) > 0 && Number(heightInput.value) > 0
            && Number(fontSizeInput.value) > 0 && fontFamilyInput.value
            && ['left', 'center', 'right'].includes(alignmentInput.value)
            && /^#[0-9A-Fa-f]{6}$/.test(colorInput.value)
            && bounds.width >= 12 && bounds.height >= 8
            && bounds.x >= 0 && bounds.y >= 0
            && bounds.x + bounds.width <= 100 && bounds.y + bounds.height <= 100);
    }

    function updateSaveButton() {
        saveButton.disabled = layoutLoading || saving || !editing || !canSave();
        saveButton.textContent = saving ? 'Saving…' : saveState === 'error' ? 'Try again' : savedLayout ? 'Save changes' : 'Save layout';
        saveButton.setAttribute('aria-busy', saving ? 'true' : 'false');
    }

    function updateEditorMode() {
        const locked = layoutLoading || saving || !editing;
        editableFields.forEach((field) => { field.disabled = locked; });
        area.tabIndex = locked ? -1 : 0;
        area.setAttribute('aria-disabled', locked ? 'true' : 'false');
        editor.classList.toggle('is-saved', savedLayout && !editing && !layoutLoading);
        editActions.classList.toggle('is-editing', savedLayout && editing);
        saveButton.hidden = savedLayout && !editing;
        editActions.hidden = !savedLayout || layoutLoading;
        editButton.hidden = editing;
        cancelButton.hidden = !editing;
        updateSaveButton();
    }

    function markChanged() {
        saveState = 'idle';
        hasUnsavedChanges = true;
        setStatus('Changes not saved yet.');
        updateSaveButton();
    }

    function updatePreview() {
        const widthInches = Number(widthInput.value);
        const heightInches = Number(heightInput.value);
        if (widthInches > 0 && heightInches > 0) preview.style.aspectRatio = `${widthInches} / ${heightInches}`;
        else preview.style.removeProperty('aspect-ratio');
        area.style.left = `${bounds.x}%`;
        area.style.top = `${bounds.y}%`;
        area.style.width = `${bounds.width}%`;
        area.style.height = `${bounds.height}%`;

        const fontSize = Number(fontSizeInput.value);
        if (fontSize > 0) {
            const previewFontSize = widthInches > 0
                ? fontSize * preview.clientWidth / (72 * widthInches)
                : fontSize * (96 / 72);
            area.style.fontSize = `${Math.min(previewFontSize, Math.max(12, area.clientHeight * 0.8))}px`;
        } else {
            area.style.removeProperty('font-size');
        }
        area.style.fontFamily = fontFamilyInput.value.trim() || '';
        area.style.textAlign = alignmentInput.value || 'center';
        if (/^#[0-9A-Fa-f]{6}$/.test(colorInput.value)) area.style.color = colorInput.value;
        else area.style.removeProperty('color');
        updateAreaSize();
        updateSaveButton();
    }

    function clampBounds() {
        bounds.width = Math.max(12, Math.min(100, bounds.width));
        bounds.height = Math.max(8, Math.min(100, bounds.height));
        bounds.x = Math.max(0, Math.min(100 - bounds.width, bounds.x));
        bounds.y = Math.max(0, Math.min(100 - bounds.height, bounds.y));
    }

    function moveByKey(event) {
        if (!editing || layoutLoading || saving) return;
        const step = event.shiftKey ? 5 : 1;
        let handled = true;
        if (event.key === 'ArrowLeft') bounds.x -= step;
        else if (event.key === 'ArrowRight') bounds.x += step;
        else if (event.key === 'ArrowUp') bounds.y -= step;
        else if (event.key === 'ArrowDown') bounds.y += step;
        else handled = false;
        if (!handled) return;
        event.preventDefault();
        markChanged();
        clampBounds();
        updatePreview();
    }

    function startPointer(event, resizing) {
        if (!editing || layoutLoading || saving || event.button !== 0) return;
        const rect = preview.getBoundingClientRect();
        pointerState = {
            resizing,
            x: event.clientX,
            y: event.clientY,
            bounds: { ...bounds },
            width: rect.width,
            height: rect.height,
        };
        area.setPointerCapture(event.pointerId);
        event.preventDefault();
    }

    function movePointer(event) {
        if (!pointerState) return;
        const deltaX = (event.clientX - pointerState.x) / pointerState.width * 100;
        const deltaY = (event.clientY - pointerState.y) / pointerState.height * 100;
        if (pointerState.resizing) {
            bounds.width = pointerState.bounds.width + deltaX;
            bounds.height = pointerState.bounds.height + deltaY;
        } else {
            bounds.x = pointerState.bounds.x + deltaX;
            bounds.y = pointerState.bounds.y + deltaY;
        }
        markChanged();
        clampBounds();
        updatePreview();
    }

    function resetForm() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        fileInput.value = '';
        artwork.removeAttribute('src');
        artwork.hidden = true;
        empty.hidden = false;
        hasArtwork = false;
        widthInput.value = '';
        heightInput.value = '';
        fontSizeInput.value = '';
        fontFamilyInput.value = '';
        alignmentInput.value = 'center';
        colorInput.value = '';
        bounds = { x: 16, y: 41, width: 68, height: 17 };
        savedLayout = false;
        editing = true;
        hasUnsavedChanges = false;
        saveState = 'idle';
        setStatus('');
        updateEditorMode();
        updatePreview();
    }

    async function loadLayout(targetEventId) {
        resetForm();
        layoutLoading = true;
        updateEditorMode();
        setStatus('Loading saved certificate layout…');
        try {
            const params = new URLSearchParams({ eventId: String(targetEventId) });
            const response = await fetch(`${dashboard.dataset.certificateLayoutEndpoint}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The saved layout could not be loaded.');
            if (eventId !== targetEventId) return;
            if (!result.layout) {
                setStatus('No saved layout for this event. Upload artwork and configure the fields to save one.');
                return;
            }
            const layout = result.layout;
            artwork.src = layout.artwork;
            artwork.hidden = false;
            empty.hidden = true;
            hasArtwork = true;
            widthInput.value = layout.widthInches.toFixed(2);
            heightInput.value = layout.heightInches.toFixed(2);
            fontSizeInput.value = layout.fontSize;
            fontFamilyInput.value = layout.fontFamily;
            alignmentInput.value = layout.alignment;
            colorInput.value = layout.textColor;
            bounds = { x: layout.x, y: layout.y, width: layout.areaWidth, height: layout.areaHeight };
            savedLayout = true;
            editing = false;
            hasUnsavedChanges = false;
            updateEditorMode();
            updatePreview();
            setStatus(`Saved layout loaded${layout.originalName ? ` (${layout.originalName})` : ''}.`);
        } catch (error) {
            if (eventId !== targetEventId) return;
            setStatus(error.message || 'The saved layout could not be loaded. Refresh and try again.', true);
        } finally {
            if (eventId === targetEventId) {
                layoutLoading = false;
                updateEditorMode();
            }
        }
    }

    async function saveLayout() {
        if (!canSave() || saving) return;
        const submittedEventId = eventId;
        saving = true;
        updateSaveButton();
        setStatus('Saving certificate layout…');
        const form = new FormData();
        form.set('eventId', String(submittedEventId));
        form.set('csrfToken', dashboard.dataset.csrfToken);
        form.set('widthInches', widthInput.value);
        form.set('heightInches', heightInput.value);
        form.set('fontSize', fontSizeInput.value);
        form.set('fontFamily', fontFamilyInput.value);
        form.set('alignment', alignmentInput.value);
        form.set('textColor', colorInput.value);
        form.set('x', String(bounds.x));
        form.set('y', String(bounds.y));
        form.set('areaWidth', String(bounds.width));
        form.set('areaHeight', String(bounds.height));
        if (fileInput.files?.[0]) form.set('artwork', fileInput.files[0]);
        try {
            const response = await fetch(dashboard.dataset.certificateLayoutEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: form,
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The certificate layout could not be saved.');
            if (eventId !== submittedEventId) return;
            fileInput.value = '';
            saveState = 'saved';
            savedLayout = true;
            editing = false;
            hasUnsavedChanges = false;
            updateEditorMode();
            setStatus(result.message, false, true);
            window.dispatchEvent(new CustomEvent('gdg:certificate-layout:saved', {
                detail: { eventId: submittedEventId },
            }));
        } catch (error) {
            if (eventId !== submittedEventId) return;
            saveState = 'error';
            setStatus(error.message || 'The certificate layout could not be saved. Try again.', true);
        } finally {
            saving = false;
            updateEditorMode();
        }
    }

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            fileInput.value = '';
            setStatus('Choose a PNG, JPG, or WebP image.', true);
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            fileInput.value = '';
            setStatus('Certificate artwork must be 10 MB or smaller.', true);
            return;
        }
        if (hasArtwork && !window.confirm('Replace the current certificate artwork?')) {
            fileInput.value = '';
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        artwork.src = objectUrl;
        artwork.hidden = false;
        empty.hidden = true;
        hasArtwork = true;
        markChanged();
        setStatus('Artwork preview loaded. Save layout to store it for this event.');
        updatePreview();
    });

    [widthInput, heightInput, fontSizeInput, fontFamilyInput, alignmentInput, colorInput].forEach((input) => {
        const updateValidatedInput = () => {
            markChanged();
            input.setCustomValidity('');
            if (input.validity.badInput || input.validity.rangeUnderflow || input.validity.rangeOverflow || input.validity.stepMismatch || input.validity.patternMismatch) {
                if (input === widthInput || input === heightInput) input.setCustomValidity('Enter a positive size in inches.');
                else if (input === fontSizeInput) input.setCustomValidity('Enter a positive font size in points.');
                else if (input === colorInput) input.setCustomValidity('Use a color in #RRGGBB format.');
                setStatus(input.validationMessage, true);
                updatePreview();
                return;
            }
            if (input === widthInput || input === heightInput) {
                const widthSet = widthInput.value !== '';
                const heightSet = heightInput.value !== '';
                if (widthSet !== heightSet) {
                    setStatus('Enter both width and height to preview the certificate proportions.');
                    updatePreview();
                    return;
                }
            }
            setStatus('Changes not saved yet.');
            updatePreview();
        };
        input.addEventListener('input', updateValidatedInput);
        input.addEventListener('change', updateValidatedInput);
    });

    saveButton.addEventListener('click', () => { void saveLayout(); });
    editButton.addEventListener('click', () => {
        if (!savedLayout || saving || layoutLoading) return;
        editing = true;
        saveState = 'idle';
        updateEditorMode();
        setStatus('Edit the layout, then save your changes or cancel.');
    });
    cancelButton.addEventListener('click', () => {
        if (!savedLayout || saving || layoutLoading || !eventId) return;
        if (hasUnsavedChanges && !window.confirm('Discard your unsaved certificate layout changes?')) return;
        void loadLayout(eventId);
    });
    area.addEventListener('pointerdown', (event) => startPointer(event, event.target === resizeHandle));
    area.addEventListener('pointermove', movePointer);
    area.addEventListener('pointerup', () => { pointerState = null; });
    area.addEventListener('pointercancel', () => { pointerState = null; });
    area.addEventListener('keydown', moveByKey);
    window.addEventListener('resize', updatePreview);
    window.addEventListener('gdg:workspace:open', (event) => {
        eventId = Number(event.detail?.id) || null;
        if (!eventId) {
            resetForm();
            setStatus('Open an event to configure its certificate layout.');
            return;
        }
        void loadLayout(eventId);
    });
    window.addEventListener('gdg:workspace:close', () => {
        eventId = null;
        resetForm();
    });
    updatePreview();
})();
