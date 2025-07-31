#!/usr/bin/env python3
"""
Test script to verify WERU calendar parsing fixes for issue #14.

This script tests the timezone and duration parsing improvements in the iCal parser
specifically for WERU's "Saturday Morning Coffeehouse" show which should be:
- Start time: 7 AM (not 8 AM)
- Duration: 180 minutes (not 120 minutes)
"""

import sys
import os
import logging

# Add the backend services to the path
sys.path.append(os.path.join(os.path.dirname(__file__), 'backend/services'))

from calendar_parser import CalendarParser

def test_weru_calendar():
    """Test WERU calendar parsing with the fixed iCal parser"""
    logging.basicConfig(level=logging.INFO)
    logger = logging.getLogger(__name__)
    
    parser = CalendarParser()
    
    try:
        # Test with WERU website
        weru_url = "https://weru.org/"
        logger.info(f"Testing WERU calendar parsing from {weru_url}...")
        
        shows = parser.parse_station_schedule(weru_url)
        logger.info(f"Found {len(shows)} total shows")
        
        # Look specifically for Saturday Morning Coffeehouse
        coffeehouse_shows = [show for show in shows if 'coffeehouse' in show.name.lower()]
        
        print(f"\n=== WERU Calendar Parsing Results ===")
        print(f"Total shows found: {len(shows)}")
        print(f"Saturday Morning Coffeehouse matches: {len(coffeehouse_shows)}")
        
        if coffeehouse_shows:
            for show in coffeehouse_shows:
                print(f"\n📻 {show.name}")
                print(f"   Start: {show.start_time}")
                print(f"   End: {show.end_time}")
                print(f"   Days: {', '.join(show.days)}")
                if show.duration_minutes:
                    print(f"   Duration: {show.duration_minutes} minutes")
                
                # Check if this matches the expected fix
                if show.start_time.hour == 7 and show.duration_minutes == 180:
                    print("   ✅ CORRECT: 7 AM start time and 180 minutes duration")
                elif show.start_time.hour == 8 and show.duration_minutes == 120:
                    print("   ❌ INCORRECT: Still showing 8 AM and 120 minutes (fix needed)")
                else:
                    print(f"   ⚠️  UNKNOWN: {show.start_time.hour}:00 AM and {show.duration_minutes} minutes")
        else:
            print("   ⚠️  Saturday Morning Coffeehouse not found in results")
        
        # Show first few shows for general verification
        print(f"\n=== First 5 Shows (General Verification) ===")
        for i, show in enumerate(shows[:5]):
            print(f"{i+1}. {show.name}")
            print(f"   Time: {show.start_time} - {show.end_time}")
            print(f"   Days: {', '.join(show.days)}")
            if show.duration_minutes:
                print(f"   Duration: {show.duration_minutes} minutes")
            print()
    
    except Exception as e:
        logger.error(f"Error testing WERU calendar: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    test_weru_calendar()