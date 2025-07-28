-- Add tags field to shows table for categorizing shows
-- This allows users to tag shows with keywords for better organization

ALTER TABLE shows ADD COLUMN tags TEXT NULL COMMENT 'Comma-separated tags for show categorization';

-- Update the shows table to ensure proper indexing for the active field
ALTER TABLE shows ADD INDEX idx_active_shows (active);

-- Update existing shows with some default tags based on their names (optional)
UPDATE shows SET tags = 'bluegrass,traditional' WHERE name LIKE '%bluegrass%' OR name LIKE '%Bluegrass%';
UPDATE shows SET tags = 'folk,acoustic' WHERE name LIKE '%folk%' OR name LIKE '%Folk%';
UPDATE shows SET tags = 'morning,coffeehouse' WHERE name LIKE '%Morning%' OR name LIKE '%Coffeehouse%';
UPDATE shows SET tags = 'music,variety' WHERE name NOT LIKE '%On-Demand%' AND tags IS NULL;