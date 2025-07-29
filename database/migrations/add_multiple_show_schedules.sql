-- Migration: Add support for multiple show schedules (original + repeat airings)
-- Date: 2025-07-28
-- Description: Creates show_schedules table to support multiple airings per show

-- Create show_schedules table for multiple airings
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
    INDEX idx_airing_type (airing_type),
    INDEX idx_priority (priority)
) COMMENT='Multiple schedule patterns per show for original and repeat airings';

-- Migrate existing show schedules to the new table
INSERT INTO show_schedules (show_id, schedule_pattern, schedule_description, airing_type, priority, active)
SELECT 
    id as show_id,
    schedule_pattern,
    schedule_description,
    'original' as airing_type,
    1 as priority,
    active
FROM shows 
WHERE schedule_pattern IS NOT NULL AND schedule_pattern != '';

-- Add a column to track if show uses multiple schedules
ALTER TABLE shows 
ADD COLUMN uses_multiple_schedules BOOLEAN DEFAULT FALSE COMMENT 'Whether this show has multiple airings';

-- Mark shows that now have schedules in the new table
UPDATE shows 
SET uses_multiple_schedules = TRUE 
WHERE id IN (SELECT DISTINCT show_id FROM show_schedules);

-- Optional: Add index for performance
ALTER TABLE shows ADD INDEX idx_uses_multiple_schedules (uses_multiple_schedules);