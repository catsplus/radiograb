-- RadioGrab Database Schema
-- MySQL/MariaDB compatible

CREATE DATABASE IF NOT EXISTS radiograb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE radiograb;

-- Stations table
CREATE TABLE IF NOT EXISTS stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) NOT NULL,
    stream_url VARCHAR(500) NULL,
    logo_url VARCHAR(500) NULL,
    calendar_url VARCHAR(500) NULL,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- Shows table
CREATE TABLE IF NOT EXISTS shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    host VARCHAR(255) NULL,
    schedule_pattern VARCHAR(255) NOT NULL COMMENT 'Cron-like pattern',
    schedule_description VARCHAR(500) NULL COMMENT 'Human readable schedule',
    retention_days INT DEFAULT 30,
    audio_format VARCHAR(10) DEFAULT 'mp3',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    INDEX idx_station_id (station_id),
    INDEX idx_active (active),
    INDEX idx_name (name)
);

-- Recordings table
CREATE TABLE IF NOT EXISTS recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    duration_seconds INT NULL,
    file_size_bytes BIGINT NULL,
    recorded_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_show_id (show_id),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_filename (filename)
);

-- Cron jobs table
CREATE TABLE IF NOT EXISTS cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT NOT NULL,
    cron_expression VARCHAR(100) NOT NULL,
    command TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_show_id (show_id),
    INDEX idx_status (status),
    INDEX idx_next_run (next_run)
);

-- Insert sample data
INSERT INTO stations (name, website_url, stream_url) VALUES 
('WEHC 90.7 FM', 'https://wehc.com', 'http://stream.wehc.com:8000/wehc'),
('Example Radio', 'https://example-radio.com', 'http://stream.example.com:8000/live');

-- Create database user (run with admin privileges)
-- CREATE USER 'radiograb'@'localhost' IDENTIFIED BY 'radiograb_password';
-- GRANT ALL PRIVILEGES ON radiograb.* TO 'radiograb'@'localhost';
-- FLUSH PRIVILEGES;