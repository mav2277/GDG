<?php

declare(strict_types=1);

final class AuthController
{
    public function showLogin(?string $loginError = null): void
    {
        $currentUser = authenticated_user();
        if ($currentUser !== null) {
            header('Location: ' . (($currentUser['role'] ?? null) === 'staff' ? 'staff.php' : 'index.php'));
            exit;
        }

        $setupAvailable = false;
        try {
            $setupAvailable = $this->isLocalRequest()
                && (new User(database_connection()))->countAdminAccounts() === 0;
        } catch (Throwable $error) {
            error_log('Login page could not check account setup state: ' . $error->getMessage());
            $loginError = $loginError ?? 'The sign-in service cannot reach the database. Check the local database settings.';
        }

        $csrfToken = csrf_token();
        require dirname(__DIR__) . '/Views/auth/login.php';
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }

        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->showLogin('Your sign-in form expired. Please try again.');
            return;
        }

        $identifierValue = $_POST['identifier'] ?? '';
        $passwordValue = $_POST['password'] ?? '';
        $identifier = is_string($identifierValue) ? trim($identifierValue) : '';
        $password = is_string($passwordValue) ? $passwordValue : '';
        if ($identifier === '' || $password === '') {
            $this->showLogin('Enter your email or username and password.');
            return;
        }

        try {
            $accounts = new User(database_connection());
            $user = $accounts->findByIdentifier($identifier);
            if ($user === null || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
                $this->showLogin('Sign-in failed. Check your credentials and try again.');
                return;
            }

            if (!in_array($user['role'], ['admin', 'staff'], true)) {
                $this->showLogin('Sign-in failed. Check your credentials and try again.');
                return;
            }

            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $accounts->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
            }

            session_regenerate_id(true);
            $_SESSION['auth_user'] = [
                'id' => (int) $user['id'],
                'display_name' => $user['display_name'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            unset($_SESSION['csrf_token']);
            csrf_token();

            header('Location: ' . ($user['role'] === 'staff' ? 'staff.php' : 'index.php'));
            exit;
        } catch (Throwable $error) {
            error_log('Admin sign-in failed due to a server error: ' . $error->getMessage());
            $this->showLogin('The sign-in service is temporarily unavailable. Check the local database settings.');
        }
    }

    public function showInitialAdminSetup(?string $setupError = null): void
    {
        if (!$this->isLocalRequest()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not found.';
            return;
        }

        try {
            if ((new User(database_connection()))->countAdminAccounts() > 0) {
                header('Location: login.php');
                exit;
            }
        } catch (Throwable $error) {
            error_log('Initial administrator setup could not check account state: ' . $error->getMessage());
            $setupError = $setupError ?? 'The setup service cannot reach the database. Check the local database settings.';
        }

        $csrfToken = csrf_token();
        require dirname(__DIR__) . '/Views/auth/setup-admin.php';
    }

    public function createInitialAdmin(): void
    {
        if (!$this->isLocalRequest()) {
            http_response_code(404);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }
        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->showInitialAdminSetup('Your setup form expired. Please try again.');
            return;
        }

        $usernameValue = $_POST['username'] ?? '';
        $emailValue = $_POST['email'] ?? '';
        $passwordValue = $_POST['password'] ?? '';
        $confirmationValue = $_POST['password_confirmation'] ?? '';
        $username = is_string($usernameValue) ? trim($usernameValue) : '';
        $email = is_string($emailValue) ? trim($emailValue) : '';
        $password = is_string($passwordValue) ? $passwordValue : '';
        $passwordConfirmation = is_string($confirmationValue) ? $confirmationValue : '';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
            $this->showInitialAdminSetup('Use 3 to 100 characters for the username: letters, numbers, dot, dash, or underscore.');
            return;
        }
        if (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->showInitialAdminSetup('Enter a valid email address.');
            return;
        }
        $passwordError = password_policy_error($password);
        if ($passwordError !== null) {
            $this->showInitialAdminSetup($passwordError);
            return;
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            $this->showInitialAdminSetup('The passwords do not match.');
            return;
        }

        $database = null;
        $lockAcquired = false;
        try {
            $database = database_connection();
            $lockAcquired = (int) $database->query("SELECT GET_LOCK('gdg_bacolod_first_admin_setup', 5)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                $this->showInitialAdminSetup('Another setup request is in progress. Please try again.');
                return;
            }

            $accounts = new User($database);
            if ($accounts->countAdminAccounts() > 0) {
                header('Location: login.php');
                exit;
            }

            $userId = $accounts->createInitialAdmin(
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            );
            $user = $accounts->findActiveById($userId);
            if ($user === null) {
                throw new RuntimeException('The initial administrator could not be loaded after creation.');
            }

            session_regenerate_id(true);
            $_SESSION['auth_user'] = [
                'id' => (int) $user['id'],
                'display_name' => $user['display_name'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            unset($_SESSION['csrf_token']);
            csrf_token();

            header('Location: index.php');
            exit;
        } catch (Throwable $error) {
            error_log('Initial administrator setup failed: ' . $error->getMessage());
            $this->showInitialAdminSetup('The account could not be created. Check whether the username or email is already in use.');
        } finally {
            if ($lockAcquired && $database instanceof PDO) {
                try {
                    $database->query("SELECT RELEASE_LOCK('gdg_bacolod_first_admin_setup')");
                } catch (Throwable $error) {
                    error_log('Could not release the initial administrator setup lock: ' . $error->getMessage());
                }
            }
        }
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }
        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Invalid request token.';
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        header('Location: login.php');
        exit;
    }

    private function isLocalRequest(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }
}
