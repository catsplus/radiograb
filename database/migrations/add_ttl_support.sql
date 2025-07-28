-- Add TTL support to recordings table
-- Migration: add_ttl_support.sql

-- Add TTL columns to recordings table
ALTER TABLE recordings 
ADD COLUMN expires_at TIMESTAMP NULL COMMENT 'When this recording expires (NULL = never)',
ADD COLUMN ttl_override_days INT NULL COMMENT 'Override TTL in days (NULL = use show default)',
ADD COLUMN ttl_type ENUM('days', 'weeks', 'months', 'indefinite') DEFAULT 'days' COMMENT 'TTL unit type',
ADD INDEX idx_expires_at (expires_at);

-- Add TTL columns to shows table (extend existing retention_days)
ALTER TABLE shows 
ADD COLUMN default_ttl_type ENUM('days', 'weeks', 'months', 'indefinite') DEFAULT 'days' COMMENT 'Default TTL unit for new recordings',
ADD COLUMN duration_minutes INT DEFAULT 60 COMMENT 'Show duration in minutes',
ADD COLUMN genre VARCHAR(100) NULL COMMENT 'Show genre/category';

-- Update existing recordings to set expires_at based on shows.retention_days
UPDATE recordings r 
JOIN shows s ON r.show_id = s.id 
SET r.expires_at = DATE_ADD(r.recorded_at, INTERVAL s.retention_days DAY)
WHERE r.expires_at IS NULL AND s.retention_days > 0;

-- Add stations table enhancements for better scheduling
ALTER TABLE stations
ADD COLUMN call_letters VARCHAR(10) NULL COMMENT 'Station call letters (e.g., WEHC)',
ADD COLUMN timezone VARCHAR(50) DEFAULT 'America/New_York' COMMENT 'Station timezone',
ADD COLUMN last_tested TIMESTAMP NULL COMMENT 'Last time stream was tested',
ADD COLUMN last_test_result ENUM('success', 'failed', 'error') NULL COMMENT 'Result of last test',
ADD COLUMN last_test_error TEXT NULL COMMENT 'Error message from last test';

-- Update existing stations with call letters based on name
UPDATE stations SET call_letters = 'WEHC' WHERE name LIKE '%WEHC%';
UPDATE stations SET call_letters = 'WERU' WHERE name LIKE '%WERU%';
UPDATE stations SET call_letters = 'WTBR' WHERE name LIKE '%WTBR%';
UPDATE stations SET call_letters = 'WYSO' WHERE name LIKE '%WYSO%';