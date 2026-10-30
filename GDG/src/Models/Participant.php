<?php

declare(strict_types=1);

final class Participant
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array{registered:int, checkedIn:int, rows:array<int, array<string, mixed>>, page:int, pageSize:int, totalRows:int} */
    public function forEvent(int $eventId, string $search, string $status, int $page, int $pageSize = 50): array
    {
        $event = $this->database->prepare('SELECT id FROM events WHERE id = :id');
        $event->execute(['id' => $eventId]);
        if ($event->fetchColumn() === false) {
            throw new OutOfBoundsException('That event could not be found.');
        }

        $totals = $this->database->prepare(
            'SELECT COUNT(*) AS registered,
                    COALESCE(SUM(CASE WHEN COALESCE(checkin_status, 0) = 1 THEN 1 ELSE 0 END), 0) AS checked_in
             FROM event_attendees WHERE event_id = :event_id'
        );
        $totals->execute(['event_id' => $eventId]);
        $counts = $totals->fetch();

        $where = ['ea.event_id = :event_id'];
        $parameters = ['event_id' => $eventId];
        if ($status === 'checked-in') {
            $where[] = 'COALESCE(ea.checkin_status, 0) = 1';
        } elseif ($status === 'not-checked-in') {
            $where[] = 'COALESCE(ea.checkin_status, 0) = 0';
        }
        if ($search !== '') {
            $where[] = "(CONCAT_WS(' ', a.first_name, a.last_name) LIKE :search_name ESCAPE '='
                        OR COALESCE(a.nickname, '') LIKE :search_nickname ESCAPE '='
                        OR ea.ticket_number LIKE :search_ticket ESCAPE '=')";
            $term = '%' . strtr($search, ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
            $parameters['search_name'] = $term;
            $parameters['search_nickname'] = $term;
            $parameters['search_ticket'] = $term;
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->database->prepare(
            'SELECT COUNT(*) FROM event_attendees AS ea
             INNER JOIN attendees AS a ON a.id = ea.attendee_id
             WHERE ' . $whereSql
        );
        $count->execute($parameters);
        $totalRows = (int) $count->fetchColumn();
        $page = max(1, min($page, max(1, (int) ceil($totalRows / $pageSize))));
        $offset = ($page - 1) * $pageSize;

        $rowsStatement = $this->database->prepare(
            'SELECT a.first_name, a.last_name, a.nickname, ea.ticket_number,
                    COALESCE(ea.checkin_status, 0) AS checkin_status, ea.checked_in_at
             FROM event_attendees AS ea
             INNER JOIN attendees AS a ON a.id = ea.attendee_id
             WHERE ' . $whereSql . '
             ORDER BY a.last_name ASC, a.first_name ASC, ea.id ASC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $rowsStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $rowsStatement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $rowsStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rowsStatement->execute();

        $rows = array_map(static fn (array $row): array => [
            'fullName' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'ticketNumber' => (string) $row['ticket_number'],
            'checkedIn' => (int) $row['checkin_status'] === 1,
            'checkedInAt' => $row['checked_in_at'] === null ? null : (string) $row['checked_in_at'],
        ], $rowsStatement->fetchAll());

        return [
            'registered' => (int) $counts['registered'],
            'checkedIn' => (int) $counts['checked_in'],
            'rows' => $rows,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalRows' => $totalRows,
        ];
    }
}
