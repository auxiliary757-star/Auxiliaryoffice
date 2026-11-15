<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

requireMethod('POST');
$user    = requireLogin(true);
$payload = requestJson();
$code    = trim((string) ($payload['id'] ?? ''));

if ($code === '') {
    jsonResponse(['success' => false, 'message' => 'Reservation ID is required.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $select = $pdo->prepare(
        "SELECT id FROM reservations
         WHERE reservation_code = ? AND user_id = ? AND status IN ('PENDING', 'REJECTED')
         FOR UPDATE"
    );
    $select->execute([$code, $user['id']]);
    $reservation = $select->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Only your pending or rejected reservations can be cancelled.'], 409);
    }
    $update = $pdo->prepare("UPDATE reservations SET status = 'CANCELLED', cancelled_at = NOW() WHERE id = ?");
    $update->execute([$reservation['id']]);
    recordReservationActivity(
        $pdo,
        (int) $reservation['id'],
        'cancelled',
        (int) $user['id'],
        'Reservation cancelled by the requester.'
    );
    queueReservationNotifications($pdo, (int) $reservation['id'], 'cancellation');
    $pdo->commit();
    jsonResponse(['success' => true]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to cancel the reservation.'], 500);
}
