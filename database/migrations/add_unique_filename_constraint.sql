-- Add unique constraint on filename field to prevent duplicate recordings
-- Migration: add_unique_filename_constraint.sql
-- Date: 2025-07-29

-- First, remove any existing duplicate entries (keep the first one of each group)
DELETE r1 FROM recordings r1
INNER JOIN recordings r2 
WHERE r1.id > r2.id 
AND r1.filename = r2.filename;

-- Add unique constraint on filename field
ALTER TABLE recordings 
ADD CONSTRAINT unique_filename UNIQUE (filename);

-- Add index for better performance on filename lookups
CREATE INDEX idx_recordings_filename ON recordings (filename);