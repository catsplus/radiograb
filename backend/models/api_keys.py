"""
Database models for API Keys management system
"""

from sqlalchemy import Column, Integer, String, Text, Boolean, DateTime, Float, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

Base = declarative_base()

class UserApiKey(Base):
    __tablename__ = 'user_api_keys'
    
    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    service_type = Column(String(50), nullable=False)  # s3_storage, transcription, llm_openai, etc.
    service_name = Column(String(255), nullable=False)  # User-defined name
    encrypted_credentials = Column(Text, nullable=False)  # Encrypted JSON
    service_configuration = Column(Text)  # JSON configuration
    is_active = Column(Boolean, default=True)
    is_validated = Column(Boolean, default=False)
    last_used_at = Column(DateTime)
    last_validation_at = Column(DateTime)
    created_at = Column(DateTime, server_default=func.now())
    updated_at = Column(DateTime, server_default=func.now(), onupdate=func.now())

class UserS3Config(Base):
    __tablename__ = 'user_s3_configs'
    
    id = Column(Integer, primary_key=True)
    api_key_id = Column(Integer, ForeignKey('user_api_keys.id'), nullable=False)
    config_name = Column(String(255), nullable=False)
    bucket_name = Column(String(255), nullable=False)
    region = Column(String(50))
    endpoint_url = Column(String(500))
    path_prefix = Column(String(255))
    storage_class = Column(String(50), default='STANDARD')
    auto_upload_recordings = Column(Boolean, default=True)
    auto_upload_playlists = Column(Boolean, default=True)
    created_at = Column(DateTime, server_default=func.now())

class UserTranscriptionConfig(Base):
    __tablename__ = 'user_transcription_configs'
    
    id = Column(Integer, primary_key=True)
    api_key_id = Column(Integer, ForeignKey('user_api_keys.id'), nullable=False)
    service_provider = Column(String(50), nullable=False)  # openai_whisper, deepinfra_whisper, etc.
    model_name = Column(String(100))
    language_code = Column(String(10), default='en')
    quality_level = Column(String(20), default='standard')
    auto_transcribe_recordings = Column(Boolean, default=False)
    monthly_minutes_limit = Column(Integer, default=1000)
    created_at = Column(DateTime, server_default=func.now())

class UserLlmConfig(Base):
    __tablename__ = 'user_llm_configs'
    
    id = Column(Integer, primary_key=True)
    api_key_id = Column(Integer, ForeignKey('user_api_keys.id'), nullable=False)
    provider = Column(String(50), nullable=False)  # openai, anthropic, google, other
    model_name = Column(String(100))
    max_tokens = Column(Integer, default=1000)
    temperature = Column(Float, default=0.7)
    enable_summarization = Column(Boolean, default=True)
    enable_playlist_generation = Column(Boolean, default=True)
    monthly_tokens_limit = Column(Integer, default=100000)
    priority_order = Column(Integer, default=1)
    created_at = Column(DateTime, server_default=func.now())

class UserApiUsageLog(Base):
    __tablename__ = 'user_api_usage_log'
    
    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    api_key_id = Column(Integer, ForeignKey('user_api_keys.id'), nullable=False)
    service_type = Column(String(50), nullable=False)
    operation_type = Column(String(100), nullable=False)  # upload, transcribe, summarize, etc.
    request_count = Column(Integer, default=1)
    total_tokens = Column(Integer, default=0)
    total_bytes = Column(Integer, default=0)
    total_cost = Column(Float, default=0.0)
    success = Column(Boolean, default=True)
    response_time_ms = Column(Integer, default=0)
    error_message = Column(Text)
    created_at = Column(DateTime, server_default=func.now())

class ApiKeyEncryptionInfo(Base):
    __tablename__ = 'api_key_encryption_info'
    
    id = Column(Integer, primary_key=True)
    api_key_id = Column(Integer, ForeignKey('user_api_keys.id'), nullable=False)
    encryption_method = Column(String(50), default='AES-256-GCM')
    key_derivation_method = Column(String(50), default='PBKDF2')
    salt = Column(String(255))
    created_at = Column(DateTime, server_default=func.now())

class UserFeatureAccess(Base):
    __tablename__ = 'user_feature_access'
    
    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    feature_name = Column(String(100), nullable=False)  # s3_storage, transcription, llm_features
    is_enabled = Column(Boolean, default=False)
    monthly_quota = Column(Integer, default=0)
    usage_this_month = Column(Integer, default=0)
    last_reset_at = Column(DateTime, server_default=func.now())
    created_at = Column(DateTime, server_default=func.now())
    updated_at = Column(DateTime, server_default=func.now(), onupdate=func.now())