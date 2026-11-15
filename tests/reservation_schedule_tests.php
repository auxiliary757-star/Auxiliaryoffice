<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/reservation_helpers.php';

function expectSchedule(bool $expected, bool $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

$now = new DateTimeImmutable('2026-11-15 10:00:00', new DateTimeZone('Asia/Manila'));
expectSchedule(
    true,
    reservationBlocksVenueDate('PENDING', '2026-11-15 17:00:00', $now),
    'A pending reservation must block its venue for the whole date.'
);
expectSchedule(
    true,
    reservationBlocksVenueDate('APPROVED', '2026-11-15 17:00:00', $now),
    'An approved reservation must block its venue until its end time.'
);
expectSchedule(
    false,
    reservationBlocksVenueDate('REJECTED', '2026-11-15 17:00:00', $now),
    'A rejected reservation must not block its venue.'
);
expectSchedule(
    false,
    reservationBlocksVenueDate('CANCELLED', '2026-11-15 17:00:00', $now),
    'A cancelled reservation must not block its venue.'
);
expectSchedule(
    false,
    reservationBlocksVenueDate('PENDING', '2026-11-15 09:59:59', $now),
    'An expired pending reservation must not block its venue.'
);
expectSchedule(
    true,
    reservationBlocksVenueDate('OCCUPIED', '2026-11-15 10:01:00', $now),
    'An occupied reservation must block its venue before its end time.'
);

fwrite(STDOUT, "Reservation schedule tests passed.\n");
