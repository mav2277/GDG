<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#080d3d">
    <title>Initial Admin Setup | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/login.css?v=5">
    <script src="assets/js/password-visibility.js?v=2" defer></script>
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-panel setup-panel" aria-labelledby="setup-title">
            <div class="login-brand" aria-label="GDG Bacolod">
                <img class="login-brand-logo" src="assets/images/gdg-logo.svg" alt="">
                <span class="login-brand-name">GDG Bacolod</span>
            </div>

            <div class="login-heading">
                <h1 id="setup-title">Set up administrator</h1>
                <p>Create the first admin account for this local system.</p>
            </div>

            <form class="login-form" method="post" action="<?= defined('CLEAN_AUTH_ROUTE') && CLEAN_AUTH_ROUTE ? 'setup-admin' : 'setup-admin.php' ?>">
                <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
                <label class="login-field" for="username">
                    <span>Username</span>
                    <input id="username" name="username" type="text" minlength="3" maxlength="100" pattern="[A-Za-z0-9_.-]+" autocomplete="username" value="<?= escape_html(is_string($_POST['username'] ?? null) ? $_POST['username'] : '') ?>" required>
                </label>
                <label class="login-field" for="setup-email">
                    <span>Email address</span>
                    <input id="setup-email" name="email" type="email" maxlength="255" autocomplete="email" value="<?= escape_html(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>" required>
                </label>
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

                <?php if (isset($setupError) && $setupError !== null): ?>
                    <p class="login-message is-error" role="alert"><?= escape_html($setupError) ?></p>
                <?php endif; ?>

                <button class="login-submit" type="submit">Create admin account</button>
            </form>
            <a class="initial-setup-link" href="<?= defined('CLEAN_AUTH_ROUTE') && CLEAN_AUTH_ROUTE ? './' : 'login.php' ?>">Back to sign in</a>
        </section>
    </main>
</body>
</html>
