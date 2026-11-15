<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/notifications/PhoneNumber.php';
require_once __DIR__ . '/../services/notifications/Templates.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectSame('+639171234567', normalizePhilippinePhone('0917 123 4567'), 'Local number normalization');
expectSame('+639171234567', normalizePhilippinePhone('+639171234567'), 'E.164 number normalization');
expectSame('639171234567', normalizePhilippinePhone('639171234567', 'semaphore'), 'Semaphore number normalization');
try {
    normalizePhilippinePhone('12345');
    throw new RuntimeException('Invalid phone number was accepted.');
} catch (InvalidArgumentException $error) {
    expectTrue(str_contains($error->getMessage(), 'Philippine'), 'Invalid phone error is informative');
}

$reservation = [
    'reservation_code' => 'RES-2026-ABC123',
    'name' => 'Test User',
    'venue' => 'University Auditorium',
    'reservation_date' => '2026-11-15',
    'start_time' => '08:00:00',
    'end_time' => '10:00:00',
    'status' => 'APPROVED',
];
foreach (['booking_confirmation', 'booking_conflict', 'approval', 'reminder', 'cancellation'] as $event) {
    $eventReservation = $reservation;
    if ($event === 'booking_conflict') {
        $eventReservation['status'] = 'REJECTED';
    }
    $template = notificationTemplate($event, $eventReservation);
    $expectedValues = ['RES-2026-ABC123', 'Test User', '2026-11-15', 'University Auditorium', $eventReservation['status']];
    foreach ($expectedValues as $value) {
        expectTrue(str_contains($template['text'], $value), "{$event} text includes {$value}");
        expectTrue(str_contains($template['html'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), "{$event} HTML includes {$value}");
    }
    if ($event === 'booking_conflict') {
        expectTrue(str_contains($template['text'], 'was not booked'), 'Conflict notice must state that the request was not booked');
    }
    expectTrue($template['subject'] !== '' && $template['html'] !== '' && $template['text'] !== '', "{$event} has both email formats");
    expectTrue(strlen(notificationSmsTemplate($event, $eventReservation)) < 160, "{$event} SMS stays under 160 characters for a normal booking");
}

$now = new DateTimeImmutable('2026-11-14 08:00:00', new DateTimeZone('Asia/Manila'));
expectTrue(reminderIsEligible('APPROVED', '2026-11-15 07:00:00', $now, 24, false), 'Upcoming approved reservation is eligible');
expectTrue(!reminderIsEligible('APPROVED', '2026-11-15 07:00:00', $now, 24, true), 'Existing reminder prevents duplicates');
expectTrue(!reminderIsEligible('CANCELLED', '2026-11-15 07:00:00', $now, 24, false), 'Cancelled reservation is skipped');
expectTrue(!reminderIsEligible('APPROVED', '2026-11-16 08:01:00', $now, 24, false), 'Reservation outside the reminder window is skipped');

fwrite(STDOUT, "Notification tests passed.\n");
