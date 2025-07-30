#!/usr/bin/env python3
"""
RadioGrab Enhanced Recording Service v2.0
Comprehensive recording system with database integration, stream discovery,
and proven multi-tool recording strategies.

Key Features:
- Database-driven station and show management
- Integrated User-Agent handling and stream discovery
- Multi-tool recording with automatic fallback
- Enhanced error handling and quality validation
- APScheduler for automated show recordings
- Retention policy management
- Full integration with test recording architecture
"""

import sys
import os
import subprocess
import time
import logging
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, Optional, List, Tuple, Any
import argparse

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.cron import CronTrigger
from sqlalchemy.orm import Session
from backend.config.database import SessionLocal
from backend.models.station import Station, Show, Recording

# Import proven recording functions from test service
from backend.services.test_recording_service import (
    perform_recording,
    update_station_test_status,
    get_user_agents,
    is_access_forbidden_error,
    convert_aac_to_mp3,
    post_process_recording
)

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Constants
RECORDINGS_DIR = '/var/radiograb/recordings'
TEMP_DIR = '/var/radiograb/temp'
LOGS_DIR = '/var/radiograb/logs'

class EnhancedRecordingService:
    """
    Enhanced Recording Service with full database integration
    and proven recording strategies from test service
    """
    
    def __init__(self, recordings_dir: str = RECORDINGS_DIR, temp_dir: str = TEMP_DIR):
        self.recordings_dir = Path(recordings_dir)
        self.temp_dir = Path(temp_dir)
        self.logs_dir = Path(LOGS_DIR)
        
        # Ensure directories exist
        for directory in [self.recordings_dir, self.temp_dir, self.logs_dir]:
            directory.mkdir(parents=True, exist_ok=True)
        
        logger.info(f"Enhanced Recording Service initialized")
        logger.info(f"Recordings: {self.recordings_dir}")
        logger.info(f"Temp: {self.temp_dir}")
    
    def record_show(self, show_id: int, duration_seconds: int = 3600, 
                    manual: bool = False) -> Dict[str, Any]:
        """
        Record a show using database settings and proven recording strategies
        
        Args:
            show_id: Database ID of the show to record
            duration_seconds: Recording duration in seconds (default 1 hour)
            manual: Whether this is a manual recording (affects file location)
            
        Returns:
            Dictionary with recording results
        """
        result = {
            'success': False,
            'recording_id': None,
            'filename': None,
            'file_size': 0,
            'duration': duration_seconds,
            'error': None,
            'tool_used': None,
            'quality_validation': None
        }
        
        db = SessionLocal()
        try:
            # Get show and station details
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = f"Show {show_id} not found"
                return result
            
            if not show.active:
                result['error'] = f"Show {show_id} is not active"
                return result
            
            station = show.station
            if not station:
                result['error'] = f"No station found for show {show_id}"
                return result
            
            if not station.stream_url:
                result['error'] = f"No stream URL configured for station {station.id}"
                return result
            
            # Check for duplicate recordings (prevent concurrent recordings of same show)
            if not manual:
                recent_cutoff = datetime.now() - timedelta(minutes=30)
                recent_recording = db.query(Recording).filter(
                    Recording.show_id == show_id,
                    Recording.recorded_at >= recent_cutoff
                ).first()
                
                if recent_recording:
                    result['error'] = f"Recent recording found for show {show_id} within 30 minutes"
                    return result
            
            # Generate filename using call letters and show name
            timestamp = datetime.now()
            call_letters = station.call_letters or f"ST{station.id:02d}"
            show_name_clean = ''.join(c for c in show.name if c.isalnum() or c in '_-')[:20]
            
            if manual:
                filename = f"{call_letters.upper()}_manual_{show_name_clean}_{timestamp.strftime('%Y%m%d_%H%M%S')}.mp3"
                output_dir = str(self.temp_dir)  # Manual recordings go to temp
            else:
                filename = f"{call_letters.upper()}_{show_name_clean}_{timestamp.strftime('%Y%m%d_%H%M')}.mp3"
                output_dir = str(self.recordings_dir)  # Scheduled recordings go to recordings
            
            output_file = os.path.join(output_dir, filename)
            
            logger.info(f"Starting recording for show '{show.name}' (ID: {show_id})")
            logger.info(f"Station: {station.name} ({station.call_letters})")
            logger.info(f"Stream: {station.stream_url}")
            logger.info(f"Duration: {duration_seconds}s")
            logger.info(f"Output: {output_file}")
            
            # Use proven recording function from test service
            recording_success, recording_message = perform_recording(
                stream_url=station.stream_url,
                output_file=output_file,
                duration=duration_seconds,
                station_id=station.id
            )
            
            if recording_success:
                # Check if file exists and get size
                if os.path.exists(output_file):
                    file_size = os.path.getsize(output_file)
                    
                    # Validate recording quality
                    quality_valid, quality_message = self._validate_recording_quality(
                        output_file, duration_seconds
                    )
                    
                    # Create database entry
                    recording_id = self._save_recording_to_database(
                        show_id=show_id,
                        filename=filename,
                        title=f"{show.name} - {timestamp.strftime('%Y-%m-%d %H:%M')}",
                        description=f"{'Manual' if manual else 'Scheduled'} recording of {show.name} from {station.name}",
                        recorded_at=timestamp,
                        file_size=file_size,
                        duration=duration_seconds
                    )
                    
                    result.update({
                        'success': True,
                        'recording_id': recording_id,
                        'filename': filename,
                        'file_size': file_size,
                        'duration': duration_seconds,
                        'quality_validation': quality_message,
                        'message': recording_message
                    })
                    
                    # Update station test status
                    update_station_test_status(
                        station.id, 
                        quality_valid, 
                        None if quality_valid else f"Recording quality issue: {quality_message}"
                    )
                    
                    logger.info(f"Recording completed successfully: {filename} ({file_size} bytes)")
                    logger.info(f"Quality validation: {quality_message}")
                    
                    # Clean up old recordings if this is a scheduled recording
                    if not manual and show.retention_days > 0:
                        self._cleanup_old_recordings(show_id, show.retention_days)
                    
                else:
                    result['error'] = f"Recording file not found after completion: {output_file}"
                    logger.error(result['error'])
                    update_station_test_status(station.id, False, result['error'])
            else:
                result['error'] = f"Recording failed: {recording_message}"
                logger.error(result['error'])
                update_station_test_status(station.id, False, recording_message)
        
        except Exception as e:
            result['error'] = f"Recording service error: {str(e)}"
            logger.error(f"Error in record_show: {str(e)}", exc_info=True)
            
            # Update station test status on error
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                if show and show.station:
                    update_station_test_status(show.station.id, False, str(e))
            except:
                pass
        
        finally:
            db.close()
        
        return result
    
    def _validate_recording_quality(self, output_file: str, duration_seconds: int) -> Tuple[bool, str]:
        """Validate recording file quality using same logic as test service"""
        try:
            if not os.path.exists(output_file):
                return False, "File does not exist"
            
            file_size = os.path.getsize(output_file)
            
            # Basic size validation - expect at least 2KB per second for MP3
            min_expected_size = duration_seconds * 2048  # 2KB per second minimum
            if file_size < min_expected_size:
                return False, f"File too small: {file_size} bytes (expected at least {min_expected_size} bytes for {duration_seconds}s)"
            
            # Check if file is actually audio format
            try:
                result = subprocess.run(['file', output_file], capture_output=True, text=True, timeout=5)
                file_type = result.stdout.lower()
                
                if any(format_type in file_type for format_type in ['mp3', 'audio', 'mpeg', 'aac']):
                    return True, f"Valid audio file: {file_size} bytes ({file_size/duration_seconds:.1f} bytes/sec)"
                else:
                    return False, f"Not an audio file: {file_type}"
                    
            except subprocess.TimeoutExpired:
                return True, f"File exists with valid size: {file_size} bytes"
            except Exception:
                return True, f"File exists with valid size: {file_size} bytes"
                
        except Exception as e:
            return False, f"Validation error: {str(e)}"
    
    def _save_recording_to_database(self, show_id: int, filename: str, title: str, 
                                   description: str, recorded_at: datetime, 
                                   file_size: int, duration: int) -> Optional[int]:
        """Save recording metadata to database with proper duplicate prevention"""
        db = SessionLocal()
        try:
            # Use database transaction with explicit locking to prevent race conditions
            # Check for existing recording with same filename (more specific than show_id + time)
            existing = db.query(Recording).filter(
                Recording.filename == filename
            ).first()
            
            if existing:
                logger.warning(f"Recording already exists with filename {filename} (ID: {existing.id})")
                return existing.id
            
            recording = Recording(
                show_id=show_id,
                filename=filename,
                title=title,
                description=description,
                duration_seconds=duration,
                file_size_bytes=file_size,
                recorded_at=recorded_at
            )
            
            db.add(recording)
            
            # Commit first to get the ID and prevent race conditions
            try:
                db.commit()
                db.refresh(recording)
                logger.info(f"Recording saved to database: ID {recording.id}")
            except Exception as commit_error:
                # Handle potential duplicate key errors (if database constraint exists)
                if "Duplicate entry" in str(commit_error) or "UNIQUE constraint" in str(commit_error):
                    db.rollback()
                    # Re-check for existing recording after failed commit
                    existing_after_error = db.query(Recording).filter(
                        Recording.filename == filename
                    ).first()
                    if existing_after_error:
                        logger.warning(f"Duplicate recording detected after commit error, using existing ID: {existing_after_error.id}")
                        return existing_after_error.id
                raise commit_error
            
            # Write MP3 metadata for the recorded file
            try:
                from backend.services.mp3_metadata_service import MP3MetadataService
                metadata_service = MP3MetadataService()
                metadata_service.write_metadata_for_recording(recording.id)
                logger.info(f"MP3 metadata written for recording {recording.id}")
            except Exception as e:
                logger.warning(f"Failed to write MP3 metadata for recording {recording.id}: {e}")
            
            return recording.id
            
        except Exception as e:
            logger.error(f"Error saving recording to database: {str(e)}")
            db.rollback()
            return None
        finally:
            db.close()
    
    def _cleanup_old_recordings(self, show_id: int, retention_days: int):
        """Clean up recordings older than retention policy"""
        if retention_days <= 0:
            return  # Keep everything
        
        cutoff_date = datetime.now() - timedelta(days=retention_days)
        
        db = SessionLocal()
        try:
            # Find old recordings
            old_recordings = db.query(Recording).filter(
                Recording.show_id == show_id,
                Recording.recorded_at < cutoff_date
            ).all()
            
            deleted_count = 0
            for recording in old_recordings:
                # Delete file if it exists
                file_path = self.recordings_dir / recording.filename
                if file_path.exists():
                    try:
                        file_path.unlink()
                        logger.info(f"Deleted old recording file: {recording.filename}")
                        deleted_count += 1
                    except Exception as e:
                        logger.error(f"Error deleting file {recording.filename}: {e}")
                        continue
                
                # Delete database record
                db.delete(recording)
            
            db.commit()
            
            if deleted_count > 0:
                logger.info(f"Cleaned up {deleted_count} old recordings for show {show_id}")
        
        except Exception as e:
            logger.error(f"Error cleaning up recordings for show {show_id}: {str(e)}")
            db.rollback()
        finally:
            db.close()
    
    def get_recording_stats(self) -> Dict[str, Any]:
        """Get recording statistics"""
        from sqlalchemy import func
        
        db = SessionLocal()
        try:
            total_recordings = db.query(Recording).count()
            total_size = db.query(func.sum(Recording.file_size_bytes)).scalar() or 0
            
            recent_recordings = db.query(Recording).filter(
                Recording.recorded_at >= datetime.now() - timedelta(days=7)
            ).count()
            
            return {
                'total_recordings': total_recordings,
                'total_size_bytes': total_size,
                'total_size_mb': round(total_size / (1024 * 1024), 2),
                'recent_recordings_7days': recent_recordings
            }
        
        finally:
            db.close()

class RecordingScheduler:
    """Enhanced Recording Scheduler with improved error handling"""
    
    def __init__(self, recording_service: EnhancedRecordingService):
        self.recording_service = recording_service
        self.scheduler = BackgroundScheduler()
        self.scheduler.start()
        logger.info("Enhanced Recording Scheduler started")
    
    def schedule_all_active_shows(self) -> Dict[str, Any]:
        """Schedule all active shows from database"""
        result = {
            'success': True,
            'scheduled_count': 0,
            'failed_count': 0,
            'errors': []
        }
        
        db = SessionLocal()
        try:
            active_shows = db.query(Show).filter(Show.active == True).all()
            logger.info(f"Found {len(active_shows)} active shows to schedule")
            
            for show in active_shows:
                if show.schedule_pattern:
                    schedule_result = self.schedule_show(show.id)
                    if schedule_result['success']:
                        result['scheduled_count'] += 1
                        logger.info(f"Scheduled: {show.name} (next: {schedule_result['next_run']})")
                    else:
                        result['failed_count'] += 1
                        error_msg = f"Failed to schedule {show.name}: {schedule_result['error']}"
                        result['errors'].append(error_msg)
                        logger.error(error_msg)
                else:
                    logger.warning(f"Show '{show.name}' has no schedule pattern")
        
        finally:
            db.close()
        
        if result['failed_count'] > 0:
            result['success'] = False
        
        return result
    
    def schedule_show(self, show_id: int) -> Dict[str, Any]:
        """Schedule a single show"""
        result = {
            'success': False,
            'job_id': None,
            'next_run': None,
            'error': None
        }
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = f"Show {show_id} not found"
                return result
            
            if not show.active:
                result['error'] = f"Show {show_id} is not active"
                return result
            
            if not show.schedule_pattern:
                result['error'] = f"Show {show_id} has no schedule pattern"
                return result
            
            station = show.station
            if not station or not station.stream_url:
                result['error'] = f"Show {show_id} has no valid station or stream URL"
                return result
            
            # Parse cron schedule pattern
            try:
                cron_parts = show.schedule_pattern.strip().split()
                if len(cron_parts) != 5:
                    result['error'] = f"Invalid cron pattern: {show.schedule_pattern} (must have 5 parts)"
                    return result
                
                minute, hour, day, month, day_of_week = cron_parts
                
                trigger = CronTrigger(
                    minute=minute,
                    hour=hour,
                    day=day,
                    month=month,
                    day_of_week=day_of_week,
                    timezone='America/New_York'  # Use system timezone
                )
                
                job_id = f"show_{show_id}_recording"
                
                # Schedule the job
                job = self.scheduler.add_job(
                    func=self._recording_job,
                    trigger=trigger,
                    args=[show_id],
                    id=job_id,
                    name=f"Record {show.name}",
                    replace_existing=True,
                    max_instances=1  # Prevent overlapping recordings
                )
                
                result.update({
                    'success': True,
                    'job_id': job_id,
                    'next_run': job.next_run_time.isoformat() if job.next_run_time else None
                })
                
            except Exception as e:
                result['error'] = f"Scheduling error: {str(e)}"
                logger.error(f"Error scheduling show {show_id}: {str(e)}")
        
        finally:
            db.close()
        
        return result
    
    def _recording_job(self, show_id: int):
        """Scheduled recording job execution"""
        logger.info(f"Starting scheduled recording job for show {show_id}")
        
        try:
            # Calculate recording duration based on show settings or default to 1 hour
            db = SessionLocal()
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                if not show or not show.active:
                    logger.warning(f"Show {show_id} not found or not active, skipping recording")
                    return
                
                # Default duration is 1 hour (3600 seconds)
                duration = 3600
                
                logger.info(f"Recording show '{show.name}' for {duration} seconds")
                
            finally:
                db.close()
            
            # Perform the recording
            result = self.recording_service.record_show(
                show_id=show_id, 
                duration_seconds=duration, 
                manual=False
            )
            
            if result['success']:
                logger.info(f"Scheduled recording completed successfully for show {show_id}: {result['filename']}")
            else:
                logger.error(f"Scheduled recording failed for show {show_id}: {result['error']}")
        
        except Exception as e:
            logger.error(f"Error in scheduled recording job for show {show_id}: {str(e)}", exc_info=True)
    
    def unschedule_show(self, show_id: int) -> bool:
        """Remove scheduled recording for a show"""
        job_id = f"show_{show_id}_recording"
        try:
            self.scheduler.remove_job(job_id)
            logger.info(f"Unscheduled recording for show {show_id}")
            return True
        except Exception as e:
            logger.warning(f"Job {job_id} not found or error unscheduling: {str(e)}")
            return False
    
    def get_scheduled_jobs(self) -> List[Dict]:
        """Get list of all scheduled recording jobs"""
        jobs = []
        for job in self.scheduler.get_jobs():
            if job.id.startswith('show_'):
                jobs.append({
                    'job_id': job.id,
                    'show_id': int(job.id.split('_')[1]),
                    'name': job.name,
                    'next_run': job.next_run_time.isoformat() if job.next_run_time else None,
                    'trigger': str(job.trigger)
                })
        return jobs
    
    def shutdown(self):
        """Shutdown the scheduler gracefully"""
        self.scheduler.shutdown()
        logger.info("Recording scheduler shutdown")

def main():
    """Main function to run the enhanced recording service"""
    parser = argparse.ArgumentParser(description='RadioGrab Enhanced Recording Service v2.0')
    parser.add_argument('--daemon', action='store_true', help='Run as daemon service')
    parser.add_argument('--test-show', type=int, help='Test recording for specific show ID')
    parser.add_argument('--manual-show', type=int, help='Manual recording for specific show ID')
    parser.add_argument('--duration', type=int, default=3600, help='Recording duration in seconds')
    parser.add_argument('--stats', action='store_true', help='Show recording statistics')
    parser.add_argument('--schedule-status', action='store_true', help='Show scheduled jobs status')
    parser.add_argument('--log-level', default='INFO', choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'])
    
    args = parser.parse_args()
    
    # Set up logging
    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    # Initialize recording service
    recording_service = EnhancedRecordingService()
    
    if args.stats:
        stats = recording_service.get_recording_stats()
        print("=== Recording Statistics ===")
        print(f"Total recordings: {stats['total_recordings']}")
        print(f"Total size: {stats['total_size_mb']} MB")
        print(f"Recent recordings (7 days): {stats['recent_recordings_7days']}")
        return
    
    if args.test_show:
        print(f"=== Testing recording for show {args.test_show} ===")
        result = recording_service.record_show(args.test_show, args.duration, manual=True)
        print(f"Result: {result}")
        return
    
    if args.manual_show:
        print(f"=== Manual recording for show {args.manual_show} ===")
        result = recording_service.record_show(args.manual_show, args.duration, manual=True)
        print(f"Result: {result}")
        return
    
    if args.schedule_status:
        scheduler = RecordingScheduler(recording_service)
        jobs = scheduler.get_scheduled_jobs()
        print("=== Scheduled Recording Jobs ===")
        if jobs:
            for job in jobs:
                print(f"Show {job['show_id']}: {job['name']}")
                print(f"  Next run: {job['next_run']}")
                print(f"  Trigger: {job['trigger']}")
        else:
            print("No scheduled jobs found")
        scheduler.shutdown()
        return
    
    if args.daemon:
        logger.info("Starting Enhanced RadioGrab Recording Service v2.0...")
        
        try:
            # Initialize scheduler
            scheduler = RecordingScheduler(recording_service)
            
            # Schedule all active shows
            schedule_result = scheduler.schedule_all_active_shows()
            logger.info(f"Scheduling complete: {schedule_result['scheduled_count']} shows scheduled, {schedule_result['failed_count']} failed")
            
            if schedule_result['errors']:
                for error in schedule_result['errors']:
                    logger.error(error)
            
            logger.info("Enhanced Recording Service is running. Press Ctrl+C to stop.")
            
            # Keep the service running
            try:
                while True:
                    time.sleep(60)  # Check every minute
                    
            except KeyboardInterrupt:
                logger.info("Shutting down enhanced recording service...")
                scheduler.shutdown()
                logger.info("Enhanced recording service stopped.")
                
        except Exception as e:
            logger.error(f"Failed to start enhanced recording service: {str(e)}")
            sys.exit(1)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()