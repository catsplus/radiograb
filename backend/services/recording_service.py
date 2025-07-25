"""
Recording Service
Enhanced version of the original PHP ripper script with Python
Handles audio recording from streaming URLs using multiple tools
Supports streamripper, wget, and ffmpeg based on station compatibility
"""
import os
import subprocess
import threading
import time
import tempfile
from datetime import datetime, timedelta
from pathlib import Path
import logging
from typing import Dict, Optional, List
from sqlalchemy.orm import Session
from backend.config.database import SessionLocal
from backend.models.station import Station, Show, Recording
import shutil

logger = logging.getLogger(__name__)

class AudioRecorder:
    """Handles audio recording from streaming URLs using multiple tools"""
    
    def __init__(self, 
                 streamripper_path: str = "/usr/bin/streamripper",
                 recordings_dir: str = "./recordings",
                 temp_dir: str = "./temp"):
        self.tools = {
            'streamripper': streamripper_path,
            'ffmpeg': '/usr/bin/ffmpeg',
            'wget': '/usr/bin/wget'
        }
        self.recordings_dir = Path(recordings_dir)
        self.temp_dir = Path(temp_dir)
        
        # Create directories if they don't exist
        self.recordings_dir.mkdir(parents=True, exist_ok=True)
        self.temp_dir.mkdir(parents=True, exist_ok=True)
        
        # Check tool availability
        self._check_tools()
    
    def _check_tools(self):
        """Check availability of recording tools"""
        self.available_tools = {}
        
        for tool_name, tool_path in self.tools.items():
            try:
                if tool_name == 'streamripper':
                    result = subprocess.run([tool_path, "--version"], 
                                          capture_output=True, text=True, timeout=5)
                elif tool_name == 'ffmpeg':
                    result = subprocess.run([tool_path, "-version"], 
                                          capture_output=True, text=True, timeout=5)
                elif tool_name == 'wget':
                    result = subprocess.run([tool_path, "--version"], 
                                          capture_output=True, text=True, timeout=5)
                
                self.available_tools[tool_name] = result.returncode == 0
                if result.returncode == 0:
                    logger.info(f"{tool_name} available at {tool_path}")
                else:
                    logger.warning(f"{tool_name} not working at {tool_path}")
                    
            except (subprocess.TimeoutExpired, FileNotFoundError):
                self.available_tools[tool_name] = False
                logger.warning(f"{tool_name} not found at {tool_path}")
    
    def _get_station_recommended_tool(self, show_id: int) -> Optional[str]:
        """Get recommended recording tool for station from database"""
        try:
            db = SessionLocal()
            show = db.query(Show).filter(Show.id == show_id).first()
            if show and show.station:
                recommended_tool = show.station.recommended_recording_tool
                if recommended_tool and self.available_tools.get(recommended_tool, False):
                    return recommended_tool
            db.close()
        except Exception as e:
            logger.error(f"Error getting recommended tool: {e}")
        return None
    
    def record_stream(self, 
                     stream_url: str, 
                     duration_seconds: int,
                     output_filename: str,
                     show_id: Optional[int] = None,
                     title: Optional[str] = None,
                     description: Optional[str] = None) -> Dict:
        """
        Record a stream for specified duration
        
        Args:
            stream_url: The streaming URL to record
            duration_seconds: How long to record in seconds
            output_filename: Name for the output file
            show_id: Database ID of the show being recorded
            title: Title for the recording
            description: Description for the recording
            
        Returns:
            Dictionary with recording results
        """
        result = {
            'success': False,
            'output_file': None,
            'file_size': 0,
            'duration': duration_seconds,
            'error': None,
            'recording_id': None
        }
        
        try:
            # Prepare output file path
            timestamp = datetime.now()
            safe_filename = self._sanitize_filename(output_filename)
            output_path = self.recordings_dir / safe_filename
            
            logger.info(f"Starting recording: {stream_url} -> {output_path} ({duration_seconds}s)")
            
            # Determine which tool to use
            recommended_tool = self._get_station_recommended_tool(show_id) if show_id else None
            tool_to_use = recommended_tool or 'streamripper'  # Default to streamripper
            
            # Ensure tool is available
            if not self.available_tools.get(tool_to_use, False):
                # Fallback to first available tool
                for tool in ['streamripper', 'ffmpeg', 'wget']:
                    if self.available_tools.get(tool, False):
                        tool_to_use = tool
                        break
                else:
                    result['error'] = "No recording tools available"
                    return result
            
            logger.info(f"Using {tool_to_use} for recording")
            
            # Build command based on tool
            if tool_to_use == 'streamripper':
                cmd = self._build_streamripper_command(stream_url, output_path.parent, duration_seconds, safe_filename)
                expected_output = output_path
            elif tool_to_use == 'ffmpeg':
                cmd = self._build_ffmpeg_command(stream_url, output_path, duration_seconds)
                expected_output = output_path
            elif tool_to_use == 'wget':
                cmd = self._build_wget_command(stream_url, output_path, duration_seconds)
                expected_output = output_path
            
            # Start recording
            start_time = time.time()
            process = subprocess.run(cmd, capture_output=True, text=True, timeout=duration_seconds + 60)
            actual_duration = int(time.time() - start_time)
            
            # Check if recording was successful
            if expected_output.exists():
                file_size = expected_output.stat().st_size
                if file_size > 0:  # Ensure file is not empty
                    result.update({
                        'success': True,
                        'output_file': str(expected_output),
                        'file_size': file_size,
                        'duration': actual_duration,
                        'tool_used': tool_to_use
                    })
                    
                    # Save to database
                    if show_id:
                        recording_id = self._save_recording_to_db(
                            show_id, safe_filename, title, description, 
                            timestamp, file_size, actual_duration
                        )
                        result['recording_id'] = recording_id
                    
                    logger.info(f"Recording completed with {tool_to_use}: {expected_output} ({file_size} bytes)")
                else:
                    result['error'] = f"Output file is empty (0 bytes). Tool: {tool_to_use}, Output: {process.stderr}"
                    logger.error(f"Recording failed: {result['error']}")
            else:
                result['error'] = f"Output file not created. Tool: {tool_to_use}, Output: {process.stderr}"
                logger.error(f"Recording failed: {result['error']}")
        
        except subprocess.TimeoutExpired:
            result['error'] = "Recording timeout"
            logger.error("Recording timeout")
        except Exception as e:
            result['error'] = f"Recording error: {str(e)}"
            logger.error(f"Recording error: {str(e)}")
        
        return result
    
    def _build_streamripper_command(self, 
                                   stream_url: str, 
                                   output_dir: Path, 
                                   duration: int,
                                   filename: str) -> List[str]:
        """Build the streamripper command"""
        
        # Basic command
        cmd = [
            self.tools['streamripper'],
            stream_url,
            "-d", str(output_dir),
            "-l", str(duration),  # Length in seconds
            "-a", filename,       # Output filename
            "-A",                 # Don't create individual track files
            "-s",                 # Silent mode
            "--quiet"            # Minimal output
        ]
        
        return cmd
    
    def _build_ffmpeg_command(self, 
                             stream_url: str, 
                             output_path: Path, 
                             duration: int) -> List[str]:
        """Build the ffmpeg command"""
        cmd = [
            self.tools['ffmpeg'], '-y',  # Overwrite output files
            '-i', stream_url,
            '-t', str(duration),         # Duration in seconds
            '-acodec', 'mp3',           # Audio codec
            '-ab', '128k',              # Audio bitrate
            '-f', 'mp3',                # Output format
            '-loglevel', 'error',       # Minimal output
            str(output_path)
        ]
        
        return cmd
    
    def _build_wget_command(self, 
                           stream_url: str, 
                           output_path: Path, 
                           duration: int) -> List[str]:
        """Build the wget command"""
        cmd = [
            'timeout', str(duration),    # Use timeout to limit duration
            self.tools['wget'],
            '-O', str(output_path),     # Output file
            '--user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            '--no-check-certificate',   # Ignore SSL certificate issues
            '--timeout=10',             # Connection timeout
            stream_url
        ]
        
        return cmd
    
    def _sanitize_filename(self, filename: str) -> str:
        """Sanitize filename for filesystem safety"""
        # Remove/replace problematic characters
        safe_chars = "-_.() abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        sanitized = ''.join(c for c in filename if c in safe_chars)
        
        # Ensure it has an extension
        if not sanitized.endswith('.mp3'):
            sanitized += '.mp3'
        
        # Limit length
        if len(sanitized) > 200:
            name_part = sanitized[:-4][:196]  # -4 for .mp3
            sanitized = name_part + '.mp3'
        
        return sanitized
    
    def _save_recording_to_db(self, 
                             show_id: int, 
                             filename: str,
                             title: Optional[str],
                             description: Optional[str],
                             recorded_at: datetime,
                             file_size: int,
                             duration: int) -> Optional[int]:
        """Save recording metadata to database"""
        db = SessionLocal()
        try:
            recording = Recording(
                show_id=show_id,
                filename=filename,
                title=title or filename,
                description=description,
                duration_seconds=duration,
                file_size_bytes=file_size,
                recorded_at=recorded_at
            )
            
            db.add(recording)
            db.commit()
            db.refresh(recording)
            
            logger.info(f"Recording saved to database: ID {recording.id}")
            return recording.id
            
        except Exception as e:
            logger.error(f"Error saving recording to database: {str(e)}")
            db.rollback()
            return None
        finally:
            db.close()

class RecordingScheduler:
    """Manages scheduled recordings using APScheduler"""
    
    def __init__(self, recorder: AudioRecorder):
        from apscheduler.schedulers.background import BackgroundScheduler
        
        self.recorder = recorder
        self.scheduler = BackgroundScheduler()
        self.scheduler.start()
        logger.info("Recording scheduler started")
    
    def schedule_show_recording(self, show_id: int) -> Dict:
        """
        Schedule recordings for a show based on its schedule pattern
        
        Args:
            show_id: Database ID of the show to schedule
            
        Returns:
            Dictionary with scheduling results
        """
        result = {
            'success': False,
            'job_id': None,
            'next_run': None,
            'error': None
        }
        
        db = SessionLocal()
        try:
            # Get show details
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = "Show not found"
                return result
            
            station = show.station
            if not station or not station.stream_url:
                result['error'] = "Station or stream URL not found"
                return result
            
            # Parse schedule pattern (assume it's a cron expression)
            try:
                from apscheduler.triggers.cron import CronTrigger
                
                # Convert schedule pattern to cron trigger
                cron_parts = show.schedule_pattern.split()
                if len(cron_parts) != 5:
                    result['error'] = f"Invalid cron pattern: {show.schedule_pattern}"
                    return result
                
                minute, hour, day, month, day_of_week = cron_parts
                
                trigger = CronTrigger(
                    minute=minute,
                    hour=hour,
                    day=day,
                    month=month,
                    day_of_week=day_of_week
                )
                
                # Create job ID
                job_id = f"show_{show_id}_recording"
                
                # Schedule the job
                job = self.scheduler.add_job(
                    func=self._record_show_job,
                    trigger=trigger,
                    args=[show_id],
                    id=job_id,
                    name=f"Record {show.name}",
                    replace_existing=True
                )
                
                result.update({
                    'success': True,
                    'job_id': job_id,
                    'next_run': job.next_run_time.isoformat() if job.next_run_time else None
                })
                
                logger.info(f"Scheduled recording for show '{show.name}' (ID: {show_id})")
                
            except Exception as e:
                result['error'] = f"Scheduling error: {str(e)}"
                logger.error(f"Error scheduling show {show_id}: {str(e)}")
        
        finally:
            db.close()
        
        return result
    
    def _record_show_job(self, show_id: int):
        """Job function that actually performs the recording"""
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show or not show.active:
                logger.warning(f"Show {show_id} not found or inactive")
                return
            
            station = show.station
            if not station.stream_url:
                logger.error(f"No stream URL for show {show_id}")
                return
            
            # Calculate recording duration (default 1 hour)
            duration = 3600  # 1 hour in seconds
            
            # Generate filename
            timestamp = datetime.now()
            filename = f"{show.station_id}_{show.name}_{timestamp.strftime('%Y%m%d_%H%M')}"
            
            # Start recording
            logger.info(f"Starting scheduled recording for '{show.name}'")
            result = self.recorder.record_stream(
                stream_url=station.stream_url,
                duration_seconds=duration,
                output_filename=filename,
                show_id=show_id,
                title=f"{show.name} - {timestamp.strftime('%Y-%m-%d %H:%M')}",
                description=f"Automated recording of {show.name} from {station.name}"
            )
            
            if result['success']:
                logger.info(f"Recording completed for '{show.name}': {result['output_file']}")
                
                # Clean up old recordings based on retention policy
                self._cleanup_old_recordings(show_id, show.retention_days)
            else:
                logger.error(f"Recording failed for '{show.name}': {result['error']}")
        
        except Exception as e:
            logger.error(f"Error in recording job for show {show_id}: {str(e)}")
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
            
            for recording in old_recordings:
                # Delete file if it exists
                file_path = self.recorder.recordings_dir / recording.filename
                if file_path.exists():
                    file_path.unlink()
                    logger.info(f"Deleted old recording file: {recording.filename}")
                
                # Delete database record
                db.delete(recording)
            
            db.commit()
            
            if old_recordings:
                logger.info(f"Cleaned up {len(old_recordings)} old recordings for show {show_id}")
        
        except Exception as e:
            logger.error(f"Error cleaning up recordings for show {show_id}: {str(e)}")
            db.rollback()
        finally:
            db.close()
    
    def unschedule_show(self, show_id: int) -> bool:
        """Remove scheduled recording for a show"""
        job_id = f"show_{show_id}_recording"
        try:
            self.scheduler.remove_job(job_id)
            logger.info(f"Unscheduled recording for show {show_id}")
            return True
        except Exception as e:
            logger.error(f"Error unscheduling show {show_id}: {str(e)}")
            return False
    
    def get_scheduled_jobs(self) -> List[Dict]:
        """Get list of all scheduled recording jobs"""
        jobs = []
        for job in self.scheduler.get_jobs():
            if job.id.startswith('show_'):
                jobs.append({
                    'job_id': job.id,
                    'name': job.name,
                    'next_run': job.next_run_time.isoformat() if job.next_run_time else None,
                    'trigger': str(job.trigger)
                })
        return jobs
    
    def shutdown(self):
        """Shutdown the scheduler"""
        self.scheduler.shutdown()
        logger.info("Recording scheduler shutdown")

def test_recording_service():
    """Test the recording service"""
    # Initialize recorder
    recorder = AudioRecorder()
    
    # Test short recording with a known stream
    print("=== Testing Recording Service ===")
    
    test_stream = "http://kexp-mp3-128.streamguys1.com/kexp128.mp3"
    test_filename = f"test_recording_{int(time.time())}.mp3"
    
    print(f"Recording 10 seconds from: {test_stream}")
    result = recorder.record_stream(
        stream_url=test_stream,
        duration_seconds=10,
        output_filename=test_filename,
        title="Test Recording",
        description="Test recording for RadioGrab"
    )
    
    print(f"Recording result: {result}")
    
    if result['success'] and result['output_file']:
        file_path = Path(result['output_file'])
        if file_path.exists():
            print(f"✅ Recording successful: {file_path} ({result['file_size']} bytes)")
            # Clean up test file
            file_path.unlink()
            print("Test file cleaned up")
        else:
            print("❌ Output file not found")
    else:
        print(f"❌ Recording failed: {result['error']}")

def main():
    """Main function to run the recording service"""
    import argparse
    import sys
    
    parser = argparse.ArgumentParser(description='RadioGrab Recording Service')
    parser.add_argument('--daemon', action='store_true', help='Run as daemon service')
    parser.add_argument('--test', action='store_true', help='Run test recording')
    parser.add_argument('--log-level', default='INFO', choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'])
    
    args = parser.parse_args()
    
    # Set up logging
    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    if args.test:
        test_recording_service()
        return
    
    if args.daemon:
        logger.info("Starting RadioGrab Recording Service...")
        
        try:
            # Initialize recorder and scheduler
            recorder = AudioRecorder(
                recordings_dir="/var/radiograb/recordings",
                temp_dir="/var/radiograb/temp"
            )
            scheduler = RecordingScheduler(recorder)
            
            # Load and schedule all active shows
            db = SessionLocal()
            try:
                active_shows = db.query(Show).filter(Show.active == True).all()
                logger.info(f"Found {len(active_shows)} active shows to schedule")
                
                for show in active_shows:
                    if show.schedule_pattern:
                        result = scheduler.schedule_show_recording(show.id)
                        if result['success']:
                            logger.info(f"Scheduled show: {show.name} (next: {result['next_run']})")
                        else:
                            logger.error(f"Failed to schedule show {show.name}: {result['error']}")
                
            finally:
                db.close()
            
            logger.info("Recording service is running. Press Ctrl+C to stop.")
            
            # Keep the service running
            try:
                while True:
                    time.sleep(60)  # Check every minute
                    
            except KeyboardInterrupt:
                logger.info("Shutting down recording service...")
                scheduler.shutdown()
                logger.info("Recording service stopped.")
                
        except Exception as e:
            logger.error(f"Failed to start recording service: {str(e)}")
            sys.exit(1)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()