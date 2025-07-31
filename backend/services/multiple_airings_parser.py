"""
"""
Parses show schedules that have multiple airings in a single description.

This service can understand natural language descriptions of complex schedules,
such as "Mondays at 7 PM and Thursdays at 3 PM". It can recognize keywords like
"original", "repeat", and "encore" to prioritize different airings.

Key Variables:
- `schedule_text`: The natural language text describing the schedule.

Inter-script Communication:
- This script is used by `schedule_parser.py` to handle complex schedules.
- It does not directly interact with the database.
"""

"""
import re
from typing import Dict, List, Optional, Tuple
import logging
from .schedule_parser import ScheduleParser

logger = logging.getLogger(__name__)

class MultipleAiringsParser:
    """Parser for detecting and handling multiple show airings"""
    
    def __init__(self):
        self.base_parser = ScheduleParser()
        
        # Patterns that suggest multiple airings
        self.multiple_patterns = [
            r'\band\b',  # "Mondays at 7 PM and Thursdays at 3 PM"
            r',\s*(?:and\s+)?',  # "Mon 7PM, Thu 3PM" or "Mon 7PM, and Thu 3PM"
            r'\+',  # "Mon 7PM + Thu 3PM"
            r'(?:also|repeat|rerun|encore)\s+(?:on\s+)?',  # "Mondays 7PM, also on Thursdays 3PM"
            r'(?:original|first)\s+(?:broadcast|airing).*(?:repeat|rerun|encore)',
        ]
        
        # Keywords that indicate repeat/secondary airings
        self.repeat_keywords = [
            'repeat', 'rerun', 'encore', 'replay', 'again',
            'second', 'secondary', 'also', 'plus'
        ]
        
        # Keywords that indicate original/primary airings
        self.original_keywords = [
            'original', 'first', 'primary', 'main', 'live', 'premiere'
        ]
    
    def parse_multiple_airings(self, schedule_text: str) -> Dict:
        """
        Parse schedule text that may contain multiple airings
        
        Args:
            schedule_text: Schedule text that may describe multiple airings
            
        Returns:
            Dictionary with multiple schedule patterns or single pattern
        """
        result = {
            'success': False,
            'airings': [],
            'has_multiple': False,
            'error': None
        }
        
        try:
            # First check if this looks like multiple airings
            if self._has_multiple_airings(schedule_text):
                airings = self._parse_multiple_schedule_text(schedule_text)
                if airings:
                    result.update({
                        'success': True,
                        'airings': airings,
                        'has_multiple': True
                    })
                    return result
            
            # Fallback to single airing parsing
            single_result = self.base_parser.parse_schedule(schedule_text)
            if single_result['success']:
                airing = {
                    'schedule_pattern': single_result['cron_expression'],
                    'schedule_description': single_result['description'],
                    'airing_type': 'original',
                    'priority': 1
                }
                result.update({
                    'success': True,
                    'airings': [airing],
                    'has_multiple': False
                })
            else:
                result['error'] = single_result.get('error', 'Failed to parse schedule')
                
        except Exception as e:
            result['error'] = f"Parse error: {str(e)}"
            logger.error(f"Multiple airings parsing error: {str(e)}")
        
        return result
    
    def _has_multiple_airings(self, schedule_text: str) -> bool:
        """Check if schedule text suggests multiple airings"""
        text_lower = schedule_text.lower()
        
        # Check for multiple timing patterns
        for pattern in self.multiple_patterns:
            if re.search(pattern, text_lower):
                return True
        
        # Check for repeat keywords
        for keyword in self.repeat_keywords:
            if re.search(r'\b' + keyword + r'\b', text_lower):
                return True
        
        # Count day mentions - multiple days might indicate multiple airings
        day_mentions = re.findall(r'\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b', text_lower)
        if len(set(day_mentions)) > 1:
            return True
        
        # Count time mentions
        time_mentions = re.findall(r'\d{1,2}(?::\d{2})?\s*(?:am|pm)', text_lower)
        if len(time_mentions) > 1:
            return True
        
        return False
    
    def _parse_multiple_schedule_text(self, schedule_text: str) -> List[Dict]:
        """Parse schedule text with multiple airings"""
        airings = []
        
        # Try to split the text into separate schedule parts
        parts = self._split_schedule_parts(schedule_text)
        
        for i, part in enumerate(parts):
            airing_info = self._parse_single_airing_part(part, i)
            if airing_info:
                airings.append(airing_info)
        
        # If we couldn't split properly, try pattern-based extraction
        if not airings:
            airings = self._extract_airings_by_pattern(schedule_text)
        
        return airings
    
    def _split_schedule_parts(self, schedule_text: str) -> List[str]:
        """Split schedule text into individual airing parts"""
        # Split on common separators
        parts = []
        
        # Try different splitting strategies
        for separator in [' and ', ', and ', ', ', ' + ', ' also ', ' repeat ']:
            if separator in schedule_text.lower():
                temp_parts = re.split(separator, schedule_text, flags=re.IGNORECASE)
                if len(temp_parts) > 1:
                    parts = [part.strip() for part in temp_parts if part.strip()]
                    break
        
        # If no splitting worked, return the original text
        if not parts:
            parts = [schedule_text]
        
        return parts
    
    def _parse_single_airing_part(self, part_text: str, index: int) -> Optional[Dict]:
        """Parse a single airing from a part of the schedule text"""
        try:
            result = self.base_parser.parse_schedule(part_text)
            if result['success']:
                # Determine airing type based on keywords and order
                airing_type = self._determine_airing_type(part_text, index)
                priority = 1 if airing_type == 'original' else index + 1
                
                return {
                    'schedule_pattern': result['cron_expression'],
                    'schedule_description': result['description'],
                    'airing_type': airing_type,
                    'priority': priority
                }
        except Exception as e:
            logger.warning(f"Failed to parse airing part '{part_text}': {e}")
        
        return None
    
    def _determine_airing_type(self, part_text: str, index: int) -> str:
        """Determine if this is original, repeat, or special airing"""
        text_lower = part_text.lower()
        
        # Check for explicit repeat keywords
        for keyword in self.repeat_keywords:
            if re.search(r'\b' + keyword + r'\b', text_lower):
                return 'repeat'
        
        # Check for explicit original keywords
        for keyword in self.original_keywords:
            if re.search(r'\b' + keyword + r'\b', text_lower):
                return 'original'
        
        # Default: first airing is original, others are repeats
        return 'original' if index == 0 else 'repeat'
    
    def _extract_airings_by_pattern(self, schedule_text: str) -> List[Dict]:
        """Extract multiple airings using pattern matching"""
        airings = []
        
        # Pattern to match day + time combinations
        pattern = r'(?:(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)s?\s+(?:at\s+)?\d{1,2}(?::\d{2})?\s*(?:am|pm))'
        
        matches = re.findall(pattern, schedule_text, re.IGNORECASE)
        
        for i, match in enumerate(matches):
            try:
                result = self.base_parser.parse_schedule(match)
                if result['success']:
                    airings.append({
                        'schedule_pattern': result['cron_expression'],
                        'schedule_description': result['description'],
                        'airing_type': 'original' if i == 0 else 'repeat',
                        'priority': i + 1
                    })
            except Exception:
                continue
        
        return airings

def parse_multiple_airings(schedule_text: str) -> Dict:
    """Convenience function for parsing multiple airings"""
    parser = MultipleAiringsParser()
    return parser.parse_multiple_airings(schedule_text)