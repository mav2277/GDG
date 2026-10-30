<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();
require_once dirname(__DIR__) . '/src/Models/Event.php';

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

$action = $input['action'] ?? '';
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    $events = new Event(database_connection());
    if ($action === 'list') {
        $respond(200, ['success' => true, 'events' => $events->all()]);
    }

    if ($action === 'create' || $action === 'update') {
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $date = is_string($input['date'] ?? null) ? $input['date'] : '';
        $venue = is_string($input['venue'] ?? null) ? trim($input['venue']) : '';
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateValid = $dateValue !== false && $dateValue->format('Y-m-d') === $date;

        if ($name === '' || mb_strlen($name) > 255 || !$dateValid || $venue === '' || mb_strlen($venue) > 255) {
            $respond(422, ['success' => false, 'message' => 'Enter an event name, a valid date, and a location (up to 255 characters each).']);
        }

        if ($action === 'create') {
            $eventId = $events->create($name, $date, $venue);
            $respond(201, ['success' => true, 'message' => 'Event created.', 'id' => $eventId]);
        }

        if ($id === false || !$events->update((int) $id, $name, $date, $venue)) {
            $respond(404, ['success' => false, 'message' => 'That event no longer exists. Refresh the dashboard and try again.']);
        }
        $respond(200, ['success' => true, 'message' => 'Event updated.']);
    }

    if ($action === 'delete') {
        if ($id === false) {
            $respond(422, ['success' => false, 'message' => 'Select a valid event to delete.']);
        }
        try {
            $events->delete((int) $id);
        } catch (OutOfBoundsException $error) {
            $respond(404, ['success' => false, 'message' => $error->getMessage()]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $respond(409, ['success' => false, 'message' => 'This event has linked registrations or records, so it cannot be deleted. Existing event history was preserved.']);
            }
            throw $error;
        }
        $respond(200, ['success' => true, 'message' => 'Event deleted.']);
    }

    $respond(422, ['success' => false, 'message' => 'Unknown event action.']);
} catch (Throwable $error) {
    error_log('Admin event action failed: ' . $error->getMessage());
    $respond(503, ['success' => false, 'message' => 'Events could not be saved. Check the database connection and try again.']);
}
