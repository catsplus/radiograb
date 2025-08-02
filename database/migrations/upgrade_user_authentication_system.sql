-- Upgrade User Authentication System for Issue #6
-- RadioGrab v3.12.1 - Safe upgrade preserving existing users
-- This migration adds new fields to existing users table and creates additional tables

-- Add new fields to existing users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE AFTER username,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER email,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS email_verification_expires TIMESTAMP NULL AFTER email_verification_token,
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL AFTER email_verification_expires,
ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP NULL AFTER password_reset_token,
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE AFTER password_reset_expires,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER is_admin,
ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL AFTER is_active,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL AFTER first_name,
ADD COLUMN IF NOT EXISTS profile_image_url VARCHAR(500) NULL AFTER last_name,
ADD COLUMN IF NOT EXISTS timezone VARCHAR(100) DEFAULT 'America/New_York' AFTER profile_image_url,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER timezone,
ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0 AFTER last_login,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add indexes
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_email (email),
ADD INDEX IF NOT EXISTS idx_username (username),
ADD INDEX IF NOT EXISTS idx_email_verified (email_verified),
ADD INDEX IF NOT EXISTS idx_is_admin (is_admin),
ADD INDEX IF NOT EXISTS idx_is_active (is_active),
ADD INDEX IF NOT EXISTS idx_email_verification_token (email_verification_token),
ADD INDEX IF NOT EXISTS idx_password_reset_token (password_reset_token);

-- Update existing admin user
UPDATE users SET 
    email = 'admin@radiograb.com',
    email_verified = TRUE,
    is_admin = TRUE,
    is_active = TRUE,
    first_name = 'Admin',
    last_name = 'User'
WHERE username = 'admin';

-- User sessions table for session management
CREATE TABLE IF NOT EXISTS user_sessions (
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
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
);

-- User preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
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

-- Add user_id foreign key to existing tables for data scoping
-- Stations table
ALTER TABLE stations 
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
ADD FOREIGN KEY IF NOT EXISTS fk_stations_user (user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD INDEX IF NOT EXISTS idx_stations_user_id (user_id);

-- Shows table  
ALTER TABLE shows
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
ADD FOREIGN KEY IF NOT EXISTS fk_shows_user (user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD INDEX IF NOT EXISTS idx_shows_user_id (user_id);

-- Custom feeds table
ALTER TABLE custom_feeds
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
ADD FOREIGN KEY IF NOT EXISTS fk_custom_feeds_user (user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD INDEX IF NOT EXISTS idx_custom_feeds_user_id (user_id);

-- Assign existing data to admin user (id=1) for backward compatibility
UPDATE stations SET user_id = 1 WHERE user_id IS NULL;
UPDATE shows SET user_id = 1 WHERE user_id IS NULL;
UPDATE custom_feeds SET user_id = 1 WHERE user_id IS NULL;