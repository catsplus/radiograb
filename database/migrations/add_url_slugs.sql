-- Add URL slugs for friendly URLs
-- This migration adds slug fields to tables that need friendly URL support

-- Add slug to shows table for show URLs like /weru/fresh_air
ALTER TABLE shows ADD COLUMN slug VARCHAR(255) NULL COMMENT 'URL-friendly slug for show pages' AFTER name;
ALTER TABLE shows ADD UNIQUE INDEX idx_station_slug (station_id, slug);

-- Add slug to users table for user profile URLs like /user/mattbaya  
ALTER TABLE users ADD COLUMN slug VARCHAR(255) NULL COMMENT 'URL-friendly slug for user profiles' AFTER username;
ALTER TABLE users ADD UNIQUE INDEX idx_user_slug (slug);

-- Shows already have playlists that are shows with show_type='playlist'
-- Custom feeds already have slugs

-- Update existing shows to generate slugs from names
UPDATE shows SET slug = LOWER(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(name, ' ', '_'),
                                        '&', 'and'
                                    ),
                                    "'", ''
                                ),
                                '"', ''
                            ),
                            '!', ''
                        ),
                        '?', ''
                    ),
                    '.', ''
                ),
                ',', ''
            ),
            ':', ''
        ),
        '--', '_'
    )
) WHERE slug IS NULL;

-- Update existing users to generate slugs from usernames
UPDATE users SET slug = LOWER(username) WHERE slug IS NULL;

-- Remove any duplicate underscores and clean up
UPDATE shows SET slug = REPLACE(slug, '__', '_') WHERE slug LIKE '%__%';
UPDATE shows SET slug = TRIM(BOTH '_' FROM slug);

-- Handle any potential duplicates by adding numbers
-- This is a simplified approach - in production you might want more sophisticated handling