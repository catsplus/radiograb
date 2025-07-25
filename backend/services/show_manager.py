"""
Show Manager Service
High-level service for managing radio shows and their recording schedules
"""
from sqlalchemy.orm import Session
from backend.config.database import SessionLocal
from backend.models.station import Station, Show, Recording
from backend.services.schedule_parser import ScheduleParser
from backend.services.recording_service import RecordingScheduler, AudioRecorder
import logging
from typing import Dict, List, Optional
from datetime import datetime

logger = logging.getLogger(__name__)

class ShowManager:
    """Manages radio shows and their recording schedules"""
    
    def __init__(self):
        self.schedule_parser = ScheduleParser()
        self.recorder = AudioRecorder()
        self.scheduler = RecordingScheduler(self.recorder)
    
    def add_show(self, 
                 station_id: int,
                 name: str,
                 schedule_text: str,
                 description: str = None,
                 host: str = None,
                 retention_days: int = 30,
                 audio_format: str = 'mp3') -> Dict:
        """
        Add a new show with natural language scheduling
        
        Args:
            station_id: ID of the station this show belongs to
            name: Name of the show
            schedule_text: Natural language schedule (e.g., "Every Tuesday at 7 PM")
            description: Optional description of the show
            host: Optional host name
            retention_days: How many days to keep recordings
            audio_format: Audio format (mp3, aac, etc.)
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'show_id': None,
            'schedule_info': {},
            'errors': [],
            'warnings': []
        }
        
        try:
            # Parse the schedule
            schedule_result = self.schedule_parser.parse_schedule(schedule_text)
            if not schedule_result['success']:
                result['errors'].append(f"Schedule parsing failed: {schedule_result['error']}")
                return result
            
            result['schedule_info'] = schedule_result
            
            # Validate station exists
            db = SessionLocal()
            try:
                station = db.query(Station).filter(Station.id == station_id).first()
                if not station:
                    result['errors'].append("Station not found")
                    return result
                
                if not station.stream_url:
                    result['warnings'].append("Station has no streaming URL configured")
                
                # Check for duplicate show name
                existing = db.query(Show).filter(
                    Show.station_id == station_id,
                    Show.name == name
                ).first()
                if existing:
                    result['errors'].append("Show with this name already exists for this station")
                    return result
                
                # Create the show
                show = Show(
                    station_id=station_id,
                    name=name,
                    description=description,
                    host=host,
                    schedule_pattern=schedule_result['cron_expression'],
                    schedule_description=schedule_result['description'],
                    retention_days=retention_days,
                    audio_format=audio_format,
                    active=True
                )
                
                db.add(show)
                db.commit()
                db.refresh(show)
                
                result['show_id'] = show.id
                
                # Schedule the recording
                if station.stream_url:
                    schedule_job_result = self.scheduler.schedule_show_recording(show.id)
                    if schedule_job_result['success']:
                        logger.info(f"Show '{name}' scheduled successfully")
                    else:
                        result['warnings'].append(f"Show created but scheduling failed: {schedule_job_result['error']}")
                else:
                    result['warnings'].append("Show created but not scheduled (no stream URL)")
                
                result['success'] = True
                logger.info(f"Show '{name}' added successfully (ID: {show.id})")
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error adding show: {str(e)}")
            result['errors'].append(f"Database error: {str(e)}")
        
        return result
    
    def update_show_schedule(self, show_id: int, new_schedule_text: str) -> Dict:
        """
        Update the schedule for an existing show
        
        Args:
            show_id: ID of the show to update
            new_schedule_text: New schedule in natural language
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'schedule_info': {},
            'errors': []
        }
        
        try:
            # Parse the new schedule
            schedule_result = self.schedule_parser.parse_schedule(new_schedule_text)
            if not schedule_result['success']:
                result['errors'].append(f"Schedule parsing failed: {schedule_result['error']}")
                return result
            
            result['schedule_info'] = schedule_result
            
            db = SessionLocal()
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                if not show:
                    result['errors'].append("Show not found")
                    return result
                
                # Update the schedule
                show.schedule_pattern = schedule_result['cron_expression']
                show.schedule_description = schedule_result['description']
                
                db.commit()
                
                # Reschedule the recording
                self.scheduler.unschedule_show(show_id)
                if show.station.stream_url and show.active:
                    schedule_job_result = self.scheduler.schedule_show_recording(show_id)
                    if not schedule_job_result['success']:
                        result['errors'].append(f"Rescheduling failed: {schedule_job_result['error']}")
                        return result
                
                result['success'] = True
                logger.info(f"Show schedule updated: {show.name}")
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error updating show schedule: {str(e)}")
            result['errors'].append(f"Update error: {str(e)}")
        
        return result
    
    def toggle_show_active(self, show_id: int, active: bool) -> Dict:
        """
        Enable or disable a show
        
        Args:
            show_id: ID of the show
            active: True to enable, False to disable
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'error': None
        }
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = "Show not found"
                return result
            
            show.active = active
            db.commit()
            
            # Update scheduling
            if active and show.station.stream_url:
                schedule_result = self.scheduler.schedule_show_recording(show_id)
                if not schedule_result['success']:
                    result['error'] = f"Show enabled but scheduling failed: {schedule_result['error']}"
                    return result
            else:
                self.scheduler.unschedule_show(show_id)
            
            result['success'] = True
            action = "enabled" if active else "disabled"
            logger.info(f"Show '{show.name}' {action}")
            
        except Exception as e:
            logger.error(f"Error toggling show {show_id}: {str(e)}")
            result['error'] = f"Toggle error: {str(e)}"
        finally:
            db.close()
        
        return result
    
    def delete_show(self, show_id: int) -> Dict:
        """
        Delete a show and all its recordings
        
        Args:
            show_id: ID of the show to delete
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'error': None,
            'recordings_deleted': 0
        }
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = "Show not found"
                return result
            
            show_name = show.name
            
            # Unschedule any recordings
            self.scheduler.unschedule_show(show_id)
            
            # Delete recording files
            recordings = db.query(Recording).filter(Recording.show_id == show_id).all()
            for recording in recordings:
                file_path = self.recorder.recordings_dir / recording.filename
                if file_path.exists():
                    file_path.unlink()
            
            result['recordings_deleted'] = len(recordings)
            
            # Delete the show (cascade will handle recordings)
            db.delete(show)
            db.commit()
            
            result['success'] = True
            logger.info(f"Show '{show_name}' deleted with {result['recordings_deleted']} recordings")
            
        except Exception as e:
            logger.error(f"Error deleting show {show_id}: {str(e)}")
            result['error'] = f"Delete error: {str(e)}"
        finally:
            db.close()
        
        return result
    
    def get_shows(self, station_id: int = None) -> List[Dict]:
        """
        Get list of shows, optionally filtered by station
        
        Args:
            station_id: Optional station ID to filter by
            
        Returns:
            List of show dictionaries
        """
        db = SessionLocal()
        try:
            query = db.query(Show)
            if station_id:
                query = query.filter(Show.station_id == station_id)
            
            shows = query.all()
            
            result = []
            for show in shows:
                # Get recording count
                recording_count = db.query(Recording).filter(Recording.show_id == show.id).count()
                
                show_dict = {
                    'id': show.id,
                    'station_id': show.station_id,
                    'station_name': show.station.name,
                    'name': show.name,
                    'description': show.description,
                    'host': show.host,
                    'schedule_pattern': show.schedule_pattern,
                    'schedule_description': show.schedule_description,
                    'retention_days': show.retention_days,
                    'audio_format': show.audio_format,
                    'active': show.active,
                    'recording_count': recording_count,
                    'created_at': show.created_at.isoformat() if show.created_at else None
                }
                result.append(show_dict)
            
            return result
            
        except Exception as e:
            logger.error(f"Error getting shows: {str(e)}")
            return []
        finally:
            db.close()
    
    def get_show_recordings(self, show_id: int, limit: int = 50) -> List[Dict]:
        """
        Get recordings for a specific show
        
        Args:
            show_id: ID of the show
            limit: Maximum number of recordings to return
            
        Returns:
            List of recording dictionaries
        """
        db = SessionLocal()
        try:
            recordings = db.query(Recording).filter(
                Recording.show_id == show_id
            ).order_by(Recording.recorded_at.desc()).limit(limit).all()
            
            result = []
            for recording in recordings:
                recording_dict = {
                    'id': recording.id,
                    'filename': recording.filename,
                    'title': recording.title,
                    'description': recording.description,
                    'duration_seconds': recording.duration_seconds,
                    'file_size_bytes': recording.file_size_bytes,
                    'recorded_at': recording.recorded_at.isoformat() if recording.recorded_at else None,
                    'file_exists': (self.recorder.recordings_dir / recording.filename).exists()
                }
                result.append(recording_dict)
            
            return result
            
        except Exception as e:
            logger.error(f"Error getting recordings for show {show_id}: {str(e)}")
            return []
        finally:
            db.close()
    
    def test_show_recording(self, show_id: int, duration_seconds: int = 30) -> Dict:
        """
        Test record a show for a short duration
        
        Args:
            show_id: ID of the show to test
            duration_seconds: Test recording duration
            
        Returns:
            Dictionary with test results
        """
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                return {'success': False, 'error': 'Show not found'}
            
            if not show.station.stream_url:
                return {'success': False, 'error': 'No stream URL configured'}
            
            # Generate test filename
            timestamp = datetime.now()
            filename = f"{show.station_id}_test_{timestamp.strftime('%Y-%m-%d-%H%M%S')}"
            
            # Record test
            result = self.recorder.record_stream(
                stream_url=show.station.stream_url,
                duration_seconds=duration_seconds,
                output_filename=filename,
                show_id=None,  # Don't save test recordings to DB
                title=f"Test Recording - {show.name}",
                description=f"Test recording for {show.name}"
            )
            
            return result
            
        except Exception as e:
            logger.error(f"Error testing show recording: {str(e)}")
            return {'success': False, 'error': f'Test error: {str(e)}'}
        finally:
            db.close()
    
    def shutdown(self):
        """Shutdown the show manager and its services"""
        self.scheduler.shutdown()
        logger.info("Show manager shutdown")

def test_show_manager():
    """Test the show manager"""
    manager = ShowManager()
    
    print("=== Testing Show Manager ===")
    
    # Test schedule parsing
    test_schedules = [
        "Every Tuesday at 7 PM",
        "Daily at 6 AM",
        "Weekdays at 9:30 AM"
    ]
    
    for schedule in test_schedules:
        print(f"\nTesting schedule: '{schedule}'")
        result = manager.schedule_parser.parse_schedule(schedule)
        if result['success']:
            print(f"✅ Cron: {result['cron_expression']}")
            print(f"   Description: {result['description']}")
        else:
            print(f"❌ Error: {result['error']}")
    
    manager.shutdown()

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_show_manager()