<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();

header('Content-Type: application/json; charset=UTF-8');
$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    $respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input) || !csrf_is_valid($input['csrfToken'] ?? null)) {
    $respond(400, ['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
}

$user = authenticated_user();
$accounts = new User(database_connection());
$action = $input['action'] ?? null;

if ($action === 'change-password') {
    $current = is_string($input['currentPassword'] ?? null) ? $input['currentPassword'] : '';
    $password = is_string($input['password'] ?? null) ? $input['password'] : '';
    $confirmation = is_string($input['passwordConfirmation'] ?? null) ? $input['passwordConfirmation'] : '';
    if ($current === '') {
        $respond(422, ['success' => false, 'message' => 'Enter your current password.']);
    }
    $passwordError = password_policy_error($password);
    if ($passwordError !== null) {
        $respond(422, ['success' => false, 'message' => $passwordError]);
    }
    if (!hash_equals($password, $confirmation)) {
        $respond(422, ['success' => false, 'message' => 'The new passwords do not match.']);
    }
    try {
        if (!$accounts->changePassword((int) ($user['id'] ?? 0), $current, password_hash($password, PASSWORD_DEFAULT))) {
            $respond(422, ['success' => false, 'message' => 'Your current password was not correct.']);
        }
        session_regenerate_id(true);
        $respond(200, ['success' => true, 'message' => 'Your password was updated.']);
    } catch (Throwable $error) {
        error_log('Admin password update failed: ' . $error->getMessage());
        $respond(503, ['success' => false, 'message' => 'Your password could not be changed. Try again.']);
    }
}

if ($action === 'invite') {
    $emailValue = $input['email'] ?? null;
    $role = $input['role'] ?? null;
    $email = is_string($emailValue) ? mb_strtolower(trim($emailValue)) : '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
        $respond(422, ['success' => false, 'message' => 'Enter a valid email address.']);
    }
    if (!is_string($role) || !in_array($role, ['admin', 'staff'], true)) {
        $respond(422, ['success' => false, 'message' => 'Choose a valid account role.']);
    }

    try {
        $token = bin2hex(random_bytes(32));
        $accounts->createOrRenewInvitation($email, $role, (int) ($user['id'] ?? 0), hash('sha256', $token));
        $respond(201, [
            'success' => true,
            'invitePath' => 'activate-account.php?token=' . $token,
            'message' => 'Invitation created. Copy and share the one-time link; the recipient chooses their own password. The link expires in 24 hours.',
        ]);
    } catch (DomainException $error) {
        $respond(409, ['success' => false, 'message' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('Account invitation creation failed: ' . $error->getMessage());
        $respond(503, ['success' => false, 'message' => 'The invitation could not be created. Try again.']);
    }
}

if ($action === 'account-access-link') {
    $targetId = filter_var($input['userId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetId === false) {
        $respond(422, ['success' => false, 'message' => 'Choose a valid account.']);
    }
    if ((int) $targetId === (int) ($user['id'] ?? 0)) {
        $respond(409, ['success' => false, 'message' => 'Use Account Security to change your own password.']);
    }

    try {
        $token = bin2hex(random_bytes(32));
        $wasActive = $accounts->createAccountAccessLink((int) $targetId, hash('sha256', $token));
        $respond(200, [
            'success' => true,
            'invitePath' => 'activate-account.php?token=' . $token,
            'message' => $wasActive
                ? 'Password reset link created. Copy and share it with the account holder. It expires in 24 hours.'
                : 'Account setup link created. Copy and share it with the account holder. It expires in 24 hours.',
            'linkType' => $wasActive ? 'password-reset' : 'account-setup',
        ]);
    } catch (OutOfBoundsException $error) {
        $respond(404, ['success' => false, 'message' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('Account access link could not be created: ' . $error->getMessage());
        $respond(503, ['success' => false, 'message' => 'The account link could not be created. Try again.']);
    }
}

if ($action === 'deactivate-account') {
    $targetId = filter_var($input['userId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetId === false) {
        $respond(422, ['success' => false, 'message' => 'Choose a valid account.']);
    }

    try {
        $accounts->deactivateAccount((int) $targetId, (int) ($user['id'] ?? 0));
        $respond(200, ['success' => true, 'message' => 'Account deactivated. Existing sessions will be denied on their next request.']);
    } catch (OutOfBoundsException $error) {
        $respond(404, ['success' => false, 'message' => $error->getMessage()]);
    } catch (DomainException $error) {
        $respond(409, ['success' => false, 'message' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('Account deactivation failed: ' . $error->getMessage());
        $respond(503, ['success' => false, 'message' => 'The account could not be deactivated. Refresh and try again.']);
    }
}

$respond(422, ['success' => false, 'message' => 'Choose a valid account action.']);
