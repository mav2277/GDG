<?php

declare(strict_types=1);

final class CertificateController
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        require_once dirname(__DIR__) . '/Models/Certificate.php';
        $model = new Certificate(database_connection());

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $eventId = filter_var($_GET['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($eventId === false) {
                $this->respond(422, ['success' => false, 'message' => 'Choose a valid event.']);
            }
            try {
                $this->respond(200, [
                    'success' => true,
                    'rows' => $model->eligibleForEvent((int) $eventId),
                    'layoutConfigured' => $model->hasActiveLayout((int) $eventId),
                ]);
            } catch (OutOfBoundsException $error) {
                $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
            } catch (Throwable $error) {
                error_log('Certificate eligibility could not be loaded: ' . $error->getMessage());
                $this->respond(503, ['success' => false, 'message' => 'Certificate records could not be loaded. Refresh and try again.']);
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
        if ($eventId === false) {
            $this->respond(422, ['success' => false, 'message' => 'Choose a valid event.']);
        }

        if ($action === 'queue_all') {
            try {
                $user = authenticated_user();
                $result = $model->queueAllFirstTime((int) $eventId, (int) ($user['id'] ?? 0));
                if ($result['outcome'] === 'layout_missing') {
                    $this->respond(409, ['success' => false, 'outcome' => 'layout_missing', 'message' => 'Save a certificate layout for this event before queueing certificates.']);
                }
                $queued = $result['queued'];
                $skipped = $result['skipped'];
                $message = $queued === 0
                    ? 'No new certificate requests were added.'
                    : sprintf('%d certificate request%s queued.', $queued, $queued === 1 ? '' : 's');
                if ($skipped > 0) {
                    $message .= sprintf(' %d participant%s skipped because a certificate request already exists.', $skipped, $skipped === 1 ? ' was' : 's were');
                }
                $message .= ' Physical printing is not configured yet.';
                $this->respond(201, [
                    'success' => true,
                    'outcome' => 'queued',
                    'queued' => $queued,
                    'skipped' => $skipped,
                    'message' => $message,
                ]);
            } catch (OutOfBoundsException $error) {
                $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
            } catch (Throwable $error) {
                error_log('Bulk certificate requests could not be queued: ' . $error->getMessage());
                $this->respond(503, ['success' => false, 'message' => 'Certificate requests could not be queued. Try again.']);
            }
        }

        $registrationId = filter_var($input['registrationId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false || $registrationId === false || !in_array($action, ['print', 'reprint'], true)) {
            $this->respond(422, ['success' => false, 'message' => 'The certificate request is invalid. Refresh the event and try again.']);
        }

        try {
            $user = authenticated_user();
            $result = $model->queue((int) $eventId, (int) $registrationId, (int) ($user['id'] ?? 0), $action === 'reprint');
            if ($result['outcome'] === 'not_eligible') {
                $this->respond(404, ['success' => false, 'message' => 'Certificates are available only for participants checked in to this event.']);
            }
            if ($result['outcome'] === 'layout_missing') {
                $this->respond(409, ['success' => false, 'outcome' => 'layout_missing', 'message' => 'Save a certificate layout for this event before queueing certificates.']);
            }
            if ($result['outcome'] === 'pending') {
                $this->respond(409, ['success' => false, 'outcome' => 'pending', 'message' => 'A certificate request is already queued or being processed.']);
            }
            if ($result['outcome'] === 'action_out_of_date') {
                $this->respond(409, ['success' => false, 'outcome' => 'action_out_of_date', 'message' => 'Certificate history changed. Refresh the list and try again.']);
            }
            $this->respond(201, [
                'success' => true,
                'outcome' => 'queued',
                'message' => 'Certificate request saved to the database queue. Physical printing is not configured yet.',
            ]);
        } catch (Throwable $error) {
            error_log('Certificate request could not be queued: ' . $error->getMessage());
            $this->respond(503, ['success' => false, 'message' => 'The certificate request could not be saved. Try again.']);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
