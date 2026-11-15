CREATE DATABASE IF NOT EXISTS ssu_reservations CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ssu_reservations;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    user_type VARCHAR(80) NOT NULL DEFAULT 'Admin',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('User', 'Administrator') NOT NULL DEFAULT 'User',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facilities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    location VARCHAR(255) NOT NULL,
    image VARCHAR(255) NOT NULL,
    capacity VARCHAR(100) NOT NULL,
    equipment TEXT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    facility_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    participants INT UNSIGNED NOT NULL,
    purpose TEXT NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'OCCUPIED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    rejection_reason TEXT NULL,
    cancelled_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_reservation_facility FOREIGN KEY (facility_id) REFERENCES facilities(id),
    CONSTRAINT fk_reservation_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reservation_schedule (facility_id, reservation_date, start_time, end_time),
    INDEX idx_reservation_status (status)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS notification_jobs (
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

CREATE TABLE IF NOT EXISTS notification_logs (
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

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_user_created (user_id, created_at)
) ENGINE=InnoDB;

INSERT INTO facilities (name, location, image, capacity, equipment, description)
VALUES
('University Auditorium', 'Samar State University Main Campus', 'assets/Auditorium.jpg', 'Large Capacity (500+ pax)', 'Sound System, Projector, Microphones, Air Conditioning', 'A spacious venue suitable for seminars, meetings, programs, and university events.'),
('Function Hall', 'Samar State University Main Campus', 'assets/function Hall.jpg', 'Medium Capacity (150 pax)', 'Whiteboard, Projector, Sound System, AC', 'A flexible venue for meetings, workshops, training sessions, and special university activities.'),
('Open Activity Area', 'Samar State University Main Campus', 'assets/bg-img.jpg', 'Large Outdoor Capacity (1,000+ pax)', 'Stage Area, Outdoor Lighting, Tents', 'An open space suitable for university activities, gatherings, exhibits, and outdoor events.')
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE users
SET role = 'Administrator'
WHERE email = 'gianzartiga11@gmail.com';