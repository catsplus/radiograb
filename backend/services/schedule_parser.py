"""
Converts plain English scheduling requests into cron expressions.

This service takes human-readable schedule descriptions (e.g., "every Tuesday at 7 PM")
and translates them into cron expressions that can be used by the APScheduler.
It also generates human-readable descriptions from cron expressions.

Key Variables:
- `schedule_text`: The natural language schedule description.

Inter-script Communication:
- This script is used by `schedule_importer.py` and `show_manager.py` to parse schedules.
- It does not directly interact with the database.
"""

import re
from datetime import datetime, time
from typing import Dict, List, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class ScheduleParser:
    """Converts natural language scheduling to cron expressions"""
    
    def __init__(self):
        # Days of week mapping
        self.days_map = {
            'monday': '1', 'mon': '1',
            'tuesday': '2', 'tue': '2', 'tues': '2',
            'wednesday': '3', 'wed': '3',
            'thursday': '4', 'thu': '4', 'thur': '4', 'thurs': '4',
            'friday': '5', 'fri': '5',
            'saturday': '6', 'sat': '6',
            'sunday': '0', 'sun': '0',
            'weekday': '1-5',
            'weekdays': '1-5',
            'weekend': '0,6',
            'weekends': '0,6',
            'daily': '*',
            'everyday': '*'
        }
        
        # Time parsing patterns
        self.time_patterns = [
            # Special cases
            (r'\bnoon\b', self._parse_noon),
            (r'\bmidnight\b', self._parse_midnight),
            # 12-hour format
            (r'(\d{1,2}):(\d{2})\s*(am|pm)', self._parse_12hour),
            (r'(\d{1,2})\s*(am|pm)', self._parse_12hour_no_minutes),
            # 24-hour format
            (r'(\d{1,2}):(\d{2})', self._parse_24hour),
            (r'(\d{1,2})', self._parse_hour_only),
        ]
    
    def parse_schedule(self, schedule_text: str) -> Dict:
        """
        Parse natural language schedule into cron expression
        
        Args:
            schedule_text: Natural language schedule description
            
        Returns:
            Dictionary with cron expression and human description
        """
        result = {
            'success': False,
            'cron_expression': None,
            'description': None,
            'error': None
        }
        
        try:
            schedule_lower = schedule_text.lower().strip()
            
            # Extract time
            time_info = self._extract_time(schedule_lower)
            if not time_info:
                result['error'] = "Could not parse time from schedule"
                return result
            
            hour, minute = time_info
            
            # Extract days
            days = self._extract_days(schedule_lower)
            if not days:
                result['error'] = "Could not parse days from schedule"
                return result
            
            # Build cron expression: minute hour day month day_of_week
            cron_expression = f"{minute} {hour} * * {days}"
            
            # Generate human-readable description
            description = self._generate_description(hour, minute, days, schedule_text)
            
            result.update({
                'success': True,
                'cron_expression': cron_expression,
                'description': description
            })
            
        except Exception as e:
            result['error'] = f"Parse error: {str(e)}"
            logger.error(f"Schedule parsing error: {str(e)}")
        
        return result
    
    def _extract_time(self, schedule_text: str) -> Optional[Tuple[int, int]]:
        """Extract hour and minute from schedule text"""
        for pattern, parser in self.time_patterns:
            match = re.search(pattern, schedule_text, re.IGNORECASE)
            if match:
                try:
                    return parser(match)
                except ValueError:
                    continue
        return None
    
    def _parse_12hour(self, match) -> Tuple[int, int]:
        """Parse 12-hour time format with minutes"""
        hour = int(match.group(1))
        minute = int(match.group(2))
        ampm = match.group(3).lower()
        
        if ampm == 'pm' and hour != 12:
            hour += 12
        elif ampm == 'am' and hour == 12:
            hour = 0
        
        if not (0 <= hour <= 23 and 0 <= minute <= 59):
            raise ValueError("Invalid time")
        
        return hour, minute
    
    def _parse_12hour_no_minutes(self, match) -> Tuple[int, int]:
        """Parse 12-hour time format without minutes"""
        hour = int(match.group(1))
        ampm = match.group(2).lower()
        
        if ampm == 'pm' and hour != 12:
            hour += 12
        elif ampm == 'am' and hour == 12:
            hour = 0
        
        if not (0 <= hour <= 23):
            raise ValueError("Invalid hour")
        
        return hour, 0
    
    def _parse_24hour(self, match) -> Tuple[int, int]:
        """Parse 24-hour time format"""
        hour = int(match.group(1))
        minute = int(match.group(2))
        
        if not (0 <= hour <= 23 and 0 <= minute <= 59):
            raise ValueError("Invalid time")
        
        return hour, minute
    
    def _parse_hour_only(self, match) -> Tuple[int, int]:
        """Parse hour only (assume 24-hour format)"""
        hour = int(match.group(1))
        
        if not (0 <= hour <= 23):
            raise ValueError("Invalid hour")
        
        return hour, 0
    
    def _parse_noon(self, match) -> Tuple[int, int]:
        """Parse 'noon'"""
        return 12, 0
    
    def _parse_midnight(self, match) -> Tuple[int, int]:
        """Parse 'midnight'"""
        return 0, 0
    
    def _extract_days(self, schedule_text: str) -> Optional[str]:
        """Extract days of week from schedule text"""
        # Look for specific days
        found_days = []
        
        for day_name, cron_value in self.days_map.items():
            if day_name in schedule_text:
                if cron_value in ['*', '1-5', '0,6']:
                    return cron_value  # Special cases
                else:
                    found_days.append(cron_value)
        
        if found_days:
            # Remove duplicates and sort
            unique_days = sorted(set(found_days))
            return ','.join(unique_days)
        
        # Default patterns
        if any(word in schedule_text for word in ['every', 'daily', 'each']):
            if any(word in schedule_text for word in ['weekday', 'workday']):
                return '1-5'  # Monday to Friday
            elif any(word in schedule_text for word in ['weekend']):
                return '0,6'  # Saturday and Sunday
            else:
                return '*'  # Every day
        
        # If no days specified, assume every day
        return '*'
    
    def _generate_description(self, hour: int, minute: int, days: str, original: str) -> str:
        """Generate human-readable description"""
        # Format time
        if minute == 0:
            time_str = f"{hour:02d}:00"
        else:
            time_str = f"{hour:02d}:{minute:02d}"
        
        # Convert to 12-hour format for description
        if hour == 0:
            time_12hr = f"12:{minute:02d} AM"
        elif hour < 12:
            time_12hr = f"{hour}:{minute:02d} AM"
        elif hour == 12:
            time_12hr = f"12:{minute:02d} PM"
        else:
            time_12hr = f"{hour-12}:{minute:02d} PM"
        
        # Format days
        if days == '*':
            days_str = "every day"
        elif days == '1-5':
            days_str = "weekdays (Monday-Friday)"
        elif days == '0,6':
            days_str = "weekends (Saturday-Sunday)"
        else:
            # Map cron values back to day names
            day_numbers = days.split(',')
            day_names = []
            day_map = {'0': 'Sunday', '1': 'Monday', '2': 'Tuesday', 
                      '3': 'Wednesday', '4': 'Thursday', '5': 'Friday', '6': 'Saturday'}
            
            for num in day_numbers:
                if num in day_map:
                    day_names.append(day_map[num])
            
            if len(day_names) == 1:
                days_str = f"every {day_names[0]}"
            else:
                days_str = f"every {', '.join(day_names[:-1])} and {day_names[-1]}"
        
        return f"Record at {time_12hr} {days_str}"
    
    def validate_cron_expression(self, cron_expr: str) -> bool:
        """Validate a cron expression"""
        try:
            parts = cron_expr.split()
            if len(parts) != 5:
                return False
            
            minute, hour, day, month, day_of_week = parts
            
            # Basic validation
            if not self._validate_cron_field(minute, 0, 59):
                return False
            if not self._validate_cron_field(hour, 0, 23):
                return False
            if not self._validate_cron_field(day, 1, 31):
                return False
            if not self._validate_cron_field(month, 1, 12):
                return False
            if not self._validate_cron_field(day_of_week, 0, 6):
                return False
            
            return True
            
        except Exception:
            return False
    
    def _validate_cron_field(self, field: str, min_val: int, max_val: int) -> bool:
        """Validate a single cron field"""
        if field == '*':
            return True
        
        # Handle ranges and lists
        for part in field.split(','):
            if '-' in part:
                try:
                    start, end = part.split('-')
                    if not (min_val <= int(start) <= max_val and min_val <= int(end) <= max_val):
                        return False
                except ValueError:
                    return False
            else:
                try:
                    if not (min_val <= int(part) <= max_val):
                        return False
                except ValueError:
                    return False
        
        return True

def test_schedule_parser():
    """Test the schedule parser with various inputs"""
    parser = ScheduleParser()
    
    test_schedules = [
        "Record every Tuesday at 7 PM",
        "Daily at 6:30 AM",
        "Weekdays at 9 AM",
        "Monday and Friday at 8:00 PM", 
        "Every weekend at 10 AM",
        "Record at 15:00 every day",
        "Sunday at noon",
        "Record Monday through Friday at 6 PM"
    ]
    
    print("=== Testing Schedule Parser ===")
    
    for schedule in test_schedules:
        print(f"\nInput: '{schedule}'")
        result = parser.parse_schedule(schedule)
        
        if result['success']:
            print(f"✅ Cron: {result['cron_expression']}")
            print(f"   Description: {result['description']}")
            print(f"   Valid: {parser.validate_cron_expression(result['cron_expression'])}")
        else:
            print(f"❌ Error: {result['error']}")

if __name__ == "__main__":
    test_schedule_parser()