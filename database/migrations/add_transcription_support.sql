-- Add transcription support to recordings table
-- Issue #25 - Transcription system integration

-- Add transcript fields to recordings table
ALTER TABLE recordings 
ADD COLUMN transcript_file VARCHAR(500) NULL COMMENT 'Path to transcript file',
ADD COLUMN transcript_provider VARCHAR(50) NULL COMMENT 'Provider used for transcription',
ADD COLUMN transcript_generated_at DATETIME NULL COMMENT 'When transcript was generated',
ADD COLUMN transcript_cost DECIMAL(10,4) NULL COMMENT 'Cost of transcription';

-- Add indexes for transcript fields
CREATE INDEX idx_recordings_transcript_provider ON recordings(transcript_provider);
CREATE INDEX idx_recordings_transcript_generated_at ON recordings(transcript_generated_at);

-- Update the transcription service types in user_api_keys
-- The API keys management already supports 'transcription' service type