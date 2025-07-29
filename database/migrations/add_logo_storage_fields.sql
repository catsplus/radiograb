-- Add logo storage and social media fields to stations table
-- Migration: add_logo_storage_fields.sql

ALTER TABLE stations 
ADD COLUMN facebook_url VARCHAR(500) NULL COMMENT 'Facebook page URL for logo extraction',
ADD COLUMN local_logo_path VARCHAR(255) NULL COMMENT 'Local file path for stored station logo',
ADD COLUMN logo_source VARCHAR(50) NULL COMMENT 'Source of logo: website, facebook, favicon, default',
ADD COLUMN logo_updated_at TIMESTAMP NULL COMMENT 'When logo was last downloaded/updated',
ADD COLUMN social_media_links JSON NULL COMMENT 'JSON object containing social media links with platform, URL, icon info',
ADD COLUMN social_media_updated_at TIMESTAMP NULL COMMENT 'When social media links were last updated';

-- Add indexes for queries
ALTER TABLE stations ADD INDEX idx_logo_source (logo_source);
ALTER TABLE stations ADD INDEX idx_social_media_updated (social_media_updated_at);

-- Update existing records to mark current logos as from website
UPDATE stations SET 
    logo_source = 'website',
    logo_updated_at = CURRENT_TIMESTAMP 
WHERE logo_url IS NOT NULL;

-- Update stations with null logos to prepare for Facebook extraction
UPDATE stations SET 
    logo_source = 'none' 
WHERE logo_url IS NULL;