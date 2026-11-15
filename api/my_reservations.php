<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';

$user = requireLogin(true);
try {
    $statement = db()->prepare(reservationSelect() . ' WHERE r.user_id = ? ORDER BY r.created_at DESC');
    $statement->execute([$user['id']]);
    jsonResponse(array_map('reservationRow', $statement->fetchAll()));
} catch (Throwable $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load your reservations.'], 500);
}
