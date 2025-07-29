#!/usr/bin/env python3
"""
Calendar Parser Service
Extracts show schedules from radio station websites
Supports various formats: HTML tables, JSON, iCal, XML
"""

import re
import json
import logging
from datetime import datetime, time, timedelta
from typing import Dict, List, Optional, Tuple, Any
from urllib.parse import urljoin, urlparse
import requests
from bs4 import BeautifulSoup
import icalendar
from dataclasses import dataclass

logger = logging.getLogger(__name__)


@dataclass
class ShowSchedule:
    """Represents a show's schedule information"""
    name: str
    start_time: time
    end_time: time
    days: List[str]  # ['monday', 'tuesday', etc.]
    description: Optional[str] = None
    host: Optional[str] = None
    genre: Optional[str] = None
    duration_minutes: Optional[int] = None
    
    def __post_init__(self):
        if self.duration_minutes is None and self.start_time and self.end_time:
            # Calculate duration
            start_dt = datetime.combine(datetime.today(), self.start_time)
            end_dt = datetime.combine(datetime.today(), self.end_time)
            
            # Handle overnight shows
            if end_dt < start_dt:
                end_dt += timedelta(days=1)
            
            self.duration_minutes = int((end_dt - start_dt).total_seconds() / 60)


class CalendarParser:
    """Parses radio station schedules from various sources"""
    
    def __init__(self, timeout: int = 30):
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'RadioGrab/1.0 (Calendar Parser)'
        })
        
        # Common time formats to try parsing
        self.time_patterns = [
            r'(\d{1,2}):(\d{2})\s*(AM|PM)',  # 12:00 PM
            r'(\d{1,2}):(\d{2})',           # 12:00 (24hr)
            r'(\d{1,2})\s*(AM|PM)',         # 12 PM
            r'(\d{1,2})\.(\d{2})',          # 12.00
            r'(\d{4})',                     # 1200 (military)
        ]
        
        # Day name mappings
        self.day_mappings = {
            'mon': 'monday', 'monday': 'monday', 'm': 'monday',
            'tue': 'tuesday', 'tuesday': 'tuesday', 'tues': 'tuesday', 't': 'tuesday',
            'wed': 'wednesday', 'wednesday': 'wednesday', 'w': 'wednesday',
            'thu': 'thursday', 'thursday': 'thursday', 'thurs': 'thursday', 'th': 'thursday',
            'fri': 'friday', 'friday': 'friday', 'f': 'friday',
            'sat': 'saturday', 'saturday': 'saturday', 's': 'saturday',
            'sun': 'sunday', 'sunday': 'sunday', 'su': 'sunday',
        }
    
    def parse_station_schedule(self, station_url: str, station_id: int = None) -> List[ShowSchedule]:
        """Main entry point to parse a station's schedule"""
        try:
            # First, check if we have a saved calendar configuration for this station
            saved_calendar_url = None
            saved_parsing_method = None
            if station_id:
                saved_calendar_url, saved_parsing_method = self._get_saved_calendar_config(station_id)
            
            schedule_urls = []
            if saved_calendar_url:
                logger.info(f"Using saved calendar URL: {saved_calendar_url} with method: {saved_parsing_method}")
                schedule_urls = [saved_calendar_url]
            else:
                # Discover schedule URLs
                schedule_urls = self._discover_schedule_urls(station_url)
            
            all_shows = []
            successful_url = None
            successful_method = None
            
            # Before trying discovered URLs, check for embedded Google Sheets
            if not saved_calendar_url:
                # First check the main station URL
                sheets_shows = self._parse_embedded_google_sheets(station_url)
                if sheets_shows:
                    logger.info(f"Found shows using embedded Google Sheets parser from main URL")
                    return sheets_shows
                
                # Also check discovered schedule URLs for embedded Google Sheets
                for url in schedule_urls[:3]:  # Check first 3 URLs to avoid too many requests
                    try:
                        sheets_shows = self._parse_embedded_google_sheets(url)
                        if sheets_shows:
                            logger.info(f"Found shows using embedded Google Sheets parser from {url}")
                            return sheets_shows
                    except Exception as e:
                        logger.debug(f"Error checking {url} for Google Sheets: {e}")
            
            for url in schedule_urls:
                try:
                    if saved_parsing_method:
                        # Use the known working parsing method
                        shows = self._parse_with_specific_method(url, saved_parsing_method)
                        parsing_method = saved_parsing_method
                    else:
                        # Try to auto-detect the parsing method
                        shows, parsing_method = self._parse_schedule_page_with_method(url)
                    
                    if shows:  # Only consider it successful if we found shows
                        all_shows.extend(shows)
                        successful_url = url
                        successful_method = parsing_method
                        break  # Stop after first successful parse
                except Exception as e:
                    logger.warning(f"Failed to parse schedule from {url}: {e}")
            
            # Save successful URL and method to database for future use
            if successful_url and station_id and not saved_calendar_url:
                self._save_calendar_config(station_id, successful_url, successful_method)
                logger.info(f"Saved calendar URL {successful_url} with method '{successful_method}' for station {station_id}")
            
            # Remove duplicates and return
            return self._deduplicate_shows(all_shows)
            
        except Exception as e:
            logger.error(f"Error parsing station schedule from {station_url}: {e}")
            return []
    
    def _discover_schedule_urls(self, station_url: str) -> List[str]:
        """Find potential schedule/programming pages on the station website"""
        urls_to_try = [station_url]
        
        try:
            response = self.session.get(station_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Look for schedule-related links
            schedule_keywords = [
                'schedule', 'programming', 'shows', 'lineup', 'calendar',
                'timetable', 'what\'s on', 'program guide', 'on air'
            ]
            
            for link in soup.find_all('a', href=True):
                href = link.get('href', '').lower()
                text = link.get_text().lower()
                
                # Check if link text or href contains schedule keywords
                for keyword in schedule_keywords:
                    if keyword in href or keyword in text:
                        full_url = urljoin(station_url, link['href'])
                        if full_url not in urls_to_try:
                            urls_to_try.append(full_url)
                        break
            
            # Also try common schedule page paths
            base_url = f"{urlparse(station_url).scheme}://{urlparse(station_url).netloc}"
            common_paths = [
                '/schedule', '/programming', '/shows', '/lineup', '/calendar',
                '/schedule.html', '/programming.html', '/shows.html'
            ]
            
            for path in common_paths:
                url = urljoin(base_url, path)
                if url not in urls_to_try:
                    urls_to_try.append(url)
        
        except Exception as e:
            logger.warning(f"Error discovering schedule URLs from {station_url}: {e}")
        
        return urls_to_try[:10]  # Limit to prevent excessive requests
    
    def _parse_schedule_page_with_method(self, url: str) -> Tuple[List[ShowSchedule], str]:
        """Parse a schedule page and return shows with the parsing method used"""
        try:
            response = self.session.get(url, timeout=self.timeout)
            response.raise_for_status()
            
            content_type = response.headers.get('content-type', '').lower()
            
            if 'application/json' in content_type:
                shows = self._parse_json_schedule(response.text)
                return shows, 'json'
            elif 'text/calendar' in content_type or url.endswith('.ics'):
                shows = self._parse_ical_schedule(response.content)
                return shows, 'ical'
            elif 'xml' in content_type:
                shows = self._parse_xml_schedule(response.content)
                return shows, 'xml'
            else:
                shows = self._parse_html_schedule(response.content, url)
                return shows, 'html'
                
        except Exception as e:
            logger.error(f"Error parsing schedule page {url}: {e}")
            return [], 'failed'
    
    def _parse_schedule_page(self, url: str) -> List[ShowSchedule]:
        """Parse a schedule page (backward compatibility)"""
        shows, _ = self._parse_schedule_page_with_method(url)
        return shows
    
    def _parse_with_specific_method(self, url: str, method: str) -> List[ShowSchedule]:
        """Parse a schedule page using a specific known method"""
        try:
            response = self.session.get(url, timeout=self.timeout)
            response.raise_for_status()
            
            if method == 'json':
                return self._parse_json_schedule(response.text)
            elif method == 'ical':
                return self._parse_ical_schedule(response.content)
            elif method == 'xml':
                return self._parse_xml_schedule(response.content)
            elif method == 'html':
                return self._parse_html_schedule(response.content, url)
            elif method.startswith('custom_'):
                return self._parse_custom_schedule(method, response, url)
            else:
                logger.warning(f"Unknown parsing method: {method}, falling back to auto-detection")
                shows, _ = self._parse_schedule_page_with_method(url)
                return shows
                
        except Exception as e:
            logger.error(f"Error parsing schedule with method '{method}' from {url}: {e}")
            return []
    
    def _parse_custom_schedule(self, method: str, response, url: str) -> List[ShowSchedule]:
        """Parse schedule using custom station-specific methods"""
        if method == 'custom_wyso':
            return self._parse_wyso_schedule(response, url)
        else:
            logger.warning(f"Unknown custom method: {method}")
            return []
    
    def _parse_wyso_schedule(self, response, url: str) -> List[ShowSchedule]:
        """Custom parser for WYSO's JavaScript-rendered schedule"""
        shows = []
        try:
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Strategy 1: Look for show links in WYSO's all-shows page structure
            show_links = soup.find_all('a', href=re.compile(r'/show/'))
            for link in show_links:
                show_name = link.get_text().strip()
                if show_name and len(show_name) > 2:
                    # Skip navigation/generic items
                    if not any(skip in show_name.lower() for skip in ['show', 'programs', 'music', 'news', 'directory']):
                        show_schedule = ShowSchedule(
                            name=show_name,
                            start_time=time(9, 0),  # Default 9 AM
                            end_time=time(10, 0),   # Default 1 hour
                            days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                            description=f"WYSO program: {show_name}",
                            host="",
                            genre="",
                            duration_minutes=60
                        )
                        shows.append(show_schedule)
            
            # Strategy 2: Look for streaming program data attributes
            if not shows:
                stream_elements = soup.find_all(attrs={"data-stream-program-name": True})
                for element in stream_elements:
                    program_name = element.get('data-stream-program-name', '').strip()
                    if program_name and len(program_name) > 2:
                        show_schedule = ShowSchedule(
                            name=program_name,
                            start_time=time(9, 0),
                            end_time=time(10, 0),
                            days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                            description=f"WYSO streaming program: {program_name}",
                            host="",
                            genre="",
                            duration_minutes=60
                        )
                        shows.append(show_schedule)
            
            # Strategy 3: Look for elements with PromoAudioShowA-title class (WYSO specific)
            if not shows:
                title_elements = soup.find_all(class_='PromoAudioShowA-title')
                for element in title_elements:
                    show_link = element.find('a')
                    if show_link:
                        show_name = show_link.get_text().strip()
                        if show_name and len(show_name) > 2:
                            show_schedule = ShowSchedule(
                                name=show_name,
                                start_time=time(9, 0),
                                end_time=time(10, 0),
                                days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                                description=f"WYSO show: {show_name}",
                                host="",
                                genre="",
                                duration_minutes=60
                            )
                            shows.append(show_schedule)
            
            # Strategy 4: Look for JSON data in script tags
            if not shows:
                scripts = soup.find_all('script')
                for script in scripts:
                    if script.string and ('schedule' in script.string.lower() or 'show' in script.string.lower()):
                        script_content = script.string
                        json_matches = re.findall(r'(\{[^{}]*(?:"(?:name|title|show)"[^{}]*)*\})', script_content)
                        for match in json_matches:
                            try:
                                data = json.loads(match)
                                if isinstance(data, dict) and ('name' in data or 'title' in data):
                                    show_name = data.get('name') or data.get('title', '').strip()
                                    if show_name and len(show_name) > 2:
                                        show_schedule = ShowSchedule(
                                            name=show_name,
                                            start_time=time(9, 0),
                                            end_time=time(10, 0),
                                            days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                                            description=data.get('description', ''),
                                            host=data.get('host', ''),
                                            genre=data.get('genre', ''),
                                            duration_minutes=60
                                        )
                                        shows.append(show_schedule)
                            except (json.JSONDecodeError, ValueError):
                                continue
            
            # Remove duplicates by name
            unique_shows = []
            seen_names = set()
            for show in shows:
                if show.name.lower() not in seen_names:
                    seen_names.add(show.name.lower())
                    unique_shows.append(show)
            
            logger.info(f"WYSO custom parser found {len(unique_shows)} unique shows from {len(shows)} total matches")
            return unique_shows
            
        except Exception as e:
            logger.error(f"Error in WYSO custom parser: {e}")
            return []
    
    def _parse_html_schedule(self, content: bytes, url: str) -> List[ShowSchedule]:
        """Parse HTML schedule tables"""
        soup = BeautifulSoup(content, 'html.parser')
        shows = []
        
        # Strategy 1: Look for tables with time/show information
        tables = soup.find_all('table')
        for table in tables:
            table_shows = self._parse_schedule_table(table)
            shows.extend(table_shows)
        
        # Strategy 2: Look for structured div/span elements
        if not shows:
            shows.extend(self._parse_schedule_divs(soup))
        
        # Strategy 3: Look for JSON-LD structured data
        if not shows:
            shows.extend(self._parse_structured_data(soup))
        
        return shows
    
    def _parse_schedule_table(self, table) -> List[ShowSchedule]:
        """Extract schedule from HTML table"""
        shows = []
        
        try:
            rows = table.find_all('tr')
            if len(rows) < 2:
                return shows
            
            # First, try to parse as Google Sheets format (WTBR style)
            google_sheets_shows = self._parse_google_sheets_table(table, rows)
            if google_sheets_shows:
                shows.extend(google_sheets_shows)
                return shows
            
            # Fall back to standard table parsing
            # Try to identify header row and time/show columns
            header_row = rows[0]
            headers = [th.get_text().strip().lower() for th in header_row.find_all(['th', 'td'])]
            
            # Look for time and day columns
            time_col = self._find_column_index(headers, ['time', 'hour'])
            day_cols = {}
            
            for i, header in enumerate(headers):
                for day_name in self.day_mappings.keys():
                    if day_name in header:
                        day_cols[self.day_mappings[day_name]] = i
                        break
            
            # Parse data rows
            for row in rows[1:]:
                cells = row.find_all(['td', 'th'])
                if len(cells) <= max([time_col] + list(day_cols.values()) if day_cols else [time_col]):
                    continue
                
                # Extract time from time column
                if time_col < len(cells):
                    time_text = cells[time_col].get_text().strip()
                    parsed_time = self._parse_time(time_text)
                    
                    if parsed_time:
                        # Extract shows from day columns
                        for day, col_idx in day_cols.items():
                            if col_idx < len(cells):
                                show_text = cells[col_idx].get_text().strip()
                                if show_text and show_text not in ['-', '']:
                                    show = self._create_show_from_text(show_text, parsed_time, [day])
                                    if show:
                                        shows.append(show)
        
        except Exception as e:
            logger.warning(f"Error parsing schedule table: {e}")
        
        return shows
    
    def _parse_google_sheets_table(self, table, rows) -> List[ShowSchedule]:
        """Parse Google Sheets table format (like WTBR schedule)"""
        shows = []
        
        try:
            # Look for day header row (contains SUNDAY, MONDAY, etc.)
            day_columns = {}
            header_row_idx = None
            
            for row_idx, row in enumerate(rows):
                cells = row.find_all(['td', 'th'])
                cell_texts = [cell.get_text().strip().lower() for cell in cells]
                
                # Check if this row contains day names
                if any(day in ' '.join(cell_texts) for day in ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']):
                    header_row_idx = row_idx
                    for i, text in enumerate(cell_texts):
                        if 'sunday' in text: day_columns['sunday'] = i
                        elif 'monday' in text: day_columns['monday'] = i
                        elif 'tuesday' in text: day_columns['tuesday'] = i
                        elif 'wednesday' in text: day_columns['wednesday'] = i
                        elif 'thursday' in text: day_columns['thursday'] = i
                        elif 'friday' in text: day_columns['friday'] = i
                        elif 'saturday' in text: day_columns['saturday'] = i
                    break
            
            if not day_columns or header_row_idx is None:
                return shows  # Not a Google Sheets format
            
            logger.info(f"Detected Google Sheets table format with day columns: {day_columns}")
            
            # Parse data rows (skip header rows)
            for row in rows[header_row_idx + 1:]:
                cells = row.find_all(['td', 'th'])
                if len(cells) < 2:
                    continue
                
                # Column 1 should contain time
                time_text = cells[1].get_text().strip()
                if not time_text or ':' not in time_text:
                    continue
                
                parsed_time = self._parse_time(time_text)
                if not parsed_time:
                    continue
                
                # Extract shows from each day column
                for day, col_idx in day_columns.items():
                    if col_idx < len(cells):
                        show_text = cells[col_idx].get_text().strip()
                        
                        # Skip empty cells, generic entries, and time entries
                        if (show_text and 
                            show_text not in ['-', '', time_text] and
                            not show_text.endswith(' AM') and 
                            not show_text.endswith(' PM') and
                            show_text.lower() not in ['wtbr rock']):  # Skip generic station ID
                            
                            # Calculate end time (assume 30-minute slots)
                            end_time = time((parsed_time.hour * 60 + parsed_time.minute + 30) // 60 % 24,
                                          (parsed_time.hour * 60 + parsed_time.minute + 30) % 60)
                            
                            show = ShowSchedule(
                                name=show_text,
                                start_time=parsed_time,
                                end_time=end_time,
                                days=[day],
                                description=f"Radio program: {show_text}",
                                host="",
                                genre=""
                            )
                            shows.append(show)
                            logger.debug(f"Added show: {show_text} at {parsed_time} on {day}")
            
            logger.info(f"Extracted {len(shows)} shows from Google Sheets table")
            
        except Exception as e:
            logger.error(f"Error parsing Google Sheets table: {e}")
        
        return shows
    
    def _parse_schedule_divs(self, soup) -> List[ShowSchedule]:
        """Parse schedule from div/span structure"""
        shows = []
        
        # Look for common schedule div patterns
        schedule_containers = soup.find_all(['div', 'section'], 
                                          class_=re.compile(r'schedule|program|show|lineup', re.I))
        
        for container in schedule_containers:
            # Look for time and show name patterns
            time_elements = container.find_all(text=re.compile(r'\d{1,2}:\d{2}'))
            
            for time_elem in time_elements:
                try:
                    parsed_time = self._parse_time(time_elem.strip())
                    if parsed_time:
                        # Look for show name in nearby elements
                        parent = time_elem.parent
                        show_text = None
                        
                        # Check siblings and parent text
                        if parent:
                            show_text = parent.get_text().replace(time_elem, '').strip()
                        
                        if show_text:
                            show = self._create_show_from_text(show_text, parsed_time, ['monday'])  # Default day
                            if show:
                                shows.append(show)
                
                except Exception as e:
                    logger.debug(f"Error parsing div schedule element: {e}")
        
        return shows
    
    def _parse_structured_data(self, soup) -> List[ShowSchedule]:
        """Parse JSON-LD or microdata structured schedule information"""
        shows = []
        
        # Look for JSON-LD
        scripts = soup.find_all('script', type='application/ld+json')
        for script in scripts:
            try:
                data = json.loads(script.string)
                if isinstance(data, dict) and 'Event' in data.get('@type', ''):
                    # Parse event data for show information
                    pass  # Implementation for specific structured data formats
            except json.JSONDecodeError:
                continue
        
        return shows
    
    def _parse_json_schedule(self, content: str) -> List[ShowSchedule]:
        """Parse JSON format schedule"""
        shows = []
        
        try:
            data = json.loads(content)
            
            # Handle different JSON structures
            if isinstance(data, list):
                # Check if this looks like WERU's calendar format
                if data and isinstance(data[0], dict) and 'start' in data[0] and 'title' in data[0]:
                    # WERU-specific format with ISO timestamps
                    shows = self._parse_weru_json_schedule(data)
                else:
                    # Standard JSON format
                    for item in data:
                        show = self._parse_json_show_item(item)
                        if show:
                            shows.append(show)
            elif isinstance(data, dict):
                # Look for common keys that might contain show lists
                for key in ['shows', 'programs', 'schedule', 'events']:
                    if key in data and isinstance(data[key], list):
                        for item in data[key]:
                            show = self._parse_json_show_item(item)
                            if show:
                                shows.append(show)
        
        except json.JSONDecodeError as e:
            logger.error(f"Error parsing JSON schedule: {e}")
        
        return shows
    
    def _parse_weru_json_schedule(self, data: List[Dict[str, Any]]) -> List[ShowSchedule]:
        """Parse WERU's specific JSON calendar format with ISO timestamps"""
        shows = []
        from datetime import datetime
        import pytz
        
        try:
            # Group shows by name and day to avoid duplicates
            show_schedules = {}
            
            for item in data:
                title = item.get('title', '').strip()
                start_iso = item.get('start', '')
                end_iso = item.get('end', '')
                
                if not title or not start_iso:
                    continue
                
                # Skip generic/automated entries
                if any(skip in title.lower() for skip in ['various', 'programming', 'automated']):
                    continue
                
                try:
                    # Parse ISO timestamp - WERU uses -0400 (EDT) offset
                    # Convert to Eastern Time properly
                    start_dt = datetime.fromisoformat(start_iso.replace('Z', '+00:00'))
                    
                    # Convert to US/Eastern timezone 
                    if start_dt.tzinfo is None:
                        # If no timezone, assume it's already in Eastern
                        eastern = pytz.timezone('US/Eastern')
                        start_dt = eastern.localize(start_dt)
                    else:
                        # Convert to Eastern
                        eastern = pytz.timezone('US/Eastern')
                        start_dt = start_dt.astimezone(eastern)
                    
                    # Extract local time (without timezone offset)
                    start_time = start_dt.time()
                    
                    # Get day of week
                    weekday = start_dt.weekday()  # 0=Monday, 6=Sunday
                    day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
                    day = day_names[weekday]
                    
                    # Parse end time if available
                    end_time = None
                    if end_iso:
                        try:
                            end_dt = datetime.fromisoformat(end_iso.replace('Z', '+00:00'))
                            if end_dt.tzinfo is None:
                                end_dt = eastern.localize(end_dt)
                            else:
                                end_dt = end_dt.astimezone(eastern)
                            end_time = end_dt.time()
                        except Exception:
                            pass
                    
                    # Create a unique key for this show
                    show_key = (title.lower(), start_time, day)
                    
                    if show_key not in show_schedules:
                        show_schedule = ShowSchedule(
                            name=title,
                            start_time=start_time,
                            end_time=end_time,
                            days=[day],
                            description=f"WERU program: {title}",
                            host=item.get('text', '').strip(),
                            genre=item.get('data', {}).get('category', '') if isinstance(item.get('data'), dict) else ''
                        )
                        show_schedules[show_key] = show_schedule
                        logger.debug(f"WERU: Added {title} at {start_time} on {day}")
                        
                except Exception as e:
                    logger.debug(f"Error parsing WERU timestamp {start_iso}: {e}")
                    continue
            
            shows = list(show_schedules.values())
            logger.info(f"WERU JSON parser found {len(shows)} unique shows")
            
        except Exception as e:
            logger.error(f"Error in WERU JSON parser: {e}")
        
        return shows
    
    def _parse_json_show_item(self, item: Dict[str, Any]) -> Optional[ShowSchedule]:
        """Parse individual show item from JSON"""
        try:
            # Extract show name
            name = item.get('name') or item.get('title') or item.get('show')
            if not name:
                return None
            
            # Extract time information
            start_time = None
            end_time = None
            
            if 'start_time' in item:
                start_time = self._parse_time(str(item['start_time']))
            if 'end_time' in item:
                end_time = self._parse_time(str(item['end_time']))
            
            # Extract days
            days = []
            if 'days' in item:
                if isinstance(item['days'], list):
                    days = [self._normalize_day(day) for day in item['days']]
                else:
                    days = [self._normalize_day(item['days'])]
            
            if start_time and days:
                return ShowSchedule(
                    name=name,
                    start_time=start_time,
                    end_time=end_time,
                    days=[day for day in days if day],
                    description=item.get('description'),
                    host=item.get('host'),
                    genre=item.get('genre')
                )
        
        except Exception as e:
            logger.debug(f"Error parsing JSON show item: {e}")
        
        return None
    
    def _parse_ical_schedule(self, content: bytes) -> List[ShowSchedule]:
        """Parse iCal/ICS calendar format"""
        shows = []
        
        try:
            cal = icalendar.Calendar.from_ical(content)
            
            for component in cal.walk():
                if component.name == "VEVENT":
                    try:
                        name = str(component.get('summary', ''))
                        dtstart = component.get('dtstart')
                        dtend = component.get('dtend')
                        
                        if name and dtstart:
                            start_time = dtstart.dt.time() if hasattr(dtstart.dt, 'time') else None
                            end_time = dtend.dt.time() if dtend and hasattr(dtend.dt, 'time') else None
                            
                            # Determine days from recurrence rules or single event
                            days = self._extract_days_from_ical_event(component)
                            
                            if start_time and days:
                                show = ShowSchedule(
                                    name=name,
                                    start_time=start_time,
                                    end_time=end_time,
                                    days=days,
                                    description=str(component.get('description', ''))
                                )
                                shows.append(show)
                    
                    except Exception as e:
                        logger.debug(f"Error parsing iCal event: {e}")
        
        except Exception as e:
            logger.error(f"Error parsing iCal schedule: {e}")
        
        return shows
    
    def _parse_xml_schedule(self, content: bytes) -> List[ShowSchedule]:
        """Parse XML format schedule"""
        shows = []
        
        try:
            soup = BeautifulSoup(content, 'xml')
            
            # Look for common XML schedule structures
            show_elements = soup.find_all(['show', 'program', 'event'])
            
            for elem in show_elements:
                try:
                    name = elem.get('name') or elem.get('title') or elem.get_text()
                    start_time_str = elem.get('start') or elem.get('start_time')
                    end_time_str = elem.get('end') or elem.get('end_time')
                    days_str = elem.get('days') or elem.get('day')
                    
                    if name and start_time_str:
                        start_time = self._parse_time(start_time_str)
                        end_time = self._parse_time(end_time_str) if end_time_str else None
                        
                        days = []
                        if days_str:
                            days = [self._normalize_day(d.strip()) for d in days_str.split(',')]
                            days = [day for day in days if day]
                        
                        if start_time and days:
                            show = ShowSchedule(
                                name=name.strip(),
                                start_time=start_time,
                                end_time=end_time,
                                days=days,
                                description=elem.get('description')
                            )
                            shows.append(show)
                
                except Exception as e:
                    logger.debug(f"Error parsing XML show element: {e}")
        
        except Exception as e:
            logger.error(f"Error parsing XML schedule: {e}")
        
        return shows
    
    def _parse_time(self, time_str: str) -> Optional[time]:
        """Parse time string into time object"""
        if not time_str:
            return None
        
        time_str = time_str.strip().upper()
        
        for pattern in self.time_patterns:
            match = re.search(pattern, time_str)
            if match:
                try:
                    groups = match.groups()
                    
                    if len(groups) == 3:  # Hour, minute, AM/PM
                        hour, minute, ampm = groups
                        hour = int(hour)
                        minute = int(minute)
                        
                        if ampm == 'PM' and hour != 12:
                            hour += 12
                        elif ampm == 'AM' and hour == 12:
                            hour = 0
                        
                        return time(hour, minute)
                    
                    elif len(groups) == 2:  # Hour, minute (24hr) or hour, AM/PM
                        if groups[1] in ['AM', 'PM']:  # Hour, AM/PM
                            hour = int(groups[0])
                            ampm = groups[1]
                            
                            if ampm == 'PM' and hour != 12:
                                hour += 12
                            elif ampm == 'AM' and hour == 12:
                                hour = 0
                            
                            return time(hour, 0)
                        else:  # Hour, minute (24hr)
                            hour, minute = int(groups[0]), int(groups[1])
                            return time(hour, minute)
                    
                    elif len(groups) == 1:  # Military time (HHMM)
                        time_str = groups[0]
                        if len(time_str) == 4:
                            hour = int(time_str[:2])
                            minute = int(time_str[2:])
                            return time(hour, minute)
                
                except ValueError:
                    continue
        
        return None
    
    def _normalize_day(self, day_str: str) -> Optional[str]:
        """Normalize day string to standard format"""
        if not day_str:
            return None
        
        day_str = day_str.strip().lower()
        return self.day_mappings.get(day_str)
    
    def _create_show_from_text(self, text: str, start_time: time, days: List[str]) -> Optional[ShowSchedule]:
        """Create ShowSchedule from text description"""
        if not text or not text.strip():
            return None
        
        # Clean up the text
        text = text.strip()
        
        # Try to extract show name, host, description
        name = text
        host = None
        description = None
        
        # Look for host information in parentheses or "with" patterns
        host_match = re.search(r'with\s+([^,\n]+)', text, re.I)
        if host_match:
            host = host_match.group(1).strip()
            name = text[:host_match.start()].strip()
        
        # Look for description after dash or colon
        desc_match = re.search(r'[-:]\s*(.+)', name)
        if desc_match:
            description = desc_match.group(1).strip()
            name = name[:desc_match.start()].strip()
        
        return ShowSchedule(
            name=name,
            start_time=start_time,
            end_time=None,
            days=days,
            description=description,
            host=host
        )
    
    def _find_column_index(self, headers: List[str], keywords: List[str]) -> int:
        """Find column index by keywords"""
        for i, header in enumerate(headers):
            for keyword in keywords:
                if keyword in header:
                    return i
        return -1
    
    def _extract_days_from_ical_event(self, event) -> List[str]:
        """Extract recurring days from iCal event"""
        days = []
        
        # Check for recurrence rules
        rrule = event.get('rrule')
        if rrule:
            byday = rrule.get('BYDAY')
            if byday:
                day_map = {
                    'MO': 'monday', 'TU': 'tuesday', 'WE': 'wednesday',
                    'TH': 'thursday', 'FR': 'friday', 'SA': 'saturday', 'SU': 'sunday'
                }
                
                if isinstance(byday, list):
                    for day in byday:
                        if day in day_map:
                            days.append(day_map[day])
                elif byday in day_map:
                    days.append(day_map[byday])
        
        # If no recurrence, use the event's day
        if not days:
            dtstart = event.get('dtstart')
            if dtstart and hasattr(dtstart.dt, 'weekday'):
                weekday_map = {
                    0: 'monday', 1: 'tuesday', 2: 'wednesday', 3: 'thursday',
                    4: 'friday', 5: 'saturday', 6: 'sunday'
                }
                days.append(weekday_map[dtstart.dt.weekday()])
        
        return days
    
    def _deduplicate_shows(self, shows: List[ShowSchedule]) -> List[ShowSchedule]:
        """Remove duplicate shows"""
        seen = set()
        unique_shows = []
        
        for show in shows:
            # Create a unique key for the show
            key = (show.name.lower().strip(), show.start_time, tuple(sorted(show.days)))
            
            if key not in seen:
                seen.add(key)
                unique_shows.append(show)
        
        return unique_shows
    
    def _get_saved_calendar_url(self, station_id: int) -> str:
        """Get saved calendar URL for a station from database"""
        try:
            from backend.config.database import SessionLocal
            from backend.models.station import Station
            db = SessionLocal()
            station = db.query(Station).filter(Station.id == station_id).first()
            calendar_url = station.calendar_url if station else None
            db.close()
            return calendar_url
        except Exception as e:
            logger.warning(f"Error getting saved calendar URL for station {station_id}: {e}")
            return None
    
    def _get_saved_calendar_config(self, station_id: int) -> Tuple[str, str]:
        """Get saved calendar URL and parsing method for a station from database"""
        try:
            from backend.config.database import SessionLocal
            from backend.models.station import Station
            db = SessionLocal()
            station = db.query(Station).filter(Station.id == station_id).first()
            if station:
                calendar_url = station.calendar_url
                parsing_method = getattr(station, 'calendar_parsing_method', None)
                db.close()
                return calendar_url, parsing_method
            db.close()
            return None, None
        except Exception as e:
            logger.warning(f"Error getting saved calendar config for station {station_id}: {e}")
            return None, None
    
    def _save_calendar_config(self, station_id: int, calendar_url: str, parsing_method: str) -> bool:
        """Save successful calendar URL and parsing method to database"""
        try:
            from backend.config.database import SessionLocal
            from backend.models.station import Station
            db = SessionLocal()
            station = db.query(Station).filter(Station.id == station_id).first()
            if station:
                station.calendar_url = calendar_url
                station.calendar_parsing_method = parsing_method
                db.commit()
            db.close()
            return True
        except Exception as e:
            logger.error(f"Error saving calendar config for station {station_id}: {e}")
            return False
    
    def _save_calendar_url(self, station_id: int, calendar_url: str) -> bool:
        """Save successful calendar URL to database (backward compatibility)"""
        return self._save_calendar_config(station_id, calendar_url, 'html')
    
    def _parse_embedded_google_sheets(self, station_url: str) -> List[ShowSchedule]:
        """Parse embedded Google Sheets iframes from the station page"""
        shows = []
        
        try:
            response = self.session.get(station_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Look for Google Sheets iframes
            iframes = soup.find_all('iframe')
            for iframe in iframes:
                src = iframe.get('src', '')
                if 'docs.google.com/spreadsheets' in src:
                    logger.info(f"Found embedded Google Sheets: {src}")
                    sheets_shows = self._parse_google_sheets_direct(src)
                    if sheets_shows:
                        shows.extend(sheets_shows)
                        break  # Use first successful sheet
            
        except Exception as e:
            logger.error(f"Error parsing embedded Google Sheets from {station_url}: {e}")
        
        return shows
    
    def _parse_google_sheets_direct(self, sheets_url: str) -> List[ShowSchedule]:
        """Parse Google Sheets schedule directly"""
        shows = []
        
        try:
            response = self.session.get(sheets_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Look for schedule data in the Google Sheets HTML
            # Google Sheets renders as HTML tables when accessed via pubhtml
            tables = soup.find_all('table')
            
            for table in tables:
                table_shows = self._parse_schedule_table(table)
                if table_shows:
                    shows.extend(table_shows)
                    break  # Use first successful table
            
            # If no shows found in tables, try to extract from text content
            if not shows:
                shows = self._parse_sheets_text_content(soup)
            
        except Exception as e:
            logger.error(f"Error parsing Google Sheets from {sheets_url}: {e}")
        
        return shows
    
    def _parse_sheets_text_content(self, soup) -> List[ShowSchedule]:
        """Extract show information from Google Sheets text content"""
        shows = []
        
        try:
            # Look for text that might be show names
            all_text = soup.get_text()
            lines = all_text.split('\n')
            
            current_day = None
            day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
            
            for line in lines:
                line = line.strip()
                if not line or len(line) < 3:
                    continue
                
                # Check if this line is a day name
                line_lower = line.lower()
                for day in day_names:
                    if day in line_lower and len(line) < 20:  # Day headers are usually short
                        current_day = day
                        break
                
                # Look for show names (avoid generic content)
                if line and len(line) > 3 and len(line) < 100:
                    if any(keyword in line_lower for keyword in [
                        'show', 'program', 'music', 'talk', 'radio', 'with', 'hour'
                    ]) and not any(skip in line_lower for skip in [
                        'schedule', 'time', 'sunday', 'monday', 'tuesday', 'wednesday', 
                        'thursday', 'friday', 'saturday', 'classic', 'rock'
                    ]):
                        # Extract time if present
                        time_match = re.search(r'(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)', line)
                        start_time = time(9, 0)  # Default
                        
                        if time_match:
                            hour = int(time_match.group(1))
                            minute = int(time_match.group(2))
                            ampm = time_match.group(3).upper()
                            
                            if ampm == 'PM' and hour != 12:
                                hour += 12
                            elif ampm == 'AM' and hour == 12:
                                hour = 0
                                
                            start_time = time(hour, minute)
                            
                            # Clean show name (remove time)
                            show_name = re.sub(r'\d{1,2}:\d{2}\s*(AM|PM|am|pm)', '', line).strip()
                        else:
                            show_name = line
                        
                        if show_name and len(show_name) > 2:
                            show = ShowSchedule(
                                name=show_name,
                                start_time=start_time,
                                end_time=time((start_time.hour + 1) % 24, start_time.minute),
                                days=[current_day] if current_day else ['monday'],
                                description=f"Radio program: {show_name}",
                                host="",
                                genre=""
                            )
                            shows.append(show)
        
        except Exception as e:
            logger.error(f"Error parsing sheets text content: {e}")
        
        return shows[:20]  # Limit to 20 shows to avoid spam


if __name__ == "__main__":
    # Test the calendar parser
    import sys
    
    logging.basicConfig(level=logging.INFO)
    
    if len(sys.argv) < 2:
        print("Usage: python calendar_parser.py <station_url>")
        sys.exit(1)
    
    parser = CalendarParser()
    shows = parser.parse_station_schedule(sys.argv[1])
    
    print(f"Found {len(shows)} shows:")
    for show in shows:
        print(f"  {show.name} - {show.start_time} on {', '.join(show.days)}")
        if show.host:
            print(f"    Host: {show.host}")
        if show.description:
            print(f"    Description: {show.description}")
        print()