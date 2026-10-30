<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#080d3d">
    <title><?= $invite !== null && (int) $invite['is_active'] === 1 ? 'Reset Password' : 'Accept Invitation' ?> | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/login.css?v=5">
    <script src="assets/js/password-visibility.js?v=2" defer></script>
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-panel setup-panel" aria-labelledby="invitation-title">
            <div class="login-brand" aria-label="GDG Bacolod">
                <img class="login-brand-logo" src="assets/images/gdg-logo.svg" alt="">
                <span class="login-brand-name">GDG Bacolod</span>
            </div>

            <div class="login-heading">
                <h1 id="invitation-title"><?= $invite !== null && (int) $invite['is_active'] === 1 ? 'Reset your password' : 'Set up your account' ?></h1>
                <?php if ($invite !== null): ?>
                    <p><?= (int) $invite['is_active'] === 1 ? 'Password reset for ' : escape_html(ucfirst($invite['role']) . ' invitation for ') ?><?= escape_html($invite['email']) ?><?= (int) $invite['is_active'] === 1 ? '. Choose a new password.' : '.' ?></p>
                <?php else: ?>
                    <p>This account link can no longer be used.</p>
                <?php endif; ?>
            </div>

            <?php if ($invite !== null): ?>
                <form class="login-form" method="post" action="activate-account.php">
                    <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
                    <input type="hidden" name="token" value="<?= escape_html(is_string($_POST['token'] ?? null) ? $_POST['token'] : (is_string($_GET['token'] ?? null) ? $_GET['token'] : '')) ?>">
                    <?php if ((int) $invite['is_active'] !== 1): ?>
                        <?php if ($invite['role'] === 'staff'): ?>
                            <label class="login-field" for="username">
                                <span>Username</span>
                                <input id="username" name="username" type="text" minlength="3" maxlength="100" pattern="[A-Za-z0-9_.-]+" autocomplete="username" value="<?= escape_html(is_string($_POST['username'] ?? null) ? $_POST['username'] : '') ?>" required>
                            </label>
                        <?php else: ?>
                            <label class="login-field" for="display-name">
                                <span>Display name</span>
                                <input id="display-name" name="display_name" type="text" maxlength="150" autocomplete="name" value="<?= escape_html(is_string($_POST['display_name'] ?? null) ? $_POST['display_name'] : ($invite['display_name'] === 'Pending invitation' ? '' : $invite['display_name'])) ?>" required>
                            </label>
                        <label class="login-field" for="username">
                            <span>Username</span>
                            <input id="username" name="username" type="text" minlength="3" maxlength="100" pattern="[A-Za-z0-9_.-]+" autocomplete="username" value="<?= escape_html(is_string($_POST['username'] ?? null) ? $_POST['username'] : (str_starts_with((string) $invite['username'], 'invite-') ? '' : $invite['username'])) ?>" required>
                        </label>
                        <?php endif; ?>
                    <?php endif; ?>
                    <label class="login-field" for="setup-password">
                        <span>Password</span>
                        <span class="password-control"><input id="setup-password" name="password" type="password" minlength="6" maxlength="72" pattern="(?=.*[A-Z])(?=.*[0-9]).+" title="Include at least one uppercase letter (A-Z) and one number (0-9)." autocomplete="new-password" aria-describedby="setup-password-help" required><button class="password-toggle" type="button" data-password-toggle data-password-name="password" aria-controls="setup-password" aria-pressed="false" aria-label="Show password" title="Show password"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span>
                        <span class="setup-hint" id="setup-password-help">Use at least 6 characters, including one uppercase letter (A-Z) and one number (0-9).</span>
                    </label>
                    <label class="login-field" for="password-confirmation">
                        <span>Confirm password</span>
                        <span class="password-control"><input id="password-confirmation" name="password_confirmation" type="password" minlength="6" maxlength="72" pattern="(?=.*[A-Z])(?=.*[0-9]).+" title="Include at least one uppercase letter (A-Z) and one number (0-9)." autocomplete="new-password" aria-describedby="password-confirmation-help" required><button class="password-toggle" type="button" data-password-toggle data-password-name="password confirmation" aria-controls="password-confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></span>
                        <span class="setup-hint" id="password-confirmation-help">Enter the same password again.</span>
                    </label>
                    <?php if (isset($errorMessage) && $errorMessage !== null): ?>
                        <p class="login-message is-error" role="alert"><?= escape_html($errorMessage) ?></p>
                    <?php endif; ?>
                    <button class="login-submit" type="submit"><?= (int) $invite['is_active'] === 1 ? 'Reset password' : 'Set password' ?></button>
                </form>
            <?php else: ?>
                <p class="login-message is-error" role="alert"><?= escape_html($errorMessage ?? 'This invitation is unavailable.') ?></p>
                <a class="initial-setup-link" href="login.php">Back to sign in</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
