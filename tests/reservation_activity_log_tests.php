<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/reservation_helpers.php';

$pdo = db();
$facilityId = (int) $pdo->query('SELECT id FROM facilities ORDER BY id LIMIT 1')->fetchColumn();
if ($facilityId < 1) {
    throw new RuntimeException('Reservation activity tests require at least one facility.');
}

$pdo->beginTransaction();
try {
    $userInsert = $pdo->prepare(
        'INSERT INTO users (full_name, email, user_type, password_hash)
         VALUES (?, ?, ?, ?)'
    );
    $userInsert->execute([
        'Reservation Log Test',
        'reservation-log-' . bin2hex(random_bytes(8)) . '@example.invalid',
        'Faculty',
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $reservationCode = 'TEST-' . strtoupper(bin2hex(random_bytes(6)));
    $reservationDate = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $reservationInsert = $pdo->prepare(
        "INSERT INTO reservations
            (reservation_code, user_id, facility_id, event_type, reservation_date,
             start_time, end_time, participants, purpose, status)
         VALUES (?, ?, ?, 'Test event', ?, '08:00:00', '08:01:00', 1, 'Activity log test', 'APPROVED')"
    );
    $reservationInsert->execute([$reservationCode, $userId, $facilityId, $reservationDate]);
    $reservationId = (int) $pdo->lastInsertId();

    recordReservationActivity($pdo, $reservationId, 'booking_submitted', $userId, 'Test booking submitted.');
    recordReservationActivity($pdo, $reservationId, 'approved', null, 'Test booking approved.');
    recordCompletedReservationActivities($pdo);
    recordCompletedReservationActivities($pdo);

    $events = $pdo->prepare(
        'SELECT event_type FROM reservation_activity_logs
         WHERE reservation_id = ? ORDER BY id'
    );
    $events->execute([$reservationId]);
    $eventTypes = $events->fetchAll(PDO::FETCH_COLUMN);
    if ($eventTypes !== ['booking_submitted', 'approved', 'completed']) {
        throw new RuntimeException('Reservation events must be logged once and in the expected order.');
    }

    $availability = $pdo->prepare(
        "SELECT id FROM reservations
         WHERE id = ? AND status IN ('APPROVED', 'OCCUPIED')
           AND TIMESTAMP(reservation_date, end_time) > ?"
    );
    $availability->execute([$reservationId, (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    if ($availability->fetchColumn()) {
        throw new RuntimeException('Completed reservations must not remain in the availability feed.');
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

fwrite(STDOUT, "Reservation activity log tests passed.\n");
