-- Station Template Sharing System for Issue #38
-- RadioGrab v3.13.1 - Multi-User Station Template System

-- Master template table for shared stations
CREATE TABLE stations_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    call_letters VARCHAR(50) NOT NULL,
    stream_url VARCHAR(500),
    website_url VARCHAR(500),
    logo_url VARCHAR(500),
    calendar_url VARCHAR(500),
    timezone VARCHAR(100) DEFAULT 'America/New_York',
    description TEXT,
    genre VARCHAR(100),
    language VARCHAR(50) DEFAULT 'English',
    country VARCHAR(100) DEFAULT 'United States',
    bitrate VARCHAR(50),
    format VARCHAR(50),
    created_by_user_id INT, -- Original contributor
    is_verified BOOLEAN DEFAULT FALSE, -- Admin verified
    is_active BOOLEAN DEFAULT TRUE, -- Can be browsed/copied
    usage_count INT DEFAULT 0, -- How many times copied
    last_tested TIMESTAMP NULL, -- Last successful test
    last_test_result ENUM('success', 'failed', 'timeout') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_call_letters (call_letters),
    INDEX idx_name (name),
    INDEX idx_genre (genre),
    INDEX idx_country (country),
    INDEX idx_usage_count (usage_count),
    INDEX idx_is_verified (is_verified),
    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user (created_by_user_id)
);

-- Track which users copied which templates
CREATE TABLE user_station_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    station_id INT NOT NULL, -- User's copied station
    copied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES stations_master(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_template (user_id, template_id),
    INDEX idx_user_id (user_id),
    INDEX idx_template_id (template_id),
    INDEX idx_station_id (station_id)
);

-- Add privacy and template tracking to existing stations table
ALTER TABLE stations 
ADD COLUMN is_private BOOLEAN DEFAULT TRUE AFTER logo_url,
ADD COLUMN template_source_id INT NULL AFTER is_private,
ADD COLUMN submitted_as_template BOOLEAN DEFAULT FALSE AFTER template_source_id,
ADD FOREIGN KEY fk_template_source (template_source_id) REFERENCES stations_master(id) ON DELETE SET NULL,
ADD INDEX idx_is_private (is_private),
ADD INDEX idx_template_source (template_source_id),
ADD INDEX idx_submitted_template (submitted_as_template);

-- Station template ratings/reviews (for future enhancement)
CREATE TABLE station_template_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    working_status ENUM('working', 'not_working', 'intermittent') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES stations_master(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_template_review (user_id, template_id),
    INDEX idx_template_id (template_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_working_status (working_status)
);

-- Template categories for better organization
CREATE TABLE template_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50), -- FontAwesome icon class
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_sort_order (sort_order),
    INDEX idx_is_active (is_active)
);

-- Many-to-many relationship between templates and categories
CREATE TABLE station_template_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (template_id) REFERENCES stations_master(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES template_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_template_category (template_id, category_id),
    INDEX idx_template_id (template_id),
    INDEX idx_category_id (category_id)
);

-- Insert default template categories
INSERT INTO template_categories (name, description, icon, sort_order) VALUES
('News/Talk', 'News, talk radio, and public affairs stations', 'fas fa-newspaper', 1),
('Music', 'Music stations of all genres', 'fas fa-music', 2),
('Public Radio', 'NPR affiliates and public broadcasting', 'fas fa-globe-americas', 3),
('College Radio', 'University and college radio stations', 'fas fa-graduation-cap', 4),
('Community', 'Local community and low-power FM stations', 'fas fa-users', 5),
('Religious', 'Religious and spiritual programming', 'fas fa-church', 6),
('International', 'Stations from around the world', 'fas fa-globe', 7),
('Specialty', 'Niche and specialty programming', 'fas fa-star', 8);

-- Populate initial templates from high-quality existing stations (admin can run this)
-- This would be run by admin to seed the template system with existing good stations
-- Example insert (admin would customize this):
-- INSERT INTO stations_master (name, call_letters, stream_url, website_url, genre, country, description, is_verified)
-- SELECT name, call_letters, stream_url, website_url, 'Public Radio' as genre, 'United States' as country, 
--        CONCAT('Community contributed template for ', name) as description, FALSE as is_verified
-- FROM stations 
-- WHERE last_test_result = 'success' AND call_letters IN ('WYSO', 'WERU', 'KCRW') 
-- LIMIT 10;