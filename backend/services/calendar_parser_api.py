#!/usr/bin/env python3
"""
Generic Calendar Parser API for RadioGrab
Pattern-based parsers that work with various radio station schedule formats
Enhanced with JavaScript-aware parsing capabilities
"""

import re
import json
import requests
from bs4 import BeautifulSoup
from datetime import datetime, time
from urllib.parse import urljoin, urlparse
import sys
import logging

# Import JavaScript-aware parser
try:
    from js_calendar_parser import JavaScriptCalendarParser
    JS_PARSER_AVAILABLE = True
except ImportError as e:
    print(f"JavaScript parser not available: {e}")
    JS_PARSER_AVAILABLE = False

logger = logging.getLogger(__name__)

def parse_with_javascript(station_url):
    """Parse calendar using JavaScript-aware parser"""
    shows = []
    
    if not JS_PARSER_AVAILABLE:
        return shows
    
    try:
        # Use shorter timeout for JavaScript parser to avoid hanging
        js_parser = JavaScriptCalendarParser(headless=True, timeout=30)
        show_schedules = js_parser.parse_station_schedule(station_url)
        
        # Convert ShowSchedule objects to dictionary format
        for show_schedule in show_schedules:
            # Convert days list to individual show entries
            for day in show_schedule.days:
                shows.append({
                    'name': show_schedule.name,
                    'start_time': show_schedule.start_time.strftime('%H:%M'),
                    'end_time': show_schedule.end_time.strftime('%H:%M') if show_schedule.end_time else '23:59',
                    'day': day,
                    'station': urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper(),
                    'dj': show_schedule.host or 'Station DJ',
                    'genre': show_schedule.genre or 'Community Radio'
                })
        
        logger.info(f"JavaScript parser found {len(shows)} show entries from {len(show_schedules)} schedules")
        
    except Exception as e:
        logger.error(f"Error parsing with JavaScript: {e}")
    finally:
        if 'js_parser' in locals():
            js_parser.cleanup_driver()
    
    return shows

def parse_wordpress_api(station_url):
    """Parse WordPress sites using REST API endpoints"""
    shows = []
    
    try:
        # Extract base URL
        from urllib.parse import urlparse
        parsed = urlparse(station_url)
        base_url = f"{parsed.scheme}://{parsed.netloc}"
        
        # Try WordPress REST API endpoints
        api_endpoints = [
            '/wp-json/wp/v2/pages',
            '/wp-json/wp/v2/posts',
            '/wp-json/wp/v2/events',  # Common events plugin endpoint
        ]
        
        for endpoint in api_endpoints:
            try:
                api_url = base_url + endpoint
                headers = {
                    'User-Agent': 'RadioGrab/1.0 (Calendar Parser)'
                }
                
                response = requests.get(api_url, headers=headers, timeout=15)
                if response.status_code == 200:
                    data = response.json()
                    
                    if isinstance(data, list):
                        for item in data:
                            show = parse_wordpress_content_item(item)
                            if show:
                                shows.append(show)
                    
                    if shows:
                        logger.info(f"WordPress API parser found {len(shows)} shows from {api_url}")
                        break
                        
            except Exception as e:
                logger.debug(f"Error with WordPress API endpoint {endpoint}: {e}")
        
        # Also try specific schedule/shows pages via API
        if not shows:
            schedule_pages = ['/schedule/', '/shows/', '/lineup/']
            for page_path in schedule_pages:
                try:
                    # Get page ID first
                    page_url = base_url + '/wp-json/wp/v2/pages?slug=' + page_path.strip('/')
                    response = requests.get(page_url, headers=headers, timeout=10)
                    
                    if response.status_code == 200:
                        pages = response.json()
                        if pages and isinstance(pages, list) and len(pages) > 0:
                            page_content = pages[0].get('content', {}).get('rendered', '')
                            if page_content:
                                # Parse the HTML content for show information  
                                page_shows = parse_html_content_for_shows(page_content, base_url)
                                shows.extend(page_shows)
                                
                except Exception as e:
                    logger.debug(f"Error parsing WordPress page {page_path}: {e}")
    
    except Exception as e:
        logger.error(f"Error in WordPress API parsing: {e}")
    
    return shows

def parse_wordpress_content_item(item):
    """Parse individual WordPress content item for show information"""
    try:
        if not isinstance(item, dict):
            return None
        
        title = item.get('title', {})
        if isinstance(title, dict):
            title = title.get('rendered', '')
        
        content = item.get('content', {})
        if isinstance(content, dict):
            content = content.get('rendered', '')
        
        excerpt = item.get('excerpt', {})
        if isinstance(excerpt, dict):
            excerpt = excerpt.get('rendered', '')
        
        # Look for show-like content
        if title and len(title.strip()) > 2:
            # Skip obvious non-show content
            title_lower = title.lower()
            if not any(skip in title_lower for skip in [
                'about', 'contact', 'home', 'privacy', 'terms', 'schedule', 'welcome'
            ]):
                # Check if it might be a show
                if any(keyword in title_lower or keyword in content.lower() for keyword in [
                    'show', 'program', 'radio', 'music', 'talk', 'hour', 'am', 'pm'
                ]):
                    return {
                        'name': title.strip(),
                        'start_time': '09:00',  # Default
                        'end_time': '10:00',    # Default
                        'day': 'monday',        # Default
                        'station': urlparse(item.get('link', '')).netloc.replace('www.', '').split('.')[0].upper() if item.get('link') else 'RADIO',
                        'dj': 'Station DJ',
                        'genre': 'Community Radio'
                    }
    
    except Exception as e:
        logger.debug(f"Error parsing WordPress item: {e}")
    
    return None

def parse_html_content_for_shows(html_content, base_url):
    """Parse HTML content looking for show information"""
    shows = []
    
    try:
        from bs4 import BeautifulSoup
        soup = BeautifulSoup(html_content, 'html.parser')
        
        # Look for text that might be show names
        text_elements = soup.find_all(text=True)
        
        for text in text_elements:
            text = text.strip()
            if len(text) > 3 and len(text) < 100:
                # Look for show-like patterns
                if any(keyword in text.lower() for keyword in [
                    'show', 'program', 'with', 'radio', 'music', 'talk', 'hour'
                ]) and not any(skip in text.lower() for skip in [
                    'about', 'contact', 'home', 'schedule', 'calendar', 'copyright'
                ]):
                    shows.append({
                        'name': text,
                        'start_time': '09:00',
                        'end_time': '10:00', 
                        'day': 'monday',
                        'station': urlparse(base_url).netloc.replace('www.', '').split('.')[0].upper(),
                        'dj': 'Station DJ',
                        'genre': 'Community Radio'
                    })
        
        # Limit results to avoid spam
        shows = shows[:20]
        
    except Exception as e:
        logger.error(f"Error parsing HTML content for shows: {e}")
    
    return shows

def parse_html_table_schedule(station_url):
    """Generic parser for HTML table-based schedules"""
    shows = []
    
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(station_url, headers=headers, timeout=15)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Look for schedule-related tables
        schedule_tables = []
        
        # Find tables with schedule-related keywords
        for table in soup.find_all('table'):
            table_text = table.get_text().lower()
            if any(keyword in table_text for keyword in ['schedule', 'programming', 'shows', 'time', 'monday', 'tuesday']):
                schedule_tables.append(table)
        
        # If no obvious schedule tables, try all tables with time patterns
        if not schedule_tables:
            for table in soup.find_all('table'):
                table_text = table.get_text()
                if re.search(r'\d{1,2}:?\d{0,2}\s*(AM|PM|am|pm)', table_text):
                    schedule_tables.append(table)
        
        for table in schedule_tables:
            parsed_shows = parse_schedule_table(table, station_url)
            shows.extend(parsed_shows)
            if shows:  # If we found shows in this table, we're done
                break
        
    except Exception as e:
        print(f"Error parsing HTML table schedule: {e}")
    
    return shows

def parse_schedule_table(table, station_url):
    """Parse a schedule table and extract show information"""
    shows = []
    
    try:
        rows = table.find_all('tr')
        if len(rows) < 2:
            return shows
        
        # Try to find header row with day names
        day_header_row = None
        start_row = 0
        
        for i, row in enumerate(rows[:5]):
            cells = [cell.get_text().strip().upper() for cell in row.find_all(['th', 'td'])]
            if any(day in ' '.join(cells) for day in ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']):
                day_header_row = i
                start_row = i + 1
                break
        
        if day_header_row is None:
            return shows
        
        # Extract day columns from header row
        header_cells = [cell.get_text().strip().upper() for cell in rows[day_header_row].find_all(['th', 'td'])]
        
        day_map = {
            'SUNDAY': 'sunday', 'MONDAY': 'monday', 'TUESDAY': 'tuesday',
            'WEDNESDAY': 'wednesday', 'THURSDAY': 'thursday', 
            'FRIDAY': 'friday', 'SATURDAY': 'saturday'
        }
        
        day_columns = {}
        time_column = 0  # Usually first column
        
        for i, header in enumerate(header_cells):
            if header in day_map:
                day_columns[day_map[header]] = i
            elif any(time_word in header.lower() for time_word in ['time', 'hour']):
                time_column = i
        
        # Extract station name from URL
        domain = urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper()
        
        # Process data rows
        for row in rows[start_row:]:
            cells = row.find_all(['td', 'th'])
            if len(cells) <= max(day_columns.values()) if day_columns else 0:
                continue
            
            # Extract time
            time_text = cells[time_column].get_text().strip() if time_column < len(cells) else ""
            
            # Parse time
            time_match = re.search(r'(\d{1,2}):?(\d{2})?\s*(AM|PM|am|pm)', time_text)
            if not time_match:
                continue
            
            hour = int(time_match.group(1))
            minute = int(time_match.group(2)) if time_match.group(2) else 0
            ampm = time_match.group(3).upper()
            
            # Convert to 24-hour format
            if ampm == 'PM' and hour != 12:
                hour += 12
            elif ampm == 'AM' and hour == 12:
                hour = 0
            
            start_time = f"{hour:02d}:{minute:02d}"
            
            # Calculate end time (assuming 1-hour slots)
            end_hour = hour + 1
            if end_hour >= 24:
                end_hour = 0
            end_time = f"{end_hour:02d}:{minute:02d}"
            
            # Extract shows for each day
            for day, col_idx in day_columns.items():
                if col_idx < len(cells):
                    show_cell = cells[col_idx]
                    show_text = show_cell.get_text().strip()
                    
                    # Skip empty cells and generic programming
                    if (show_text and 
                        show_text.lower() not in ['', 'music', 'programming', 'various'] and
                        len(show_text) > 1):
                        
                        # Clean up show name
                        show_name = re.sub(r'\s+', ' ', show_text).strip()
                        
                        # Determine genre based on show name
                        genre = determine_genre(show_name)
                        
                        shows.append({
                            'name': show_name,
                            'start_time': start_time,
                            'end_time': end_time,
                            'day': day,
                            'station': domain,
                            'dj': 'DJ',
                            'genre': genre
                        })
        
    except Exception as e:
        print(f"Error parsing schedule table: {e}")
    
    return shows

def determine_genre(show_name):
    """Determine genre based on show name keywords"""
    show_lower = show_name.lower()
    
    if any(word in show_lower for word in ['jazz', 'blues']):
        return "Jazz/Blues"
    elif any(word in show_lower for word in ['rock', 'metal', 'punk']):
        return "Rock"
    elif any(word in show_lower for word in ['folk', 'acoustic', 'coffeehouse']):
        return "Folk"
    elif any(word in show_lower for word in ['talk', 'news', 'discussion', 'morning edition', 'all things considered']):
        return "Talk/News"
    elif any(word in show_lower for word in ['classical', 'symphony', 'opera']):
        return "Classical"
    elif any(word in show_lower for word in ['country', 'bluegrass', 'americana']):
        return "Country/Americana"
    elif any(word in show_lower for word in ['world', 'international', 'global']):
        return "World"
    elif any(word in show_lower for word in ['hip hop', 'rap', 'urban']):
        return "Hip Hop"
    elif any(word in show_lower for word in ['reggae', 'ska']):
        return "Reggae"
    elif any(word in show_lower for word in ['latin', 'spanish', 'salsa']):
        return "Latin"
    elif any(word in show_lower for word in ['electronic', 'techno', 'house']):
        return "Electronic"
    else:
        return "Community Radio"

def parse_google_sheets_schedule(station_url):
    """Parse Google Sheets schedules, including embedded ones"""
    shows = []
    
    try:
        # First check if this is already a Google Sheets URL
        if 'docs.google.com/spreadsheets' in station_url:
            return parse_google_sheets_direct(station_url)
        
        # Otherwise, look for embedded Google Sheets iframes
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(station_url, headers=headers, timeout=15)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Look for Google Sheets iframes
        iframes = soup.find_all('iframe')
        for iframe in iframes:
            src = iframe.get('src', '')
            if 'docs.google.com/spreadsheets' in src:
                logger.info(f"Found embedded Google Sheets: {src}")
                sheets_shows = parse_google_sheets_direct(src)
                shows.extend(sheets_shows)
                if sheets_shows:  # If we found shows, use this iframe
                    break
        
        return shows
        
    except Exception as e:
        logger.error(f"Error parsing Google Sheets from {station_url}: {e}")
        return []

def parse_google_sheets_direct(sheets_url):
    """Generic parser for Google Sheets-based schedules"""
    shows = []
    
    try:
        
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(sheets_url, headers=headers, timeout=15)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Find the schedule table in the Google Sheets HTML
        tables = soup.find_all('table')
        
        for table in tables:
            rows = table.find_all('tr')
            if len(rows) < 4:  # Need at least 4 rows for table format
                continue
                
            # For Google Sheets schedules, the typical structure is:
            # Row 0: Empty
            # Row 1: Title 
            # Row 2: Day headers (SUNDAY, MONDAY, etc. starting from column 2)
            # Row 3+: Time slots with shows
            
            # Find the day header row (contains day names)
            day_header_row = None
            start_row = 0
            
            for i, row in enumerate(rows[:5]):  # Check first 5 rows
                cells = [cell.get_text().strip().upper() for cell in row.find_all(['th', 'td'])]
                if any(day in ' '.join(cells) for day in ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']):
                    day_header_row = i
                    start_row = i + 1
                    break
            
            if day_header_row is None:
                continue
                
            # Extract day columns from header row
            header_cells = [cell.get_text().strip().upper() for cell in rows[day_header_row].find_all(['th', 'td'])]
            
            day_map = {
                'SUNDAY': 'sunday', 'MONDAY': 'monday', 'TUESDAY': 'tuesday',
                'WEDNESDAY': 'wednesday', 'THURSDAY': 'thursday', 
                'FRIDAY': 'friday', 'SATURDAY': 'saturday'
            }
            
            day_columns = {}
            time_column = 1  # Time is typically in column 1
            
            for i, header in enumerate(header_cells):
                if header in day_map:
                    day_columns[day_map[header]] = i
            
            # Process data rows (starting from after the header row)
            for row_idx, row in enumerate(rows[start_row:], start_row):
                cells = row.find_all(['td', 'th'])
                if len(cells) <= max(day_columns.values()) if day_columns else 0:
                    continue
                
                # Extract time from first column or time column
                time_text = ""
                if time_column >= 0 and time_column < len(cells):
                    time_text = cells[time_column].get_text().strip()
                elif len(cells) > 0:
                    time_text = cells[0].get_text().strip()
                
                # Parse time
                time_match = re.search(r'(\d{1,2}):?(\d{2})?\s*(AM|PM|am|pm)', time_text)
                if not time_match:
                    continue
                
                hour = int(time_match.group(1))
                minute = int(time_match.group(2)) if time_match.group(2) else 0
                ampm = time_match.group(3).upper()
                
                # Convert to 24-hour format
                if ampm == 'PM' and hour != 12:
                    hour += 12
                elif ampm == 'AM' and hour == 12:
                    hour = 0
                
                start_time = f"{hour:02d}:{minute:02d}"
                
                # Calculate end time (30 minutes later, typical for radio)
                end_hour = hour
                end_minute = minute + 30
                if end_minute >= 60:
                    end_minute -= 60
                    end_hour += 1
                end_time = f"{end_hour:02d}:{end_minute:02d}"
                
                # Extract shows for each day
                for day, col_idx in day_columns.items():
                    if col_idx < len(cells):
                        show_cell = cells[col_idx]
                        show_text = show_cell.get_text().strip()
                        
                        # Skip empty cells and default programming
                        if (show_text and 
                            show_text.lower() not in ['', 'music', 'filler', 'programming'] and
                            len(show_text) > 1):
                            
                            # Clean up show name
                            show_name = re.sub(r'\s+', ' ', show_text).strip()
                            
                            # Determine genre based on show name
                            genre = "Community Radio"
                            if any(word in show_name.lower() for word in ['jazz', 'blues']):
                                genre = "Jazz/Blues"
                            elif any(word in show_name.lower() for word in ['rock', 'metal']):
                                genre = "Rock"
                            elif any(word in show_name.lower() for word in ['folk', 'acoustic']):
                                genre = "Folk"
                            elif any(word in show_name.lower() for word in ['talk', 'news', 'discussion']):
                                genre = "Talk"
                            elif any(word in show_name.lower() for word in ['classical', 'symphony']):
                                genre = "Classical"
                            
                            # Extract station name from URL
                            domain = urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper()
                            
                            shows.append({
                                'name': show_name,
                                'start_time': start_time,
                                'end_time': end_time,
                                'day': day,
                                'station': domain,
                                'dj': f'{domain} DJ',
                                'genre': genre
                            })
            
            # If we found shows in this table, we're done
            if shows:
                break
    
    except Exception as e:
        print(f"Error parsing Google Sheets schedule: {e}")
    
    return shows

def detect_google_sheets_url(station_url):
    """Try to detect Google Sheets URL from a station's website"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(station_url, headers=headers, timeout=10)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Look for Google Sheets URLs in links and iframes
        google_patterns = [
            r'https://docs\.google\.com/spreadsheets/d/[a-zA-Z0-9-_]+/[^"\s]*',
            r'https://docs\.google\.com/spreadsheets/d/e/[a-zA-Z0-9-_]+/pubhtml[^"\s]*'
        ]
        
        # Search in href attributes
        for link in soup.find_all(['a', 'iframe'], href=True):
            href = link.get('href', '')
            for pattern in google_patterns:
                match = re.search(pattern, href)
                if match:
                    return match.group(0)
        
        # Search in src attributes for iframes
        for iframe in soup.find_all('iframe', src=True):
            src = iframe.get('src', '')
            for pattern in google_patterns:
                match = re.search(pattern, src)
                if match:
                    return match.group(0)
        
        # Search in page text
        page_text = soup.get_text()
        for pattern in google_patterns:
            match = re.search(pattern, page_text)
            if match:
                return match.group(0)
                
    except Exception as e:
        print(f"Error detecting Google Sheets URL: {e}")
    
    return None

def parse_wordpress_calendar(station_url):
    """Parse WordPress calendar plugins and events"""
    shows = []
    
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(station_url, headers=headers, timeout=15)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Look for common WordPress calendar plugin classes and structures
        wordpress_selectors = [
            '.tribe-events-list-event',  # The Events Calendar plugin
            '.event-item',               # Generic event items
            '.calendar-event',           # Common calendar event class
            '.fc-event',                 # FullCalendar
            '.show-listing',             # Radio-specific
            '.program-listing'
        ]
        
        domain = urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper()
        
        for selector in wordpress_selectors:
            events = soup.select(selector)
            
            for event in events:
                event_data = extract_event_data(event, domain)
                if event_data:
                    shows.append(event_data)
        
        # Look for structured data (JSON-LD)
        json_ld_shows = extract_json_ld_events(soup, domain)
        shows.extend(json_ld_shows)
        
    except Exception as e:
        print(f"Error parsing WordPress calendar: {e}")
    
    return shows

def extract_event_data(event_element, station_name):
    """Extract show data from a WordPress event element"""
    try:
        # Try to find show name
        name_selectors = ['.event-title', '.tribe-event-title', 'h3', 'h4', '.title']
        show_name = None
        
        for selector in name_selectors:
            name_elem = event_element.select_one(selector)
            if name_elem:
                show_name = name_elem.get_text().strip()
                break
        
        if not show_name:
            return None
        
        # Try to find time
        time_selectors = ['.event-time', '.tribe-event-time', '.time', '.datetime']
        time_text = ""
        
        for selector in time_selectors:
            time_elem = event_element.select_one(selector)
            if time_elem:
                time_text = time_elem.get_text().strip()
                break
        
        # Parse time
        time_match = re.search(r'(\d{1,2}):?(\d{2})?\s*(AM|PM|am|pm)', time_text)
        if not time_match:
            return None
        
        hour = int(time_match.group(1))
        minute = int(time_match.group(2)) if time_match.group(2) else 0
        ampm = time_match.group(3).upper()
        
        # Convert to 24-hour format
        if ampm == 'PM' and hour != 12:
            hour += 12
        elif ampm == 'AM' and hour == 12:
            hour = 0
        
        start_time = f"{hour:02d}:{minute:02d}"
        
        # Try to determine day
        day_text = event_element.get_text().lower()
        day = 'unknown'
        
        for day_name in ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']:
            if day_name in day_text:
                day = day_name
                break
        
        # Calculate end time (assume 1-2 hours)
        end_hour = hour + 1
        if end_hour >= 24:
            end_hour = 0
        end_time = f"{end_hour:02d}:{minute:02d}"
        
        genre = determine_genre(show_name)
        
        return {
            'name': show_name,
            'start_time': start_time,
            'end_time': end_time,
            'day': day,
            'station': station_name,
            'dj': f'{station_name} DJ',
            'genre': genre
        }
        
    except Exception as e:
        print(f"Error extracting event data: {e}")
        return None

def extract_json_ld_events(soup, station_name):
    """Extract events from JSON-LD structured data"""
    shows = []
    
    try:
        # Look for JSON-LD script tags
        for script in soup.find_all('script', type='application/ld+json'):
            try:
                data = json.loads(script.string)
                
                # Handle single event or array of events
                events = data if isinstance(data, list) else [data]
                
                for event in events:
                    if event.get('@type') == 'Event':
                        show_data = parse_json_ld_event(event, station_name)
                        if show_data:
                            shows.append(show_data)
                            
            except json.JSONDecodeError:
                continue
                
    except Exception as e:
        print(f"Error extracting JSON-LD events: {e}")
    
    return shows

def parse_json_ld_event(event, station_name):
    """Parse a single JSON-LD event"""
    try:
        name = event.get('name')
        if not name:
            return None
        
        start_date = event.get('startDate')
        if start_date:
            # Parse ISO date format
            start_dt = datetime.fromisoformat(start_date.replace('Z', '+00:00'))
            start_time = start_dt.strftime('%H:%M')
            
            # Determine day from date
            day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
            day = day_names[start_dt.weekday()]
        else:
            start_time = '00:00'
            day = 'unknown'
        
        end_date = event.get('endDate')
        if end_date:
            end_dt = datetime.fromisoformat(end_date.replace('Z', '+00:00'))
            end_time = end_dt.strftime('%H:%M')
        else:
            # Default to 1 hour duration
            hour = int(start_time.split(':')[0])
            end_hour = (hour + 1) % 24
            end_time = f"{end_hour:02d}:{start_time.split(':')[1]}"
        
        genre = determine_genre(name)
        
        return {
            'name': name,
            'start_time': start_time,
            'end_time': end_time,
            'day': day,
            'station': station_name,
            'dj': f'{station_name} DJ',
            'genre': genre
        }
        
    except Exception as e:
        print(f"Error parsing JSON-LD event: {e}")
        return None

def parse_ical_feed(station_url):
    """Parse iCal/ICS calendar feeds"""
    shows = []
    
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        # Try to find iCal feeds on the page
        response = requests.get(station_url, headers=headers, timeout=10)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        ical_urls = []
        
        # Look for .ics links
        for link in soup.find_all('a', href=True):
            href = link['href']
            if href.endswith('.ics') or 'ical' in href.lower() or 'calendar' in href.lower():
                ical_url = urljoin(station_url, href)
                ical_urls.append(ical_url)
        
        # Common iCal feed paths to try
        domain_base = f"{urlparse(station_url).scheme}://{urlparse(station_url).netloc}"
        common_paths = ['/calendar.ics', '/events.ics', '/schedule.ics', '/calendar/feed']
        
        for path in common_paths:
            ical_urls.append(domain_base + path)
        
        # Try each potential iCal URL
        for ical_url in ical_urls:
            try:
                ical_response = requests.get(ical_url, headers=headers, timeout=10)
                if ical_response.status_code == 200 and 'BEGIN:VCALENDAR' in ical_response.text:
                    ical_shows = parse_ical_content(ical_response.text, station_url)
                    shows.extend(ical_shows)
                    if shows:  # If we found shows, stop trying other URLs
                        break
            except:
                continue
                
    except Exception as e:
        print(f"Error parsing iCal feed: {e}")
    
    return shows

def parse_ical_content(ical_content, station_url):
    """Parse iCal content and extract events"""
    shows = []
    
    try:
        domain = urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper()
        
        # Simple iCal parsing (without external dependencies)
        events = []
        current_event = {}
        
        for line in ical_content.split('\n'):
            line = line.strip()
            
            if line == 'BEGIN:VEVENT':
                current_event = {}
            elif line == 'END:VEVENT':
                if current_event:
                    events.append(current_event.copy())
                current_event = {}
            elif ':' in line and current_event is not None:
                key, value = line.split(':', 1)
                current_event[key] = value
        
        # Convert events to show format
        for event in events:
            summary = event.get('SUMMARY', '')
            if not summary:
                continue
            
            dtstart = event.get('DTSTART', '')
            if dtstart:
                # Parse datetime (simplified)
                try:
                    if 'T' in dtstart:
                        date_part, time_part = dtstart.split('T')
                        if len(time_part) >= 4:
                            hour = int(time_part[:2])
                            minute = int(time_part[2:4])
                            start_time = f"{hour:02d}:{minute:02d}"
                        else:
                            start_time = '00:00'
                    else:
                        start_time = '00:00'
                        
                    # Simplified day extraction (would need proper date parsing for accuracy)
                    day = 'unknown'
                    
                except:
                    start_time = '00:00'
                    day = 'unknown'
            else:
                start_time = '00:00'
                day = 'unknown'
            
            # Calculate end time
            dtend = event.get('DTEND', '')
            if dtend and 'T' in dtend:
                try:
                    time_part = dtend.split('T')[1]
                    if len(time_part) >= 4:
                        hour = int(time_part[:2])
                        minute = int(time_part[2:4])
                        end_time = f"{hour:02d}:{minute:02d}"
                    else:
                        end_time = start_time
                except:
                    end_time = start_time
            else:
                # Default to 1 hour duration
                hour = int(start_time.split(':')[0])
                end_hour = (hour + 1) % 24
                end_time = f"{end_hour:02d}:{start_time.split(':')[1]}"
            
            genre = determine_genre(summary)
            
            shows.append({
                'name': summary,
                'start_time': start_time,
                'end_time': end_time,
                'day': day,
                'station': domain,
                'dj': f'{domain} DJ',
                'genre': genre
            })
            
    except Exception as e:
        print(f"Error parsing iCal content: {e}")
    
    return shows

def parse_station_calendar(station_url):
    """Main function to parse any station's calendar"""
    # Try different parsing strategies in order of likelihood
    parsing_strategies = []
    
    # Add JavaScript parser first if available
    if JS_PARSER_AVAILABLE:
        parsing_strategies.append(('JavaScript Calendar', parse_with_javascript))
    
    # Add traditional strategies
    parsing_strategies.extend([
        ('WordPress API', parse_wordpress_api),
        ('Google Sheets', parse_google_sheets_schedule),
        ('HTML Table', parse_html_table_schedule),
        ('WordPress Calendar', parse_wordpress_calendar),
        ('iCal Feed', parse_ical_feed),
        ('Generic', parse_generic_station)
    ])
    
    shows = []
    successful_strategy = None
    
    for strategy_name, parse_function in parsing_strategies:
        try:
            shows = parse_function(station_url)
            if shows:  # If we found shows, use this strategy
                successful_strategy = strategy_name
                break
        except Exception as e:
            print(f"Error with {strategy_name} parsing: {e}")
            continue
    
    result = {
        'success': len(shows) > 0,
        'shows': shows,
        'station_url': station_url,
        'total_shows': len(shows),
        'parsing_strategy': successful_strategy
    }
    
    # Add helpful suggestions if no shows found
    if len(shows) == 0:
        result['suggestions'] = [
            "Check if the station has a PDF schedule document",
            "Look for a program guide or show listing page",
            "Contact the station directly for schedule information",
            "Check if shows are listed individually rather than in a schedule format",
            "Some community stations have flexible programming without fixed schedules"
        ]
        result['error'] = 'No structured schedule found on the provided page'
    
    return result

def parse_generic_station(station_url):
    """Generic parsing for unknown stations"""
    shows = []
    
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        response = requests.get(station_url, headers=headers, timeout=10)
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Extract station name from domain
        domain = urlparse(station_url).netloc.replace('www.', '').split('.')[0].upper()
        
        # Basic parsing attempt - could be enhanced
        text_content = soup.get_text()
        time_pattern = r'(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)?\s*[-–]?\s*([^\n\r]{10,80})'
        matches = re.finditer(time_pattern, text_content)
        
        for match in matches:
            hour = int(match.group(1))
            minute = int(match.group(2))
            meridiem = match.group(3)
            show_text = match.group(4).strip()
            
            # Convert to 24-hour format
            if meridiem and meridiem.upper() == 'PM' and hour != 12:
                hour += 12
            elif meridiem and meridiem.upper() == 'AM' and hour == 12:
                hour = 0
            
            start_time = f"{hour:02d}:{minute:02d}"
            
            # Clean up show name
            show_name = re.sub(r'[^\w\s\-\'\\"&]', ' ', show_text)
            show_name = re.sub(r'\s+', ' ', show_name).strip()
            
            if len(show_name) > 3 and len(show_name) < 50:
                shows.append({
                    'name': show_name,
                    'start_time': start_time,
                    'day': 'unknown',
                    'station': domain
                })
        
    except Exception as e:
        print(f"Error parsing generic station: {e}")
    
    return shows

def main():
    if len(sys.argv) != 2:
        print(json.dumps({'success': False, 'error': 'Station URL required'}))
        sys.exit(1)
    
    station_url = sys.argv[1]
    result = parse_station_calendar(station_url)
    print(json.dumps(result))

if __name__ == '__main__':
    main()