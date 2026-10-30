<?php

declare(strict_types=1);

final class Certificate
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function eligibleForEvent(int $eventId): array
    {
        $event = $this->database->prepare('SELECT id FROM events WHERE id = :id');
        $event->execute(['id' => $eventId]);
        if ($event->fetchColumn() === false) {
            throw new OutOfBoundsException('That event could not be found.');
        }

        $statement = $this->database->prepare(
            'SELECT ea.id AS registration_id, a.first_name, a.last_name, ea.checked_in_at,
                    COALESCE(attempts.attempt_count, 0) AS attempts,
                    latest.status AS latest_status
             FROM event_attendees AS ea
             INNER JOIN attendees AS a ON a.id = ea.attendee_id
             LEFT JOIN (
                 SELECT event_attendee_id, COUNT(*) AS attempt_count
                 FROM print_jobs
                 WHERE print_type = \'certificate\'
                 GROUP BY event_attendee_id
             ) AS attempts ON attempts.event_attendee_id = ea.id
             LEFT JOIN (
                 SELECT pj.event_attendee_id, pj.status
                 FROM print_jobs AS pj
                 INNER JOIN (
                     SELECT event_attendee_id, MAX(id) AS latest_id
                     FROM print_jobs
                     WHERE print_type = \'certificate\'
                     GROUP BY event_attendee_id
                 ) AS latest_job ON latest_job.latest_id = pj.id
             ) AS latest ON latest.event_attendee_id = ea.id
             WHERE ea.event_id = :event_id AND COALESCE(ea.checkin_status, 0) = 1
             ORDER BY ea.checked_in_at ASC, a.last_name ASC, a.first_name ASC, ea.id ASC'
        );
        $statement->execute(['event_id' => $eventId]);

        return array_map(static fn (array $row): array => [
            'registrationId' => (int) $row['registration_id'],
            'fullName' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'checkedInAt' => $row['checked_in_at'] === null ? null : (string) $row['checked_in_at'],
            'attempts' => (int) $row['attempts'],
            'status' => $row['latest_status'] === null ? 'not_printed' : (string) $row['latest_status'],
        ], $statement->fetchAll());
    }

    public function hasActiveLayout(int $eventId): bool
    {
        $statement = $this->database->prepare(
            'SELECT id FROM event_print_templates
             WHERE event_id = :event_id AND print_type = \'certificate\' AND is_active = 1
             ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['event_id' => $eventId]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array{outcome:string, queued:int, skipped:int} */
    public function queueAllFirstTime(int $eventId, int $userId): array
    {
        $this->database->beginTransaction();
        try {
            $event = $this->database->prepare('SELECT id FROM events WHERE id = :event_id FOR UPDATE');
            $event->execute(['event_id' => $eventId]);
            if ($event->fetchColumn() === false) {
                throw new OutOfBoundsException('That event could not be found.');
            }

            $template = $this->database->prepare(
                'SELECT id FROM event_print_templates
                 WHERE event_id = :event_id AND print_type = \'certificate\' AND is_active = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $template->execute(['event_id' => $eventId]);
            $templateId = $template->fetchColumn();
            if ($templateId === false) {
                $this->database->rollBack();
                return ['outcome' => 'layout_missing', 'queued' => 0, 'skipped' => 0];
            }

            $registrations = $this->database->prepare(
                'SELECT id FROM event_attendees
                 WHERE event_id = :event_id AND COALESCE(checkin_status, 0) = 1
                 FOR UPDATE'
            );
            $registrations->execute(['event_id' => $eventId]);
            $eligibleCount = 0;
            while ($registrations->fetch(PDO::FETCH_NUM) !== false) {
                $eligibleCount++;
            }

            $insert = $this->database->prepare(
                'INSERT INTO print_jobs (
                    event_attendee_id, template_id, print_type, status, is_reprint, requested_by_user_id
                 )
                 SELECT ea.id, :template_id, \'certificate\', \'queued\', 0, :user_id
                 FROM event_attendees AS ea
                 WHERE ea.event_id = :event_id AND COALESCE(ea.checkin_status, 0) = 1
                   AND NOT EXISTS (
                     SELECT 1 FROM print_jobs AS pj
                     WHERE pj.event_attendee_id = ea.id AND pj.print_type = \'certificate\'
                   )'
            );
            $insert->bindValue(':template_id', (int) $templateId, PDO::PARAM_INT);
            $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insert->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $insert->execute();
            $queuedCount = $insert->rowCount();
            $this->database->commit();

            return ['outcome' => 'queued', 'queued' => $queuedCount, 'skipped' => $eligibleCount - $queuedCount];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{outcome:string} */
    public function queue(int $eventId, int $registrationId, int $userId, bool $isReprint): array
    {
        $this->database->beginTransaction();
        try {
            $registration = $this->database->prepare(
                'SELECT id FROM event_attendees
                 WHERE id = :registration_id AND event_id = :event_id AND COALESCE(checkin_status, 0) = 1
                 FOR UPDATE'
            );
            $registration->execute(['registration_id' => $registrationId, 'event_id' => $eventId]);
            if ($registration->fetchColumn() === false) {
                $this->database->rollBack();
                return ['outcome' => 'not_eligible'];
            }

            $template = $this->database->prepare(
                'SELECT id FROM event_print_templates
                 WHERE event_id = :event_id AND print_type = \'certificate\' AND is_active = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $template->execute(['event_id' => $eventId]);
            $templateId = $template->fetchColumn();
            if ($templateId === false) {
                $this->database->rollBack();
                return ['outcome' => 'layout_missing'];
            }

            $history = $this->database->prepare(
                'SELECT COUNT(*) AS attempts,
                        MAX(CASE WHEN status IN (\'queued\', \'printing\') THEN 1 ELSE 0 END) AS pending
                 FROM print_jobs WHERE event_attendee_id = :registration_id AND print_type = \'certificate\''
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

            $insert = $this->database->prepare(
                'INSERT INTO print_jobs (event_attendee_id, template_id, print_type, status, is_reprint, requested_by_user_id)
                 VALUES (:registration_id, :template_id, \'certificate\', \'queued\', :is_reprint, :user_id)'
            );
            $insert->bindValue(':registration_id', $registrationId, PDO::PARAM_INT);
            $insert->bindValue(':template_id', (int) $templateId, PDO::PARAM_INT);
            $insert->bindValue(':is_reprint', $isReprint ? 1 : 0, PDO::PARAM_INT);
            $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insert->execute();
            $this->database->commit();

            return ['outcome' => 'queued'];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }
}
