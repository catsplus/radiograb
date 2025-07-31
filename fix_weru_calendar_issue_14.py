#!/usr/bin/env python3
"""
Fix for GitHub Issue #14: Correct WERU Calendar Parsing

This script addresses the timezone and duration parsing issues in iCal parsing that
were causing incorrect times for WERU shows, specifically "Saturday Morning Coffeehouse"
which should be 7 AM, 180 minutes (not 8 AM, 120 minutes).

The fix has been applied to calendar_parser.py and this script tests and re-imports
WERU shows to verify the correction.

Usage:
    python fix_weru_calendar_issue_14.py --test-only    # Test parsing only
    python fix_weru_calendar_issue_14.py --reimport     # Test and reimport shows  
    python fix_weru_calendar_issue_14.py --station-id 2 # Specific station ID
"""

import sys
import os
import logging
import argparse
from datetime import datetime, timedelta
from typing import List, Optional

# Add project root to Python path
sys.path.insert(0, '/opt/radiograb')

try:
    from backend.config.database import SessionLocal
    from backend.models.station import Station, Show
    from backend.services.calendar_parser import CalendarParser
    from backend.services.js_calendar_parser import JavaScriptCalendarParser
except ImportError as e:
    print(f"Import error: {e}")
    print("This script should be run from the RadioGrab container environment")
    sys.exit(1)

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class WERUCalendarFixer:
    """Fix WERU calendar parsing issues (GitHub Issue #14)"""
    
    def __init__(self):
        self.db = SessionLocal()
        self.calendar_parser = CalendarParser()
        self.js_parser = None
        
    def __del__(self):
        if self.db:
            self.db.close()
        if self.js_parser:
            self.js_parser.cleanup_driver()
    
    def find_weru_station(self, station_id: Optional[int] = None) -> Optional[Station]:
        """Find WERU station in database"""
        try:
            if station_id:
                station = self.db.query(Station).filter(Station.id == station_id).first()
                if station:
                    logger.info(f"Found station ID {station_id}: {station.name}")
                    return station
                else:
                    logger.error(f"Station ID {station_id} not found")
                    return None
            
            # Look for WERU by call letters or name
            weru_patterns = ['WERU', 'weru', 'East Range']
            for pattern in weru_patterns:
                station = self.db.query(Station).filter(
                    (Station.call_letters.ilike(f'%{pattern}%')) | 
                    (Station.name.ilike(f'%{pattern}%'))
                ).first()
                if station:
                    logger.info(f"Found WERU station: ID {station.id}, Name: {station.name}")
                    return station
            
            # Fallback: look for stations with weru.org in URL
            station = self.db.query(Station).filter(
                Station.website_url.ilike('%weru.org%')
            ).first()
            if station:
                logger.info(f"Found WERU station by URL: ID {station.id}, Name: {station.name}")
                return station
                
            logger.error("WERU station not found in database")
            return None
            
        except Exception as e:
            logger.error(f"Error finding WERU station: {e}")
            return None
    
    def test_calendar_parsing(self, station: Station) -> List:
        """Test calendar parsing with the fixed iCal parser"""
        logger.info(f"Testing calendar parsing for {station.name}")
        
        shows = []
        
        try:
            # Test with standard calendar parser first
            if station.website_url:
                logger.info(f"Parsing calendar from {station.website_url}")
                shows = self.calendar_parser.parse_station_schedule(
                    station.website_url, 
                    station_id=station.id
                )
                logger.info(f"Standard parser found {len(shows)} shows")
            
            # If no shows found, try JavaScript parser
            if not shows:
                logger.info("Trying JavaScript-aware parser...")
                try:
                    self.js_parser = JavaScriptCalendarParser(headless=True, timeout=30)
                    shows = self.js_parser.parse_station_schedule(station.website_url)
                    logger.info(f"JavaScript parser found {len(shows)} shows")
                except Exception as e:
                    logger.warning(f"JavaScript parser failed: {e}")
            
            # Analyze results for Saturday Morning Coffeehouse specifically
            coffeehouse_shows = []
            for show in shows:
                if 'coffeehouse' in show.name.lower():
                    coffeehouse_shows.append(show)
                    logger.info(f"Found potential Coffeehouse show: {show.name}")
                    logger.info(f"  Time: {show.start_time} - {show.end_time}")
                    logger.info(f"  Days: {', '.join(show.days)}")
                    if hasattr(show, 'duration_minutes') and show.duration_minutes:
                        logger.info(f"  Duration: {show.duration_minutes} minutes")
            
            if coffeehouse_shows:
                for show in coffeehouse_shows:
                    # Check if this matches the expected fix
                    if hasattr(show, 'duration_minutes'):
                        if show.start_time.hour == 7 and show.duration_minutes == 180:
                            logger.info("✅ CORRECT: Saturday Morning Coffeehouse shows 7 AM, 180 minutes")
                        elif show.start_time.hour == 8 and show.duration_minutes == 120:
                            logger.warning("❌ STILL INCORRECT: Shows 8 AM, 120 minutes - need further investigation")
                        else:
                            logger.info(f"⚠️  Found Coffeehouse at {show.start_time} for {show.duration_minutes} minutes")
                    else:
                        logger.info(f"⚠️  Found Coffeehouse at {show.start_time} but no duration info")
            else:
                logger.warning("Saturday Morning Coffeehouse not found in parsed results")
            
            return shows
            
        except Exception as e:
            logger.error(f"Error testing calendar parsing: {e}")
            import traceback
            traceback.print_exc()
            return []
    
    def reimport_shows(self, station: Station, shows: List) -> bool:
        """Re-import shows with corrected parsing"""
        logger.info(f"Re-importing {len(shows)} shows for {station.name}")
        
        try:
            # Get existing shows for this station
            existing_shows = self.db.query(Show).filter(Show.station_id == station.id).all()
            existing_names = {show.name.lower().strip() for show in existing_shows}
            
            imported_count = 0
            updated_count = 0
            
            for show_schedule in shows:
                show_name = show_schedule.name.strip()
                show_name_lower = show_name.lower()
                
                # Check if show already exists
                existing_show = None
                for show in existing_shows:
                    if show.name.lower().strip() == show_name_lower:
                        existing_show = show
                        break
                
                if existing_show:
                    # Update existing show with corrected information
                    logger.info(f"Updating existing show: {show_name}")
                    existing_show.description = show_schedule.description or existing_show.description
                    existing_show.host = show_schedule.host or existing_show.host
                    existing_show.genre = show_schedule.genre or existing_show.genre
                    
                    # Update schedule if we have time information
                    if show_schedule.start_time and show_schedule.days:
                        # Convert to cron pattern (simplified)
                        # This is a basic implementation - may need refinement
                        hour = show_schedule.start_time.hour
                        minute = show_schedule.start_time.minute
                        
                        # Map days to cron format
                        day_map = {
                            'monday': '1', 'tuesday': '2', 'wednesday': '3',
                            'thursday': '4', 'friday': '5', 'saturday': '6', 'sunday': '0'
                        }
                        cron_days = ','.join([day_map.get(day, '*') for day in show_schedule.days if day in day_map])
                        
                        if cron_days:
                            cron_pattern = f"{minute} {hour} * * {cron_days}"
                            existing_show.schedule_pattern = cron_pattern
                            
                            # Create human-readable description
                            day_names = [day.capitalize() for day in show_schedule.days]
                            time_str = show_schedule.start_time.strftime('%I:%M %p').lstrip('0')
                            existing_show.schedule_description = f"{', '.join(day_names)} at {time_str}"
                    
                    updated_count += 1
                else:
                    # Create new show (inactive by default per recent updates)
                    logger.info(f"Creating new show: {show_name}")
                    
                    new_show = Show(
                        station_id=station.id,
                        name=show_name,
                        description=show_schedule.description or f"Radio program: {show_name}",
                        host=show_schedule.host or "",
                        genre=show_schedule.genre or "Community Radio",
                        active=False,  # Inactive by default per issue filtering
                        retention_days=30
                    )
                    
                    # Set schedule if we have time information
                    if show_schedule.start_time and show_schedule.days:
                        hour = show_schedule.start_time.hour
                        minute = show_schedule.start_time.minute
                        
                        day_map = {
                            'monday': '1', 'tuesday': '2', 'wednesday': '3',
                            'thursday': '4', 'friday': '5', 'saturday': '6', 'sunday': '0'
                        }
                        cron_days = ','.join([day_map.get(day, '*') for day in show_schedule.days if day in day_map])
                        
                        if cron_days:
                            cron_pattern = f"{minute} {hour} * * {cron_days}"
                            new_show.schedule_pattern = cron_pattern
                            
                            day_names = [day.capitalize() for day in show_schedule.days]
                            time_str = show_schedule.start_time.strftime('%I:%M %p').lstrip('0')
                            new_show.schedule_description = f"{', '.join(day_names)} at {time_str}"
                    
                    self.db.add(new_show)
                    imported_count += 1
            
            # Commit changes
            self.db.commit()
            logger.info(f"Successfully imported {imported_count} new shows and updated {updated_count} existing shows")
            return True
            
        except Exception as e:
            logger.error(f"Error reimporting shows: {e}")
            self.db.rollback()
            import traceback
            traceback.print_exc()
            return False
    
    def run_fix(self, station_id: Optional[int] = None, test_only: bool = False, reimport: bool = False):
        """Main fix execution"""
        logger.info("🔧 Starting WERU Calendar Fix (GitHub Issue #14)")
        
        # Step 1: Find WERU station
        station = self.find_weru_station(station_id)
        if not station:
            logger.error("Cannot proceed without WERU station")
            return False
        
        # Step 2: Test calendar parsing with fix
        shows = self.test_calendar_parsing(station)
        
        if not shows:
            logger.warning("No shows found - may need manual calendar URL configuration")
            return False
        
        # Step 3: Re-import shows if requested
        if reimport and not test_only:
            success = self.reimport_shows(station, shows)
            if success:
                logger.info("✅ WERU calendar fix completed successfully")
            else:
                logger.error("❌ Failed to re-import shows")
            return success
        else:
            logger.info("✅ Testing completed - use --reimport to apply changes")
            return True

def main():
    parser = argparse.ArgumentParser(description='Fix WERU Calendar Parsing (Issue #14)')
    parser.add_argument('--station-id', type=int, help='Specific station ID to process')
    parser.add_argument('--test-only', action='store_true', help='Test parsing only, do not import')
    parser.add_argument('--reimport', action='store_true', help='Re-import shows after testing')
    
    args = parser.parse_args()
    
    # Validate arguments
    if not args.test_only and not args.reimport:
        parser.print_help()
        print("\nPlease specify --test-only or --reimport")
        sys.exit(1)
    
    fixer = WERUCalendarFixer()
    
    try:
        success = fixer.run_fix(
            station_id=args.station_id,
            test_only=args.test_only,
            reimport=args.reimport
        )
        sys.exit(0 if success else 1)
    except KeyboardInterrupt:
        logger.info("Fix interrupted by user")
        sys.exit(1)
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()