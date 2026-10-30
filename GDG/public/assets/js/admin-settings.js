(() => {
    'use strict';

    const page = document.getElementById('settings-page');
    if (!page) return;

    const inviteForm = document.getElementById('invite-form');
    const inviteFeedback = document.getElementById('invite-feedback');
    const inviteLinkWrap = document.getElementById('invite-link-wrap');
    const inviteLink = document.getElementById('invite-link');
    const passwordDialog = document.getElementById('password-dialog');
    const passwordForm = document.getElementById('password-form');
    const passwordFeedback = document.getElementById('password-feedback');
    const accountsBody = document.getElementById('team-accounts-body');
    const accountsFeedback = document.getElementById('accounts-feedback');
    const accountLinkWrap = document.getElementById('account-link-wrap');
    const accountLink = document.getElementById('account-link');

    function feedback(element, message, isError = false) {
        element.textContent = message;
        element.classList.toggle('is-error', isError);
        element.hidden = !message;
    }

    async function send(payload) {
        const response = await fetch(page.dataset.actionsEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ...payload, csrfToken: page.dataset.csrfToken }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'The request could not be completed.');
        return result;
    }

    function makeCell(text, className = '') {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = text;
        return cell;
    }

    function makeAction(label, action, accountId, destructive = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `settings-button ${destructive ? 'settings-button-danger' : 'settings-button-secondary'}`;
        button.textContent = label;
        button.dataset.accountAction = action;
        button.dataset.userId = String(accountId);
        return button;
    }

    function renderAccounts(accounts) {
        accountsBody.replaceChildren();
        if (!accounts.length) {
            const row = document.createElement('tr');
            const cell = makeCell('No accounts found.', 'team-accounts-state');
            cell.colSpan = 4;
            row.append(cell);
            accountsBody.append(row);
            return;
        }

        const currentUserId = Number(page.dataset.currentUserId);
        for (const account of accounts) {
            const row = document.createElement('tr');
            const identity = document.createElement('td');
            identity.className = 'team-account-identity';
            const name = document.createElement('strong');
            name.textContent = account.displayName || 'Unnamed account';
            const detail = document.createElement('span');
            detail.textContent = [account.email || account.username, account.username ? `@${account.username}` : ''].filter(Boolean).join(' · ');
            identity.append(name, detail);

            const role = makeCell(account.role === 'admin' ? 'Admin' : 'Staff');
            const statusLabels = {
                active: 'Active',
                pending: 'Invitation pending',
                expired: 'Invitation expired',
                disabled: 'Disabled',
            };
            const status = makeCell(statusLabels[account.status] || 'Unknown', `team-account-status status-${account.status}`);
            const actions = document.createElement('td');
            actions.className = 'team-account-actions';

            if (Number(account.id) === currentUserId) {
                const ownLabel = document.createElement('span');
                ownLabel.className = 'team-account-self';
                ownLabel.textContent = 'You';
                actions.append(ownLabel);
            } else if (account.status === 'active') {
                actions.append(
                    makeAction('Reset password', 'account-access-link', account.id),
                    makeAction('Deactivate', 'deactivate-account', account.id, true)
                );
            } else {
                const label = account.status === 'disabled' ? 'Restore access' : 'Renew setup link';
                actions.append(makeAction(label, 'account-access-link', account.id));
            }

            row.append(identity, role, status, actions);
            accountsBody.append(row);
        }
    }

    async function loadAccounts() {
        accountsBody.replaceChildren();
        const loading = document.createElement('tr');
        const loadingCell = makeCell('Loading accounts…', 'team-accounts-state');
        loadingCell.colSpan = 4;
        loading.append(loadingCell);
        accountsBody.append(loading);
        try {
            const response = await fetch(page.dataset.accountsEndpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Accounts could not be loaded.');
            renderAccounts(result.accounts || []);
            feedback(accountsFeedback, '');
        } catch (error) {
            const row = document.createElement('tr');
            const cell = makeCell(error.message || 'Accounts could not be loaded. Refresh and try again.', 'team-accounts-state is-error');
            cell.colSpan = 4;
            row.append(cell);
            accountsBody.replaceChildren(row);
        }
    }

    async function handleAccountAction(button) {
        const action = button.dataset.accountAction;
        const userId = Number(button.dataset.userId);
        if (!Number.isInteger(userId) || userId < 1) return;
        if (action === 'deactivate-account' && !window.confirm('Deactivate this account? The user will lose access on their next request.')) return;

        button.disabled = true;
        accountLinkWrap.hidden = true;
        feedback(accountsFeedback, action === 'deactivate-account' ? 'Deactivating account…' : 'Preparing account link…');
        try {
            const result = await send({ action, userId });
            if (result.invitePath) {
                accountLink.value = new URL(result.invitePath, window.location.href).toString();
                document.getElementById('account-link-label').textContent = result.linkType === 'password-reset'
                    ? 'One-time password reset link · expires in 24 hours'
                    : 'One-time account setup link · expires in 24 hours';
                accountLinkWrap.hidden = false;
                feedback(accountsFeedback, result.message);
            } else {
                feedback(accountsFeedback, result.message);
                await loadAccounts();
            }
        } catch (error) {
            feedback(accountsFeedback, error.message || 'The account action could not be completed.', true);
        } finally {
            button.disabled = false;
        }
    }

    accountsBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-account-action]');
        if (button) handleAccountAction(button);
    });
    document.getElementById('refresh-accounts').addEventListener('click', loadAccounts);
    document.getElementById('copy-account-link').addEventListener('click', async () => {
        accountLink.select();
        try {
            await navigator.clipboard.writeText(accountLink.value);
        } catch {
            document.execCommand('copy');
        }
        feedback(accountsFeedback, 'Account link copied. Share it only with the intended account holder.');
    });
    loadAccounts();

    inviteForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.getElementById('invite-submit');
        submit.disabled = true;
        inviteLinkWrap.hidden = true;
        feedback(inviteFeedback, 'Creating invitation…');
        try {
            const result = await send({
                action: 'invite',
                email: inviteForm.elements.email.value,
                role: inviteForm.elements.role.value,
            });
            inviteLink.value = new URL(result.invitePath, window.location.href).toString();
            inviteLinkWrap.hidden = false;
            inviteForm.elements.email.value = '';
            feedback(inviteFeedback, result.message);
            await loadAccounts();
        } catch (error) {
            feedback(inviteFeedback, error.message || 'The invitation could not be created.', true);
        } finally {
            submit.disabled = false;
        }
    });

    document.getElementById('copy-invite-link').addEventListener('click', async () => {
        inviteLink.select();
        try {
            await navigator.clipboard.writeText(inviteLink.value);
        } catch {
            document.execCommand('copy');
        }
        feedback(inviteFeedback, 'Invitation link copied. Share it only with the intended recipient.');
    });

    document.getElementById('open-password-dialog').addEventListener('click', () => {
        feedback(passwordFeedback, '');
        passwordForm.reset();
        passwordDialog.showModal();
        document.getElementById('current-password').focus();
    });
    const closePasswordDialog = () => passwordDialog.close();
    document.getElementById('close-password-dialog').addEventListener('click', closePasswordDialog);
    document.getElementById('cancel-password-change').addEventListener('click', closePasswordDialog);

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.getElementById('save-password');
        submit.disabled = true;
        feedback(passwordFeedback, 'Updating password…');
        try {
            const result = await send({
                action: 'change-password',
                currentPassword: passwordForm.elements.currentPassword.value,
                password: passwordForm.elements.password.value,
                passwordConfirmation: passwordForm.elements.passwordConfirmation.value,
            });
            passwordForm.reset();
            passwordDialog.close();
            window.GDGPreviewUI.toast(result.message);
        } catch (error) {
            feedback(passwordFeedback, error.message || 'Your password could not be changed.', true);
        } finally {
            submit.disabled = false;
        }
    });
})();
