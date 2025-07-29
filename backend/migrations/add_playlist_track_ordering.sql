-- Migration: Add Playlist Track Ordering Support
-- Date: 2025-01-29
-- Description: Add track ordering for playlist entries

-- Add track ordering field to recordings table
ALTER TABLE recordings ADD COLUMN track_number INT NULL COMMENT 'Track order in playlist (NULL for regular recordings)';

-- Add index for playlist ordering queries
CREATE INDEX idx_recordings_show_track ON recordings(show_id, track_number);

-- Update existing uploaded recordings to have sequential track numbers
-- This handles any existing uploads by giving them sequential numbers
SET @row_number = 0;
UPDATE recordings r1
JOIN (
    SELECT id, 
           @row_number := CASE 
               WHEN @prev_show_id = show_id THEN @row_number + 1 
               ELSE 1 
           END AS new_track_number,
           @prev_show_id := show_id
    FROM recordings 
    WHERE source_type = 'uploaded'
    ORDER BY show_id, recorded_at
) r2 ON r1.id = r2.id
SET r1.track_number = r2.new_track_number
WHERE r1.source_type = 'uploaded';

-- Reset the row number variable
SET @row_number = 0;
SET @prev_show_id = 0;