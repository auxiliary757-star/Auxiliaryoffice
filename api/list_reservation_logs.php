<?php
declare(strict_types=1);

require_once __DIR__ . '/reservation_helpers.php';
require_once __DIR__ . '/../config/auth.php';

try {
    requireRole('Administrator', true);
    $pdo = db();
    recordCompletedReservationActivities($pdo);

    $statement = $pdo->query(
        'SELECT l.id, l.event_type, l.details, l.created_at,
                r.reservation_code, r.reservation_date, r.start_time, r.end_time, r.purpose,
                f.name AS venue, COALESCE(requester.full_name, \'Deleted account\') AS requester,
                COALESCE(actor.full_name, IF(l.actor_user_id IS NULL, \'System\', \'Deleted account\')) AS actor
         FROM reservation_activity_logs l
         INNER JOIN reservations r ON r.id = l.reservation_id
         INNER JOIN facilities f ON f.id = r.facility_id
         LEFT JOIN users requester ON requester.id = r.user_id
         LEFT JOIN users actor ON actor.id = l.actor_user_id
         ORDER BY l.created_at DESC, l.id DESC'
    );
    jsonResponse($statement->fetchAll());
} catch (Throwable $error) {
    error_log('Unable to load reservation activity logs: ' . $error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load reservation activity logs.'], 500);
}
