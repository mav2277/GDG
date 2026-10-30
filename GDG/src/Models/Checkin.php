<?php

declare(strict_types=1);

final class Checkin
{
    public function __construct(private PDO $database)
    {
    }

    /**
     * Marks a matching registration as checked in exactly once.
     * The conditional UPDATE is atomic, including when requests arrive together.
     * @return array{outcome:string, fullName?:string, checkedInAt?:string, registrationId?:int}
     */
    public function checkIn(int $eventId, string $ticketNumber): array
    {
        $registration = $this->findRegistration($eventId, $ticketNumber);
        if ($registration === null) {
            return ['outcome' => 'not_registered'];
        }

        $statement = $this->database->prepare(
            'UPDATE event_attendees
             SET checkin_status = 1, checked_in_at = CURRENT_TIMESTAMP
             WHERE id = :id AND event_id = :event_id AND COALESCE(checkin_status, 0) = 0'
        );
        $statement->execute(['id' => $registration['registration_id'], 'event_id' => $eventId]);

        $current = $this->findRegistrationById((int) $registration['registration_id']);
        if ($current === null) {
            return ['outcome' => 'not_registered'];
        }

        return [
            'outcome' => $statement->rowCount() === 1 ? 'checked_in' : 'already_checked_in',
            'fullName' => trim((string) $current['first_name'] . ' ' . (string) $current['last_name']),
            'registrationId' => (int) $registration['registration_id'],
            'checkedInAt' => $current['checked_in_at'] === null
                ? ''
                : str_replace(' ', 'T', (string) $current['checked_in_at']),
        ];
    }

    private function findRegistration(int $eventId, string $ticketNumber): ?array
    {
        $statement = $this->database->prepare(
            'SELECT ea.id AS registration_id
             FROM event_attendees AS ea
             INNER JOIN events AS e ON e.id = ea.event_id
             WHERE ea.event_id = :event_id AND ea.ticket_number = :ticket_number
             LIMIT 1'
        );
        $statement->execute(['event_id' => $eventId, 'ticket_number' => $ticketNumber]);
        $registration = $statement->fetch();
        return $registration === false ? null : $registration;
    }

    private function findRegistrationById(int $registrationId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT a.first_name, a.last_name, ea.checked_in_at
             FROM event_attendees AS ea
             INNER JOIN attendees AS a ON a.id = ea.attendee_id
             WHERE ea.id = :id AND ea.checkin_status = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $registrationId]);
        $record = $statement->fetch();
        return $record === false ? null : $record;
    }
}
