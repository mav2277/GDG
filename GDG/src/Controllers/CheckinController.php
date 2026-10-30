<?php

declare(strict_types=1);

final class CheckinController
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            $this->respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input) || !csrf_is_valid($input['csrfToken'] ?? null)) {
            $this->respond(400, ['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        }

        $eventId = filter_var($input['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $ticketValue = $input['ticketNumber'] ?? null;
        if ($eventId === false || !is_string($ticketValue)) {
            $this->respond(422, ['success' => false, 'message' => 'Choose an event and enter a ticket number or QR value.']);
        }

        $ticketNumber = trim($ticketValue);
        if ($ticketNumber === '' || mb_strlen($ticketNumber) > 100) {
            $this->respond(422, ['success' => false, 'message' => 'Enter a ticket number or QR value up to 100 characters.']);
        }

        try {
            $database = database_connection();
            $user = authenticated_user();
            if (($user['role'] ?? null) === 'staff') {
                require_once dirname(__DIR__) . '/Models/Event.php';
                if (!(new Event($database))->isScheduledOn((int) $eventId, app_today())) {
                    $this->respond(403, [
                        'success' => false,
                        'outcome' => 'event_unavailable',
                        'message' => 'This event is not scheduled for today. Select an event dated today.',
                    ]);
                }
            }
            $result = (new Checkin($database))->checkIn((int) $eventId, $ticketNumber);
            if ($result['outcome'] === 'not_registered') {
                $this->respond(404, [
                    'success' => false,
                    'outcome' => 'not_registered',
                    'message' => 'QR detected, but its value is not registered for the selected event. No check-in was recorded.',
                ]);
            }

            $alreadyCheckedIn = $result['outcome'] === 'already_checked_in';
            $nameTagQueue = $alreadyCheckedIn ? 'unchanged' : 'unavailable';
            if (!$alreadyCheckedIn) {
                try {
                    require_once dirname(__DIR__) . '/Models/NameTag.php';
                    $queueResult = (new NameTag($database))->queue(
                        (int) $eventId,
                        (int) $result['registrationId'],
                        (int) (authenticated_user()['id'] ?? 0),
                        false
                    );
                    $nameTagQueue = match ($queueResult['outcome']) {
                        'queued' => 'queued',
                        'pending' => 'already_queued',
                        'action_out_of_date' => 'already_requested',
                        default => 'unavailable',
                    };
                } catch (Throwable $printError) {
                    error_log('Check-in succeeded but name-tag queueing failed: ' . $printError->getMessage());
                }
            }
            $this->respond($alreadyCheckedIn ? 409 : 200, [
                'success' => !$alreadyCheckedIn,
                'outcome' => $result['outcome'],
                'fullName' => $result['fullName'],
                'checkedInAt' => $result['checkedInAt'],
                'nameTagQueue' => $nameTagQueue,
                'message' => $alreadyCheckedIn ? 'This participant was already checked in. No second check-in was added.' : 'Check-in successful.',
            ]);
        } catch (Throwable $error) {
            error_log('Participant check-in failed: ' . $error->getMessage());
            $this->respond(503, [
                'success' => false,
                'outcome' => 'error',
                'message' => 'Check-in could not be recorded because the service is unavailable. Try again.',
            ]);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
