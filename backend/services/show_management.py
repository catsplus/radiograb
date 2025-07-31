#!/usr/bin/env python3
"""
Provides enhanced show management functionalities.

This service offers features such as retrieving upcoming recordings, cleaning up
test recordings, toggling show active status, and updating show tags. It integrates
with the recording scheduler to manage show schedules.

Key Variables:
- `limit`: The maximum number of upcoming recordings to retrieve.
- `max_age_hours`: The age in hours after which test recordings are cleaned up.
- `show_id`: The database ID of the show to manage.
- `active`: A boolean indicating whether to activate or deactivate a show.
- `tags`: Comma-separated tags for a show.

Inter-script Communication:
- This script is called by the frontend API to manage shows.
- It uses `recording_service.py` to interact with the `RecordingScheduler`.
- It interacts with the `Show`, `Station`, and `Recording` models from `backend/models/station.py`.
"""


import sys
import os
import logging
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, Any, List, Optional
import argparse
import glob
import pytz

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Show, Station, Recording
from backend.services.recording_service import RecordingScheduler, EnhancedRecordingService
from apscheduler.triggers.cron import CronTrigger

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ShowManagementService:
    """
    Enhanced show management with schedule analysis and cleanup
    """
    
    def __init__(self):
        self.temp_dir = Path('/var/radiograb/temp')
        self.recording_service = EnhancedRecordingService()
        self.scheduler = RecordingScheduler(self.recording_service)
        
    def get_next_recordings(self, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Get upcoming recordings based on show schedules
        
        Args:
            limit: Maximum number of upcoming recordings to return
            
        Returns:
            List of upcoming recordings with show and time information
        """
        upcoming = []
        db = SessionLocal()
        
        try:
            # Get all active shows with schedules
            active_shows = db.query(Show).filter(
                Show.active == True,
                Show.schedule_pattern.isnot(None)
            ).all()
            
            # Use timezone-aware datetime
            eastern = pytz.timezone('America/New_York')
            now = datetime.now(eastern)
            
            logger.debug(f"Current time: {now} ({now.strftime('%A')})")
            
            for show in active_shows:
                if not show.schedule_pattern:
                    continue
                    
                try:
                    # Parse cron pattern
                    cron_parts = show.schedule_pattern.strip().split()
                    if len(cron_parts) != 5:
                        continue
                        
                    minute, hour, day, month, day_of_week = cron_parts
                    
                    # Debug logging for troubleshooting
                    logger.info(f"Processing show {show.id} ({show.name}): pattern={show.schedule_pattern}")
                    
                    # APScheduler uses different day-of-week numbering than standard cron
                    # Standard cron: 0=Sunday, 1=Monday, ..., 6=Saturday  
                    # APScheduler: 0=Monday, 1=Tuesday, ..., 6=Sunday
                    # We need to convert from cron format to APScheduler format
                    if day_of_week != '*':
                        # Handle multiple days (e.g., "1,2,3,4,5")
                        if ',' in day_of_week:
                            cron_days = day_of_week.split(',')
                            apscheduler_days = []
                            for cron_day in cron_days:
                                cron_day = int(cron_day.strip())
                                # Convert: cron Sunday(0) -> APScheduler Sunday(6)
                                #          cron Monday(1) -> APScheduler Monday(0)
                                apscheduler_day = (cron_day - 1) % 7
                                apscheduler_days.append(str(apscheduler_day))
                            converted_day_of_week = ','.join(apscheduler_days)
                        else:
                            cron_day = int(day_of_week)
                            # Convert single day
                            converted_day_of_week = str((cron_day - 1) % 7)
                    else:
                        converted_day_of_week = day_of_week
                    
                    logger.info(f"Show {show.id}: Converting cron day_of_week '{day_of_week}' to APScheduler '{converted_day_of_week}'")
                    
                    # Create trigger to find next run time
                    trigger = CronTrigger(
                        minute=minute,
                        hour=hour,
                        day=day,
                        month=month,
                        day_of_week=converted_day_of_week,
                        timezone='America/New_York'
                    )
                    
                    # Get next run time - ensure we get FUTURE runs only
                    next_run = trigger.get_next_fire_time(None, now)
                    
                    logger.info(f"Show {show.id} ({show.name}) calculated next_run: {next_run}")
                    
                    # Additional validation: make sure this is actually in the future
                    if next_run:
                        # Ensure next_run is at least 1 minute in the future
                        time_diff = (next_run - now).total_seconds()
                        logger.info(f"Show {show.id} time_diff: {time_diff:.0f} seconds")
                        
                        if time_diff < 60:  # Less than 1 minute in future
                            logger.info(f"Show {show.id} ({show.name}) next_run {next_run} is too soon (in {time_diff:.0f}s), getting next occurrence")
                            # Get the occurrence after this one
                            next_run = trigger.get_next_fire_time(next_run, next_run)
                            if next_run:
                                logger.info(f"Show {show.id} updated next_run: {next_run}")
                    
                    if next_run:
                        upcoming.append({
                            'show_id': show.id,
                            'show_name': show.name,
                            'station_name': show.station.name if show.station else 'Unknown',
                            'station_id': show.station_id,
                            'next_run': next_run,
                            'schedule_description': show.schedule_description,
                            'tags': show.tags.split(',') if hasattr(show, 'tags') and show.tags else []
                        })
                        
                except Exception as e:
                    logger.warning(f"Error parsing schedule for show {show.id}: {e}")
                    continue
            
            # Sort by next run time and limit results
            upcoming.sort(key=lambda x: x['next_run'])
            return upcoming[:limit]
            
        finally:
            db.close()
    
    def cleanup_test_recordings(self, max_age_hours: int = 4) -> Dict[str, Any]:
        """
        Clean up test recordings older than specified hours
        
        Args:
            max_age_hours: Maximum age in hours before cleanup
            
        Returns:
            Dictionary with cleanup results
        """
        result = {
            'success': True,
            'files_deleted': 0,
            'bytes_freed': 0,
            'errors': []
        }
        
        if not self.temp_dir.exists():
            return result
            
        cutoff_time = datetime.now() - timedelta(hours=max_age_hours)
        
        try:
            # Find all test recording files
            test_patterns = [
                '*_test_*.mp3',
                '*_test_*.mp3.mp3',  # AAC converted files
                '*_manual_*.mp3'     # Manual recordings also in temp
            ]
            
            for pattern in test_patterns:
                for file_path in self.temp_dir.glob(pattern):
                    try:
                        # Check file modification time
                        file_mtime = datetime.fromtimestamp(file_path.stat().st_mtime)
                        
                        if file_mtime < cutoff_time:
                            file_size = file_path.stat().st_size
                            file_path.unlink()
                            
                            result['files_deleted'] += 1
                            result['bytes_freed'] += file_size
                            
                            logger.info(f"Deleted old test recording: {file_path.name}")
                            
                    except Exception as e:
                        error_msg = f"Error deleting {file_path.name}: {str(e)}"
                        result['errors'].append(error_msg)
                        logger.error(error_msg)
                        
        except Exception as e:
            result['success'] = False
            result['errors'].append(f"Cleanup error: {str(e)}")
            logger.error(f"Test recording cleanup error: {e}")
        
        return result
    
    def toggle_show_active(self, show_id: int, active: bool) -> Dict[str, Any]:
        """
        Toggle show active/inactive status and update scheduler
        
        Args:
            show_id: Database ID of the show
            active: True to activate, False to deactivate
            
        Returns:
            Dictionary with operation results
        """
        result = {
            'success': False,
            'message': '',
            'error': None
        }
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = f"Show {show_id} not found"
                return result
            
            old_status = show.active
            show.active = active
            db.commit()
            
            # Update scheduler
            if active and show.schedule_pattern:
                # Add to scheduler
                schedule_result = self.scheduler.schedule_show(show_id)
                if schedule_result['success']:
                    result['message'] = f'Show activated and scheduled for recording'
                else:
                    result['message'] = f'Show activated but scheduling failed: {schedule_result["error"]}'
            else:
                # Remove from scheduler
                unschedule_result = self.scheduler.unschedule_show(show_id)
                result['message'] = f'Show {"deactivated" if not active else "activated"} and {"removed from" if not active else "added to"} scheduler'
            
            result['success'] = True
            logger.info(f"Show {show_id} status changed: {old_status} -> {active}")
            
        except Exception as e:
            result['error'] = f"Database error: {str(e)}"
            db.rollback()
            logger.error(f"Error toggling show {show_id}: {e}")
        finally:
            db.close()
            
        return result
    
    def update_show_tags(self, show_id: int, tags: str) -> Dict[str, Any]:
        """
        Update show tags
        
        Args:
            show_id: Database ID of the show
            tags: Comma-separated tags string
            
        Returns:
            Dictionary with operation results
        """
        result = {
            'success': False,
            'message': '',
            'error': None
        }
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                result['error'] = f"Show {show_id} not found"
                return result
            
            # Clean and validate tags
            if tags:
                tag_list = [tag.strip() for tag in tags.split(',') if tag.strip()]
                cleaned_tags = ','.join(tag_list)
            else:
                cleaned_tags = None
            
            show.tags = cleaned_tags
            db.commit()
            
            result['success'] = True
            result['message'] = f'Tags updated for show {show.name}'
            logger.info(f"Updated tags for show {show_id}: {cleaned_tags}")
            
        except Exception as e:
            result['error'] = f"Database error: {str(e)}"
            db.rollback()
            logger.error(f"Error updating tags for show {show_id}: {e}")
        finally:
            db.close()
            
        return result
    
    def get_show_statistics(self) -> Dict[str, Any]:
        """
        Get comprehensive show statistics
        
        Returns:
            Dictionary with show statistics
        """
        db = SessionLocal()
        try:
            total_shows = db.query(Show).count()
            active_shows = db.query(Show).filter(Show.active == True).count()
            scheduled_shows = db.query(Show).filter(
                Show.active == True,
                Show.schedule_pattern.isnot(None)
            ).count()
            
            # Get tag statistics
            shows_with_tags = db.query(Show).filter(Show.tags.isnot(None)).count()
            
            # Get recent recordings
            recent_recordings = db.query(Recording).filter(
                Recording.recorded_at >= datetime.now() - timedelta(days=7)
            ).count()
            
            return {
                'total_shows': total_shows,
                'active_shows': active_shows,
                'inactive_shows': total_shows - active_shows,
                'scheduled_shows': scheduled_shows,
                'shows_with_tags': shows_with_tags,
                'recent_recordings_7days': recent_recordings
            }
            
        finally:
            db.close()
    
    def shutdown(self):
        """Shutdown the scheduler"""
        self.scheduler.shutdown()

def main():
    """Command line interface for show management"""
    parser = argparse.ArgumentParser(description='RadioGrab Show Management Service')
    parser.add_argument('--next-recordings', type=int, default=10, help='Get next N recordings')
    parser.add_argument('--cleanup-tests', type=int, default=4, help='Cleanup test recordings older than N hours')
    parser.add_argument('--toggle-show', type=int, help='Toggle show active status')
    parser.add_argument('--activate', action='store_true', help='Activate show (use with --toggle-show)')
    parser.add_argument('--deactivate', action='store_true', help='Deactivate show (use with --toggle-show)')
    parser.add_argument('--update-tags', type=int, help='Update tags for show ID')
    parser.add_argument('--tags', type=str, help='Comma-separated tags (use with --update-tags)')
    parser.add_argument('--stats', action='store_true', help='Show statistics')
    
    args = parser.parse_args()
    
    service = ShowManagementService()
    
    try:
        if args.next_recordings:
            upcoming = service.get_next_recordings(args.next_recordings)
            print(f"=== Next {len(upcoming)} Recordings ===")
            for recording in upcoming:
                print(f"- {recording['show_name']} ({recording['station_name']})")
                print(f"  Next: {recording['next_run'].strftime('%Y-%m-%d %H:%M:%S')}")
                if recording['tags']:
                    print(f"  Tags: {', '.join(recording['tags'])}")
                print()
                
        elif args.cleanup_tests:
            result = service.cleanup_test_recordings(args.cleanup_tests)
            print(f"=== Test Recording Cleanup ===")
            print(f"Files deleted: {result['files_deleted']}")
            print(f"Bytes freed: {result['bytes_freed']:,}")
            if result['errors']:
                print(f"Errors: {len(result['errors'])}")
                for error in result['errors']:
                    print(f"  - {error}")
                    
        elif args.toggle_show:
            if args.activate:
                result = service.toggle_show_active(args.toggle_show, True)
            elif args.deactivate:
                result = service.toggle_show_active(args.toggle_show, False)
            else:
                print("Use --activate or --deactivate with --toggle-show")
                return
                
            print(f"Toggle show {args.toggle_show}: {result}")
            
        elif args.update_tags:
            if not args.tags:
                print("Use --tags with --update-tags")
                return
                
            result = service.update_show_tags(args.update_tags, args.tags)
            print(f"Update tags for show {args.update_tags}: {result}")
            
        elif args.stats:
            stats = service.get_show_statistics()
            print("=== Show Statistics ===")
            print(f"Total shows: {stats['total_shows']}")
            print(f"Active shows: {stats['active_shows']}")
            print(f"Inactive shows: {stats['inactive_shows']}")
            print(f"Scheduled shows: {stats['scheduled_shows']}")
            print(f"Shows with tags: {stats['shows_with_tags']}")
            print(f"Recent recordings (7 days): {stats['recent_recordings_7days']}")
            
        else:
            parser.print_help()
            
    finally:
        service.shutdown()

if __name__ == "__main__":
    main()