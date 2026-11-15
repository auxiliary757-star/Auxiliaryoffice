<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';
session_start();

requireMethod('POST');
requireRole('Administrator', true);
$payload = requestJson();
$code = trim((string) ($payload['id'] ?? ''));
$status = strtoupper(trim((string) ($payload['status'] ?? '')));

if ($code === '' || !in_array($status, ['APPROVED', 'REJECTED', 'OCCUPIED', 'CANCELLED'], true)) {
    jsonResponse(['success' => false, 'message' => 'A valid reservation and status are required.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $lookup = $pdo->prepare('SELECT facility_id FROM reservations WHERE reservation_code = ?');
    $lookup->execute([$code]);
    $reservationLookup = $lookup->fetch();
    if (!$reservationLookup) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
    }

    $facilityLock = $pdo->prepare('SELECT id FROM facilities WHERE id = ? FOR UPDATE');
    $facilityLock->execute([$reservationLookup['facility_id']]);
    $current = $pdo->prepare('SELECT * FROM reservations WHERE reservation_code = ? FOR UPDATE');
    $current->execute([$code]);
    $reservation = $current->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
    }

    if ($reservation['status'] === 'CANCELLED' && $status !== 'CANCELLED') {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'A cancelled reservation cannot be reopened.'], 409);
    }

    $statusChanged = $reservation['status'] !== $status;
    if (in_array($status, ['APPROVED', 'OCCUPIED'], true)) {
        $conflict = $pdo->prepare("SELECT status, TIMESTAMP(reservation_date, end_time) AS reservation_end
            FROM reservations
            WHERE facility_id = ? AND reservation_date = ? AND id <> ?
              AND status IN ('PENDING', 'APPROVED', 'OCCUPIED')
            FOR UPDATE");
        $conflict->execute([$reservation['facility_id'], $reservation['reservation_date'], $reservation['id']]);
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
        if ($hasActiveReservation) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Cannot approve this request because the venue already has an active reservation on that date.'], 409);
        }
    }

    $reviewerId = $_SESSION['user_id'] ?? null;
    if ($statusChanged) {
        $update = $pdo->prepare(
            "UPDATE reservations
             SET status = ?, reviewed_at = NOW(), reviewed_by = ?,
                 cancelled_at = IF(? = 'CANCELLED', NOW(), cancelled_at)
             WHERE id = ?"
        );
        $update->execute([$status, $reviewerId, $status, $reservation['id']]);
        $activityEvent = match ($status) {
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'OCCUPIED' => 'occupied',
            'CANCELLED' => 'cancelled',
        };
        recordReservationActivity(
            $pdo,
            (int) $reservation['id'],
            $activityEvent,
            $reviewerId === null ? null : (int) $reviewerId,
            'Reservation status changed to ' . strtolower($status) . '.'
        );
    }
    if ($statusChanged && $status === 'APPROVED') {
        queueReservationNotifications($pdo, (int) $reservation['id'], 'approval');
    } elseif ($statusChanged && $status === 'CANCELLED') {
        queueReservationNotifications($pdo, (int) $reservation['id'], 'cancellation');
    }
    $pdo->commit();
    jsonResponse(['success' => true, 'reservation' => reservationByCode($pdo, $code)]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to update reservation status.'], 500);
}
