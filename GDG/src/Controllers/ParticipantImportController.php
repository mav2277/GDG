<?php

declare(strict_types=1);

final class ParticipantImportController
{
    private const MAX_FILE_BYTES = 5242880;
    private const MAX_DATA_ROWS = 20000;
    private const FIELDS = ['ticket', 'first', 'last', 'email', 'nickname'];

    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            $this->respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
        }
        if (!csrf_is_valid($_POST['csrfToken'] ?? null)) {
            $this->respond(400, ['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        }

        $eventId = filter_var($_POST['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $file = $_FILES['csvFile'] ?? null;
        if ($eventId === false || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->respond(422, ['success' => false, 'message' => 'Choose an event and a CSV file to import.']);
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_FORM_SIZE) {
            $this->respond(422, ['success' => false, 'message' => 'The uploaded CSV is larger than the allowed file size.']);
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? '')) || (int) ($file['size'] ?? 0) > self::MAX_FILE_BYTES) {
            $this->respond(422, ['success' => false, 'message' => 'CSV files must be no larger than 5 MB.']);
        }
        $fileName = basename((string) ($file['name'] ?? 'participants.csv'));
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'csv') {
            $this->respond(422, ['success' => false, 'message' => 'Choose a .csv file.']);
        }

        $delimiterName = $_POST['delimiter'] ?? null;
        $delimiter = match ($delimiterName) {
            'comma' => ',',
            'semicolon' => ';',
            'tab' => "\t",
            default => null,
        };
        $mapping = $_POST['mapping'] ?? null;
        if ($delimiter === null || !is_array($mapping)) {
            $this->respond(422, ['success' => false, 'message' => 'Choose a delimiter and map each required participant field.']);
        }

        $columnIndexes = [];
        foreach (self::FIELDS as $field) {
            $index = filter_var($mapping[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($index === false) {
                $this->respond(422, ['success' => false, 'message' => 'Map ticket number, first name, last name, email, and nickname to CSV columns.']);
            }
            $columnIndexes[$field] = (int) $index;
        }
        if (count(array_unique($columnIndexes)) !== count(self::FIELDS)) {
            $this->respond(422, ['success' => false, 'message' => 'Each participant field must use a different CSV column.']);
        }

        $parsed = $this->readCsv((string) $file['tmp_name'], $delimiter, $columnIndexes);
        if (isset($parsed['error'])) {
            $this->respond(422, ['success' => false, 'message' => $parsed['error']]);
        }

        try {
            require_once dirname(__DIR__) . '/Models/ParticipantImport.php';
            $result = (new ParticipantImport(database_connection()))->import(
                (int) $eventId,
                (int) (authenticated_user()['id'] ?? 0),
                mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?? 'participants.csv', 0, 255),
                $parsed['rows']
            );
            $this->respond(200, ['success' => true] + $result);
        } catch (OutOfBoundsException $error) {
            $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('Participant CSV import failed: ' . $error->getMessage());
            $this->respond(503, ['success' => false, 'message' => 'The CSV could not be imported. Check the database connection and schema, then try again.']);
        }
    }

    private function readCsv(string $path, string $delimiter, array $mapping): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['error' => 'The uploaded CSV could not be read.'];
        }
        try {
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            if (!is_array($headers) || $headers === []) {
                return ['error' => 'The CSV needs a header row followed by participant rows.'];
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
            foreach ($mapping as $index) {
                if (!array_key_exists($index, $headers)) {
                    return ['error' => 'A selected column does not exist in the CSV header. Check the delimiter and field mapping.'];
                }
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }
                if (count($rows) >= self::MAX_DATA_ROWS) {
                    return ['error' => 'A CSV may contain up to 20,000 participant rows per import.'];
                }
                if (count($values) !== count($headers)) {
                    $rows[] = ['row' => $line, 'ticket' => '', 'first' => '', 'last' => '', 'email' => '', 'nickname' => '', 'error' => 'Column count does not match the header.'];
                    continue;
                }

                $record = ['row' => $line];
                foreach ($mapping as $field => $index) {
                    $record[$field] = trim((string) ($values[$index] ?? ''));
                }
                $rows[] = $this->validateRow($record);
            }
            if ($rows === []) {
                return ['error' => 'The CSV has a header but no participant rows.'];
            }
            return ['rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    private function validateRow(array $row): array
    {
        foreach (['ticket' => 100, 'first' => 100, 'last' => 100, 'email' => 255, 'nickname' => 100] as $field => $limit) {
            if (!mb_check_encoding($row[$field], 'UTF-8')) {
                $row['error'] = 'CSV text must use UTF-8 encoding.';
                return $row;
            }
            if (mb_strlen($row[$field]) > $limit) {
                $row['error'] = ucfirst($field) . ' exceeds the ' . $limit . '-character limit.';
                return $row;
            }
        }
        if ($row['ticket'] === '' || $row['first'] === '' || $row['last'] === '' || $row['email'] === '') {
            $row['error'] = 'Ticket number, first name, last name, and email are required.';
            return $row;
        }
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
            $row['error'] = 'Email address is not valid.';
        }
        return $row;
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
