<?php

declare(strict_types=1);

final class InvitationController
{
    public function show(): void
    {
        $token = $this->requestToken($_GET['token'] ?? null);
        $invite = $token === null ? null : (new User(database_connection()))->accountAccessByTokenHash(hash('sha256', $token));
        $csrfToken = csrf_token();
        $errorMessage = $invite === null
            ? 'This account link is invalid, expired, or has already been used.'
            : null;
        require dirname(__DIR__) . '/Views/auth/accept-invitation.php';
    }

    public function accept(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }
        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->showWithError('The form expired. Open the invitation link again and retry.');
            return;
        }

        $token = $this->requestToken($_POST['token'] ?? null);
        $displayNameValue = $_POST['display_name'] ?? null;
        $usernameValue = $_POST['username'] ?? null;
        $passwordValue = $_POST['password'] ?? null;
        $confirmationValue = $_POST['password_confirmation'] ?? null;
        $displayName = is_string($displayNameValue) ? trim($displayNameValue) : '';
        $username = is_string($usernameValue) ? trim($usernameValue) : '';
        $password = is_string($passwordValue) ? $passwordValue : '';
        $confirmation = is_string($confirmationValue) ? $confirmationValue : '';

        if ($token === null) {
            http_response_code(410);
            $this->show();
            return;
        }
        $invite = (new User(database_connection()))->accountAccessByTokenHash(hash('sha256', $token));
        if ($invite === null) {
            http_response_code(410);
            $this->show();
            return;
        }
        $isPasswordReset = (int) $invite['is_active'] === 1;
        if (!$isPasswordReset) {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
                $this->showWithError('Use 3 to 100 characters for the username: letters, numbers, dot, dash, or underscore.');
                return;
            }
            if ($invite['role'] === 'staff') {
                $displayName = $username;
            } elseif ($displayName === '' || mb_strlen($displayName) > 150) {
                $this->showWithError('Enter a display name of 1 to 150 characters.');
                return;
            }
        }
        $passwordError = password_policy_error($password);
        if ($passwordError !== null) {
            $this->showWithError($passwordError);
            return;
        }
        if (!hash_equals($password, $confirmation)) {
            $this->showWithError('The passwords do not match.');
            return;
        }

        try {
            $user = (new User(database_connection()))->completeAccountAccess(
                hash('sha256', $token),
                $isPasswordReset ? null : $displayName,
                $isPasswordReset ? null : $username,
                password_hash($password, PASSWORD_DEFAULT)
            );
            if ($user === null) {
                http_response_code(410);
                $this->show();
                return;
            }

            session_regenerate_id(true);
            $_SESSION['auth_user'] = [
                'id' => $user['id'],
                'display_name' => $user['display_name'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            unset($_SESSION['csrf_token']);
            csrf_token();
            header('Location: ' . ($user['role'] === 'staff' ? 'staff.php' : 'index.php'));
            exit;
        } catch (DomainException $error) {
            $this->showWithError($error->getMessage());
            return;
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $this->showWithError('That username is already in use. Choose another one.');
                return;
            }
            error_log('Invitation activation failed: ' . $error->getMessage());
            $this->showWithError('The account could not be activated. Please try again.');
        } catch (Throwable $error) {
            error_log('Invitation activation failed: ' . $error->getMessage());
            $this->showWithError('The account could not be activated. Please try again.');
        }
    }

    private function showWithError(string $errorMessage): void
    {
        $token = $this->requestToken($_POST['token'] ?? $_GET['token'] ?? null);
        $invite = $token === null ? null : (new User(database_connection()))->accountAccessByTokenHash(hash('sha256', $token));
        $csrfToken = csrf_token();
        require dirname(__DIR__) . '/Views/auth/accept-invitation.php';
    }

    private function requestToken(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : null;
    }
}
