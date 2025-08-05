-- Add streaming controls and content categorization
-- Issue #27 - Streaming vs Download Mode

-- Add streaming control fields to stations table
ALTER TABLE stations 
ADD COLUMN default_stream_mode ENUM('stream_only', 'allow_downloads', 'inherit') DEFAULT 'allow_downloads' COMMENT 'Default streaming behavior for all shows on this station',
ADD COLUMN content_type ENUM('talk', 'music', 'mixed', 'unknown') DEFAULT 'unknown' COMMENT 'Primary content type for auto-categorization',
ADD COLUMN dmca_compliance_mode ENUM('strict', 'standard', 'relaxed') DEFAULT 'standard' COMMENT 'DMCA compliance level';

-- Add streaming control fields to shows table  
ALTER TABLE shows
ADD COLUMN stream_mode ENUM('stream_only', 'allow_downloads', 'inherit') DEFAULT 'inherit' COMMENT 'Streaming behavior - inherit from station or override',
ADD COLUMN content_type ENUM('talk', 'music', 'mixed', 'unknown') DEFAULT 'unknown' COMMENT 'Content type for this specific show',
ADD COLUMN is_syndicated BOOLEAN DEFAULT FALSE COMMENT 'Whether this is a syndicated show (usually stream-only)',
ADD COLUMN auto_categorized BOOLEAN DEFAULT FALSE COMMENT 'Whether content_type was set automatically',
ADD COLUMN stream_only BOOLEAN GENERATED ALWAYS AS (
    CASE 
        WHEN stream_mode = 'stream_only' THEN TRUE
        WHEN stream_mode = 'allow_downloads' THEN FALSE
        WHEN stream_mode = 'inherit' THEN (
            SELECT CASE 
                WHEN s.default_stream_mode = 'stream_only' THEN TRUE
                ELSE FALSE
            END
            FROM stations s WHERE s.id = station_id
        )
        ELSE FALSE
    END
) STORED COMMENT 'Computed field for backward compatibility';

-- Add indexes for performance
CREATE INDEX idx_stations_stream_mode ON stations(default_stream_mode);
CREATE INDEX idx_stations_content_type ON stations(content_type);
CREATE INDEX idx_shows_stream_mode ON shows(stream_mode);
CREATE INDEX idx_shows_content_type ON shows(content_type);
CREATE INDEX idx_shows_syndicated ON shows(is_syndicated);

-- Create content categorization rules table
CREATE TABLE content_categorization_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_type ENUM('keyword', 'pattern', 'genre', 'station') NOT NULL,
    rule_value VARCHAR(255) NOT NULL COMMENT 'Keyword, regex pattern, genre, or station identifier',
    target_content_type ENUM('talk', 'music', 'mixed') NOT NULL,
    confidence_score DECIMAL(3,2) DEFAULT 0.80 COMMENT 'Confidence level (0.00-1.00)',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 100 COMMENT 'Lower numbers = higher priority',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_rule_type (rule_type),
    INDEX idx_active_priority (is_active, priority),
    INDEX idx_content_type (target_content_type)
);

-- Insert default categorization rules
INSERT INTO content_categorization_rules (rule_type, rule_value, target_content_type, confidence_score, priority) VALUES
-- Talk show keywords
('keyword', 'news', 'talk', 0.90, 10),
('keyword', 'talk', 'talk', 0.85, 20),
('keyword', 'interview', 'talk', 0.80, 30),
('keyword', 'discussion', 'talk', 0.80, 30),
('keyword', 'morning show', 'talk', 0.75, 40),
('keyword', 'call-in', 'talk', 0.85, 25),
('keyword', 'phone-in', 'talk', 0.85, 25),
('keyword', 'live', 'talk', 0.60, 80),

-- Music show keywords  
('keyword', 'music', 'music', 0.80, 30),
('keyword', 'hits', 'music', 0.85, 25),
('keyword', 'countdown', 'music', 0.80, 30),
('keyword', 'top 40', 'music', 0.90, 15),
('keyword', 'classic rock', 'music', 0.95, 10),
('keyword', 'jazz', 'music', 0.95, 10),
('keyword', 'classical', 'music', 0.95, 10),
('keyword', 'country', 'music', 0.90, 15),
('keyword', 'hip hop', 'music', 0.95, 10),
('keyword', 'electronic', 'music', 0.95, 10),

-- Mixed content keywords
('keyword', 'variety', 'mixed', 0.80, 30),
('keyword', 'magazine', 'mixed', 0.75, 40),

-- Syndicated show patterns (usually stream-only)
('pattern', '.*(NPR|National Public Radio).*', 'talk', 0.90, 5),
('pattern', '.*(This American Life|Fresh Air|All Things Considered).*', 'talk', 0.95, 5),
('pattern', '.*(Marketplace|Planet Money|TED Radio Hour).*', 'talk', 0.95, 5);

-- Create streaming URL obfuscation tokens table
CREATE TABLE stream_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    recording_id INT NOT NULL,
    user_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    access_count INT DEFAULT 0,
    max_accesses INT DEFAULT 10 COMMENT 'Maximum number of times this token can be used',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recording_id) REFERENCES recordings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_recording_user (recording_id, user_id)
);

-- Add migration tracking
INSERT INTO schema_migrations (migration_name) VALUES ('add_streaming_controls');