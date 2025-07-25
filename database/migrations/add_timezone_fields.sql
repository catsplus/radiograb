-- Migration: Add timezone fields to stations and shows tables
-- Date: 2025-07-23
-- Purpose: Support proper timezone handling for recording schedules

-- Add timezone field to stations table
ALTER TABLE stations ADD COLUMN timezone VARCHAR(50) DEFAULT 'America/New_York' COMMENT 'Station timezone (e.g., America/New_York, America/Chicago)';

-- Add timezone field to shows table  
ALTER TABLE shows ADD COLUMN timezone VARCHAR(50) DEFAULT 'America/New_York' COMMENT 'Show timezone (inherits from station)';

-- Add start_time and end_time fields to shows table for better schedule management
ALTER TABLE shows ADD COLUMN start_time TIME NULL COMMENT 'Show start time in station timezone';
ALTER TABLE shows ADD COLUMN end_time TIME NULL COMMENT 'Show end time in station timezone';
ALTER TABLE shows ADD COLUMN days VARCHAR(255) NULL COMMENT 'Comma-separated days (monday,tuesday,etc)';
ALTER TABLE shows ADD COLUMN cron_expression VARCHAR(100) NULL COMMENT 'Generated cron expression for recording';

-- Update existing stations to have EST timezone
UPDATE stations SET timezone = 'America/New_York' WHERE timezone IS NULL OR timezone = '';

-- Update existing shows to inherit station timezone
UPDATE shows s 
JOIN stations st ON s.station_id = st.id 
SET s.timezone = st.timezone 
WHERE s.timezone IS NULL OR s.timezone = '';

-- Add indexes for timezone-based queries
CREATE INDEX idx_stations_timezone ON stations(timezone);
CREATE INDEX idx_shows_timezone ON shows(timezone);
CREATE INDEX idx_shows_start_time ON shows(start_time);