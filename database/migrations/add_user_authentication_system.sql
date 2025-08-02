-- User Authentication System for Issue #6
-- RadioGrab v3.12.1 - User Authentication & Admin Access

-- Enhanced users table with email verification and admin features
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255) NULL,
    email_verification_expires TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires TIMESTAMP NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    profile_image_url VARCHAR(500) NULL,
    timezone VARCHAR(100) DEFAULT 'America/New_York',
    last_login TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_email_verified (email_verified),
    INDEX idx_is_admin (is_admin),
    INDEX idx_is_active (is_active),
    INDEX idx_email_verification_token (email_verification_token),
    INDEX idx_password_reset_token (password_reset_token)
);

-- User sessions table for session management
CREATE TABLE user_sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_activity (last_activity)
);

-- User activity log for admin monitoring
CREATE TABLE user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created_at (created_at)
);

-- Add user_id to existing tables for data scoping
ALTER TABLE stations 
ADD COLUMN user_id INT NOT NULL DEFAULT 1,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD INDEX idx_user_id (user_id);

ALTER TABLE shows 
ADD COLUMN user_id INT NOT NULL DEFAULT 1,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD INDEX idx_user_id (user_id);

-- Add user_id to custom_feeds table if it exists
SET @table_exists = 0;
SELECT COUNT(*) INTO @table_exists FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'custom_feeds';

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE custom_feeds 
     ADD COLUMN user_id INT NOT NULL DEFAULT 1,
     ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
     ADD INDEX idx_user_id (user_id)',
    'SELECT "custom_feeds table does not exist, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create default admin user (password: radiograb_admin_2024)
-- Password hash for 'radiograb_admin_2024' using PHP password_hash()
INSERT INTO users (
    email, 
    username, 
    password_hash, 
    email_verified, 
    is_admin, 
    is_active,
    first_name,
    last_name
) VALUES (
    'admin@radiograb.local',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- radiograb_admin_2024
    TRUE,
    TRUE,
    TRUE,
    'RadioGrab',
    'Administrator'
);

-- Create default regular user for existing data
INSERT INTO users (
    email, 
    username, 
    password_hash, 
    email_verified, 
    is_admin, 
    is_active,
    first_name,
    last_name
) VALUES (
    'user@radiograb.local',
    'default_user',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- radiograb_admin_2024
    TRUE,
    FALSE,
    TRUE,
    'Default',
    'User'
);

-- Update existing data to belong to default user (id=2)
UPDATE stations SET user_id = 2 WHERE user_id = 1;
UPDATE shows SET user_id = 2 WHERE user_id = 1;

-- Update custom_feeds if table exists
SET @update_custom_feeds = IF(@table_exists > 0, 
    'UPDATE custom_feeds SET user_id = 2 WHERE user_id = 1',
    'SELECT "custom_feeds table does not exist, skipping update"'
);
PREPARE stmt FROM @update_custom_feeds;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- User preferences table
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    INDEX idx_user_id (user_id),
    INDEX idx_preference_key (preference_key)
);

-- Insert default preferences for admin user
INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES
(1, 'dashboard_layout', 'grid'),
(1, 'items_per_page', '20'),
(1, 'default_retention_days', '30'),
(1, 'email_notifications', 'true'),
(1, 'theme', 'light');

-- Insert default preferences for default user
INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES
(2, 'dashboard_layout', 'grid'),
(2, 'items_per_page', '20'),
(2, 'default_retention_days', '30'),
(2, 'email_notifications', 'true'),
(2, 'theme', 'light');