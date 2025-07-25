-- Add recording method and stream testing fields to stations table
-- Run this migration to track optimal recording tools per station

ALTER TABLE stations 
ADD COLUMN recommended_recording_tool VARCHAR(50) NULL COMMENT 'Best recording tool: streamripper, ffmpeg, wget',
ADD COLUMN stream_compatibility VARCHAR(20) DEFAULT 'unknown' COMMENT 'compatible, incompatible, unknown',
ADD COLUMN stream_test_results TEXT NULL COMMENT 'JSON test results from stream testing',
ADD COLUMN last_stream_test TIMESTAMP NULL COMMENT 'When stream was last tested';

-- Add index for performance
CREATE INDEX idx_stations_compatibility ON stations(stream_compatibility);
CREATE INDEX idx_stations_last_test ON stations(last_stream_test);

-- Update existing stations with unknown compatibility
UPDATE stations SET stream_compatibility = 'unknown' WHERE stream_compatibility IS NULL;