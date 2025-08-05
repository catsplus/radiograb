-- Migration: Add missing authentication system tables
-- Date: August 5, 2025
-- Issue: #64 - Authentication failure due to missing database tables

-- User sessions table for session management
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_expires (expires_at)
);

-- User preferences table for user settings
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(255) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    INDEX idx_user_preferences_user_id (user_id)
);

-- User activity log table for audit trail
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    resource_type VARCHAR(100),
    resource_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_user_id (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created (created_at)
);

-- Add missing columns to users table if they don't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64),
ADD COLUMN IF NOT EXISTS email_verification_expires TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_email_token ON users(email_verification_token);
CREATE INDEX IF NOT EXISTS idx_users_verification_expires ON users(email_verification_expires);