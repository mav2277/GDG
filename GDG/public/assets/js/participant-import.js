(() => {
    'use strict';

    const dashboard = document.getElementById('dashboard-view');
    const dialog = document.getElementById('participant-import-dialog');
    if (!dashboard || !dialog) return;

    const form = document.getElementById('participant-import-form');
    const openButton = document.querySelector('[data-open-participant-import]');
    const closeButtons = [...dialog.querySelectorAll('[data-close-participant-import]')];
    const fileInput = document.getElementById('participant-csv-file');
    const delimiterInput = document.getElementById('participant-csv-delimiter');
    const mappingPanel = dialog.querySelector('[data-import-mapping]');
    const selects = [...dialog.querySelectorAll('[data-import-field]')];
    const rowCount = dialog.querySelector('[data-import-row-count]');
    const feedback = dialog.querySelector('[data-import-feedback]');
    const issuesList = dialog.querySelector('[data-import-issues]');
    const submitButton = dialog.querySelector('[data-import-submit]');
    const maxBytes = 5 * 1024 * 1024;
    const maxRows = 20000;
    let eventId = null;
    let fileRows = null;
    let submitted = false;
    let busy = false;

    function delimiterValue() {
        return { comma: ',', semicolon: ';', tab: '\t' }[delimiterInput.value];
    }

    function parseCsv(text, delimiter) {
        const rows = [];
        let row = [];
        let field = '';
        let quoted = false;
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            if (quoted) {
                if (char === '"' && text[i + 1] === '"') {
                    field += '"';
                    i++;
                } else if (char === '"') {
                    quoted = false;
                } else {
                    field += char;
                }
            } else if (char === '"' && field === '') {
                quoted = true;
            } else if (char === delimiter) {
                row.push(field);
                field = '';
            } else if (char === '\n' || char === '\r') {
                row.push(field);
                if (row.some((value) => value.trim() !== '')) rows.push(row);
                row = [];
                field = '';
                if (char === '\r' && text[i + 1] === '\n') i++;
            } else {
                field += char;
            }
        }
        if (quoted) throw new Error('A quoted CSV field was not closed. Check the file and try again.');
        row.push(field);
        if (row.some((value) => value.trim() !== '')) rows.push(row);
        return rows;
    }

    function updateSubmitState() {
        const indexes = selects.map((select) => select.value);
        const validMap = indexes.every((value) => value !== '') && new Set(indexes).size === indexes.length;
        submitButton.disabled = busy || submitted || !eventId || !fileInput.files.length || !fileRows
            || fileRows.length < 2 || fileRows.length - 1 > maxRows || !validMap;
        closeButtons.forEach((button) => { button.disabled = busy; });
    }

    function setFeedback(message, type = '') {
        feedback.textContent = message;
        feedback.className = `participant-import-feedback${type ? ` is-${type}` : ''}`;
        feedback.hidden = !message;
    }

    function renderColumns(headers) {
        selects.forEach((select) => {
            select.replaceChildren(new Option('Choose a CSV column', ''));
            headers.forEach((header, index) => {
                const label = String(header).trim() || '(blank header)';
                select.add(new Option(`Column ${index + 1} — ${label.slice(0, 80)}`, String(index)));
            });
        });
    }

    async function inspectFile() {
        fileRows = null;
        submitted = false;
        issuesList.replaceChildren();
        issuesList.hidden = true;
        mappingPanel.hidden = true;
        setFeedback('');
        const file = fileInput.files[0];
        if (!file) {
            updateSubmitState();
            return;
        }
        if (!file.name.toLowerCase().endsWith('.csv')) {
            setFeedback('Choose a file with the .csv extension.', 'error');
            updateSubmitState();
            return;
        }
        if (file.size > maxBytes) {
            setFeedback('The CSV is larger than 5 MB.', 'error');
            updateSubmitState();
            return;
        }
        try {
            const text = (await file.text()).replace(/^\uFEFF/, '');
            fileRows = parseCsv(text, delimiterValue());
            if (fileRows.length < 2) throw new Error('The CSV needs a header and at least one participant row.');
            renderColumns(fileRows[0]);
            mappingPanel.hidden = false;
            const count = fileRows.length - 1;
            rowCount.textContent = count > maxRows
                ? `${count.toLocaleString()} data rows found. The limit is ${maxRows.toLocaleString()} rows per import.`
                : `${count.toLocaleString()} data rows found. Review the column mapping before importing.`;
            setFeedback('File read locally for preview. The CSV is uploaded only after you choose Import participants.');
        } catch (error) {
            fileRows = null;
            setFeedback(error.message || 'The CSV could not be previewed. Check its delimiter and format.', 'error');
        }
        updateSubmitState();
    }

    function renderIssues(issues) {
        issuesList.replaceChildren();
        issues.forEach((issue) => {
            const item = document.createElement('li');
            item.textContent = issue;
            issuesList.append(item);
        });
        issuesList.hidden = issues.length === 0;
    }

    async function submitImport(event) {
        event.preventDefault();
        if (submitButton.disabled || !fileInput.files[0]) return;

        busy = true;
        updateSubmitState();
        submitButton.textContent = 'Importing…';
        setFeedback('Importing participant rows…');
        issuesList.hidden = true;
        const data = new FormData();
        data.append('csvFile', fileInput.files[0]);
        data.append('eventId', String(eventId));
        data.append('csrfToken', dashboard.dataset.csrfToken);
        data.append('delimiter', delimiterInput.value);
        selects.forEach((select) => data.append(`mapping[${select.dataset.importField}]`, select.value));

        try {
            const response = await fetch(dashboard.dataset.participantImportEndpoint, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The CSV could not be imported.');

            submitted = true;
            const summary = result.imported === 0 && result.skipped > 0 && result.rejected === 0
                ? `No new registrations were added. All ${result.skipped} participant row${result.skipped === 1 ? '' : 's'} already exist for this event and were skipped.`
                : `Import finished: ${result.imported} new registration${result.imported === 1 ? '' : 's'} added, ${result.skipped} already registered (skipped), ${result.rejected} rejected (${result.rowsSeen} rows reviewed).`;
            setFeedback(summary, result.rejected ? 'warning' : result.skipped > 0 && result.imported === 0 ? 'warning' : 'success');
            const issues = result.issues || [];
            if (result.issuesTruncated) issues.push('Only the first 50 row details are shown.');
            renderIssues(issues);
            window.dispatchEvent(new CustomEvent('gdg:participants:load'));
            window.dispatchEvent(new CustomEvent('gdg:registrations:changed'));
        } catch (error) {
            setFeedback(error.message || 'The CSV could not be imported. Try again.', 'error');
        } finally {
            busy = false;
            submitButton.textContent = 'Import participants';
            updateSubmitState();
        }
    }

    openButton.addEventListener('click', () => {
        form.reset();
        fileRows = null;
        submitted = false;
        issuesList.replaceChildren();
        issuesList.hidden = true;
        mappingPanel.hidden = true;
        setFeedback('');
        updateSubmitState();
        dialog.showModal();
    });
    closeButtons.forEach((button) => button.addEventListener('click', () => dialog.close()));
    fileInput.addEventListener('change', () => { void inspectFile(); });
    delimiterInput.addEventListener('change', () => { if (fileInput.files.length) void inspectFile(); });
    selects.forEach((select) => select.addEventListener('change', updateSubmitState));
    form.addEventListener('submit', submitImport);
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    window.addEventListener('gdg:workspace:open', (event) => {
        eventId = Number(event.detail?.id) || null;
        updateSubmitState();
    });
    window.addEventListener('gdg:workspace:close', () => {
        eventId = null;
        if (dialog.open) dialog.close();
        updateSubmitState();
    });
})();
