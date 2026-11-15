<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

if (PHP_SAPI !== 'cli' || $argc !== 4) {
    fwrite(STDERR, "Usage: php bin/test_notifications.php <email|sms> <recipient> <booking_confirmation|booking_conflict|approval|reminder|cancellation>\n");
    exit(2);
}

[$script, $channel, $recipient, $event] = $argv;
if (!in_array($channel, ['email', 'sms'], true)) {
    throw new InvalidArgumentException('Channel must be email or sms.');
}
$reservation = [
    'reservation_code' => 'RES-TEST-000001',
    'name' => 'Notification Test',
    'venue' => 'University Auditorium',
    'reservation_date' => date('Y-m-d'),
    'start_time' => '08:00:00',
    'end_time' => '09:00:00',
    'status' => $event === 'cancellation'
        ? 'CANCELLED'
        : ($event === 'booking_conflict' ? 'REJECTED' : (in_array($event, ['approval', 'reminder'], true) ? 'APPROVED' : 'PENDING')),
];

if ($channel === 'email') {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid recipient email address.');
    }
    sendNotificationJob([
        'event_type' => $event,
        'channel' => 'email',
        'recipient' => $recipient,
        'payload_json' => json_encode($reservation, JSON_THROW_ON_ERROR),
    ]);
} else {
    sendNotificationJob([
        'event_type' => $event,
        'channel' => 'sms',
        'recipient' => $recipient,
        'payload_json' => json_encode($reservation, JSON_THROW_ON_ERROR),
    ]);
}

fwrite(STDOUT, "Test {$channel} notification processed in " . (filter_var(getenv('NOTIFICATIONS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN) ? 'send' : 'dev-log') . " mode.\n");
