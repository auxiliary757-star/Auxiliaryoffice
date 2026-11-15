-- Active: 1791470516582@@127.0.0.1@3306@ssu_reservations
ALTER TABLE users
    ADD COLUMN phone VARCHAR(30) NULL AFTER email,
    ADD COLUMN notifications_email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER phone,
    ADD COLUMN notifications_sms_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notifications_email_enabled;

ALTER TABLE reservations
    MODIFY COLUMN status ENUM('PENDING', 'APPROVED', 'REJECTED', 'OCCUPIED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    ADD COLUMN cancelled_at DATETIME NULL AFTER rejection_reason;

CREATE TABLE notification_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    dedupe_key VARCHAR(190) NOT NULL UNIQUE,
    payload_json JSON NOT NULL,
    status ENUM('QUEUED', 'PROCESSING', 'SENT', 'FAILED') NOT NULL DEFAULT 'QUEUED',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_job_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX idx_notification_job_work (status, available_at)
) ENGINE=InnoDB;

CREATE TABLE notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    status ENUM('SENT', 'FAILED', 'LOGGED') NOT NULL,
    error TEXT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_log_job FOREIGN KEY (notification_job_id) REFERENCES notification_jobs(id) ON DELETE CASCADE,
    INDEX idx_notification_log_job (notification_job_id, attempted_at)
) ENGINE=InnoDB;

