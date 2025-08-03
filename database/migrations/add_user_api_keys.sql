-- User API Keys Management System
-- Issues #13, #25, #26 - S3 Storage, Transcription, LLM Features

-- Main API keys table for secure storage
CREATE TABLE user_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_type ENUM('s3_storage', 'transcription', 'llm_openai', 'llm_anthropic', 'llm_google', 'llm_other') NOT NULL,
    service_name VARCHAR(100) NOT NULL COMMENT 'Human readable name (e.g., "AWS S3", "Whisper API", "OpenAI GPT-4")',
    
    -- Encrypted API credentials (JSON format for flexibility)
    encrypted_credentials TEXT NOT NULL COMMENT 'Encrypted JSON containing API keys and configuration',
    
    -- Service configuration
    is_active BOOLEAN DEFAULT 1 COMMENT 'Whether this API key is enabled for use',
    is_validated BOOLEAN DEFAULT 0 COMMENT 'Whether the API key has been successfully tested',
    last_validated_at TIMESTAMP NULL COMMENT 'When the API key was last successfully validated',
    validation_error TEXT NULL COMMENT 'Last validation error message if any',
    
    -- Usage tracking
    usage_count INT DEFAULT 0 COMMENT 'Number of times this API key has been used',
    last_used_at TIMESTAMP NULL COMMENT 'When this API key was last used',
    monthly_usage_limit INT NULL COMMENT 'Optional monthly usage limit set by user',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_service (user_id, service_type, service_name),
    INDEX idx_user_service_type (user_id, service_type),
    INDEX idx_active_validated (is_active, is_validated)
);

-- S3 Storage configurations for Issue #13
CREATE TABLE user_s3_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key_id INT NOT NULL,
    
    -- S3 Configuration
    config_name VARCHAR(100) NOT NULL COMMENT 'User-defined name for this S3 config',
    bucket_name VARCHAR(255) NOT NULL,
    region VARCHAR(50) DEFAULT 'us-east-1',
    endpoint_url VARCHAR(255) NULL COMMENT 'For non-AWS S3-compatible services',
    path_prefix VARCHAR(255) DEFAULT 'radiograb/' COMMENT 'Folder structure within bucket',
    
    -- Upload settings
    auto_upload_recordings BOOLEAN DEFAULT 1 COMMENT 'Automatically upload new recordings',
    auto_upload_playlists BOOLEAN DEFAULT 1 COMMENT 'Automatically upload playlist tracks',
    upload_immediately BOOLEAN DEFAULT 0 COMMENT 'Upload immediately vs batch',
    delete_local_after_upload BOOLEAN DEFAULT 0 COMMENT 'Delete local files after successful upload',
    
    -- Storage class and lifecycle
    storage_class ENUM('STANDARD', 'REDUCED_REDUNDANCY', 'STANDARD_IA', 'GLACIER', 'DEEP_ARCHIVE') DEFAULT 'STANDARD',
    enable_lifecycle BOOLEAN DEFAULT 0 COMMENT 'Enable automatic lifecycle management',
    lifecycle_days INT DEFAULT 90 COMMENT 'Days before transitioning to cheaper storage',
    
    -- Status and tracking
    is_active BOOLEAN DEFAULT 1,
    total_uploaded_bytes BIGINT DEFAULT 0,
    total_uploaded_files INT DEFAULT 0,
    last_upload_at TIMESTAMP NULL,
    last_sync_error TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (api_key_id) REFERENCES user_api_keys(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_config_name (user_id, config_name),
    INDEX idx_user_active (user_id, is_active)
);

-- Transcription service configurations for Issue #25
CREATE TABLE user_transcription_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key_id INT NOT NULL,
    
    -- Service configuration
    service_provider ENUM('whisper_local', 'whisper_api', 'google_stt', 'azure_stt', 'aws_transcribe') NOT NULL,
    model_name VARCHAR(100) DEFAULT 'base' COMMENT 'Model to use (e.g., whisper "base", "large")',
    language_code VARCHAR(10) DEFAULT 'en' COMMENT 'Primary language for transcription',
    
    -- Quality and cost settings
    quality_level ENUM('draft', 'standard', 'high', 'premium') DEFAULT 'standard',
    enable_punctuation BOOLEAN DEFAULT 1,
    enable_speaker_detection BOOLEAN DEFAULT 0 COMMENT 'Diarization if supported',
    enable_timestamps BOOLEAN DEFAULT 1,
    
    -- Auto-transcription settings
    auto_transcribe_recordings BOOLEAN DEFAULT 0 COMMENT 'Automatically transcribe new recordings',
    min_duration_seconds INT DEFAULT 300 COMMENT 'Minimum recording length to auto-transcribe',
    max_duration_seconds INT DEFAULT 7200 COMMENT 'Maximum recording length to auto-transcribe',
    
    -- Cost management
    monthly_minutes_limit INT DEFAULT 1000 COMMENT 'Monthly transcription limit in minutes',
    cost_per_minute DECIMAL(8,4) NULL COMMENT 'Estimated cost per minute for budgeting',
    
    -- Status
    is_active BOOLEAN DEFAULT 1,
    total_transcribed_minutes INT DEFAULT 0,
    total_transcriptions INT DEFAULT 0,
    last_transcription_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (api_key_id) REFERENCES user_api_keys(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_auto_transcribe (auto_transcribe_recordings, is_active)
);

-- LLM service configurations for Issue #26
CREATE TABLE user_llm_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key_id INT NOT NULL,
    
    -- Service configuration
    provider ENUM('openai', 'anthropic', 'google', 'azure_openai', 'local_llm') NOT NULL,
    model_name VARCHAR(100) NOT NULL COMMENT 'Model identifier (e.g., "gpt-4", "claude-3-sonnet")',
    api_version VARCHAR(20) DEFAULT 'v1' COMMENT 'API version to use',
    
    -- Model parameters
    max_tokens INT DEFAULT 1000,
    temperature DECIMAL(3,2) DEFAULT 0.7 COMMENT 'Creativity level 0.0-1.0',
    system_prompt TEXT NULL COMMENT 'Default system prompt for this configuration',
    
    -- Feature enablement
    enable_summarization BOOLEAN DEFAULT 1 COMMENT 'Use for show/transcript summaries',
    enable_playlist_generation BOOLEAN DEFAULT 1 COMMENT 'Use for automatic playlist creation',
    enable_content_analysis BOOLEAN DEFAULT 1 COMMENT 'Use for content analysis',
    enable_recommendations BOOLEAN DEFAULT 1 COMMENT 'Use for content recommendations',
    
    -- Cost management
    monthly_tokens_limit INT DEFAULT 100000 COMMENT 'Monthly token usage limit',
    cost_per_1k_tokens DECIMAL(8,4) NULL COMMENT 'Estimated cost per 1000 tokens',
    priority_order INT DEFAULT 1 COMMENT 'Order of preference when multiple LLMs available',
    
    -- Usage tracking
    is_active BOOLEAN DEFAULT 1,
    total_tokens_used INT DEFAULT 0,
    total_requests INT DEFAULT 0,
    last_used_at TIMESTAMP NULL,
    average_response_time_ms INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (api_key_id) REFERENCES user_api_keys(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_user_features (user_id, enable_summarization, enable_playlist_generation),
    INDEX idx_priority (user_id, is_active, priority_order)
);

-- API usage log for monitoring and billing
CREATE TABLE user_api_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key_id INT NOT NULL,
    service_type ENUM('s3_storage', 'transcription', 'llm') NOT NULL,
    
    -- Operation details
    operation_type VARCHAR(50) NOT NULL COMMENT 'upload, transcribe, summarize, etc.',
    resource_type VARCHAR(50) NULL COMMENT 'recording, playlist, transcript, etc.',
    resource_id INT NULL COMMENT 'ID of the resource being processed',
    
    -- Usage metrics
    tokens_used INT DEFAULT 0 COMMENT 'For LLM services',
    bytes_processed BIGINT DEFAULT 0 COMMENT 'For storage/transcription',
    duration_seconds INT DEFAULT 0 COMMENT 'For transcription services',
    
    -- Cost tracking
    estimated_cost DECIMAL(10,4) DEFAULT 0.00 COMMENT 'Estimated cost for this operation',
    
    -- Result tracking
    success BOOLEAN DEFAULT 1,
    error_message TEXT NULL,
    response_time_ms INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (api_key_id) REFERENCES user_api_keys(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_service_date (service_type, created_at),
    INDEX idx_resource (resource_type, resource_id)
);

-- Encryption key management (for securing API keys)
CREATE TABLE api_key_encryption_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_version INT NOT NULL DEFAULT 1 COMMENT 'Version of encryption key used',
    encryption_method VARCHAR(50) DEFAULT 'AES-256-GCM' COMMENT 'Encryption algorithm used',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    
    INDEX idx_version_active (key_version, is_active)
);

-- Insert initial encryption info
INSERT INTO api_key_encryption_info (key_version, encryption_method, is_active) 
VALUES (1, 'AES-256-GCM', 1);

-- User feature access based on available API keys
CREATE TABLE user_feature_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feature_name ENUM('s3_storage', 'auto_transcription', 'llm_summarization', 'llm_playlist_generation', 'content_analysis') NOT NULL,
    is_enabled BOOLEAN DEFAULT 0 COMMENT 'Whether user has this feature enabled',
    requires_api_key BOOLEAN DEFAULT 1 COMMENT 'Whether this feature requires API keys',
    last_checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_feature (user_id, feature_name),
    INDEX idx_user_enabled (user_id, is_enabled)
);