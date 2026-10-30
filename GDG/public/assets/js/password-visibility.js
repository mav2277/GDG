(() => {
    'use strict';

    const eye = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOff = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a16 16 0 0 1-3.1 3.8M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5c1.1 0 2.1-.2 3-.5"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>';

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!input) return;

        const name = toggle.dataset.passwordName || 'password';
        toggle.innerHTML = eye;
        toggle.setAttribute('aria-label', `Show ${name}`);
        toggle.title = `Show ${name}`;
        toggle.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            toggle.innerHTML = reveal ? eyeOff : eye;
            toggle.setAttribute('aria-label', `${reveal ? 'Hide' : 'Show'} ${name}`);
            toggle.title = `${reveal ? 'Hide' : 'Show'} ${name}`;
            toggle.setAttribute('aria-pressed', String(reveal));
        });
    });
})();
