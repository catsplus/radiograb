-- Add streaming vs download controls for DMCA compliance (Issue #27)
-- RadioGrab v3.12.1 - Streaming and Download Controls

-- Add stream_only field to shows table
ALTER TABLE shows 
ADD COLUMN stream_only BOOLEAN DEFAULT FALSE COMMENT 'If true, show recordings are stream-only (no downloads)';

-- Add content_type field for auto-categorization  
ALTER TABLE shows 
ADD COLUMN content_type ENUM('music', 'talk', 'mixed', 'unknown') DEFAULT 'unknown' COMMENT 'Auto-categorized content type';

-- Add syndicated flag for automatic stream-only detection
ALTER TABLE shows 
ADD COLUMN is_syndicated BOOLEAN DEFAULT FALSE COMMENT 'Syndicated shows default to stream-only';

-- Add index for stream_only queries
ALTER TABLE shows ADD INDEX idx_stream_only (stream_only);

-- Add index for content_type queries  
ALTER TABLE shows ADD INDEX idx_content_type (content_type);

-- Auto-categorize existing shows based on keywords
-- Music shows (likely to have music content)
UPDATE shows SET content_type = 'music' 
WHERE (
    name LIKE '%music%' OR name LIKE '%Music%' OR
    name LIKE '%jazz%' OR name LIKE '%Jazz%' OR
    name LIKE '%rock%' OR name LIKE '%Rock%' OR
    name LIKE '%blues%' OR name LIKE '%Blues%' OR
    name LIKE '%folk%' OR name LIKE '%Folk%' OR
    name LIKE '%country%' OR name LIKE '%Country%' OR
    name LIKE '%classical%' OR name LIKE '%Classical%' OR
    name LIKE '%bluegrass%' OR name LIKE '%Bluegrass%' OR
    genre LIKE '%music%'
) AND content_type = 'unknown';

-- Talk shows (spoken word content)
UPDATE shows SET content_type = 'talk' 
WHERE (
    name LIKE '%news%' OR name LIKE '%News%' OR
    name LIKE '%talk%' OR name LIKE '%Talk%' OR
    name LIKE '%interview%' OR name LIKE '%Interview%' OR
    name LIKE '%discussion%' OR name LIKE '%Discussion%' OR
    name LIKE '%morning%' OR name LIKE '%Morning%' OR
    name LIKE '%afternoon%' OR name LIKE '%Afternoon%' OR
    name LIKE '%evening%' OR name LIKE '%Evening%' OR
    name LIKE '%show%' OR name LIKE '%Show%' OR
    name LIKE '%program%' OR name LIKE '%Program%' OR
    genre LIKE '%talk%' OR genre LIKE '%news%'
) AND content_type = 'unknown';

-- Mixed content shows (variety/mix)
UPDATE shows SET content_type = 'mixed' 
WHERE (
    name LIKE '%variety%' OR name LIKE '%Variety%' OR
    name LIKE '%mix%' OR name LIKE '%Mix%' OR
    name LIKE '%eclectic%' OR name LIKE '%Eclectic%' OR
    genre LIKE '%variety%' OR genre LIKE '%mixed%'
) AND content_type = 'unknown';

-- Mark syndicated shows (common syndicated program names)
UPDATE shows SET is_syndicated = TRUE, stream_only = TRUE 
WHERE (
    name LIKE '%NPR%' OR
    name LIKE '%All Things Considered%' OR
    name LIKE '%Morning Edition%' OR
    name LIKE '%Fresh Air%' OR
    name LIKE '%This American Life%' OR
    name LIKE '%Wait Wait%' OR
    name LIKE '%Car Talk%' OR
    name LIKE '%Prairie Home%' OR
    name LIKE '%BBC%' OR
    description LIKE '%syndicated%' OR
    description LIKE '%national%'
);

-- Set music shows with potentially copyrighted content to stream-only by default
-- This is conservative - admins can manually override as needed
UPDATE shows SET stream_only = TRUE 
WHERE content_type = 'music' AND is_syndicated = FALSE;

-- Keep talk shows downloadable by default (less copyright issues)
UPDATE shows SET stream_only = FALSE 
WHERE content_type = 'talk' AND is_syndicated = FALSE;

-- Mixed content defaults to stream-only for safety
UPDATE shows SET stream_only = TRUE 
WHERE content_type = 'mixed';