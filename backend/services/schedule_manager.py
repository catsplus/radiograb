#!/usr/bin/env python3
"""
RadioGrab Schedule Manager
Utility functions for managing show schedules and integrating with the recording service
"""

import sys
import os
import logging
from typing import Dict, Any, Optional

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Show
from backend.services.recording_service import RecordingScheduler, EnhancedRecordingService

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ScheduleManager:
    """
    Manages integration between web interface and recording scheduler
    """
    
    def __init__(self):
        self.recording_service = EnhancedRecordingService()
        self.scheduler = RecordingScheduler(self.recording_service)
        
    def add_show_schedule(self, show_id: int) -> Dict[str, Any]:
        """
        Add a show to the scheduler after it's created in the web interface
        
        Args:
            show_id: Database ID of the show to schedule
            
        Returns:
            Dictionary with scheduling results
        """
        logger.info(f"Adding schedule for show {show_id}")
        
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                return {'success': False, 'error': f'Show {show_id} not found'}
                
            if not show.active:
                return {'success': False, 'error': f'Show {show_id} is not active'}
                
            if not show.schedule_pattern:
                return {'success': False, 'error': f'Show {show_id} has no schedule pattern'}
            
            # Schedule the show
            result = self.scheduler.schedule_show(show_id)
            
            if result['success']:
                logger.info(f"Successfully scheduled show {show_id}: {show.name}")
            else:
                logger.error(f"Failed to schedule show {show_id}: {result['error']}")
                
            return result
            
        finally:
            db.close()
    
    def update_show_schedule(self, show_id: int) -> Dict[str, Any]:
        """
        Update a show's schedule (remove old, add new)
        
        Args:
            show_id: Database ID of the show to reschedule
            
        Returns:
            Dictionary with scheduling results
        """
        logger.info(f"Updating schedule for show {show_id}")
        
        # Remove existing schedule
        self.scheduler.unschedule_show(show_id)
        
        # Add new schedule
        return self.add_show_schedule(show_id)
    
    def remove_show_schedule(self, show_id: int) -> Dict[str, Any]:
        """
        Remove a show from the scheduler
        
        Args:
            show_id: Database ID of the show to unschedule
            
        Returns:
            Dictionary with unscheduling results
        """
        logger.info(f"Removing schedule for show {show_id}")
        
        success = self.scheduler.unschedule_show(show_id)
        
        return {
            'success': success,
            'message': f'Show {show_id} {"unscheduled" if success else "was not scheduled"}'
        }
    
    def refresh_all_schedules(self) -> Dict[str, Any]:
        """
        Refresh all show schedules (useful after database changes)
        
        Returns:
            Dictionary with refresh results
        """
        logger.info("Refreshing all show schedules")
        
        # Get current jobs and remove them
        current_jobs = self.scheduler.get_scheduled_jobs()
        for job in current_jobs:
            self.scheduler.unschedule_show(job['show_id'])
        
        # Reschedule all active shows
        result = self.scheduler.schedule_all_active_shows()
        
        logger.info(f"Schedule refresh complete: {result['scheduled_count']} scheduled, {result['failed_count']} failed")
        
        return result
    
    def get_schedule_status(self) -> Dict[str, Any]:
        """
        Get current scheduling status
        
        Returns:
            Dictionary with status information
        """
        db = SessionLocal()
        try:
            # Get active shows from database
            active_shows = db.query(Show).filter(Show.active == True).all()
            
            # Get scheduled jobs
            scheduled_jobs = self.scheduler.get_scheduled_jobs()
            
            # Calculate statistics
            total_active_shows = len(active_shows)
            total_scheduled_jobs = len(scheduled_jobs)
            
            shows_with_schedule = len([s for s in active_shows if s.schedule_pattern])
            shows_without_schedule = total_active_shows - shows_with_schedule
            
            unscheduled_shows = []
            for show in active_shows:
                if show.schedule_pattern:
                    job_exists = any(job['show_id'] == show.id for job in scheduled_jobs)
                    if not job_exists:
                        unscheduled_shows.append({
                            'id': show.id,
                            'name': show.name,
                            'schedule_pattern': show.schedule_pattern
                        })
            
            return {
                'success': True,
                'total_active_shows': total_active_shows,
                'shows_with_schedule': shows_with_schedule,
                'shows_without_schedule': shows_without_schedule,
                'total_scheduled_jobs': total_scheduled_jobs,
                'unscheduled_shows': unscheduled_shows,
                'scheduled_jobs': scheduled_jobs
            }
            
        finally:
            db.close()
    
    def shutdown(self):
        """Shutdown the scheduler"""
        self.scheduler.shutdown()

def main():
    """Command line interface for schedule management"""
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Schedule Manager')
    parser.add_argument('--add-show', type=int, help='Add schedule for show ID')
    parser.add_argument('--update-show', type=int, help='Update schedule for show ID')  
    parser.add_argument('--remove-show', type=int, help='Remove schedule for show ID')
    parser.add_argument('--refresh-all', action='store_true', help='Refresh all schedules')
    parser.add_argument('--status', action='store_true', help='Show scheduling status')
    
    args = parser.parse_args()
    
    manager = ScheduleManager()
    
    try:
        if args.add_show:
            result = manager.add_show_schedule(args.add_show)
            print(f"Add show {args.add_show}: {result}")
            
        elif args.update_show:
            result = manager.update_show_schedule(args.update_show)
            print(f"Update show {args.update_show}: {result}")
            
        elif args.remove_show:
            result = manager.remove_show_schedule(args.remove_show)
            print(f"Remove show {args.remove_show}: {result}")
            
        elif args.refresh_all:
            result = manager.refresh_all_schedules()
            print(f"Refresh all schedules: {result}")
            
        elif args.status:
            status = manager.get_schedule_status()
            print("=== Schedule Status ===")
            print(f"Total active shows: {status['total_active_shows']}")
            print(f"Shows with schedule: {status['shows_with_schedule']}")
            print(f"Shows without schedule: {status['shows_without_schedule']}")
            print(f"Total scheduled jobs: {status['total_scheduled_jobs']}")
            
            if status['unscheduled_shows']:
                print(f"\nUnscheduled shows ({len(status['unscheduled_shows'])}):")
                for show in status['unscheduled_shows']:
                    print(f"  - {show['name']} (ID: {show['id']}) - {show['schedule_pattern']}")
            
            if status['scheduled_jobs']:
                print(f"\nScheduled jobs ({len(status['scheduled_jobs'])}):")
                for job in status['scheduled_jobs']:
                    print(f"  - Show {job['show_id']}: {job['name']} - Next: {job['next_run']}")
        else:
            parser.print_help()
            
    finally:
        manager.shutdown()

if __name__ == "__main__":
    main()