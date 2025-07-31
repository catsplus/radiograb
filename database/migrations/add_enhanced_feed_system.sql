-- Enhanced RSS Feed System Migration
-- Adds support for custom feeds, station feeds, playlist feeds, and universal feeds

-- Custom RSS Feeds table
-- Allows users to create custom feeds by selecting specific shows
CREATE TABLE custom_feeds (
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
    INDEX idx_public (is_public)
);

-- Custom Feed Shows junction table
-- Links shows to custom feeds
CREATE TABLE custom_feed_shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_feed_id INT NOT NULL,
    show_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (custom_feed_id) REFERENCES custom_feeds(id) ON DELETE CASCADE,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feed_show (custom_feed_id, show_id),
    INDEX idx_sort_order (custom_feed_id, sort_order)
);

-- Station Feeds table
-- Pre-configured feeds for each station (all shows per station)
CREATE TABLE station_feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    custom_title VARCHAR(255),
    custom_description TEXT,
    custom_image_url VARCHAR(500),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_station (station_id),
    INDEX idx_active (is_active)
);

-- Enhanced shows table for RSS metadata
-- Add RSS-specific fields to existing shows table
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_title VARCHAR(255) NULL;
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_description TEXT NULL;
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_image_url VARCHAR(500) NULL;
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_category VARCHAR(100) NULL DEFAULT 'Arts';
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_explicit ENUM('yes', 'no', 'clean') NULL DEFAULT 'no';
ALTER TABLE shows ADD COLUMN IF NOT EXISTS feed_author VARCHAR(255) NULL;

-- Feed generation tracking
CREATE TABLE feed_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feed_type ENUM('show', 'custom', 'station', 'playlist', 'universal') NOT NULL,
    feed_id INT,
    feed_identifier VARCHAR(255),
    status ENUM('success', 'error', 'warning') NOT NULL,
    error_message TEXT,
    generation_time_ms INT,
    items_count INT,
    file_size_bytes INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_feed_type (feed_type),
    INDEX idx_feed_id (feed_type, feed_id),
    INDEX idx_generated_at (generated_at),
    INDEX idx_status (status)
);

-- Insert default station feeds for existing stations
INSERT INTO station_feeds (station_id, custom_title, custom_description, is_active)
SELECT 
    id,
    CONCAT(name, ' - All Shows'),
    CONCAT('All radio shows and recordings from ', name),
    1
FROM stations 
WHERE status = 'active'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Insert universal feed entries into custom_feeds
INSERT INTO custom_feeds (name, description, slug, custom_title, custom_description, feed_type, is_public)
VALUES 
    ('All Shows', 'Aggregated feed of all radio show recordings', 'all-shows', 'RadioGrab - All Shows', 'Complete collection of all radio show recordings from all stations', 'universal', 1),
    ('All Playlists', 'Aggregated feed of all user-created playlists', 'all-playlists', 'RadioGrab - All Playlists', 'Complete collection of all user-created playlist tracks', 'universal', 1)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Update existing shows to use feed metadata where available
UPDATE shows s
SET 
    feed_title = CASE 
        WHEN s.name IS NOT NULL THEN s.name 
        ELSE NULL 
    END,
    feed_description = CASE 
        WHEN s.long_description IS NOT NULL THEN s.long_description
        WHEN s.description IS NOT NULL THEN s.description
        ELSE NULL 
    END,
    feed_image_url = s.image_url,
    feed_author = CASE 
        WHEN s.host IS NOT NULL THEN s.host
        ELSE (SELECT st.name FROM stations st WHERE st.id = s.station_id)
    END
WHERE s.feed_title IS NULL;