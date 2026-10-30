<?php

declare(strict_types=1);

function authenticated_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function require_signed_in_user(): array
{
    $user = authenticated_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    try {
        $freshUser = (new User(database_connection()))->findActiveById((int) ($user['id'] ?? 0));
    } catch (Throwable $error) {
        error_log('Admin authorization check failed: ' . $error->getMessage());
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'The Admin Console is temporarily unavailable.';
        exit;
    }

    if ($freshUser === null) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $freshUser['id'],
        'display_name' => $freshUser['display_name'],
        'username' => $freshUser['username'],
        'role' => $freshUser['role'],
    ];
    return $_SESSION['auth_user'];
}

function require_admin(): void
{
    $user = require_signed_in_user();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'This account does not have access to the Admin Console.';
        exit;
    }
}

function require_staff(): void
{
    $user = require_signed_in_user();
    if ($user['role'] !== 'staff') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'This page is for Staff accounts.';
        exit;
    }
}

function require_checkin_access(): void
{
    $user = require_signed_in_user();
    if (!in_array($user['role'], ['admin', 'staff'], true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'This account cannot access check-in.';
        exit;
    }
}
