<?php

declare(strict_types=1);

final class ParticipantImport
{
    public function __construct(private PDO $database)
    {
    }

    /**
     * Imports valid rows atomically with row-level skips/rejections recorded in the result.
     * @param array<int, array{row:int,ticket:string,first:string,last:string,email:string,nickname:string,error?:string}> $rows
     * @return array{rowsSeen:int,imported:int,skipped:int,rejected:int,issues:array<int,string>,issuesTruncated:bool}
     */
    public function import(int $eventId, int $userId, string $fileName, array $rows): array
    {
        $this->database->beginTransaction();
        try {
            $event = $this->database->prepare('SELECT id FROM events WHERE id = :id FOR UPDATE');
            $event->execute(['id' => $eventId]);
            if ($event->fetchColumn() === false) {
                throw new OutOfBoundsException('That event could not be found. Refresh the page and try again.');
            }

            $result = ['rowsSeen' => count($rows), 'imported' => 0, 'skipped' => 0, 'rejected' => 0, 'issues' => [], 'issuesTruncated' => false];
            $findAttendee = $this->database->prepare(
                'SELECT id, first_name, last_name FROM attendees WHERE email = :email LIMIT 1 FOR UPDATE'
            );
            $findRegistration = $this->database->prepare(
                'SELECT id, ticket_number FROM event_attendees WHERE attendee_id = :attendee_id AND event_id = :event_id LIMIT 1 FOR UPDATE'
            );
            $findTicket = $this->database->prepare(
                'SELECT id FROM event_attendees WHERE ticket_number = :ticket_number LIMIT 1'
            );
            $insertAttendee = $this->database->prepare(
                'INSERT INTO attendees (first_name, last_name, email, nickname)
                 VALUES (:first_name, :last_name, :email, :nickname)'
            );
            $insertRegistration = $this->database->prepare(
                'INSERT INTO event_attendees (attendee_id, event_id, ticket_number)
                 VALUES (:attendee_id, :event_id, :ticket_number)'
            );

            foreach ($rows as $row) {
                $rowNumber = (int) $row['row'];
                if (isset($row['error'])) {
                    $this->recordIssue($result, $rowNumber, $row['error'], true);
                    continue;
                }

                $findAttendee->execute(['email' => $row['email']]);
                $attendee = $findAttendee->fetch();
                if ($attendee !== false) {
                    $findRegistration->execute(['attendee_id' => $attendee['id'], 'event_id' => $eventId]);
                    $registration = $findRegistration->fetch();
                    if ($registration !== false) {
                        if ((string) $registration['ticket_number'] === $row['ticket']) {
                            $this->recordIssue($result, $rowNumber, 'This attendee is already registered for the selected event; this row was skipped.', false);
                        } else {
                            $this->recordIssue($result, $rowNumber, 'This email already has a different ticket for this event.', true);
                        }
                        continue;
                    }
                }

                $findTicket->execute(['ticket_number' => $row['ticket']]);
                if ($findTicket->fetchColumn() !== false) {
                    $this->recordIssue($result, $rowNumber, 'Ticket number is already assigned to another registration.', true);
                    continue;
                }

                if ($attendee === false) {
                    $insertAttendee->execute([
                        'first_name' => $row['first'],
                        'last_name' => $row['last'],
                        'email' => $row['email'],
                        'nickname' => $row['nickname'] === '' ? null : $row['nickname'],
                    ]);
                    $attendeeId = (int) $this->database->lastInsertId();
                } else {
                    $attendeeId = (int) $attendee['id'];
                }

                $insertRegistration->execute([
                    'attendee_id' => $attendeeId,
                    'event_id' => $eventId,
                    'ticket_number' => $row['ticket'],
                ]);
                $result['imported']++;
            }

            $batch = $this->database->prepare(
                'INSERT INTO import_batches
                    (event_id, imported_by_user_id, source_file_name, status, rows_seen, rows_imported, rows_skipped, rows_rejected)
                 VALUES (:event_id, :user_id, :file_name, :status, :rows_seen, :rows_imported, :rows_skipped, :rows_rejected)'
            );
            $batch->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
                'file_name' => $fileName,
                'status' => $result['rejected'] > 0 ? 'completed_with_errors' : 'completed',
                'rows_seen' => $result['rowsSeen'],
                'rows_imported' => $result['imported'],
                'rows_skipped' => $result['skipped'],
                'rows_rejected' => $result['rejected'],
            ]);

            $this->database->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function recordIssue(array &$result, int $rowNumber, string $message, bool $rejected): void
    {
        $key = $rejected ? 'rejected' : 'skipped';
        $result[$key]++;
        if (count($result['issues']) < 50) {
            $result['issues'][] = 'Row ' . $rowNumber . ': ' . $message;
        } else {
            $result['issuesTruncated'] = true;
        }
    }
}
