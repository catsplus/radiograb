#!/usr/bin/env python3
"""
JavaScript-Aware Calendar Parser Service
Extends the existing calendar parser to handle dynamic content rendered by JavaScript
Uses Selenium WebDriver for JavaScript execution
"""

import re
import json
import logging
import time
from datetime import datetime, time as dt_time, timedelta
from typing import Dict, List, Optional, Tuple, Any
from urllib.parse import urljoin, urlparse
import requests
from bs4 import BeautifulSoup

# Selenium imports
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.chrome.service import Service

# Import existing calendar parser components
from calendar_parser import CalendarParser, ShowSchedule

logger = logging.getLogger(__name__)


class JavaScriptCalendarParser(CalendarParser):
    """Enhanced calendar parser with JavaScript execution capabilities"""
    
    def __init__(self, timeout: int = 30, headless: bool = True):
        super().__init__(timeout)
        self.headless = headless
        self.driver = None
        
        # JavaScript-specific patterns for show detection
        self.js_show_selectors = [
            # WordPress Calendarize It patterns
            '.calendarize-it-event-title',
            '.calendarize-it-event',
            '.event-title',
            '.event-name',
            
            # FullCalendar patterns
            '.fc-event-title',
            '.fc-event',
            '.fc-title',
            
            # Generic calendar patterns
            '[data-event-title]',
            '[data-show-name]',
            '[data-program-name]',
            '.show-title',
            '.program-title',
            '.schedule-item',
            '.calendar-event',
            
            # WTBR-specific patterns (from investigation)
            '.tribe-events-list-event-title',
            '.tribe-event-title',
            '.entry-title',
        ]
        
        # Common AJAX endpoints to check
        self.ajax_endpoints = [
            '/wp-admin/admin-ajax.php',
            '/wp-json/wp/v2/events',
            '/wp-json/tribe/events/v1/events',
            '/api/events',
            '/calendar/events',
            '/schedule/events',
        ]
    
    def _get_webdriver(self) -> webdriver.Chrome:
        """Initialize and return Chrome WebDriver"""
        if self.driver is None:
            try:
                chrome_options = Options()
                
                if self.headless:
                    chrome_options.add_argument('--headless')
                
                # Standard options for server environments
                chrome_options.add_argument('--no-sandbox')
                chrome_options.add_argument('--disable-dev-shm-usage')
                chrome_options.add_argument('--disable-gpu')
                chrome_options.add_argument('--window-size=1920,1080')
                chrome_options.add_argument('--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
                
                # Disable images and CSS for faster loading
                prefs = {
                    "profile.managed_default_content_settings.images": 2,
                    "profile.default_content_setting_values.notifications": 2
                }
                chrome_options.add_experimental_option("prefs", prefs)
                
                # Use Chromium browser which should be installed via apt
                chrome_options.binary_location = '/usr/bin/chromium-browser'
                
                # First, try using system chromedriver
                import shutil
                system_chromedriver = shutil.which('chromedriver')
                
                if system_chromedriver:
                    logger.info(f"Using system chromedriver: {system_chromedriver}")
                    service = Service(system_chromedriver)
                else:
                    # Fallback to webdriver manager with environment variable
                    import os
                    
                    # Set home directory for webdriver manager to a writable location
                    original_home = os.environ.get('HOME')
                    os.environ['HOME'] = '/var/radiograb/temp'
                    
                    try:
                        cache_dir = "/var/radiograb/temp/.wdm"
                        os.makedirs(cache_dir, exist_ok=True)
                        service = Service(ChromeDriverManager().install())
                    finally:
                        # Restore original HOME if it existed
                        if original_home:
                            os.environ['HOME'] = original_home
                        else:
                            os.environ.pop('HOME', None)
                
                self.driver = webdriver.Chrome(service=service, options=chrome_options)
                self.driver.set_page_load_timeout(self.timeout)
                
            except Exception as e:
                logger.error(f"Failed to initialize Chrome WebDriver: {e}")
                raise
        
        return self.driver
    
    def cleanup_driver(self):
        """Clean up WebDriver resources"""
        if self.driver:
            try:
                self.driver.quit()
            except Exception as e:
                logger.warning(f"Error closing WebDriver: {e}")
            finally:
                self.driver = None
    
    def parse_station_schedule(self, station_url: str, station_id: int = None) -> List[ShowSchedule]:
        """Main entry point - tries JavaScript parsing first, falls back to standard parsing"""
        try:
            logger.info(f"Starting JavaScript-aware schedule parsing for {station_url}")
            
            # First try JavaScript-aware parsing
            js_shows = self._parse_with_javascript(station_url, station_id)
            
            if js_shows:
                logger.info(f"JavaScript parsing found {len(js_shows)} shows")
                return self._deduplicate_shows(js_shows)
            
            # Fall back to standard parsing
            logger.info("JavaScript parsing found no shows, falling back to standard parsing")
            standard_shows = super().parse_station_schedule(station_url, station_id)
            
            return standard_shows
            
        except Exception as e:
            logger.error(f"Error in JavaScript-aware schedule parsing: {e}")
            # Final fallback to standard parsing
            try:
                return super().parse_station_schedule(station_url, station_id)
            except Exception as fallback_error:
                logger.error(f"Standard parsing also failed: {fallback_error}")
                return []
        finally:
            self.cleanup_driver()
    
    def _parse_with_javascript(self, station_url: str, station_id: int = None) -> List[ShowSchedule]:
        """Parse schedule using JavaScript execution"""
        shows = []
        
        try:
            driver = self._get_webdriver()
            driver.get(station_url)
            
            # Wait for initial page load
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.TAG_NAME, "body"))
            )
            
            # Wait for JavaScript to execute
            time.sleep(3)
            
            # Strategy 1: Look for rendered calendar events
            shows.extend(self._extract_calendar_events(driver))
            
            # Strategy 2: Check for AJAX-loaded content
            if not shows:
                shows.extend(self._check_ajax_endpoints(driver, station_url))
            
            # Strategy 3: Look for WordPress calendar plugins
            if not shows:
                shows.extend(self._parse_wordpress_calendar(driver))
            
            # Strategy 4: Extract from JavaScript variables
            if not shows:
                shows.extend(self._extract_from_js_variables(driver))
            
            # Strategy 5: Additional generic parsing approaches
            if not shows:
                shows.extend(self._parse_additional_generic(driver))
            
            return shows
            
        except TimeoutException:
            logger.warning(f"Page load timeout for {station_url}")
            return []
        except WebDriverException as e:
            logger.error(f"WebDriver error for {station_url}: {e}")
            return []
        except Exception as e:
            logger.error(f"Unexpected error in JavaScript parsing: {e}")
            return []
    
    def _extract_calendar_events(self, driver) -> List[ShowSchedule]:
        """Extract events from rendered calendar elements"""
        shows = []
        
        try:
            # Look for events using various selectors
            for selector in self.js_show_selectors:
                try:
                    elements = driver.find_elements(By.CSS_SELECTOR, selector)
                    for element in elements:
                        show = self._parse_event_element(element, driver)
                        if show:
                            shows.append(show)
                except Exception as e:
                    logger.debug(f"Error with selector {selector}: {e}")
            
            # Look for elements with event data attributes
            event_elements = driver.find_elements(By.CSS_SELECTOR, "[data-start-time], [data-event], [data-show]")
            for element in event_elements:
                show = self._parse_event_element(element, driver)
                if show:
                    shows.append(show)
            
        except Exception as e:
            logger.error(f"Error extracting calendar events: {e}")
        
        return shows
    
    def _parse_event_element(self, element, driver) -> Optional[ShowSchedule]:
        """Parse a single event element into a ShowSchedule"""
        try:
            # Extract show name
            show_name = None
            
            # Try different methods to get the show name
            show_name = element.text.strip()
            if not show_name:
                show_name = element.get_attribute('data-event-title') or \
                           element.get_attribute('data-show-name') or \
                           element.get_attribute('data-program-name') or \
                           element.get_attribute('title')
            
            if not show_name or len(show_name.strip()) < 2:
                return None
            
            # Extract time information
            start_time = None
            end_time = None
            days = []
            
            # Look for time in data attributes
            start_time_str = element.get_attribute('data-start-time') or \
                           element.get_attribute('data-start') or \
                           element.get_attribute('data-time')
            
            end_time_str = element.get_attribute('data-end-time') or \
                         element.get_attribute('data-end')
            
            # Look for time in nearby elements
            if not start_time_str:
                try:
                    # Look in parent or sibling elements for time
                    parent = element.find_element(By.XPATH, "..")
                    time_elements = parent.find_elements(By.CSS_SELECTOR, ".time, .event-time, .start-time, [data-time]")
                    for time_elem in time_elements:
                        time_text = time_elem.text.strip() or time_elem.get_attribute('data-time')
                        if time_text and re.search(r'\d{1,2}:\d{2}', time_text):
                            start_time_str = time_text
                            break
                except:
                    pass
            
            # Parse time strings
            if start_time_str:
                start_time = self._parse_time(start_time_str)
            
            if end_time_str:
                end_time = self._parse_time(end_time_str)
            
            # Extract days
            days_str = element.get_attribute('data-days') or \
                      element.get_attribute('data-day') or \
                      element.get_attribute('data-recurring')
            
            if days_str:
                days = self._parse_days_string(days_str)
            
            # If no specific days, try to infer from context or use default
            if not days:
                # Look for day information in nearby elements
                try:
                    parent = element.find_element(By.XPATH, "..")
                    day_text = parent.text.lower()
                    for day_name in self.day_mappings:
                        if day_name in day_text:
                            normalized_day = self.day_mappings[day_name]
                            if normalized_day not in days:
                                days.append(normalized_day)
                except:
                    pass
                
                # Default to weekdays if no days found
                if not days:
                    days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
            
            # Set default time if none found
            if not start_time:
                start_time = dt_time(9, 0)  # Default 9 AM
                
            if not end_time and start_time:
                end_time = dt_time((start_time.hour + 1) % 24, start_time.minute)  # Default 1 hour duration
            
            # Extract additional information
            description = element.get_attribute('data-description') or \
                         element.get_attribute('data-excerpt') or ""
            
            host = element.get_attribute('data-host') or \
                  element.get_attribute('data-presenter') or ""
            
            genre = element.get_attribute('data-genre') or \
                   element.get_attribute('data-category') or ""
            
            return ShowSchedule(
                name=show_name.strip(),
                start_time=start_time,
                end_time=end_time,
                days=days,
                description=description,
                host=host,
                genre=genre
            )
            
        except Exception as e:
            logger.debug(f"Error parsing event element: {e}")
            return None
    
    def _check_ajax_endpoints(self, driver, base_url: str) -> List[ShowSchedule]:
        """Check common AJAX endpoints for event data"""
        shows = []
        
        try:
            parsed_url = urlparse(base_url)
            base_domain = f"{parsed_url.scheme}://{parsed_url.netloc}"
            
            for endpoint in self.ajax_endpoints:
                ajax_url = urljoin(base_domain, endpoint)
                
                try:
                    # Execute JavaScript to make AJAX request
                    script = f"""
                    return new Promise((resolve) => {{
                        fetch('{ajax_url}')
                            .then(response => response.json())
                            .then(data => resolve(data))
                            .catch(error => resolve(null));
                    }});
                    """
                    
                    result = driver.execute_async_script(script)
                    
                    if result and isinstance(result, (dict, list)):
                        ajax_shows = self._parse_ajax_response(result)
                        shows.extend(ajax_shows)
                        
                        if ajax_shows:
                            logger.info(f"Found {len(ajax_shows)} shows from AJAX endpoint: {ajax_url}")
                            break  # Stop after first successful endpoint
                
                except Exception as e:
                    logger.debug(f"Error checking AJAX endpoint {ajax_url}: {e}")
            
        except Exception as e:
            logger.error(f"Error checking AJAX endpoints: {e}")
        
        return shows
    
    def _parse_ajax_response(self, data) -> List[ShowSchedule]:
        """Parse AJAX response data for show information"""
        shows = []
        
        try:
            if isinstance(data, list):
                for item in data:
                    show = self._parse_ajax_event(item)
                    if show:
                        shows.append(show)
            elif isinstance(data, dict):
                # Look for event arrays in common keys
                for key in ['events', 'shows', 'programs', 'data', 'items']:
                    if key in data and isinstance(data[key], list):
                        for item in data[key]:
                            show = self._parse_ajax_event(item)
                            if show:
                                shows.append(show)
                        break
                
                # Also try parsing the dict itself as a single event
                show = self._parse_ajax_event(data)
                if show:
                    shows.append(show)
        
        except Exception as e:
            logger.error(f"Error parsing AJAX response: {e}")
        
        return shows
    
    def _parse_ajax_event(self, event_data) -> Optional[ShowSchedule]:
        """Parse individual event from AJAX response"""
        try:
            if not isinstance(event_data, dict):
                return None
            
            # Extract name from various possible keys
            name = event_data.get('title') or \
                  event_data.get('name') or \
                  event_data.get('post_title') or \
                  event_data.get('event_name') or \
                  event_data.get('show_name')
            
            if not name or len(name.strip()) < 2:
                return None
            
            # Extract time information
            start_time = None
            end_time = None
            
            # Try various time field names
            start_fields = ['start_time', 'start', 'event_start', 'time', 'start_date']
            end_fields = ['end_time', 'end', 'event_end', 'end_date']
            
            for field in start_fields:
                if field in event_data:
                    start_time = self._parse_time(str(event_data[field]))
                    if start_time:
                        break
            
            for field in end_fields:
                if field in event_data:
                    end_time = self._parse_time(str(event_data[field]))
                    if end_time:
                        break
            
            # Extract days
            days = []
            days_data = event_data.get('days') or \
                       event_data.get('recurring') or \
                       event_data.get('schedule') or \
                       event_data.get('day_of_week')
            
            if days_data:
                if isinstance(days_data, list):
                    days = [self._normalize_day(str(day)) for day in days_data]
                else:
                    days = self._parse_days_string(str(days_data))
                days = [day for day in days if day]
            
            # Default values if missing
            if not start_time:
                start_time = dt_time(9, 0)
            
            if not end_time:
                end_time = dt_time((start_time.hour + 1) % 24, start_time.minute)
            
            if not days:
                days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
            
            return ShowSchedule(
                name=name.strip(),
                start_time=start_time,
                end_time=end_time,
                days=days,
                description=event_data.get('description', ''),
                host=event_data.get('host', ''),
                genre=event_data.get('category', '')
            )
            
        except Exception as e:
            logger.debug(f"Error parsing AJAX event: {e}")
            return None
    
    def _parse_wordpress_calendar(self, driver) -> List[ShowSchedule]:
        """Parse WordPress calendar plugins (Calendarize It, The Events Calendar, etc.)"""
        shows = []
        
        try:
            # Wait for calendar to load
            time.sleep(2)
            
            # Calendarize It plugin
            calendarize_events = driver.find_elements(By.CSS_SELECTOR, ".calendarize-it-event")
            for event in calendarize_events:
                show = self._parse_calendarize_it_event(event)
                if show:
                    shows.append(show)
            
            # The Events Calendar plugin
            tribe_events = driver.find_elements(By.CSS_SELECTOR, ".tribe-events-list-event, .tribe-event")
            for event in tribe_events:
                show = self._parse_tribe_event(event)
                if show:
                    shows.append(show)
            
            # FullCalendar events
            fc_events = driver.find_elements(By.CSS_SELECTOR, ".fc-event")
            for event in fc_events:
                show = self._parse_fullcalendar_event(event)
                if show:
                    shows.append(show)
        
        except Exception as e:
            logger.error(f"Error parsing WordPress calendar: {e}")
        
        return shows
    
    def _parse_calendarize_it_event(self, element) -> Optional[ShowSchedule]:
        """Parse Calendarize It plugin event"""
        try:
            title_elem = element.find_element(By.CSS_SELECTOR, ".calendarize-it-event-title, .event-title")
            show_name = title_elem.text.strip()
            
            if not show_name:
                return None
            
            # Look for time information
            time_elem = None
            try:
                time_elem = element.find_element(By.CSS_SELECTOR, ".calendarize-it-event-time, .event-time")
            except:
                pass
            
            start_time = dt_time(9, 0)  # Default
            if time_elem:
                time_text = time_elem.text.strip()
                parsed_time = self._parse_time(time_text)
                if parsed_time:
                    start_time = parsed_time
            
            return ShowSchedule(
                name=show_name,
                start_time=start_time,
                end_time=dt_time((start_time.hour + 1) % 24, start_time.minute),
                days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                description="",
                host="",
                genre=""
            )
            
        except Exception as e:
            logger.debug(f"Error parsing Calendarize It event: {e}")
            return None
    
    def _parse_tribe_event(self, element) -> Optional[ShowSchedule]:
        """Parse The Events Calendar plugin event"""
        try:
            # Try different title selectors
            title_elem = None
            for selector in [".tribe-events-list-event-title a", ".tribe-event-title", ".entry-title a", ".entry-title"]:
                try:
                    title_elem = element.find_element(By.CSS_SELECTOR, selector)
                    break
                except:
                    continue
            
            if not title_elem:
                return None
            
            show_name = title_elem.text.strip()
            if not show_name:
                return None
            
            # Look for time
            start_time = dt_time(9, 0)  # Default
            try:
                time_elem = element.find_element(By.CSS_SELECTOR, ".tribe-event-date-start, .event-time")
                time_text = time_elem.text.strip()
                parsed_time = self._parse_time(time_text)
                if parsed_time:
                    start_time = parsed_time
            except:
                pass
            
            return ShowSchedule(
                name=show_name,
                start_time=start_time,
                end_time=dt_time((start_time.hour + 1) % 24, start_time.minute),
                days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                description="",
                host="",
                genre=""
            )
            
        except Exception as e:
            logger.debug(f"Error parsing Tribe event: {e}")
            return None
    
    def _parse_fullcalendar_event(self, element) -> Optional[ShowSchedule]:
        """Parse FullCalendar event"""
        try:
            title_elem = element.find_element(By.CSS_SELECTOR, ".fc-title, .fc-event-title")
            show_name = title_elem.text.strip()
            
            if not show_name:
                return None
            
            # FullCalendar events often have time in data attributes
            start_time_str = element.get_attribute('data-start') or element.get_attribute('data-time')
            start_time = dt_time(9, 0)  # Default
            
            if start_time_str:
                parsed_time = self._parse_time(start_time_str)
                if parsed_time:
                    start_time = parsed_time
            
            return ShowSchedule(
                name=show_name,
                start_time=start_time,
                end_time=dt_time((start_time.hour + 1) % 24, start_time.minute),
                days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                description="",
                host="",
                genre=""
            )
            
        except Exception as e:
            logger.debug(f"Error parsing FullCalendar event: {e}")
            return None
    
    def _extract_from_js_variables(self, driver) -> List[ShowSchedule]:
        """Extract show data from JavaScript variables"""
        shows = []
        
        try:
            # Common JavaScript variable names that might contain event data
            js_variables = [
                'events', 'shows', 'programs', 'schedule', 'calendar_events',
                'eventData', 'showData', 'programData', 'calendarEvents'
            ]
            
            for var_name in js_variables:
                try:
                    script = f"return typeof {var_name} !== 'undefined' ? {var_name} : null;"
                    result = driver.execute_script(script)
                    
                    if result and isinstance(result, (list, dict)):
                        js_shows = self._parse_ajax_response(result)
                        shows.extend(js_shows)
                        
                        if js_shows:
                            logger.info(f"Found {len(js_shows)} shows from JavaScript variable: {var_name}")
                
                except Exception as e:
                    logger.debug(f"Error extracting from JS variable {var_name}: {e}")
        
        except Exception as e:
            logger.error(f"Error extracting from JavaScript variables: {e}")
        
        return shows
    
    def _parse_additional_generic(self, driver) -> List[ShowSchedule]:
        """Additional generic parsing approaches for various calendar systems"""
        shows = []
        
        try:
            # Wait for any dynamic content to load
            time.sleep(3)
            
            # Strategy 1: Look for any links that might be event/show links
            event_link_patterns = [
                "a[href*='/event/']",
                "a[href*='/show/']", 
                "a[href*='/program/']",
                "a[href*='event']",
                "a[href*='show']"
            ]
            
            for pattern in event_link_patterns:
                try:
                    elements = driver.find_elements(By.CSS_SELECTOR, pattern)
                    for element in elements:
                        show_name = element.text.strip()
                        if show_name and len(show_name) > 2:
                            # Skip generic navigation items
                            if not any(skip in show_name.lower() for skip in [
                                'event', 'calendar', 'schedule', 'home', 'about', 'contact',
                                'news', 'blog', 'archive', 'category', 'tag', 'search'
                            ]):
                                show = ShowSchedule(
                                    name=show_name,
                                    start_time=dt_time(9, 0),  # Default 9 AM
                                    end_time=dt_time(10, 0),   # Default 1 hour
                                    days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                                    description=f"Radio program: {show_name}",
                                    host="",
                                    genre=""
                                )
                                shows.append(show)
                except Exception as e:
                    logger.debug(f"Error with link pattern {pattern}: {e}")
            
            # Strategy 2: Look for text patterns that suggest show names
            if not shows:
                try:
                    # Find all text elements and look for show-like patterns
                    text_elements = driver.find_elements(By.CSS_SELECTOR, "h1, h2, h3, h4, .title, .name, .show, .program")
                    for element in text_elements:
                        text = element.text.strip()
                        if text and len(text) > 3 and len(text) < 100:
                            # Look for patterns that suggest show names
                            if any(keyword in text.lower() for keyword in [
                                'show', 'program', 'with', 'radio', 'music', 'talk', 'news'
                            ]) and not any(skip in text.lower() for skip in [
                                'about', 'contact', 'home', 'schedule', 'calendar'
                            ]):
                                show = ShowSchedule(
                                    name=text,
                                    start_time=dt_time(9, 0),
                                    end_time=dt_time(10, 0),
                                    days=['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                                    description=f"Radio program: {text}",
                                    host="",
                                    genre=""
                                )
                                shows.append(show)
                except Exception as e:
                    logger.debug(f"Error in text pattern parsing: {e}")
            
            # Strategy 3: Try clicking calendar navigation to load more events
            if not shows:
                try:
                    nav_buttons = driver.find_elements(By.CSS_SELECTOR, 
                        ".fc-next-button, .fc-prev-button, .calendar-nav, .next, .prev, [class*='nav']")
                    if nav_buttons:
                        nav_buttons[0].click()
                        time.sleep(2)
                        # Parse any newly loaded events
                        additional_shows = self._extract_calendar_events(driver)
                        shows.extend(additional_shows)
                except Exception as e:
                    logger.debug(f"Error with calendar navigation: {e}")
        
        except Exception as e:
            logger.error(f"Error in additional generic parsing: {e}")
        
        return shows
    
    def _parse_days_string(self, days_str: str) -> List[str]:
        """Parse a string containing day information"""
        days = []
        
        try:
            days_str = days_str.lower()
            
            # Handle comma-separated days
            if ',' in days_str:
                day_parts = days_str.split(',')
                for part in day_parts:
                    day = self._normalize_day(part.strip())
                    if day and day not in days:
                        days.append(day)
            else:
                # Handle space-separated or single day
                for day_name in self.day_mappings:
                    if day_name in days_str:
                        normalized_day = self.day_mappings[day_name]
                        if normalized_day not in days:
                            days.append(normalized_day)
        
        except Exception as e:
            logger.debug(f"Error parsing days string '{days_str}': {e}")
        
        return days


def test_js_parser():
    """Test the JavaScript calendar parser"""
    import sys
    import logging
    
    logging.basicConfig(level=logging.INFO)
    
    if len(sys.argv) < 2:
        print("Usage: python js_calendar_parser.py <station_url>")
        sys.exit(1)
    
    parser = JavaScriptCalendarParser(headless=True)
    
    try:
        shows = parser.parse_station_schedule(sys.argv[1])
        
        print(f"Found {len(shows)} shows:")
        for show in shows:
            print(f"  {show.name} - {show.start_time} on {', '.join(show.days)}")
            if show.host:
                print(f"    Host: {show.host}")
            if show.description:
                print(f"    Description: {show.description}")
            print()
    
    finally:
        parser.cleanup_driver()


if __name__ == "__main__":
    test_js_parser()