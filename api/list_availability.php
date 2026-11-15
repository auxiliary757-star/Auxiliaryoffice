<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin(true);

try {
    $statement = db()->prepare("SELECT r.reservation_code AS id, f.name AS venue, r.reservation_date AS date, r.start_time AS startTime, r.end_time AS endTime, r.status
        FROM reservations r
        INNER JOIN facilities f ON f.id = r.facility_id
        WHERE r.status IN ('PENDING', 'APPROVED', 'OCCUPIED')
          AND TIMESTAMP(r.reservation_date, r.end_time) > ?
        ORDER BY r.reservation_date, r.start_time");
    $statement->execute([date('Y-m-d H:i:s')]);

    $availability = array_map(static function (array $row): array {
        return [
            'id' => $row['id'],
            'venue' => $row['venue'],
            'date' => $row['date'],
            'startTime' => substr($row['startTime'], 0, 5),
            'endTime' => substr($row['endTime'], 0, 5),
            'status' => $row['status'],
        ];
    }, $statement->fetchAll());

    jsonResponse($availability);
} catch (Throwable $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load venue availability.'], 500);
}