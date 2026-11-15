<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';

try {
    requireRole('Administrator', true);
    $statement = db()->query(reservationSelect() . ' ORDER BY r.created_at DESC');
    jsonResponse(array_map('reservationRow', $statement->fetchAll()));
} catch (Throwable $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load reservations.'], 500);
}
