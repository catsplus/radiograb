-- Migration: Add Show Metadata Fields
-- Date: 2025-01-29
-- Description: Add fields to support automatic show metadata detection and enrichment

-- Add new metadata fields to shows table
ALTER TABLE shows ADD COLUMN long_description TEXT NULL COMMENT 'Extended description from website';
ALTER TABLE shows ADD COLUMN genre VARCHAR(100) NULL COMMENT 'Show genre/category';
ALTER TABLE shows ADD COLUMN image_url VARCHAR(500) NULL COMMENT 'Show-specific image URL';
ALTER TABLE shows ADD COLUMN website_url VARCHAR(500) NULL COMMENT 'Direct show page URL';

-- Add metadata tracking fields
ALTER TABLE shows ADD COLUMN description_source VARCHAR(50) NULL COMMENT 'Source of description: calendar, website, manual, generated';
ALTER TABLE shows ADD COLUMN image_source VARCHAR(50) NULL COMMENT 'Source of image: calendar, website, station, default';
ALTER TABLE shows ADD COLUMN metadata_json TEXT NULL COMMENT 'Extended metadata as JSON';
ALTER TABLE shows ADD COLUMN metadata_updated DATETIME NULL COMMENT 'Last metadata update timestamp';

-- Add index on metadata_updated for efficient querying
CREATE INDEX idx_shows_metadata_updated ON shows(metadata_updated);

-- Add index on description_source and image_source for reporting
CREATE INDEX idx_shows_metadata_sources ON shows(description_source, image_source);

-- Update existing shows to set default description_source for manual entries
UPDATE shows 
SET description_source = 'manual', 
    metadata_updated = NOW() 
WHERE description IS NOT NULL AND description_source IS NULL;

-- Update existing shows to use station logo as default image fallback
UPDATE shows s
JOIN stations st ON s.station_id = st.id
SET s.image_url = st.logo_url,
    s.image_source = 'station',
    s.metadata_updated = NOW()
WHERE s.image_url IS NULL 
  AND st.logo_url IS NOT NULL 
  AND st.logo_url != '';

-- Set default fallback image for shows without any image
UPDATE shows 
SET image_url = '/assets/images/default-show.png',
    image_source = 'default',
    metadata_updated = NOW()
WHERE image_url IS NULL OR image_url = '';