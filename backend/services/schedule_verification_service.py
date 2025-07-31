"""
Verifies and updates radio station schedules on a weekly basis.

This service automatically checks station websites for schedule changes.
It compares the currently stored schedule with the newly parsed one and updates
the database accordingly. It can add new shows, update existing ones, and deactivate
shows that are no longer on the schedule.

Key Variables:
- `station_id`: The database ID of the station to verify.
- `force`: A boolean to force verification even if recently checked.

Inter-script Communication:
- This script is typically run as a cron job.
- It uses `js_calendar_parser.py` to parse station schedules.
- It interacts with the `Station` and `Show` models from `backend/models/station.py`.
"""
#!/usr/bin/env python3
"""
RadioGrab Weekly Schedule Verification Service
Automatically checks station schedules for changes once per week
"""

import sys
import os
import logging
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, Any, List, Optional
import argparse
import json

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Station, Show
from backend.services.js_calendar_parser import JavaScriptCalendarParser

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ScheduleVerificationService:
    """
    Service to verify and update station schedules weekly
    """
    
    def __init__(self):
        self.parser = JavaScriptCalendarParser()
        self.verification_log_path = Path('/var/radiograb/logs/schedule_verification.log')
        self.changes_log_path = Path('/var/radiograb/logs/schedule_changes.json')
        
        # Ensure log directory exists
        self.verification_log_path.parent.mkdir(parents=True, exist_ok=True)
        self.changes_log_path.parent.mkdir(parents=True, exist_ok=True)
    
    def should_verify_station(self, station) -> bool:
        """
        Check if a station should be verified this week
        
        Args:
            station: Station database model
            
        Returns:
            True if station should be verified
        """
        # Always verify if never checked
        if not station.last_tested:
            return True
            
        # Verify if last check was more than 7 days ago
        week_ago = datetime.now() - timedelta(days=7)
        return station.last_tested < week_ago
    
    def verify_station_schedule(self, station_id: int) -> Dict[str, Any]:
        """
        Verify and update a single station's schedule
        
        Args:
            station_id: Database ID of the station
            
        Returns:
            Dictionary with verification results
        """
        result = {
            'station_id': station_id,
            'station_name': '',
            'success': False,
            'shows_found': 0,
            'shows_updated': 0,
            'shows_added': 0,
            'shows_removed': 0,
            'changes': [],
            'errors': []
        }
        
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                result['errors'].append(f"Station {station_id} not found")
                return result
                
            result['station_name'] = station.name
            logger.info(f"Verifying schedule for station {station.name} (ID: {station_id})")
            
            # Check if station has calendar URL
            if not station.calendar_url:
                result['errors'].append("No calendar URL configured")
                logger.warning(f"Station {station.name} has no calendar URL")
                return result
            
            # Parse current schedule from station website
            try:
                parsed_schedule = self.parser.parse_station_schedule(
                    station.calendar_url,
                    station.id
                )
                
                if not parsed_schedule:
                    result['errors'].append("No shows found in current schedule")
                    return result
                    
                result['shows_found'] = len(parsed_schedule)
                
                # Get existing shows for this station
                existing_shows = {
                    show.name.lower(): show 
                    for show in db.query(Show).filter(Show.station_id == station_id).all()
                }
                
                # Process each show found in the schedule
                for show_data in parsed_schedule:
                    show_name = show_data.name.strip() if show_data.name else ''
                    if not show_name:
                        continue
                        
                    show_key = show_name.lower()
                    # Convert ShowSchedule object to schedule pattern format
                    schedule_pattern = f"{show_data.start_time} on {', '.join(show_data.days)}"
                    schedule_description = show_data.description or ''
                    
                    if show_key in existing_shows:
                        # Update existing show if schedule changed
                        existing_show = existing_shows[show_key]
                        
                        if existing_show.schedule_pattern != schedule_pattern:
                            old_pattern = existing_show.schedule_pattern
                            existing_show.schedule_pattern = schedule_pattern
                            existing_show.schedule_description = schedule_description
                            existing_show.updated_at = datetime.now()
                            
                            result['shows_updated'] += 1
                            result['changes'].append({
                                'type': 'updated',
                                'show_name': show_name,
                                'old_schedule': old_pattern,
                                'new_schedule': schedule_pattern,
                                'old_description': existing_show.schedule_description,
                                'new_description': schedule_description
                            })
                            
                            logger.info(f"Updated schedule for {show_name}: {old_pattern} -> {schedule_pattern}")
                        
                        # Remove from existing_shows so we know it's still active
                        del existing_shows[show_key]
                        
                    else:
                        # Add new show (inactive by default - user must manually activate)
                        new_show = Show(
                            name=show_name,
                            station_id=station_id,
                            schedule_pattern=schedule_pattern,
                            schedule_description=schedule_description,
                            description=f"{station.name} program: {show_name}",
                            active=False,  # Inactive by default - user decides what to activate
                            created_at=datetime.now(),
                            updated_at=datetime.now()
                        )
                        
                        db.add(new_show)
                        result['shows_added'] += 1
                        result['changes'].append({
                            'type': 'added',
                            'show_name': show_name,
                            'schedule': schedule_pattern,
                            'description': schedule_description
                        })
                        
                        logger.info(f"Added new show: {show_name} with schedule {schedule_pattern}")
                
                # Mark remaining shows as inactive (removed from schedule)
                for show_name, show in existing_shows.items():
                    if show.active:  # Only deactivate if currently active
                        show.active = False
                        show.updated_at = datetime.now()
                        
                        result['shows_removed'] += 1
                        result['changes'].append({
                            'type': 'removed',
                            'show_name': show.name,
                            'old_schedule': show.schedule_pattern,
                            'reason': 'No longer found in station schedule'
                        })
                        
                        logger.info(f"Deactivated show {show.name} - no longer in schedule")
                
                # Update station last_tested timestamp and result based on findings
                station.last_tested = datetime.now()
                
                if result['shows_found'] > 0:
                    station.last_test_result = 'success'
                    station.last_test_error = None
                    logger.info(f"Calendar verification successful: {result['shows_found']} shows found")
                else:
                    station.last_test_result = 'failed'
                    station.last_test_error = "No shows found in current schedule"
                    logger.warning(f"Calendar verification completed but no shows found")
                
                db.commit()
                result['success'] = True
                
            except Exception as e:
                error_msg = f"Schedule parsing error: {str(e)}"
                result['errors'].append(error_msg)
                logger.error(f"Error parsing schedule for {station.name}: {e}")
                
                # Update station with error info
                station.last_tested = datetime.now()
                station.last_test_result = 'error'
                station.last_test_error = error_msg
                db.commit()
                
        except Exception as e:
            result['errors'].append(f"Database error: {str(e)}")
            logger.error(f"Database error during verification: {e}")
            db.rollback()
        finally:
            db.close()
            
        return result
    
    def verify_all_stations(self, force: bool = False) -> Dict[str, Any]:
        """
        Verify schedules for all stations that need checking
        
        Args:
            force: If True, verify all stations regardless of last check time
            
        Returns:
            Dictionary with overall verification results
        """
        overall_result = {
            'success': True,
            'stations_checked': 0,
            'stations_with_changes': 0,
            'total_changes': 0,
            'errors': [],
            'station_results': []
        }
        
        db = SessionLocal()
        try:
            # Get all active stations
            stations = db.query(Station).filter(Station.status == 'active').all()
            
            for station in stations:
                if force or self.should_verify_station(station):
                    logger.info(f"Checking station: {station.name}")
                    
                    station_result = self.verify_station_schedule(station.id)
                    overall_result['station_results'].append(station_result)
                    overall_result['stations_checked'] += 1
                    
                    if station_result['changes']:
                        overall_result['stations_with_changes'] += 1
                        overall_result['total_changes'] += len(station_result['changes'])
                    
                    if not station_result['success']:
                        overall_result['success'] = False
                        overall_result['errors'].extend(station_result['errors'])
                else:
                    logger.debug(f"Skipping {station.name} - checked recently")
            
            # Log results
            self.log_verification_results(overall_result)
            
        except Exception as e:
            overall_result['success'] = False
            overall_result['errors'].append(f"Overall verification error: {str(e)}")
            logger.error(f"Error during overall verification: {e}")
        finally:
            db.close()
            
        return overall_result
    
    def log_verification_results(self, results: Dict[str, Any]):
        """
        Log verification results to files
        
        Args:
            results: Verification results dictionary
        """
        try:
            # Log summary to text file
            with open(self.verification_log_path, 'a') as f:
                f.write(f"\n=== Schedule Verification - {datetime.now()} ===\n")
                f.write(f"Stations checked: {results['stations_checked']}\n")
                f.write(f"Stations with changes: {results['stations_with_changes']}\n")
                f.write(f"Total changes: {results['total_changes']}\n")
                f.write(f"Success: {results['success']}\n")
                
                if results['errors']:
                    f.write("Errors:\n")
                    for error in results['errors']:
                        f.write(f"  - {error}\n")
                
                f.write("\nStation Details:\n")
                for station_result in results['station_results']:
                    f.write(f"  {station_result['station_name']}: ")
                    f.write(f"Found {station_result['shows_found']} shows, ")
                    f.write(f"Updated {station_result['shows_updated']}, ")
                    f.write(f"Added {station_result['shows_added']}, ")
                    f.write(f"Removed {station_result['shows_removed']}\n")
                
                f.write("\n")
            
            # Log detailed changes to JSON file
            if results['total_changes'] > 0:
                change_log = {
                    'timestamp': datetime.now().isoformat(),
                    'summary': {
                        'stations_checked': results['stations_checked'],
                        'stations_with_changes': results['stations_with_changes'],
                        'total_changes': results['total_changes']
                    },
                    'changes': []
                }
                
                for station_result in results['station_results']:
                    if station_result['changes']:
                        change_log['changes'].append({
                            'station_id': station_result['station_id'],
                            'station_name': station_result['station_name'],
                            'changes': station_result['changes']
                        })
                
                with open(self.changes_log_path, 'a') as f:
                    f.write(json.dumps(change_log, indent=2) + '\n')
                    
        except Exception as e:
            logger.error(f"Error logging verification results: {e}")
    
    def get_verification_history(self, days: int = 30) -> Dict[str, Any]:
        """
        Get verification history for the last N days
        
        Args:
            days: Number of days to look back
            
        Returns:
            Dictionary with verification history
        """
        history = {
            'success': True,
            'days': days,
            'verifications': [],
            'total_changes': 0,
            'error': None
        }
        
        try:
            if self.changes_log_path.exists():
                with open(self.changes_log_path, 'r') as f:
                    cutoff_date = datetime.now() - timedelta(days=days)
                    
                    for line in f:
                        try:
                            log_entry = json.loads(line.strip())
                            entry_date = datetime.fromisoformat(log_entry['timestamp'])
                            
                            if entry_date >= cutoff_date:
                                history['verifications'].append(log_entry)
                                history['total_changes'] += log_entry['summary']['total_changes']
                        except (json.JSONDecodeError, KeyError, ValueError):
                            continue
                            
        except Exception as e:
            history['success'] = False
            history['error'] = str(e)
            logger.error(f"Error getting verification history: {e}")
            
        return history

def main():
    """Command line interface for schedule verification"""
    parser = argparse.ArgumentParser(description='RadioGrab Schedule Verification Service')
    parser.add_argument('--verify-all', action='store_true', help='Verify all stations')
    parser.add_argument('--force', action='store_true', help='Force verification regardless of last check time')
    parser.add_argument('--station-id', type=int, help='Verify specific station by ID')
    parser.add_argument('--history', type=int, default=30, help='Show verification history for N days')
    parser.add_argument('--daemon', action='store_true', help='Run as daemon service')
    
    args = parser.parse_args()
    
    service = ScheduleVerificationService()
    
    try:
        if args.station_id:
            # Verify single station
            result = service.verify_station_schedule(args.station_id)
            print(f"=== Station Verification Results ===")
            print(f"Station: {result['station_name']}")
            print(f"Success: {result['success']}")
            print(f"Shows found: {result['shows_found']}")
            print(f"Shows updated: {result['shows_updated']}")
            print(f"Shows added: {result['shows_added']}")
            print(f"Shows removed: {result['shows_removed']}")
            
            if result['changes']:
                print("\nChanges:")
                for change in result['changes']:
                    print(f"  - {change['type']}: {change['show_name']}")
                    
            if result['errors']:
                print("\nErrors:")
                for error in result['errors']:
                    print(f"  - {error}")
                    
        elif args.verify_all:
            # Verify all stations
            result = service.verify_all_stations(args.force)
            print(f"=== Overall Verification Results ===")
            print(f"Success: {result['success']}")
            print(f"Stations checked: {result['stations_checked']}")
            print(f"Stations with changes: {result['stations_with_changes']}")
            print(f"Total changes: {result['total_changes']}")
            
            if result['errors']:
                print(f"Errors: {len(result['errors'])}")
                for error in result['errors']:
                    print(f"  - {error}")
                    
        elif args.history:
            # Show verification history
            history = service.get_verification_history(args.history)
            print(f"=== Verification History ({args.history} days) ===")
            print(f"Total verifications: {len(history['verifications'])}")
            print(f"Total changes: {history['total_changes']}")
            
            for verification in history['verifications'][-5:]:  # Show last 5
                print(f"\n{verification['timestamp']}: {verification['summary']['total_changes']} changes")
                
        elif args.daemon:
            # Run as daemon (placeholder for future cron integration)
            logger.info("Starting schedule verification daemon")
            result = service.verify_all_stations()
            logger.info(f"Daemon run completed: {result['stations_checked']} stations checked, {result['total_changes']} total changes")
            
        else:
            parser.print_help()
            
    except KeyboardInterrupt:
        logger.info("Verification interrupted by user")
    except Exception as e:
        logger.error(f"Verification failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()