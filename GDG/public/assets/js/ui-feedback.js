(() => {
    'use strict';

    const toast = document.getElementById('toast');
    let timer = null;

    function showToast(message) {
        window.clearTimeout(timer);
        toast.textContent = message;
        toast.hidden = false;
        timer = window.setTimeout(() => {
            toast.hidden = true;
        }, 3600);
    }

    window.GDGPreviewUI = Object.freeze({ toast: showToast });
})();
