<?php

declare(strict_types=1);

final class Event
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array<int, array{id:int, name:string, date:string, venue:string, registered:int, checkedIn:int}> */
    public function all(): array
    {
        $rows = $this->database->query(
            'SELECT e.id, e.event_name, e.event_date, e.venue,
                    COALESCE(attendance.registered, 0) AS registered,
                    COALESCE(attendance.checked_in, 0) AS checked_in
             FROM events AS e
             LEFT JOIN (
                 SELECT event_id, COUNT(*) AS registered,
                        SUM(CASE WHEN COALESCE(checkin_status, 0) = 1 THEN 1 ELSE 0 END) AS checked_in
                 FROM event_attendees
                 GROUP BY event_id
             ) AS attendance ON attendance.event_id = e.id
             ORDER BY e.event_date DESC, e.id DESC'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['event_name'],
            'date' => (string) $row['event_date'],
            'venue' => (string) ($row['venue'] ?? ''),
            'registered' => (int) $row['registered'],
            'checkedIn' => (int) $row['checked_in'],
        ], $rows);
    }

    /** @return array<int, array{id:int, name:string, date:string, venue:string}> */
    public function availableForStaffOn(string $date): array
    {
        $statement = $this->database->prepare(
            'SELECT id, event_name, event_date, venue
             FROM events
             WHERE event_date = :event_date
             ORDER BY event_name ASC, id ASC'
        );
        $statement->execute(['event_date' => $date]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['event_name'],
            'date' => (string) $row['event_date'],
            'venue' => (string) ($row['venue'] ?? ''),
        ], $statement->fetchAll());
    }

    public function isScheduledOn(int $eventId, string $date): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM events WHERE id = :id AND event_date = :event_date LIMIT 1'
        );
        $statement->execute(['id' => $eventId, 'event_date' => $date]);
        return $statement->fetchColumn() !== false;
    }

    public function create(string $name, string $date, string $venue): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO events (event_name, event_date, venue) VALUES (:name, :date, :venue)'
        );
        $statement->execute(['name' => $name, 'date' => $date, 'venue' => $venue]);
        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, string $name, string $date, string $venue): bool
    {
        $statement = $this->database->prepare(
            'UPDATE events SET event_name = :name, event_date = :date, venue = :venue WHERE id = :id'
        );
        $statement->execute(['name' => $name, 'date' => $date, 'venue' => $venue, 'id' => $id]);
        return $statement->rowCount() > 0 || $this->find($id) !== null;
    }

    public function delete(int $id): void
    {
        $statement = $this->database->prepare('DELETE FROM events WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            throw new OutOfBoundsException('That event no longer exists. Refresh the dashboard and try again.');
        }
    }

    private function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT id FROM events WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }
}
