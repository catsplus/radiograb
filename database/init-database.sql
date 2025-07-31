-- RadioGrab Complete Database Initialization
-- This file creates all necessary tables for a fresh RadioGrab installation
-- Generated: 2025-07-31

CREATE DATABASE IF NOT EXISTS radiograb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE radiograb;

-- ============================================================================
-- CORE TABLES (Base functionality)
-- ============================================================================

-- Stations table
CREATE TABLE IF NOT EXISTS stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    call_letters VARCHAR(10) NULL COMMENT 'Station call letters (e.g., WEHC)',
    website_url VARCHAR(500) NOT NULL,
    stream_url VARCHAR(500) NULL,
    logo_url VARCHAR(500) NULL,
    calendar_url VARCHAR(500) NULL,
    calendar_parsing_method TEXT NULL,
    status VARCHAR(50) DEFAULT 'active',
    timezone VARCHAR(50) DEFAULT 'America/New_York' COMMENT 'Station timezone',
    
    -- Stream testing fields
    recommended_recording_tool VARCHAR(50) NULL COMMENT 'streamripper, ffmpeg, wget',
    stream_compatibility VARCHAR(20) DEFAULT 'unknown' COMMENT 'compatible, incompatible, unknown',
    stream_test_results TEXT NULL COMMENT 'JSON test results',
    last_stream_test TIMESTAMP NULL,
    user_agent VARCHAR(500) NULL COMMENT 'Required User-Agent for this stream',
    
    -- Station testing tracking
    last_tested TIMESTAMP NULL COMMENT 'Last successful recording/test',
    last_test_result VARCHAR(20) NULL COMMENT 'success, failed, error',
    last_test_error TEXT NULL COMMENT 'Error message if failed',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_call_letters (call_letters),
    INDEX idx_stream_compatibility (stream_compatibility),
    INDEX idx_recommended_tool (recommended_recording_tool)
);

-- Shows table
CREATE TABLE IF NOT EXISTS shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    long_description TEXT NULL COMMENT 'Extended description from website',
    host VARCHAR(255) NULL,
    genre VARCHAR(100) NULL COMMENT 'Show genre/category',
    image_url VARCHAR(500) NULL COMMENT 'Show-specific image',
    website_url VARCHAR(500) NULL COMMENT 'Direct show page URL',
    
    -- Metadata tracking
    description_source VARCHAR(50) NULL COMMENT 'calendar, website, manual, generated',
    image_source VARCHAR(50) NULL COMMENT 'calendar, website, station, default',
    metadata_json TEXT NULL COMMENT 'Extended metadata as JSON',
    metadata_updated TIMESTAMP NULL COMMENT 'Last metadata update',
    
    -- RSS metadata fields
    feed_title VARCHAR(255) NULL,
    feed_description TEXT NULL,
    feed_image_url VARCHAR(500) NULL,
    feed_category VARCHAR(100) NULL,
    feed_explicit TINYINT(1) DEFAULT 0,
    feed_author VARCHAR(255) NULL,
    
    -- Show type and scheduling
    show_type VARCHAR(20) DEFAULT 'scheduled' COMMENT 'scheduled or playlist',
    schedule_pattern VARCHAR(255) NULL COMMENT 'Cron-like pattern (nullable for playlists)',
    schedule_description VARCHAR(500) NULL COMMENT 'Human readable',
    retention_days INT DEFAULT 30 COMMENT '0 = never expire (for playlists)',
    default_ttl_type ENUM('days', 'weeks', 'months', 'indefinite') DEFAULT 'days',
    duration_minutes INT DEFAULT 60 COMMENT 'Show duration in minutes',
    audio_format VARCHAR(10) DEFAULT 'mp3',
    active BOOLEAN DEFAULT TRUE,
    
    -- Upload/playlist specific fields
    allow_uploads BOOLEAN DEFAULT FALSE COMMENT 'Allow user uploads to this show',
    max_file_size_mb INT DEFAULT 100 COMMENT 'Max upload size in MB',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    INDEX idx_station_id (station_id),
    INDEX idx_active (active),
    INDEX idx_name (name),
    INDEX idx_show_type (show_type)
);

-- Show schedules table (multiple airings support)
CREATE TABLE IF NOT EXISTS show_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT NOT NULL,
    schedule_pattern VARCHAR(255) NOT NULL COMMENT 'Cron-like pattern for this airing',
    schedule_description VARCHAR(500) NULL COMMENT 'Human readable description for this airing',
    airing_type ENUM('original', 'repeat', 'special') DEFAULT 'original' COMMENT 'Type of airing',
    priority INT DEFAULT 1 COMMENT 'Priority order (1=highest, used for primary airing)',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_show_id (show_id),
    INDEX idx_active (active),
    INDEX idx_priority (priority)
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
    
    -- TTL support
    expires_at TIMESTAMP NULL COMMENT 'When this recording expires (NULL = never)',
    ttl_override_days INT NULL COMMENT 'Override TTL in days (NULL = use show default)',
    ttl_type ENUM('days', 'weeks', 'months', 'indefinite') DEFAULT 'days' COMMENT 'TTL unit type',
    
    -- Upload/source tracking
    source_type VARCHAR(20) DEFAULT 'recorded' COMMENT 'recorded or uploaded',
    uploaded_by VARCHAR(100) NULL COMMENT 'User who uploaded (future auth)',
    original_filename VARCHAR(255) NULL COMMENT 'Original upload filename',
    track_number INT NULL COMMENT 'Track order in playlist (NULL for regular recordings)',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_show_id (show_id),
    INDEX idx_recorded_at (recorded_at),  
    INDEX idx_filename (filename),
    INDEX idx_expires_at (expires_at),
    UNIQUE KEY unique_filename (filename)
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

-- ============================================================================
-- ENHANCED FEATURES (RSS Feeds, Custom Feeds, etc.)
-- ============================================================================

-- Custom RSS Feeds table
CREATE TABLE IF NOT EXISTS custom_feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) NOT NULL UNIQUE,
    custom_title VARCHAR(255),
    custom_description TEXT,
    custom_image_url VARCHAR(500),
    feed_type ENUM('custom', 'station', 'playlist', 'universal') NOT NULL DEFAULT 'custom',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_feed_type (feed_type),
    INDEX idx_is_public (is_public)
);

-- Many-to-many relationship between custom feeds and shows
CREATE TABLE IF NOT EXISTS custom_feed_shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_feed_id INT NOT NULL,
    show_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (custom_feed_id) REFERENCES custom_feeds(id) ON DELETE CASCADE,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feed_show (custom_feed_id, show_id),
    INDEX idx_custom_feed_id (custom_feed_id),
    INDEX idx_show_id (show_id),
    INDEX idx_sort_order (sort_order)
);

-- Station feeds configuration
CREATE TABLE IF NOT EXISTS station_feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    custom_title VARCHAR(255),
    custom_description TEXT,
    custom_image_url VARCHAR(500),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    INDEX idx_station_id (station_id),
    INDEX idx_is_active (is_active)
);

-- ============================================================================
-- ADMIN & BRANDING SYSTEM
-- ============================================================================

-- Users table (admin authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY username (username)
);

-- Site settings table (branding customization)
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(255) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY setting_name (setting_name)
);

-- ============================================================================
-- SYSTEM TABLES (Logging, Monitoring, etc.)
-- ============================================================================

-- Stream testing log table
CREATE TABLE IF NOT EXISTS stream_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    test_type VARCHAR(50) NOT NULL COMMENT 'manual, scheduled, discovery',
    tool_used VARCHAR(50) NOT NULL COMMENT 'streamripper, ffmpeg, wget',
    success BOOLEAN NOT NULL,
    file_size_bytes BIGINT NULL,
    duration_seconds INT NULL,
    error_message TEXT NULL,
    test_command TEXT NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    INDEX idx_station_id (station_id),
    INDEX idx_test_type (test_type),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at)
);

-- System info table
CREATE TABLE IF NOT EXISTS system_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL UNIQUE,
    value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key_name (key_name)
);

-- Migration tracking table
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_migration_name (migration_name),
    INDEX idx_executed_at (executed_at)
);

-- Feed generation log (monitoring)
CREATE TABLE IF NOT EXISTS feed_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feed_type ENUM('show', 'station', 'custom', 'universal', 'playlist') NOT NULL,
    feed_id INT NULL COMMENT 'Reference to feed ID (NULL for universal feeds)',
    feed_identifier VARCHAR(255) NULL COMMENT 'Feed slug or identifier',
    status ENUM('success', 'error', 'warning') NOT NULL,
    error_message TEXT NULL,
    generation_time_ms INT NULL,
    items_count INT NULL,
    file_size_bytes BIGINT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_feed_type (feed_type),
    INDEX idx_feed_id (feed_id),
    INDEX idx_status (status),
    INDEX idx_generated_at (generated_at)
);

-- ============================================================================
-- DEFAULT DATA
-- ============================================================================

-- Insert default site settings
INSERT IGNORE INTO site_settings (setting_name, setting_value) VALUES
('site_title', 'RadioGrab'),
('site_tagline', 'Your Personal Radio Recorder'),
('site_logo', '/assets/images/radiograb-logo.png'),
('brand_color', '#343a40'),
('footer_text', '&copy; 2025 RadioGrab. All rights reserved.');

-- Insert system info
INSERT IGNORE INTO system_info (key_name, value) VALUES
('schema_version', '3.9.0'),
('last_migration', CURRENT_TIMESTAMP),
('database_initialized', CURRENT_TIMESTAMP);

-- Mark all migrations as executed (for fresh installs)
INSERT IGNORE INTO schema_migrations (migration_name) VALUES
('add_call_sign_field'),
('add_enhanced_feed_system'),
('add_logo_storage_fields'),
('add_multiple_show_schedules'),
('add_recording_method_fields'),
('add_show_tags_field'),
('add_site_settings'),
('add_stream_testing_fields'),
('add_system_info_table'),
('add_timezone_fields'),
('add_ttl_support'),
('add_unique_filename_constraint'),
('add_users_table'),
('create_migrations_table');

-- Insert sample station data
INSERT IGNORE INTO stations (name, call_letters, website_url, stream_url) VALUES 
('WEHC 90.7 FM', 'WEHC', 'https://wehc.com', 'http://stream.wehc.com:8000/wehc'),
('WERU 89.9 FM', 'WERU', 'https://weru.org', 'https://stream.pacificaservice.org:9000/weru_128');