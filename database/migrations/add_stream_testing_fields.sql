-- Add stream testing fields to stations table
-- This migration adds fields to store stream test results and recommended recording tools

USE radiograb;

-- Add new columns to stations table
ALTER TABLE stations 
ADD COLUMN recommended_recording_tool VARCHAR(50) NULL COMMENT 'Best tool for recording this stream (streamripper, ffmpeg, wget)',
ADD COLUMN stream_compatibility VARCHAR(20) DEFAULT 'unknown' COMMENT 'Stream compatibility status (compatible, incompatible, unknown)',
ADD COLUMN stream_test_results TEXT NULL COMMENT 'JSON data with detailed stream test results',
ADD COLUMN last_stream_test TIMESTAMP NULL COMMENT 'When stream was last tested',
ADD INDEX idx_stream_compatibility (stream_compatibility),
ADD INDEX idx_recommended_tool (recommended_recording_tool);

-- Create stream testing log table for historical tracking
CREATE TABLE IF NOT EXISTS stream_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    stream_url VARCHAR(500) NOT NULL,
    test_results TEXT NOT NULL COMMENT 'JSON results from stream test',
    recommended_tool VARCHAR(50) NULL,
    compatibility_status VARCHAR(20) NOT NULL,
    test_duration_seconds DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    INDEX idx_station_id (station_id),
    INDEX idx_compatibility (compatibility_status),
    INDEX idx_created_at (created_at)
);

-- Update existing stations to mark them for testing
UPDATE stations 
SET stream_compatibility = 'unknown', 
    last_stream_test = NULL 
WHERE stream_url IS NOT NULL AND stream_url != '';

-- Insert comment for tracking
INSERT INTO schema_migrations (migration_name, executed_at) 
VALUES ('add_stream_testing_fields', NOW())
ON DUPLICATE KEY UPDATE executed_at = NOW();