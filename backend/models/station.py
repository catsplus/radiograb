"""
Database models for RadioGrab
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, ForeignKey
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from backend.config.database import Base

class Station(Base):
    __tablename__ = "stations"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
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
    host = Column(String(255), nullable=True)
    schedule_pattern = Column(String(255), nullable=False)  # Cron-like pattern
    schedule_description = Column(String(500), nullable=True)  # Human readable
    retention_days = Column(Integer, default=30)
    audio_format = Column(String(10), default='mp3')
    active = Column(Boolean, default=True)
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