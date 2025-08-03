"""
Defines the SQLAlchemy ORM models for the RadioGrab application.

This script contains the database schema for `Station`, `Show`, `Recording`, and
`CronJob` entities. These models represent the core data structures of the application
and are used by various services to interact with the database.

Key Models:
- `Station`: Represents a radio station with its details and stream information.
- `Show`: Represents a radio show with its schedule, metadata, and associated station.
- `Recording`: Represents a recorded audio file, linked to a specific show.
- `CronJob`: Represents a scheduled cron job for recordings.

Inter-script Communication:
- This script is imported by all other Python scripts that need to interact with the database models.
- It relies on `backend.config.database.Base` for ORM functionality.
"""

from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, ForeignKey, Float
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from backend.config.database import Base

class Station(Base):
    __tablename__ = "stations"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    call_letters = Column(String(10), nullable=True)
    website_url = Column(String(500), nullable=False)
    stream_url = Column(String(500), nullable=True)
    logo_url = Column(String(500), nullable=True)
    calendar_url = Column(String(500), nullable=True)
    calendar_parsing_method = Column(Text, nullable=True)
    status = Column(String(50), default='active')
    
    # Stream testing fields
    recommended_recording_tool = Column(String(50), nullable=True)  # streamripper, ffmpeg, wget
    stream_compatibility = Column(String(20), default='unknown')    # compatible, incompatible, unknown
    stream_test_results = Column(Text, nullable=True)               # JSON test results
    last_stream_test = Column(DateTime(timezone=True), nullable=True)
    user_agent = Column(String(500), nullable=True)                # Required User-Agent for this stream
    
    # Station testing tracking (updated on any recording or test)
    last_tested = Column(DateTime(timezone=True), nullable=True)    # Last successful recording/test
    last_test_result = Column(String(20), nullable=True)            # success, failed, error
    last_test_error = Column(Text, nullable=True)                   # Error message if failed
    
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    shows = relationship("Show", back_populates="station")

class Show(Base):
    __tablename__ = "shows"
    
    id = Column(Integer, primary_key=True, index=True)
    station_id = Column(Integer, ForeignKey("stations.id"), nullable=False)
    name = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    long_description = Column(Text, nullable=True)  # Extended description from website
    host = Column(String(255), nullable=True)
    genre = Column(String(100), nullable=True)  # Show genre/category
    image_url = Column(String(500), nullable=True)  # Show-specific image
    website_url = Column(String(500), nullable=True)  # Direct show page URL
    
    # Metadata tracking
    description_source = Column(String(50), nullable=True)  # 'calendar', 'website', 'manual', 'generated'
    image_source = Column(String(50), nullable=True)  # 'calendar', 'website', 'station', 'default'
    metadata_json = Column(Text, nullable=True)  # Extended metadata as JSON
    metadata_updated = Column(DateTime(timezone=True), nullable=True)  # Last metadata update
    
    # Show type and scheduling
    show_type = Column(String(20), default='scheduled')  # 'scheduled' or 'playlist'
    schedule_pattern = Column(String(255), nullable=True)  # Cron-like pattern (nullable for playlists)
    schedule_description = Column(String(500), nullable=True)  # Human readable
    retention_days = Column(Integer, default=30)  # 0 = never expire (for playlists)
    audio_format = Column(String(10), default='mp3')
    active = Column(Boolean, default=True)
    
    # Upload/playlist specific fields
    allow_uploads = Column(Boolean, default=False)  # Allow user uploads to this show
    max_file_size_mb = Column(Integer, default=100)  # Max upload size in MB
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    station = relationship("Station", back_populates="shows")
    recordings = relationship("Recording", back_populates="show")
    cron_jobs = relationship("CronJob", back_populates="show")

class Recording(Base):
    __tablename__ = "recordings"
    
    id = Column(Integer, primary_key=True, index=True)
    show_id = Column(Integer, ForeignKey("shows.id"), nullable=False)
    filename = Column(String(255), nullable=False)
    title = Column(String(255), nullable=True)
    description = Column(Text, nullable=True)
    duration_seconds = Column(Integer, nullable=True)
    file_size_bytes = Column(Integer, nullable=True)
    recorded_at = Column(DateTime(timezone=True), nullable=False)
    
    # Upload/source tracking
    source_type = Column(String(20), default='recorded')  # 'recorded' or 'uploaded'
    uploaded_by = Column(String(100), nullable=True)  # User who uploaded (future auth)
    original_filename = Column(String(255), nullable=True)  # Original upload filename
    track_number = Column(Integer, nullable=True)  # Track order in playlist (NULL for regular recordings)
    
    # Transcription fields
    transcript_file = Column(String(500), nullable=True)  # Path to transcript file
    transcript_provider = Column(String(50), nullable=True)  # Provider used for transcription
    transcript_generated_at = Column(DateTime(timezone=True), nullable=True)
    transcript_cost = Column(Float, nullable=True)  # Cost of transcription
    
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    # Relationships
    show = relationship("Show", back_populates="recordings")

class CronJob(Base):
    __tablename__ = "cron_jobs"
    
    id = Column(Integer, primary_key=True, index=True)
    show_id = Column(Integer, ForeignKey("shows.id"), nullable=False)
    cron_expression = Column(String(100), nullable=False)
    command = Column(Text, nullable=False)
    status = Column(String(50), default='active')
    last_run = Column(DateTime(timezone=True), nullable=True)
    next_run = Column(DateTime(timezone=True), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    # Relationships
    show = relationship("Show", back_populates="cron_jobs")