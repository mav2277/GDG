(() => {
    const message = document.getElementById('login-message');

    if (!message) return;

    document.getElementById('forgot-password').addEventListener('click', () => {
        message.textContent = 'Password recovery is not connected yet. Please contact your system administrator.';
        message.hidden = false;
    });
})();
