#!/usr/bin/env python3
"""
Simple script to parse schedule text and return JSON
Called from PHP with schedule text as argument
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