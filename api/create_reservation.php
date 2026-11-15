<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

requireMethod('POST');
$user         = requireLogin(true);
$payload      = requestJson();
$venue        = trim((string) ($payload['venue'] ?? ''));
$eventType    = trim((string) ($payload['eventType'] ?? ''));
$date         = trim((string) ($payload['date'] ?? ''));
$startTime    = trim((string) ($payload['startTime'] ?? ''));
$endTime      = trim((string) ($payload['endTime'] ?? ''));
$name         = $user['name'];
$purpose      = trim((string) ($payload['purpose'] ?? ''));
$participants = filter_var($payload['participants'] ?? null, FILTER_VALIDATE_INT);

if ($venue === '' || $eventType === '' || $date === '' || $startTime === '' || $endTime === '' || $name === '' || $purpose === '' || $participants === false || $participants < 1) {
    jsonResponse(['success' => false, 'message' => 'Complete reservation details are required.'], 422);
}

if (strtotime("{$date} {$startTime}") === false || strtotime("{$date} {$endTime}") === false || $startTime >= $endTime) {
    jsonResponse(['success' => false, 'message' => 'The reservation time range is invalid.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $facilityStatement = $pdo->prepare('SELECT id FROM facilities WHERE name = ? LIMIT 1 FOR UPDATE');
    $facilityStatement->execute([$venue]);
    $facility = $facilityStatement->fetch();
    if (!$facility) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'The selected venue does not exist.'], 422);
    }

    $conflict = $pdo->prepare("SELECT status, TIMESTAMP(reservation_date, end_time) AS reservation_end
        FROM reservations
        WHERE facility_id = ? AND reservation_date = ?
          AND status IN ('PENDING', 'APPROVED', 'OCCUPIED')");
    $conflict->execute([$facility['id'], $date]);
    $hasActiveReservation = false;
    $now = new DateTimeImmutable('now');
    foreach ($conflict->fetchAll() as $existingReservation) {
        if (reservationBlocksVenueDate(
            (string) $existingReservation['status'],
            (string) $existingReservation['reservation_end'],
            $now
        )) {
            $hasActiveReservation = true;
            break;
        }
    }

    $code   = 'RES-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $status = $hasActiveReservation ? 'REJECTED' : 'PENDING';
    $rejectionReason = $hasActiveReservation
        ? 'Your request was not booked: this venue already has a pending or approved reservation on the selected date. Choose another date.'
        : null;
    $insert = $pdo->prepare('INSERT INTO reservations (reservation_code, user_id, facility_id, event_type, reservation_date, start_time, end_time, participants, purpose, status, rejection_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$code, $user['id'], $facility['id'], $eventType, $date, $startTime, $endTime, $participants, $purpose, $status, $rejectionReason]);
    $reservationId = (int) $pdo->lastInsertId();

    recordReservationActivity($pdo, $reservationId, 'booking_submitted', $user['id'], 'Reservation request submitted.');
    if ($hasActiveReservation) {
        recordReservationActivity(
            $pdo,
            $reservationId,
            'rejected',
            null,
            'Automatically rejected because the venue already has an active reservation on the selected date.'
        );
    }
    queueReservationNotifications(
        $pdo,
        $reservationId,
        $hasActiveReservation ? 'booking_conflict' : 'booking_confirmation'
    );
    $pdo->commit();
    jsonResponse([
        'success' => !$hasActiveReservation,
        'reservation' => reservationByCode($pdo, $code),
        'message' => $hasActiveReservation
            ? $rejectionReason
            : 'Reservation request submitted.',
    ], $hasActiveReservation ? 409 : 201);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to save the reservation.'], 500);
}
