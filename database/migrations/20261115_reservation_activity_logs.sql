CREATE TABLE IF NOT EXISTS reservation_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    details TEXT NOT NULL,
    source_key VARCHAR(190) NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_activity_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_activity_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reservation_activity_created (created_at, id),
    INDEX idx_reservation_activity_reservation (reservation_id, created_at)
) ENGINE=InnoDB;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT r.id, 'booking_submitted', requester.id, 'Reservation request submitted.',
       CONCAT('booking:', r.id), r.created_at
FROM reservations r
LEFT JOIN users requester ON requester.id = r.user_id
WHERE 1 = 1
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT j.reservation_id,
       CASE j.event_type WHEN 'approval' THEN 'approved' ELSE 'cancelled' END,
       NULL,
       CASE j.event_type WHEN 'approval' THEN 'Reservation approved.' ELSE 'Reservation cancelled.' END,
       CONCAT('notification-job:', MIN(j.id)),
       MIN(j.created_at)
FROM notification_jobs j
WHERE j.event_type IN ('approval', 'cancellation')
GROUP BY j.reservation_id, j.event_type
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT r.id, 'rejected', reviewer.id, 'Reservation rejected.',
       CONCAT('legacy-status:', r.id, ':rejected'), COALESCE(r.reviewed_at, r.updated_at)
FROM reservations r
LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
WHERE r.status = 'REJECTED'
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT r.id, 'occupied', reviewer.id, 'Reservation marked occupied.',
       CONCAT('legacy-status:', r.id, ':occupied'), COALESCE(r.reviewed_at, r.updated_at)
FROM reservations r
LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
WHERE r.status = 'OCCUPIED'
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT r.id, 'cancelled', reviewer.id, 'Reservation cancelled.',
       CONCAT('legacy-status:', r.id, ':cancelled'), COALESCE(r.cancelled_at, r.reviewed_at, r.updated_at)
FROM reservations r
LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
WHERE r.status = 'CANCELLED'
  AND NOT EXISTS (
      SELECT 1 FROM notification_jobs j
      WHERE j.reservation_id = r.id AND j.event_type = 'cancellation'
  )
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;

INSERT INTO reservation_activity_logs
    (reservation_id, event_type, actor_user_id, details, source_key, created_at)
SELECT r.id, 'completed', NULL, 'Reservation ended, venue released.',
       CONCAT('completed:', r.id), TIMESTAMP(r.reservation_date, r.end_time)
FROM reservations r
WHERE r.status IN ('APPROVED', 'OCCUPIED')
  AND TIMESTAMP(r.reservation_date, r.end_time) <= NOW()
ON DUPLICATE KEY UPDATE source_key = reservation_activity_logs.source_key;
