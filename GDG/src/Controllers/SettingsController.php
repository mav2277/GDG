<?php

declare(strict_types=1);

final class SettingsController
{
    public function index(): void
    {
        $user = authenticated_user();
        $account = (new User(database_connection()))->findActiveById((int) ($user['id'] ?? 0));
        if ($account === null) {
            header('Location: login.php');
            exit;
        }
        $csrfToken = csrf_token();
        require dirname(__DIR__) . '/Views/admin/settings.php';
    }
}
