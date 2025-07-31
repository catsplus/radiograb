"""
Imports parsed calendar data into the database as shows.

This service takes the schedule data extracted by the `CalendarParser` and
creates or updates show entries in the database. It can automatically create
new shows, update existing ones, and handle selective imports.

Key Variables:
- `station_id`: The database ID of the station for which to import the schedule.
- `auto_create_shows`: A boolean indicating whether to automatically create new shows.
- `update_existing`: A boolean indicating whether to update existing shows.
- `selected_shows`: An optional list of shows to import selectively.

Inter-script Communication:
- This script is called by the frontend API to import schedules.
- It uses `calendar_parser.py` to parse station schedules.
- It uses `schedule_parser.py` to generate cron expressions and descriptions.
- It interacts with the `Station` and `Show` models from `backend/models/station.py`.
"""
"""
Schedule Importer Service
Imports parsed calendar data into the database as shows
"""

import logging
import json
from datetime import datetime, time
from typing import List, Dict, Optional, Tuple
from sqlalchemy.orm import Session

from backend.config.database import SessionLocal
from backend.models.station import Station, Show
from backend.services.calendar_parser import CalendarParser, ShowSchedule
from backend.services.schedule_parser import ScheduleParser

logger = logging.getLogger(__name__)


class ScheduleImporter:
    """Imports parsed schedule data into the database"""
    
    def __init__(self):
        self.calendar_parser = CalendarParser()
        self.schedule_parser = ScheduleParser()
        
    def import_station_schedule(self, station_id: int, 
                              auto_create_shows: bool = True,
                              update_existing: bool = False,
                              selected_shows: Optional[List[Dict]] = None) -> Dict[str, int]:
        """Import schedule for a station from its website"""
        
        results = {
            'shows_found': 0,
            'shows_created': 0,
            'shows_updated': 0,
            'shows_skipped': 0,
            'errors': 0
        }
        
        try:
            db = SessionLocal()
            
            # Get station details
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                logger.error(f"Station {station_id} not found")
                return results
            
            logger.info(f"Importing schedule for station: {station.name}")
            
            # Parse schedule from station website
            shows = self.calendar_parser.parse_station_schedule(station.website_url, station_id)
            results['shows_found'] = len(shows)
            
            if not shows:
                logger.warning(f"No shows found in schedule for {station.name}")
                return results
            
            # Filter shows if selective import is requested
            if selected_shows:
                selected_names = {show['name'] for show in selected_shows}
                original_count = len(shows)
                shows = [show for show in shows if show.name in selected_names]
                logger.info(f"Filtered {original_count} shows to {len(shows)} selected shows")
                results['shows_found'] = len(shows)
                
                if not shows:
                    logger.warning("No selected shows found in parsed schedule")
                    return results
            
            # Process each show
            for show_schedule in shows:
                try:
                    result = self._process_show_schedule(
                        db, station_id, show_schedule, 
                        auto_create_shows, update_existing
                    )
                    
                    if result == 'created':
                        results['shows_created'] += 1
                    elif result == 'updated':
                        results['shows_updated'] += 1
                    elif result == 'skipped':
                        results['shows_skipped'] += 1
                        
                except Exception as e:
                    logger.error(f"Error processing show {show_schedule.name}: {e}")
                    results['errors'] += 1
            
            db.commit()
            db.close()
            
            logger.info(f"Schedule import completed for {station.name}: "
                       f"{results['shows_created']} created, "
                       f"{results['shows_updated']} updated, "
                       f"{results['shows_skipped']} skipped")
            
        except Exception as e:
            logger.error(f"Error importing schedule for station {station_id}: {e}")
            results['errors'] += 1
        
        return results
    
    def _process_show_schedule(self, db: Session, station_id: int, 
                             show_schedule: ShowSchedule,
                             auto_create: bool, update_existing: bool) -> str:
        """Process a single show schedule entry"""
        
        # Check if show already exists (exact name match, case-insensitive)
        existing_show = db.query(Show).filter(
            Show.station_id == station_id,
            Show.name.ilike(show_schedule.name)
        ).first()
        
        if existing_show:
            if update_existing:
                logger.info(f"Updating existing show: '{show_schedule.name}' -> '{existing_show.name}'")
                return self._update_existing_show(db, existing_show, show_schedule)
            else:
                logger.info(f"Show '{show_schedule.name}' already exists as '{existing_show.name}', skipping")
                return 'skipped'
        
        if not auto_create:
            logger.debug(f"Auto-create disabled, skipping '{show_schedule.name}'")
            return 'skipped'
        
        # Create new show
        return self._create_new_show(db, station_id, show_schedule)
    
    def _create_new_show(self, db: Session, station_id: int, 
                        show_schedule: ShowSchedule) -> str:
        """Create a new show from schedule data"""
        
        try:
            # Convert show schedule to cron expression
            cron_expression = self._generate_cron_expression(show_schedule)
            
            # Create schedule description
            schedule_desc = self._generate_schedule_description(show_schedule)
            
            # Create the show
            show = Show(
                station_id=station_id,
                name=show_schedule.name,
                description=show_schedule.description or f"Imported from station schedule",
                schedule_pattern=cron_expression,
                schedule_description=schedule_desc,
                active=True,  # Start active by default
                host=show_schedule.host
            )
            
            db.add(show)
            db.flush()  # Get the ID
            db.commit()  # Ensure it's saved
            
            logger.info(f"Created show: {show.name} (ID: {show.id}, active: {show.active}, schedule: {schedule_desc})")
            return 'created'
            
        except Exception as e:
            logger.error(f"Error creating show {show_schedule.name}: {e}")
            raise
    
    def _update_existing_show(self, db: Session, existing_show: Show, 
                            show_schedule: ShowSchedule) -> str:
        """Update an existing show with new schedule data"""
        
        try:
            updated = False
            
            # Update description if empty or this is auto-imported
            if (not existing_show.description or 
                existing_show.description == "Imported from station schedule"):
                if show_schedule.description:
                    existing_show.description = show_schedule.description
                    updated = True
            
            # Update host if empty
            if not existing_show.host and show_schedule.host:
                existing_show.host = show_schedule.host
                updated = True
            
            # Update schedule pattern if different
            new_cron = self._generate_cron_expression(show_schedule)
            new_desc = self._generate_schedule_description(show_schedule)
            
            if existing_show.schedule_pattern != new_cron:
                existing_show.schedule_pattern = new_cron
                existing_show.schedule_description = new_desc
                updated = True
            
            if updated:
                logger.info(f"Updated show: {existing_show.name}")
                return 'updated'
            else:
                return 'skipped'
                
        except Exception as e:
            logger.error(f"Error updating show {existing_show.name}: {e}")
            raise
    
    def _generate_cron_expression(self, show_schedule: ShowSchedule) -> str:
        """Generate cron expression from show schedule"""
        
        # Convert days to cron day numbers (0=Sunday, 1=Monday, etc.)
        day_map = {
            'sunday': '0', 'monday': '1', 'tuesday': '2', 'wednesday': '3',
            'thursday': '4', 'friday': '5', 'saturday': '6'
        }
        
        cron_days = []
        for day in show_schedule.days:
            if day in day_map:
                cron_days.append(day_map[day])
        
        if not cron_days:
            # Default to weekdays if no days specified
            cron_days = ['1', '2', '3', '4', '5']
        
        # Create cron expression: minute hour * * day_of_week
        minute = show_schedule.start_time.minute
        hour = show_schedule.start_time.hour
        days_str = ','.join(sorted(cron_days))
        
        return f"{minute} {hour} * * {days_str}"
    
    def _generate_schedule_description(self, show_schedule: ShowSchedule) -> str:
        """Generate human-readable schedule description"""
        
        # Format days
        if len(show_schedule.days) == 7:
            days_str = "every day"
        elif len(show_schedule.days) == 5 and all(d in show_schedule.days for d in 
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']):
            days_str = "weekdays"
        elif len(show_schedule.days) == 2 and all(d in show_schedule.days for d in 
                ['saturday', 'sunday']):
            days_str = "weekends"
        elif len(show_schedule.days) == 1:
            days_str = f"every {show_schedule.days[0].title()}"
        else:
            # Format as "Mondays, Wednesdays, and Fridays"
            day_names = [day.title() for day in show_schedule.days]
            if len(day_names) == 2:
                days_str = f"{day_names[0]} and {day_names[1]}"
            else:
                days_str = ", ".join(day_names[:-1]) + f", and {day_names[-1]}"
        
        # Format time
        time_str = show_schedule.start_time.strftime("%I:%M %p").lstrip('0')
        
        # Add end time if available
        if show_schedule.end_time:
            end_str = show_schedule.end_time.strftime("%I:%M %p").lstrip('0')
            time_str = f"{time_str} to {end_str}"
        
        return f"Record {days_str} at {time_str}"
    
    def preview_station_schedule(self, station_id: int) -> List[Dict]:
        """Preview what shows would be imported without actually importing"""
        
        try:
            db = SessionLocal()
            
            # Get station details
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                return []
            
            # Parse schedule from station website
            shows = self.calendar_parser.parse_station_schedule(station.website_url, station_id)
            
            preview_data = []
            for show_schedule in shows:
                # Check if show already exists
                existing_show = db.query(Show).filter(
                    Show.station_id == station_id,
                    Show.name.ilike(f"%{show_schedule.name}%")
                ).first()
                
                cron_expression = self._generate_cron_expression(show_schedule)
                schedule_desc = self._generate_schedule_description(show_schedule)
                
                preview_data.append({
                    'name': show_schedule.name,
                    'description': show_schedule.description,
                    'host': show_schedule.host,
                    'genre': show_schedule.genre,
                    'start_time': show_schedule.start_time.strftime("%H:%M"),
                    'end_time': show_schedule.end_time.strftime("%H:%M") if show_schedule.end_time else None,
                    'days': show_schedule.days,
                    'duration_minutes': show_schedule.duration_minutes,
                    'schedule_description': schedule_desc,
                    'cron_expression': cron_expression,
                    'exists': existing_show is not None,
                    'existing_id': existing_show.id if existing_show else None,
                    'action': 'update' if existing_show else 'create'
                })
            
            db.close()
            return preview_data
            
        except Exception as e:
            logger.error(f"Error previewing schedule for station {station_id}: {e}")
            return []


def main():
    """Command line interface for schedule import"""
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Schedule Importer')
    parser.add_argument('--station-id', type=int, required=True,
                       help='Station ID to import schedule for')
    parser.add_argument('--preview', action='store_true',
                       help='Preview shows without importing')
    parser.add_argument('--auto-create', action='store_true', default=True,
                       help='Automatically create shows (default: True)')
    parser.add_argument('--update-existing', action='store_true',
                       help='Update existing shows')
    parser.add_argument('--verbose', '-v', action='store_true',
                       help='Verbose logging')
    parser.add_argument('--selected-shows', type=str,
                       help='Path to JSON file containing selected shows data')
    
    args = parser.parse_args()
    
    # Setup logging
    log_level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=log_level,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    importer = ScheduleImporter()
    
    if args.preview:
        preview_data = importer.preview_station_schedule(args.station_id)
        
        print(f"Found {len(preview_data)} shows in schedule:")
        for show in preview_data:
            status = "EXISTS" if show['exists'] else "NEW"
            print(f"  [{status}] {show['name']}")
            print(f"    Schedule: {show['schedule_description']}")
            if show['host']:
                print(f"    Host: {show['host']}")
            if show['description']:
                print(f"    Description: {show['description'][:100]}...")
            print()
    else:
        # Load selected shows if provided
        selected_shows_data = None
        if args.selected_shows:
            try:
                with open(args.selected_shows, 'r') as f:
                    selected_shows_data = json.loads(f.read())
                logger.info(f"Loaded {len(selected_shows_data)} selected shows from file")
            except Exception as e:
                logger.error(f"Error loading selected shows file: {e}")
                return
        
        results = importer.import_station_schedule(
            args.station_id,
            auto_create_shows=args.auto_create,
            update_existing=args.update_existing,
            selected_shows=selected_shows_data
        )
        
        print(f"Import completed:")
        print(f"  Shows found: {results['shows_found']}")
        print(f"  Shows created: {results['shows_created']}")
        print(f"  Shows updated: {results['shows_updated']}")
        print(f"  Shows skipped: {results['shows_skipped']}")
        print(f"  Errors: {results['errors']}")


if __name__ == "__main__":
    main()