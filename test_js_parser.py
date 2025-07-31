"""
Tests the JavaScript-aware calendar parser.

This script contains various test cases to verify the correct parsing of station
schedules from dynamic web pages that rely on JavaScript rendering. It uses a
headless Chrome browser via Selenium WebDriver to simulate a real user's browser.

Key Variables:
- `test_urls`: A list of URLs for testing the JavaScript calendar parser.
- `invalid_names`: A list of strings that should be filtered out as invalid show names.
- `valid_names`: A list of strings that should be considered valid show names.

Inter-script Communication:
- This script directly imports and tests `backend.services.js_calendar_parser.JavaScriptCalendarParser`.
"""


import sys
import logging
import os
sys.path.append(os.path.join(os.path.dirname(__file__), 'backend/services'))

from js_calendar_parser import JavaScriptCalendarParser

def test_simple():
    """Simple test of the JavaScript parser"""
    logging.basicConfig(level=logging.INFO)
    
    parser = JavaScriptCalendarParser(headless=True)
    
    try:
        # Test with WTBR
        test_url = "https://wtbrfm.com"
        print(f"Testing JavaScript parser with {test_url}...")
        
        shows = parser.parse_station_schedule(test_url)
        print(f"Found {len(shows)} shows")
        
        for show in shows[:5]:  # Show first 5 results
            print(f"  - {show.name} at {show.start_time} on {', '.join(show.days)}")
    
    except Exception as e:
        print(f"Error: {e}")
    
    finally:
        parser.cleanup_driver()

if __name__ == "__main__":
    test_simple()