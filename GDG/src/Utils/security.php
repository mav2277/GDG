<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_is_valid(mixed $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function escape_html(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function password_policy_error(string $password): ?string
{
    if (mb_strlen($password) < 6) {
        return 'Use at least 6 characters.';
    }
    if (preg_match('/[A-Z]/', $password) !== 1) {
        return 'Add at least one uppercase letter (A-Z).';
    }
    if (preg_match('/[0-9]/', $password) !== 1) {
        return 'Add at least one number (0-9).';
    }
    if (strlen($password) > 72) {
        return 'That password is too long. Try a shorter one.';
    }
    return null;
}
