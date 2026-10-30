<?php

declare(strict_types=1);

final class AdminDashboardController
{
    public function index(): void
    {
        require_once dirname(__DIR__) . '/Models/Event.php';
        $events = [];
        $eventLoadError = null;
        try {
            $events = (new Event(database_connection()))->all();
        } catch (Throwable $error) {
            error_log('Admin dashboard could not load events: ' . $error->getMessage());
            http_response_code(503);
            $eventLoadError = 'Events could not be loaded. Check the database connection, then refresh this page.';
        }
        $csrfToken = csrf_token();
        require dirname(__DIR__) . '/Views/admin/dashboard.php';
    }
}
