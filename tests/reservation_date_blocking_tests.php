<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/reservation_helpers.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

$pdo = db();
$facilityId = (int) $pdo->query('SELECT id FROM facilities ORDER BY id LIMIT 1')->fetchColumn();
if ($facilityId < 1) {
    throw new RuntimeException('Reservation date-blocking tests require at least one facility.');
}

$pdo->beginTransaction();
try {
    $userInsert = $pdo->prepare(
        'INSERT INTO users (full_name, email, user_type, password_hash)
         VALUES (?, ?, ?, ?)'
    );
    $userInsert->execute([
        'Reservation Date Test',
        'reservation-date-' . bin2hex(random_bytes(8)) . '@example.invalid',
        'Faculty',
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $reservationDate = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
    $insert = $pdo->prepare(
        "INSERT INTO reservations
            (reservation_code, user_id, facility_id, event_type, reservation_date,
             start_time, end_time, participants, purpose, status)
         VALUES (?, ?, ?, 'Test event', ?, '08:00:00', '17:00:00', 1, 'Date blocking test', 'PENDING')"
    );
    $insert->execute([
        'DATE-' . strtoupper(bin2hex(random_bytes(6))),
        $userId,
        $facilityId,
        $reservationDate,
    ]);
    $reservationId = (int) $pdo->lastInsertId();

    $blockers = $pdo->prepare(
        "SELECT id, status, TIMESTAMP(reservation_date, end_time) AS reservation_end
         FROM reservations
         WHERE facility_id = ? AND reservation_date = ?
           AND status IN ('PENDING', 'APPROVED', 'OCCUPIED')"
    );
    $blockers->execute([$facilityId, $reservationDate]);
    $blockingReservations = $blockers->fetchAll();
    $fixtureReservationBlocksDate = false;
    foreach ($blockingReservations as $blockingReservation) {
        if ((int) $blockingReservation['id'] === $reservationId) {
            $fixtureReservationBlocksDate = reservationBlocksVenueDate(
                (string) $blockingReservation['status'],
                (string) $blockingReservation['reservation_end'],
                new DateTimeImmutable('now')
            );
            break;
        }
    }
    if (!$fixtureReservationBlocksDate) {
        throw new RuntimeException('A pending reservation must block its venue for the entire date.');
    }

    $availability = $pdo->prepare(
        "SELECT id FROM reservations
         WHERE id = ? AND status IN ('PENDING', 'APPROVED', 'OCCUPIED')
           AND TIMESTAMP(reservation_date, end_time) > ?"
    );
    $availability->execute([$reservationId, (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    if (!$availability->fetchColumn()) {
        throw new RuntimeException('Pending reservations must appear in the shared availability feed.');
    }

    $rejectedInsert = $pdo->prepare(
        "INSERT INTO reservations
            (reservation_code, user_id, facility_id, event_type, reservation_date,
             start_time, end_time, participants, purpose, status, rejection_reason)
         VALUES (?, ?, ?, 'Test event', ?, '19:00:00', '20:00:00', 1, 'Conflict notice test', 'REJECTED', 'Date already reserved')"
    );
    $rejectedInsert->execute([
        'DATE-' . strtoupper(bin2hex(random_bytes(6))),
        $userId,
        $facilityId,
        $reservationDate,
    ]);
    $rejectedReservationId = (int) $pdo->lastInsertId();
    queueReservationNotifications($pdo, $rejectedReservationId, 'booking_conflict');
    $noticeJob = $pdo->prepare(
        "SELECT event_type FROM notification_jobs
         WHERE reservation_id = ? AND channel = 'email'"
    );
    $noticeJob->execute([$rejectedReservationId]);
    if ($noticeJob->fetchColumn() !== 'booking_conflict') {
        throw new RuntimeException('A rejected same-date request must queue an email conflict notice.');
    }

    $cancel = $pdo->prepare("UPDATE reservations SET status = 'CANCELLED' WHERE id = ?");
    $cancel->execute([$reservationId]);
    $blockers->execute([$facilityId, $reservationDate]);
    foreach ($blockers->fetchAll() as $blockingReservation) {
        if ((int) $blockingReservation['id'] === $reservationId) {
            throw new RuntimeException('A cancelled reservation must release its venue date immediately.');
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

fwrite(STDOUT, "Reservation date-blocking tests passed.\n");
