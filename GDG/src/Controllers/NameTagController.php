<?php

declare(strict_types=1);

final class NameTagController
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        require_once dirname(__DIR__) . '/Models/NameTag.php';
        $model = new NameTag(database_connection());

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $eventId = filter_var($_GET['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($eventId === false) {
                $this->respond(422, ['success' => false, 'message' => 'Choose a valid event.']);
            }
            try {
                $this->respond(200, ['success' => true, 'rows' => $model->forEvent((int) $eventId)]);
            } catch (OutOfBoundsException $error) {
                $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
            } catch (Throwable $error) {
                error_log('Name-tag history could not be loaded: ' . $error->getMessage());
                $this->respond(503, ['success' => false, 'message' => 'Name-tag records could not be loaded. Refresh and try again.']);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: GET, POST');
            $this->respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input) || !csrf_is_valid($input['csrfToken'] ?? null)) {
            $this->respond(400, ['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        }
        $eventId = filter_var($input['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = $input['action'] ?? null;
        if ($eventId === false || !in_array($action, ['print', 'reprint', 'queue_all'], true)) {
            $this->respond(422, ['success' => false, 'message' => 'The name-tag request is invalid. Refresh the event and try again.']);
        }

        try {
            $user = authenticated_user();
            if ($action === 'queue_all') {
                $result = $model->queueAllFirstTime((int) $eventId, (int) ($user['id'] ?? 0));
                if ($result['outcome'] === 'event_not_found') {
                    $this->respond(404, ['success' => false, 'message' => 'That event could not be found.']);
                }
                $this->respond(201, [
                    'success' => true,
                    'outcome' => 'queued',
                    'queued' => $result['queued'],
                    'skipped' => $result['skipped'],
                    'message' => sprintf('%d initial name-tag request(s) queued; %d checked-in participant(s) skipped because they already have a name-tag job.', $result['queued'], $result['skipped']),
                ]);
            }
            $registrationId = filter_var($input['registrationId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($registrationId === false) {
                $this->respond(422, ['success' => false, 'message' => 'Choose a valid participant.']);
            }
            $result = $model->queue((int) $eventId, (int) $registrationId, (int) ($user['id'] ?? 0), $action === 'reprint');
            if ($result['outcome'] === 'not_registered') {
                $this->respond(404, ['success' => false, 'message' => 'That participant is not registered for this event.']);
            }
            if ($result['outcome'] === 'not_checked_in') {
                $this->respond(409, ['success' => false, 'outcome' => 'not_checked_in', 'message' => 'This participant must check in before a name-tag request can be queued.']);
            }
            if ($result['outcome'] === 'pending') {
                $this->respond(409, ['success' => false, 'outcome' => 'pending', 'message' => 'A name-tag request is already queued or being processed.']);
            }
            if ($result['outcome'] === 'action_out_of_date') {
                $this->respond(409, ['success' => false, 'outcome' => 'action_out_of_date', 'message' => 'The print history changed. Refresh the Name Tags list and try again.']);
            }
            $this->respond(201, [
                'success' => true,
                'outcome' => 'queued',
                'message' => 'Name-tag print request saved to the database queue. Physical printing is not configured yet.',
            ]);
        } catch (Throwable $error) {
            error_log('Name-tag request could not be queued: ' . $error->getMessage());
            $this->respond(503, ['success' => false, 'message' => 'The name-tag request could not be saved. Try again.']);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
