<?php

declare(strict_types=1);

$sidebarActivePage = 'settings';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#11165f">
    <title>Settings | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=6">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css?v=3">
    <link rel="stylesheet" href="assets/css/admin-settings.css?v=3">
    <script src="assets/js/ui-feedback.js" defer></script>
    <script src="assets/js/admin-settings.js?v=2" defer></script>
    <script src="assets/js/password-visibility.js?v=2" defer></script>
</head>
<body>
    <div class="app-shell">
        <?php require dirname(__DIR__) . '/components/admin-sidebar.php'; ?>
        <main class="main-content">
            <div class="settings-wrap" id="settings-page" data-actions-endpoint="account-actions.php" data-accounts-endpoint="admin-accounts.php" data-current-user-id="<?= (int) $account['id'] ?>" data-csrf-token="<?= escape_html($csrfToken) ?>">
                <header class="settings-heading">
                    <h1>Settings</h1>
                    <p>Account and system configuration.</p>
                </header>

                <section class="settings-card account-card" aria-label="Current account">
                    <span class="settings-account-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.4c0 4.3-2.9 7.4-7 9.1-4.1-1.7-7-4.8-7-9.1V6l7-2.5Z"></path><path d="m9.2 11.9 1.8 1.8 3.9-4"></path></svg>
                    </span>
                    <div class="settings-account-copy">
                        <strong><?= escape_html($account['display_name']) ?></strong>
                        <span><?= escape_html(ucfirst($account['role'])) ?> Account</span>
                        <span class="settings-account-email">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"></rect><path d="m4.5 7 7.5 6 7.5-6"></path></svg>
                            <?= escape_html($account['email'] ?? 'No email address set') ?>
                        </span>
                    </div>
                </section>

                <section class="settings-card" aria-labelledby="security-heading">
                    <h2 id="security-heading">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15.5" r="3.5"></circle><path d="m10.5 13 8-8 2.5 2.5-2 2-1.5-1.5-2 2-1.5-1.5-2.2 2.2"></path></svg>
                        Account Security
                    </h2>
                    <p>Change your password or manage your account security.</p>
                    <button class="settings-button settings-button-secondary" type="button" id="open-password-dialog">Change password</button>
                </section>

                <section class="settings-card" aria-labelledby="invite-heading">
                    <h2 id="invite-heading">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="3"></circle><path d="M3.5 19v-1.3A5.2 5.2 0 0 1 8.7 12.5h.6a5.2 5.2 0 0 1 5.2 5.2V19M18 8v7m-3.5-3.5h7"></path></svg>
                        Invite Staff &amp; Admins
                    </h2>
                    <p>Create a one-time invitation link.</p>
                    <form class="settings-invite-form" id="invite-form">
                        <label class="visually-hidden" for="invite-email">Email address</label>
                        <input id="invite-email" name="email" type="email" maxlength="255" autocomplete="email" placeholder="name@example.com" required>
                        <label class="visually-hidden" for="invite-role">Account role</label>
                        <select id="invite-role" name="role">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button class="settings-button settings-button-primary" type="submit" id="invite-submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="3"></circle><path d="M3.5 19v-1.3A5.2 5.2 0 0 1 8.7 12.5h.6a5.2 5.2 0 0 1 5.2 5.2V19M18 8v7m-3.5-3.5h7"></path></svg>
                            Invite
                        </button>
                    </form>
                    <div class="settings-feedback" id="invite-feedback" role="status" aria-live="polite" hidden></div>
                    <div class="settings-invite-link" id="invite-link-wrap" hidden>
                        <label for="invite-link">One-time invitation link · expires in 24 hours</label>
                        <div>
                            <input id="invite-link" type="text" readonly>
                            <button class="settings-button settings-button-secondary" type="button" id="copy-invite-link">Copy link</button>
                        </div>
                    </div>
                </section>

                <section class="settings-card team-accounts-card" aria-labelledby="team-accounts-heading">
                    <div class="team-accounts-heading">
                        <div>
                            <h2 id="team-accounts-heading">Team Accounts</h2>
                            <p>View accounts, renew setup links, reset passwords, or remove access.</p>
                        </div>
                        <button class="settings-button settings-button-secondary" id="refresh-accounts" type="button">Refresh</button>
                    </div>
                    <p class="settings-feedback" id="accounts-feedback" role="status" aria-live="polite" hidden></p>
                    <div class="team-accounts-table-wrap">
                        <table class="team-accounts-table">
                            <thead>
                                <tr><th scope="col">Account</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                            </thead>
                            <tbody id="team-accounts-body">
                                <tr><td class="team-accounts-state" colspan="4">Loading accounts…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="settings-invite-link" id="account-link-wrap" hidden>
                        <label for="account-link" id="account-link-label">One-time account link · expires in 24 hours</label>
                        <div>
                            <input id="account-link" type="text" readonly>
                            <button class="settings-button settings-button-secondary" type="button" id="copy-account-link">Copy link</button>
                        </div>
                    </div>
                </section>

                <section class="settings-about" aria-labelledby="about-heading">
                    <h2 id="about-heading">About this system</h2>
                    <p>GDG Bacolod Event Check-In — participant verification, attendance recording, name-tag printing, and certificate management. Staff accounts can run event check-ins. Admin accounts manage events, participants, and settings.</p>
                </section>
            </div>
        </main>
    </div>

    <dialog class="password-dialog" id="password-dialog" aria-labelledby="password-dialog-title">
        <form id="password-form" class="password-form">
            <div class="password-dialog-heading">
                <div>
                    <h2 id="password-dialog-title">Change password</h2>
                    <p>Use at least 6 characters, including one uppercase letter (A-Z) and one number (0-9).</p>
                </div>
                <button class="settings-dialog-close" type="button" id="close-password-dialog" aria-label="Close">×</button>
            </div>
            <label for="current-password">Current password</label>
            <span class="settings-password-control"><input id="current-password" name="currentPassword" type="password" autocomplete="current-password" required><button class="settings-password-toggle" type="button" data-password-toggle data-password-name="current password" aria-controls="current-password" aria-pressed="false" aria-label="Show current password" title="Show current password"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span>
            <label for="new-password">New password</label>
            <span class="settings-password-control"><input id="new-password" name="password" type="password" minlength="6" maxlength="72" pattern="(?=.*[A-Z])(?=.*[0-9]).+" title="Include at least one uppercase letter (A-Z) and one number (0-9)." autocomplete="new-password" required><button class="settings-password-toggle" type="button" data-password-toggle data-password-name="new password" aria-controls="new-password" aria-pressed="false" aria-label="Show new password" title="Show new password"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span>
            <label for="confirm-password">Confirm new password</label>
            <span class="settings-password-control"><input id="confirm-password" name="passwordConfirmation" type="password" minlength="6" maxlength="72" pattern="(?=.*[A-Z])(?=.*[0-9]).+" title="Include at least one uppercase letter (A-Z) and one number (0-9)." autocomplete="new-password" aria-describedby="password-confirmation-help" required><button class="settings-password-toggle" type="button" data-password-toggle data-password-name="password confirmation" aria-controls="confirm-password" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span>
            <p id="password-confirmation-help">Enter the same password again.</p>
            <p class="settings-feedback" id="password-feedback" role="status" aria-live="polite" hidden></p>
            <div class="settings-dialog-actions">
                <button class="settings-button settings-button-secondary" type="button" id="cancel-password-change">Cancel</button>
                <button class="settings-button settings-button-primary" type="submit" id="save-password">Save password</button>
            </div>
        </form>
    </dialog>
    <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
