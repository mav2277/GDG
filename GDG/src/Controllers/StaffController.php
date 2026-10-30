<?php

declare(strict_types=1);

final class StaffController
{
    public function index(): void
    {
        require_once dirname(__DIR__) . '/Models/Event.php';
        try {
            $events = (new Event(database_connection()))->availableForStaffOn(app_today());
        } catch (Throwable $error) {
            error_log('Staff event list could not be loaded: ' . $error->getMessage());
            http_response_code(503);
            $events = [];
            $eventLoadError = 'Events could not be loaded. Check the database connection, then refresh this page.';
        }
        $csrfToken = csrf_token();
        $user = authenticated_user();
        require dirname(__DIR__) . '/Views/staff/check-in.php';
    }
}
