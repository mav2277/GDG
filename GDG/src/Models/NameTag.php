<?php

declare(strict_types=1);

final class NameTag
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function forEvent(int $eventId): array
    {
        $event = $this->database->prepare('SELECT id FROM events WHERE id = :id');
        $event->execute(['id' => $eventId]);
        if ($event->fetchColumn() === false) {
            throw new OutOfBoundsException('That event could not be found.');
        }

        $statement = $this->database->prepare(
            'SELECT ea.id AS registration_id, a.first_name, a.last_name, a.nickname,
                    COALESCE(attempts.attempt_count, 0) AS attempts,
                    latest.status AS latest_status, latest.created_at AS latest_created_at
             FROM event_attendees AS ea
             INNER JOIN attendees AS a ON a.id = ea.attendee_id
             LEFT JOIN (
                 SELECT event_attendee_id, COUNT(*) AS attempt_count
                 FROM print_jobs
                 WHERE print_type = \'name_tag\'
                 GROUP BY event_attendee_id
             ) AS attempts ON attempts.event_attendee_id = ea.id
             LEFT JOIN (
                 SELECT pj.event_attendee_id, pj.status, pj.created_at
                 FROM print_jobs AS pj
                 INNER JOIN (
                     SELECT event_attendee_id, MAX(id) AS latest_id
                     FROM print_jobs
                     WHERE print_type = \'name_tag\'
                     GROUP BY event_attendee_id
                 ) AS latest_job ON latest_job.latest_id = pj.id
             ) AS latest ON latest.event_attendee_id = ea.id
             WHERE ea.event_id = :event_id AND COALESCE(ea.checkin_status, 0) = 1
             ORDER BY a.last_name ASC, a.first_name ASC, ea.id ASC'
        );
        $statement->execute(['event_id' => $eventId]);

        return array_map(static fn (array $row): array => [
            'registrationId' => (int) $row['registration_id'],
            'fullName' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'attempts' => (int) $row['attempts'],
            'status' => $row['latest_status'] === null ? 'not_printed' : (string) $row['latest_status'],
            'lastRequestedAt' => $row['latest_created_at'] === null ? null : (string) $row['latest_created_at'],
        ], $statement->fetchAll());
    }

    /**
     * Add a print request. A queued/active request cannot be duplicated, and a
     * reprint must follow an existing request. This records queue intent only.
     * @return array{outcome:string, status?:string}
     */
    public function queue(int $eventId, int $registrationId, int $userId, bool $isReprint): array
    {
        $this->database->beginTransaction();
        try {
            $registration = $this->database->prepare(
                'SELECT id, COALESCE(checkin_status, 0) AS checkin_status FROM event_attendees
                 WHERE id = :registration_id AND event_id = :event_id FOR UPDATE'
            );
            $registration->execute(['registration_id' => $registrationId, 'event_id' => $eventId]);
            $registrationRow = $registration->fetch();
            if ($registrationRow === false) {
                $this->database->rollBack();
                return ['outcome' => 'not_registered'];
            }
            if ((int) $registrationRow['checkin_status'] !== 1) {
                $this->database->rollBack();
                return ['outcome' => 'not_checked_in'];
            }

            $history = $this->database->prepare(
                'SELECT COUNT(*) AS attempts,
                        MAX(CASE WHEN status IN (\'queued\', \'printing\') THEN 1 ELSE 0 END) AS pending
                 FROM print_jobs WHERE event_attendee_id = :registration_id AND print_type = \'name_tag\''
            );
            $history->execute(['registration_id' => $registrationId]);
            $state = $history->fetch();
            $attempts = (int) $state['attempts'];
            if ((int) $state['pending'] === 1) {
                $this->database->rollBack();
                return ['outcome' => 'pending'];
            }
            if (($isReprint && $attempts === 0) || (!$isReprint && $attempts > 0)) {
                $this->database->rollBack();
                return ['outcome' => 'action_out_of_date'];
            }

            $template = $this->database->prepare(
                'SELECT id FROM event_print_templates
                 WHERE event_id = :event_id AND print_type = \'name_tag\' AND is_active = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $template->execute(['event_id' => $eventId]);
            $templateId = $template->fetchColumn();

            $insert = $this->database->prepare(
                'INSERT INTO print_jobs (event_attendee_id, template_id, print_type, status, is_reprint, requested_by_user_id)
                 VALUES (:registration_id, :template_id, \'name_tag\', \'queued\', :is_reprint, :user_id)'
            );
            $insert->bindValue(':registration_id', $registrationId, PDO::PARAM_INT);
            $insert->bindValue(':template_id', $templateId === false ? null : (int) $templateId, $templateId === false ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insert->bindValue(':is_reprint', $isReprint ? 1 : 0, PDO::PARAM_INT);
            $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insert->execute();
            $this->database->commit();

            return ['outcome' => 'queued', 'status' => 'queued'];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    /** Queue one initial request per registration with no name-tag history. */
    public function queueAllFirstTime(int $eventId, int $userId): array
    {
        $this->database->beginTransaction();
        try {
            $event = $this->database->prepare('SELECT id FROM events WHERE id = :id FOR UPDATE');
            $event->execute(['id' => $eventId]);
            if ($event->fetchColumn() === false) {
                $this->database->rollBack();
                return ['outcome' => 'event_not_found'];
            }

            $registrations = $this->database->prepare(
                'SELECT id, COALESCE(checkin_status, 0) AS checkin_status FROM event_attendees WHERE event_id = :event_id FOR UPDATE'
            );
            $registrations->execute(['event_id' => $eventId]);
            $registrationRows = $registrations->fetchAll();
            $eligibleCount = count(array_filter($registrationRows, static fn (array $row): bool => (int) $row['checkin_status'] === 1));
            $template = $this->database->prepare(
                'SELECT id FROM event_print_templates WHERE event_id = :event_id AND print_type = \'name_tag\' AND is_active = 1 ORDER BY id DESC LIMIT 1'
            );
            $template->execute(['event_id' => $eventId]);
            $templateId = $template->fetchColumn();
            $inserted = $this->database->prepare(
                'INSERT INTO print_jobs (event_attendee_id, template_id, print_type, status, is_reprint, requested_by_user_id)
                 SELECT ea.id, :template_id, \'name_tag\', \'queued\', 0, :user_id
                 FROM event_attendees AS ea
                 WHERE ea.event_id = :event_id AND COALESCE(ea.checkin_status, 0) = 1
                   AND NOT EXISTS (SELECT 1 FROM print_jobs AS pj WHERE pj.event_attendee_id = ea.id AND pj.print_type = \'name_tag\')'
            );
            $inserted->bindValue(':template_id', $templateId === false ? null : (int) $templateId, $templateId === false ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $inserted->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $inserted->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $inserted->execute();
            $queued = $inserted->rowCount();
            $this->database->commit();
            return ['outcome' => 'queued', 'queued' => $queued, 'skipped' => $eligibleCount - $queued];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }
}
