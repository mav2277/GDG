<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#080d3d">
    <title>Admin Sign In | GDG Bacolod</title>
    <link rel="stylesheet" href="assets/css/login.css?v=9">
    <script src="assets/js/login.js?v=2" defer></script>
    <script src="assets/js/password-visibility.js?v=2" defer></script>
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-brand" aria-label="GDG Bacolod">
                <img class="login-brand-logo" src="assets/images/gdg-logo.svg" alt="">
                <span class="login-brand-name">GDG Bacolod</span>
            </div>

            <div class="login-heading">
                <h1 id="login-title">Event Check-In Portal</h1>
                <p>Sign in to continue.</p>
            </div>

            <form class="login-form" id="login-form" method="post" action="<?= defined('CLEAN_AUTH_ROUTE') && CLEAN_AUTH_ROUTE ? './' : 'login.php' ?>">
                <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
                <label class="login-field" for="identifier">
                    <span>Email address or username</span>
                    <input id="identifier" name="identifier" type="text" autocomplete="username" placeholder="Email or username" value="<?= escape_html(is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '') ?>" required>
                </label>

                <label class="login-field" for="password">
                    <span>Password</span>
                    <span class="password-control">
                        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                        <button class="password-toggle" type="button" id="password-toggle" data-password-toggle data-password-name="password" aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                    </span>
                </label>

                <div class="login-options">
                    <button class="forgot-password" id="forgot-password" type="button">Forgot password?</button>
                </div>

                <p class="login-message<?= isset($loginError) && $loginError !== null ? ' is-error' : '' ?>" id="login-message" role="status" aria-live="polite"<?= !isset($loginError) || $loginError === null ? ' hidden' : '' ?>><?= isset($loginError) ? escape_html($loginError) : '' ?></p>
                <button class="login-submit" type="submit">Sign in</button>
            </form>

            <?php if (!empty($setupAvailable)): ?>
                <a class="initial-setup-link" href="<?= defined('CLEAN_AUTH_ROUTE') && CLEAN_AUTH_ROUTE ? 'setup-admin' : 'setup-admin.php' ?>">Set up the first administrator</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
