<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function reservationBlocksVenueDate(
    string $status,
    string $reservationEnd,
    DateTimeImmutable $now
): bool {
    if (!in_array($status, ['PENDING', 'APPROVED', 'OCCUPIED'], true)) {
        return false;
    }

    return new DateTimeImmutable($reservationEnd, $now->getTimezone()) > $now;
}

function recordReservationActivity(
    PDO $pdo,
    int $reservationId,
    string $eventType,
    ?int $actorUserId,
    string $details
): void {
    $allowedEvents = ['booking_submitted', 'approved', 'rejected', 'cancelled', 'occupied'];
    if (!in_array($eventType, $allowedEvents, true)) {
        throw new InvalidArgumentException('Unsupported reservation activity event.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO reservation_activity_logs (reservation_id, event_type, actor_user_id, details)
         VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$reservationId, $eventType, $actorUserId, $details]);
}

function recordCompletedReservationActivities(PDO $pdo): void
{
    $statement = $pdo->prepare(
        "INSERT INTO reservation_activity_logs
            (reservation_id, event_type, actor_user_id, details, source_key, created_at)
         SELECT r.id, 'completed', NULL, 'Reservation ended, venue released',
                CONCAT('completed:', r.id), TIMESTAMP(r.reservation_date, r.end_time)
         FROM reservations r
         WHERE r.status IN ('APPROVED', 'OCCUPIED')
           AND TIMESTAMP(r.reservation_date, r.end_time) <= ?
         ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key"
    );
    $statement->execute([(new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
}

function reservationRow(array $row): array
{
    return [
        'id' => $row['reservation_code'],
        'database_id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'venue' => $row['venue'],
        'imageUrl' => $row['image'],
        'venueImage' => $row['image'],
        'eventType' => $row['event_type'],
        'date' => $row['reservation_date'],
        'startTime' => substr($row['start_time'], 0, 5),
        'endTime' => substr($row['end_time'], 0, 5),
        'participants' => (int) $row['participants'],
        'reservedBy' => $row['requester'],
        'requester' => $row['requester'],
        'userType' => $row['user_type'],
        'purpose' => $row['purpose'],
        'status' => $row['status'],
        'rejectionReason' => $row['rejection_reason'],
        'submittedAt' => $row['created_at'],
        'reviewedAt' => $row['reviewed_at'],
        'reviewedBy' => $row['reviewer'],
        'equipment' => $row['equipment'] ?: 'See reservation notes',
    ];
}

function reservationSelect(): string
{
    return 'SELECT r.*, f.name AS venue, f.image, f.equipment, u.full_name AS requester, u.user_type, reviewer.full_name AS reviewer
            FROM reservations r
            INNER JOIN facilities f ON f.id = r.facility_id
            INNER JOIN users u ON u.id = r.user_id
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by';
}

function reservationByCode(PDO $pdo, string $code): ?array
{
    $statement = $pdo->prepare(reservationSelect() . ' WHERE r.reservation_code = ?');
    $statement->execute([$code]);
    $row = $statement->fetch();
    return $row ? reservationRow($row) : null;
}
