<?php
declare(strict_types=1);

require_once __DIR__ . '/PhoneNumber.php';
require_once __DIR__ . '/Templates.php';
require_once __DIR__ . '/SmsProviderInterface.php';
require_once __DIR__ . '/SemaphoreProvider.php';
require_once __DIR__ . '/TwilioProvider.php';
require_once __DIR__ . '/../email/SmtpMailer.php';

function queueReservationNotifications(PDO $pdo, int $reservationId, string $event): void
{
    try {
        $allowedEvents = ['booking_confirmation', 'booking_conflict', 'approval', 'reminder', 'cancellation'];
        if (!in_array($event, $allowedEvents, true)) {
            throw new InvalidArgumentException('Unsupported notification event.');
        }

        $statement = $pdo->prepare(
            'SELECT r.id, r.reservation_code, r.reservation_date, r.start_time, r.end_time, r.status,
                    f.name AS venue, u.full_name AS name, u.email, u.phone
             FROM reservations r
             INNER JOIN facilities f ON f.id = r.facility_id
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.id = ? LIMIT 1'
        );
        $statement->execute([$reservationId]);
        $reservation = $statement->fetch();
        if (!$reservation) {
            throw new RuntimeException('Reservation not found while queuing notifications.');
        }

        $payload = json_encode([
            'reservation_code' => $reservation['reservation_code'],
            'reservation_date' => $reservation['reservation_date'],
            'start_time' => $reservation['start_time'],
            'end_time' => $reservation['end_time'],
            'status' => $reservation['status'],
            'venue' => $reservation['venue'],
            'name' => $reservation['name'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO notification_jobs
                (reservation_id, event_type, channel, recipient, dedupe_key, payload_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $reservationId, $event, 'email', $reservation['email'],
            "{$event}:{$reservationId}:email", $payload,
        ]);
        $phone = trim((string) ($reservation['phone'] ?? ''));
        if ($phone !== '') {
            $insert->execute([
                $reservationId, $event, 'sms', $phone,
                "{$event}:{$reservationId}:sms", $payload,
            ]);
        }
    } catch (Throwable $error) {
        error_log('Unable to queue reservation notification: ' . $error->getMessage());
    }
}

function reminderIsEligible(
    string $status,
    string $reservationStart,
    DateTimeImmutable $now,
    int $windowHours,
    bool $alreadyQueued
): bool {
    if ($status !== 'APPROVED' || $windowHours < 1 || $alreadyQueued) {
        return false;
    }
    $start = new DateTimeImmutable($reservationStart, $now->getTimezone());
    return $start >= $now && $start <= $now->modify("+{$windowHours} hours");
}

function queueDueReservationReminders(PDO $pdo, ?DateTimeImmutable $now = null): int
{
    $now = $now ?: new DateTimeImmutable('now');
    $windowHours = max(1, (int) (getenv('REMINDER_WINDOW_HOURS') ?: 24));
    $start = $now->format('Y-m-d H:i:s');
    $end = $now->modify("+{$windowHours} hours")->format('Y-m-d H:i:s');

    $statement = $pdo->prepare(
        "SELECT r.id, r.status, TIMESTAMP(r.reservation_date, r.start_time) AS reservation_start
         FROM reservations r
         WHERE r.status = 'APPROVED'
           AND TIMESTAMP(r.reservation_date, r.start_time) BETWEEN ? AND ?
           AND NOT EXISTS (
               SELECT 1 FROM notification_jobs j
               WHERE j.reservation_id = r.id AND j.event_type = 'reminder'
           )
         ORDER BY r.reservation_date, r.start_time"
    );
    $statement->execute([$start, $end]);
    $queued = 0;
    foreach ($statement->fetchAll() as $reservation) {
        if (reminderIsEligible(
            (string) $reservation['status'],
            (string) $reservation['reservation_start'],
            $now,
            $windowHours,
            false
        )) {
            queueReservationNotifications($pdo, (int) $reservation['id'], 'reminder');
            $queued++;
        }
    }
    return $queued;
}

function processNotificationQueue(PDO $pdo, int $limit = 20): int
{
    $limit = max(1, min(100, $limit));
    recoverStalledNotificationJobs($pdo);
    $statement = $pdo->prepare(
        "SELECT id FROM notification_jobs
         WHERE status = 'QUEUED' AND available_at <= NOW()
         ORDER BY id LIMIT {$limit}"
    );
    $statement->execute();
    $processed = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $jobId) {
        processNotificationJob($pdo, (int) $jobId);
        $processed++;
    }
    return $processed;
}

function recoverStalledNotificationJobs(PDO $pdo): void
{
    $maxAttempts = max(1, (int) (getenv('NOTIFICATION_MAX_ATTEMPTS') ?: 5));
    $pdo->beginTransaction();
    try {
        $statement = $pdo->query(
            "SELECT id, event_type, channel, recipient, attempts
             FROM notification_jobs
             WHERE status = 'PROCESSING' AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             FOR UPDATE"
        );
        $stalledJobs = $statement->fetchAll();
        $log = $pdo->prepare(
            "INSERT INTO notification_logs (notification_job_id, event_type, channel, recipient, status, error)
             VALUES (?, ?, ?, ?, 'FAILED', ?)"
        );
        foreach ($stalledJobs as $job) {
            $log->execute([
                $job['id'],
                $job['event_type'],
                $job['channel'],
                $job['recipient'],
                'Worker stopped before recording the delivery result.',
            ]);
            $status = (int) $job['attempts'] >= $maxAttempts ? 'FAILED' : 'QUEUED';
            if ($status === 'QUEUED') {
                $baseSeconds = max(1, (int) (getenv('NOTIFICATION_RETRY_BASE_SECONDS') ?: 60));
                $delay = min(3600, $baseSeconds * (2 ** min(10, (int) $job['attempts'] - 1)));
                $update = $pdo->prepare(
                    "UPDATE notification_jobs SET status = ?,
                        available_at = DATE_ADD(NOW(), INTERVAL {$delay} SECOND)
                     WHERE id = ?"
                );
            } else {
                $update = $pdo->prepare('UPDATE notification_jobs SET status = ? WHERE id = ?');
            }
            $update->execute([$status, $job['id']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function processNotificationJob(PDO $pdo, int $jobId): void
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM notification_jobs WHERE id = ? FOR UPDATE');
        $statement->execute([$jobId]);
        $job = $statement->fetch();
        if (!$job || $job['status'] !== 'QUEUED') {
            $pdo->commit();
            return;
        }
        $attempt = (int) $job['attempts'] + 1;
        $claim = $pdo->prepare("UPDATE notification_jobs SET status = 'PROCESSING', attempts = ? WHERE id = ?");
        $claim->execute([$attempt, $jobId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $errorMessage = null;
    try {
        sendNotificationJob($job);
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
    }

    try {
        $pdo->beginTransaction();
        $log = $pdo->prepare(
            'INSERT INTO notification_logs (notification_job_id, event_type, channel, recipient, status, error)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $sent = $errorMessage === null;
        $log->execute([
            $jobId, $job['event_type'], $job['channel'], $job['recipient'],
            $sent
                ? (filter_var(getenv('NOTIFICATIONS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN) ? 'SENT' : 'LOGGED')
                : 'FAILED',
            $errorMessage,
        ]);

        if ($sent) {
            $update = $pdo->prepare("UPDATE notification_jobs SET status = 'SENT' WHERE id = ?");
            $update->execute([$jobId]);
        } else {
            $maxAttempts = max(1, (int) (getenv('NOTIFICATION_MAX_ATTEMPTS') ?: 5));
            if ($attempt >= $maxAttempts) {
                $update = $pdo->prepare("UPDATE notification_jobs SET status = 'FAILED' WHERE id = ?");
                $update->execute([$jobId]);
            } else {
                $baseSeconds = max(1, (int) (getenv('NOTIFICATION_RETRY_BASE_SECONDS') ?: 60));
                $delay = min(3600, $baseSeconds * (2 ** min(10, $attempt - 1)));
                $update = $pdo->prepare(
                    "UPDATE notification_jobs SET status = 'QUEUED',
                        available_at = DATE_ADD(NOW(), INTERVAL {$delay} SECOND)
                     WHERE id = ?"
                );
                $update->execute([$jobId]);
            }
        }
        $pdo->commit();
        if ($errorMessage !== null) {
            error_log("Notification job {$jobId} failed (attempt {$attempt}): {$errorMessage}");
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Unable to record notification attempt for job {$jobId}: " . $error->getMessage());
        throw $error;
    }
}

function sendNotificationJob(array $job): void
{
    $reservation = json_decode((string) $job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($reservation)) {
        throw new RuntimeException('Notification payload is invalid.');
    }

    $enabled = filter_var(getenv('NOTIFICATIONS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    if ($job['channel'] === 'email') {
        $template = notificationTemplate((string) $job['event_type'], $reservation);
        if (!$enabled) {
            error_log('NOTIFICATION DEV MODE email to ' . $job['recipient'] . ': ' . $template['text']);
            return;
        }
        sendNotificationEmail((string) $job['recipient'], $template);
        return;
    }
    if ($job['channel'] !== 'sms') {
        throw new RuntimeException('Unsupported notification channel.');
    }

    $providerName = strtolower(getenv('SMS_PROVIDER') ?: 'semaphore');
    $provider = match ($providerName) {
        'semaphore' => new SemaphoreProvider(),
        'twilio' => new TwilioProvider(),
        default => throw new RuntimeException('SMS_PROVIDER must be semaphore or twilio.'),
    };
    normalizePhilippinePhone((string) $job['recipient'], $providerName);
    $message = notificationSmsTemplate((string) $job['event_type'], $reservation);
    if (!$enabled) {
        error_log('NOTIFICATION DEV MODE sms to ' . $job['recipient'] . ': ' . $message);
        return;
    }
    $provider->send((string) $job['recipient'], $message);
}

function sendNotificationEmail(string $recipient, array $template): void
{
    sendSmtpEmail(
        $recipient,
        (string) $template['subject'],
        (string) $template['html'],
        (string) $template['text']
    );
}
