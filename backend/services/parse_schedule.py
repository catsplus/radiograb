#!/usr/bin/env python3
"""
"""
Parses natural language schedule descriptions into cron expressions.

This script is a command-line tool that takes a human-readable schedule
description (e.g., "every weekday at 8:00 AM") and converts it into a cron
expression that can be used by the APScheduler.

Key Variables:
- `schedule_text`: The natural language schedule description.

Inter-script Communication:
- This script is called by the frontend API when adding or editing a show.
- It uses `multiple_airings_parser.py` to handle complex schedules.
- It does not directly interact with the database.
"""

"""

import sys
import json
import os

# Add the project root to the path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from backend.services.schedule_parser import ScheduleParser

def main():
    if len(sys.argv) != 2:
        result = {
            'success': False,
            'error': 'Schedule text required as argument'
        }
        print(json.dumps(result))
        sys.exit(1)
    
    schedule_text = sys.argv[1]
    parser = ScheduleParser()
    
    try:
        result = parser.parse_schedule(schedule_text)
        
        # Convert to expected format for PHP
        if result['success']:
            output = {
                'cron': result['cron_expression'],
                'description': result['description']
            }
        else:
            output = {
                'error': result['error']
            }
        
        print(json.dumps(output))
        
    except Exception as e:
        output = {
            'error': f'Parsing failed: {str(e)}'
        }
        print(json.dumps(output))
        sys.exit(1)

if __name__ == "__main__":
    main()