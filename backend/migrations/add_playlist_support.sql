-- Migration: Add Playlist/Upload Support
-- Date: 2025-01-29
-- Description: Extend shows and recordings to support user uploads and playlists

-- Add playlist/upload fields to shows table
ALTER TABLE shows ADD COLUMN show_type VARCHAR(20) DEFAULT 'scheduled' COMMENT 'Type: scheduled or playlist';
ALTER TABLE shows ADD COLUMN allow_uploads BOOLEAN DEFAULT FALSE COMMENT 'Allow user uploads to this show';
ALTER TABLE shows ADD COLUMN max_file_size_mb INT DEFAULT 100 COMMENT 'Max upload size in MB';

-- Make schedule_pattern nullable for playlists
ALTER TABLE shows MODIFY COLUMN schedule_pattern VARCHAR(255) NULL COMMENT 'Cron-like pattern (nullable for playlists)';

-- Add upload tracking fields to recordings table
ALTER TABLE recordings ADD COLUMN source_type VARCHAR(20) DEFAULT 'recorded' COMMENT 'Source: recorded or uploaded';
ALTER TABLE recordings ADD COLUMN uploaded_by VARCHAR(100) NULL COMMENT 'User who uploaded (future auth)';
ALTER TABLE recordings ADD COLUMN original_filename VARCHAR(255) NULL COMMENT 'Original upload filename';

-- Add indexes for performance
CREATE INDEX idx_shows_show_type ON shows(show_type);
CREATE INDEX idx_shows_allow_uploads ON shows(allow_uploads);
CREATE INDEX idx_recordings_source_type ON recordings(source_type);

-- Update existing shows to be 'scheduled' type
UPDATE shows SET show_type = 'scheduled' WHERE show_type IS NULL;

-- Update existing recordings to be 'recorded' source
UPDATE recordings SET source_type = 'recorded' WHERE source_type IS NULL;