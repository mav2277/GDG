(() => {
    'use strict';

    document.querySelectorAll('[data-coming-soon]').forEach((button) => {
        button.addEventListener('click', () => {
            window.GDGPreviewUI.toast(`${button.dataset.comingSoon} will be built in a later step.`);
        });
    });
})();
