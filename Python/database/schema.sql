CREATE DATABASE IF NOT EXISTS fire_detection
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fire_detection;

CREATE TABLE IF NOT EXISTS fire_events (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    detected_at DATETIME NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    status ENUM('detected', 'confirmed', 'false_alarm', 'resolved') NOT NULL DEFAULT 'detected',
    alert_sent BOOLEAN NOT NULL DEFAULT FALSE,
    confidence DOUBLE NOT NULL,
    source VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX ix_fire_events_detected_at (detected_at),
    INDEX ix_fire_events_status (status),
    INDEX ix_fire_events_alert_sent (alert_sent),
    INDEX ix_fire_events_source (source)
) ENGINE=InnoDB;
