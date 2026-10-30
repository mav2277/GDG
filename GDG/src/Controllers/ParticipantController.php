<?php

declare(strict_types=1);

final class ParticipantController
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Allow: GET');
            $this->respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
        }

        $eventId = filter_var($_GET['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'all';
        if ($eventId === false || $page === false || !is_string($search) || mb_strlen($search) > 120
            || !is_string($status) || !in_array($status, ['all', 'checked-in', 'not-checked-in'], true)) {
            $this->respond(422, ['success' => false, 'message' => 'The participant search or filter is invalid.']);
        }

        try {
            require_once dirname(__DIR__) . '/Models/Participant.php';
            $data = (new Participant(database_connection()))->forEvent((int) $eventId, trim($search), $status, (int) $page);
            $this->respond(200, ['success' => true] + $data);
        } catch (OutOfBoundsException $error) {
            $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('Participant list could not be loaded: ' . $error->getMessage());
            $this->respond(503, ['success' => false, 'message' => 'Participant records could not be loaded. Refresh the page and try again.']);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
