#!/usr/bin/env python3
"""
ICS Parser Service
Parses uploaded ICS files and converts them to RadioGrab show format
"""

import sys
import os
import logging
from pathlib import Path
from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional
from dataclasses import dataclass
import re

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from icalendar import Calendar, Event
from backend.models.calendar_show import CalendarShow

logger = logging.getLogger(__name__)

@dataclass
class ICSParseResult:
    """Result of ICS file parsing"""
    success: bool
    shows: List[CalendarShow] = None
    error: Optional[str] = None
    method_info: Optional[str] = None
    stats: Optional[Dict[str, Any]] = None

class ICSParser:
    """Parser for ICS calendar files"""
    
    def __init__(self):
        self.supported_rrule_freq = {'WEEKLY', 'DAILY'}
        self.day_mapping = {
            'MO': 'Monday',
            'TU': 'Tuesday', 
            'WE': 'Wednesday',
            'TH': 'Thursday',
            'FR': 'Friday',
            'SA': 'Saturday',
            'SU': 'Sunday'
        }
    
    def parse_ics_file(self, file_path: str, station_id: int) -> ICSParseResult:
        """
        Parse an ICS file and extract show information
        
        Args:
            file_path: Path to the ICS file
            station_id: Station ID for the shows
            
        Returns:
            ICSParseResult with parsing results
        """
        try:
            if not os.path.exists(file_path):
                return ICSParseResult(
                    success=False,
                    error=f"ICS file not found: {file_path}"
                )
            
            with open(file_path, 'rb') as f:
                calendar_data = f.read()
            
            # Parse the calendar
            calendar = Calendar.from_ical(calendar_data)
            
            shows = []
            events_processed = 0
            events_skipped = 0
            method_info = []
            
            # Add method information
            method_info.append("📅 ICS File Analysis:")
            method_info.append(f"   • Calendar format: iCalendar (.ics)")
            method_info.append(f"   • Parser: Python icalendar library")
            
            # Check calendar properties
            if 'X-WR-CALNAME' in calendar:
                cal_name = str(calendar['X-WR-CALNAME'])
                method_info.append(f"   • Calendar name: {cal_name}")
            
            # Process events
            for component in calendar.walk():
                if component.name == "VEVENT":
                    try:
                        show = self._parse_event(component, station_id)
                        if show:
                            shows.append(show)
                            events_processed += 1
                        else:
                            events_skipped += 1
                    except Exception as e:
                        logger.warning(f"Failed to parse event: {e}")
                        events_skipped += 1
            
            # Add processing stats to method info
            method_info.append(f"   • Events processed: {events_processed}")
            method_info.append(f"   • Events skipped: {events_skipped}")
            method_info.append(f"   • Shows discovered: {len(shows)}")
            
            # Filter and validate shows
            valid_shows = self._filter_valid_shows(shows)
            
            if events_skipped > 0:
                method_info.append(f"   • Invalid/filtered shows: {len(shows) - len(valid_shows)}")
            
            stats = {
                'events_processed': events_processed,
                'events_skipped': events_skipped,
                'shows_discovered': len(shows),
                'valid_shows': len(valid_shows)
            }
            
            return ICSParseResult(
                success=True,
                shows=valid_shows,
                method_info="\n".join(method_info),
                stats=stats
            )
            
        except Exception as e:
            logger.error(f"Error parsing ICS file {file_path}: {e}")
            return ICSParseResult(
                success=False,
                error=f"Failed to parse ICS file: {str(e)}"
            )
    
    def _parse_event(self, event: Event, station_id: int) -> Optional[CalendarShow]:
        """Parse a single calendar event into a CalendarShow"""
        try:
            # Extract basic information
            summary = str(event.get('summary', ''))
            description = str(event.get('description', '')) if event.get('description') else ''
            
            # Skip if no summary
            if not summary.strip():
                return None
            
            # Get start time
            dtstart = event.get('dtstart')
            if not dtstart:
                return None
            
            start_datetime = dtstart.dt
            if isinstance(start_datetime, datetime):
                start_time = start_datetime.time()
            else:
                # All-day event, skip
                return None
            
            # Get end time
            end_time = None
            duration_minutes = 60  # Default
            
            dtend = event.get('dtend')
            duration = event.get('duration')
            
            if dtend:
                end_datetime = dtend.dt
                if isinstance(end_datetime, datetime):
                    end_time = end_datetime.time()
                    duration_minutes = int((end_datetime - start_datetime).total_seconds() / 60)
            elif duration:
                duration_minutes = int(duration.dt.total_seconds() / 60)
            
            # Parse recurrence rule
            days = self._parse_recurrence(event, start_datetime)
            if not days:
                # Single event, use the day of the week
                days = self._get_day_name(start_datetime.weekday())
            
            # Clean up name and description
            name = self._clean_show_name(summary)
            description = self._clean_description(description)
            
            # Extract host and genre if possible
            host = self._extract_host(description, summary)
            genre = self._extract_genre(description, summary)
            
            return CalendarShow(
                name=name,
                start_time=start_time,
                end_time=end_time,
                days=days,
                description=description,
                host=host,
                genre=genre,
                duration_minutes=duration_minutes,
                station_id=station_id
            )
            
        except Exception as e:
            logger.warning(f"Failed to parse event {summary}: {e}")
            return None
    
    def _parse_recurrence(self, event: Event, start_datetime: datetime) -> Optional[str]:
        """Parse RRULE to determine recurring days"""
        rrule = event.get('rrule')
        if not rrule:
            return None
        
        try:
            freq = rrule.get('FREQ', [None])[0]
            byday = rrule.get('BYDAY', [])
            
            if freq == 'WEEKLY':
                if byday:
                    # Convert BYDAY to day names
                    day_names = []
                    for day_code in byday:
                        if day_code in self.day_mapping:
                            day_names.append(self.day_mapping[day_code])
                    
                    if day_names:
                        return ', '.join(day_names)
                else:
                    # Weekly without BYDAY, use the start day
                    return self._get_day_name(start_datetime.weekday())
            
            elif freq == 'DAILY':
                return 'Daily'
            
        except Exception as e:
            logger.warning(f"Failed to parse RRULE: {e}")
        
        return None
    
    def _get_day_name(self, weekday: int) -> str:
        """Convert weekday number to name"""
        days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        return days[weekday]
    
    def _clean_show_name(self, name: str) -> str:
        """Clean and normalize show name"""
        # Remove extra whitespace
        name = ' '.join(name.split())
        
        # Remove common prefixes/suffixes
        patterns_to_remove = [
            r'^(LIVE|RADIO|SHOW):\s*',
            r'\s*-\s*(LIVE|RADIO|SHOW)$',
            r'\s*\([^)]*\)$',  # Remove parenthetical at end
        ]
        
        for pattern in patterns_to_remove:
            name = re.sub(pattern, '', name, flags=re.IGNORECASE).strip()
        
        return name[:100]  # Limit length
    
    def _clean_description(self, description: str) -> str:
        """Clean and normalize description"""
        if not description:
            return ''
        
        # Remove HTML tags
        description = re.sub(r'<[^>]+>', '', description)
        
        # Remove extra whitespace
        description = ' '.join(description.split())
        
        # Limit length
        return description[:500]
    
    def _extract_host(self, description: str, summary: str) -> Optional[str]:
        """Try to extract host information"""
        text = f"{summary} {description}".lower()
        
        # Common host patterns
        host_patterns = [
            r'(?:host|hosted by|with|featuring)[:]\s*([^.,\n]+)',
            r'([^.,\n]+)\s+hosts?',
            r'presented by\s+([^.,\n]+)',
        ]
        
        for pattern in host_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                host = match.group(1).strip()
                # Clean up host name
                host = re.sub(r'\s+', ' ', host)
                if len(host) > 3 and len(host) < 50:
                    return host
        
        return None
    
    def _extract_genre(self, description: str, summary: str) -> Optional[str]:
        """Try to extract genre information"""
        text = f"{summary} {description}".lower()
        
        # Common genre keywords
        genres = {
            'news': ['news', 'current affairs', 'politics', 'journalism'],
            'music': ['music', 'songs', 'hits', 'classics', 'rock', 'pop', 'jazz', 'blues', 'country', 'hip hop'],
            'talk': ['talk show', 'interview', 'discussion', 'conversation'],
            'sports': ['sports', 'football', 'basketball', 'baseball', 'soccer', 'hockey'],
            'culture': ['culture', 'arts', 'literature', 'books', 'film', 'movies'],
            'comedy': ['comedy', 'humor', 'funny', 'jokes'],
            'education': ['educational', 'learning', 'science', 'history', 'documentary'],
            'religion': ['religious', 'faith', 'spiritual', 'church', 'ministry']
        }
        
        for genre, keywords in genres.items():
            for keyword in keywords:
                if keyword in text:
                    return genre.title()
        
        return None
    
    def _filter_valid_shows(self, shows: List[CalendarShow]) -> List[CalendarShow]:
        """Filter out invalid or duplicate shows"""
        if not shows:
            return []
        
        valid_shows = []
        seen_names = set()
        
        for show in shows:
            # Skip if name is too short or generic
            if len(show.name) < 3:
                continue
            
            # Skip generic/invalid names
            invalid_patterns = [
                r'^(schedule|calendar|events?)$',
                r'^(show|program|radio)$',  
                r'^(home|about|contact)$',
                r'^(archive|past|previous)$',
                r'^\d+$',  # Just numbers
                r'^[a-z]$',  # Single letters
            ]
            
            is_invalid = False
            for pattern in invalid_patterns:
                if re.match(pattern, show.name.lower().strip()):
                    is_invalid = True
                    break
            
            if is_invalid:
                continue
            
            # Skip duplicates (same name and time)
            show_key = f"{show.name.lower()}_{show.start_time}_{show.days}"
            if show_key in seen_names:
                continue
            
            seen_names.add(show_key)
            valid_shows.append(show)
        
        return valid_shows


def main():
    """Command line interface"""
    import argparse
    
    parser = argparse.ArgumentParser(description='ICS Calendar Parser')
    parser.add_argument('ics_file', help='Path to ICS file')
    parser.add_argument('--station-id', type=int, required=True, help='Station ID')
    parser.add_argument('--debug', action='store_true', help='Enable debug logging')
    
    args = parser.parse_args()
    
    if args.debug:
        logging.basicConfig(level=logging.DEBUG)
    else:
        logging.basicConfig(level=logging.INFO)
    
    parser = ICSParser()
    result = parser.parse_ics_file(args.ics_file, args.station_id)
    
    if result.success:
        print(f"✅ Successfully parsed {len(result.shows)} shows")
        
        if result.method_info:
            print("\n" + result.method_info)
        
        print(f"\n📊 Shows found:")
        for show in result.shows:
            print(f"   • {show.name} - {show.days} at {show.start_time}")
    else:
        print(f"❌ Failed to parse ICS file: {result.error}")


if __name__ == '__main__':
    main()