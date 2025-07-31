"""
Automatically extracts and enriches show metadata from multiple sources.

This service attempts to find show descriptions, images, hosts, and genres by
parsing calendar feeds and crawling station websites. It uses a fallback hierarchy
to ensure that some metadata is always available.

Key Variables:
- `show_id`: The database ID of the show to enrich.
- `station_id`: The database ID of the station associated with the show.

Inter-script Communication:
- This script is used by `show_manager.py` and `show_metadata_cli.py`.
- It uses `calendar_parser.py` to extract metadata from calendar feeds.
- It interacts with the `Station` and `Show` models from `backend/models/station.py`.
"""
"""
Show Metadata Auto-Detection Service
Automatically extracts and enriches show metadata from multiple sources:
1. Calendar feeds (iCal, Google Calendar, HTML schedules)
2. Station website crawling  
3. Fallback to manual entry prompts
"""

import os
import sys
import re
import json
import logging
import requests
from datetime import datetime
from typing import Dict, List, Optional, Tuple, Any
from urllib.parse import urljoin, urlparse, quote
from dataclasses import dataclass, asdict
from bs4 import BeautifulSoup

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.services.calendar_parser import CalendarParser, ShowSchedule
from backend.config.database import SessionLocal
from backend.models.station import Station, Show

logger = logging.getLogger(__name__)

@dataclass
class ShowMetadata:
    """Comprehensive show metadata"""
    name: str
    description: Optional[str] = None
    long_description: Optional[str] = None  # Extended description from website
    host: Optional[str] = None
    genre: Optional[str] = None
    image_url: Optional[str] = None
    website_url: Optional[str] = None  # Direct show page URL
    social_links: Optional[Dict[str, str]] = None  # Social media links
    tags: Optional[List[str]] = None  # Show tags/categories
    
    # Metadata source tracking
    description_source: Optional[str] = None  # 'calendar', 'website', 'manual'
    image_source: Optional[str] = None  # 'calendar', 'website', 'station', 'default'
    last_updated: Optional[datetime] = None
    
    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary for database storage"""
        data = asdict(self)
        # Convert datetime to string for JSON serialization
        if data['last_updated']:
            data['last_updated'] = data['last_updated'].isoformat()
        return data


class ShowMetadataDetector:
    """Detects and enriches show metadata from multiple sources"""
    
    def __init__(self, timeout: int = 30):
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        })
        
        # Initialize calendar parser
        self.calendar_parser = CalendarParser(timeout=timeout)
        
        # Common show page URL patterns
        self.show_url_patterns = [
            '/show/{show_name}',
            '/shows/{show_name}',
            '/programs/{show_name}',
            '/programming/{show_name}',
            '/{show_name}',
            '/shows/{show_name}.html',
            '/programs/{show_name}.html'
        ]
        
        # Common image selectors for Open Graph and other metadata
        self.image_selectors = [
            'meta[property="og:image"]',
            'meta[name="twitter:image"]',
            'meta[name="image"]',
            '.show-image img',
            '.program-image img',
            '.host-photo img',
            'img[alt*="show"]',
            'img[alt*="program"]'
        ]
        
        # Common description selectors
        self.description_selectors = [
            'meta[property="og:description"]',
            'meta[name="twitter:description"]',
            'meta[name="description"]',
            '.show-description',
            '.program-description',
            '.show-content',
            '.program-content',
            '.description',
            '.summary'
        ]
    
    def detect_and_enrich_show_metadata(self, show_id: int, station_id: int) -> ShowMetadata:
        """
        Main entry point: detect and enrich metadata for a show
        
        Args:
            show_id: Database show ID
            station_id: Database station ID
            
        Returns:
            ShowMetadata object with detected information
        """
        try:
            # Get show and station info from database
            db = SessionLocal()
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                station = db.query(Station).filter(Station.id == station_id).first()
                
                if not show or not station:
                    logger.error(f"Show {show_id} or station {station_id} not found")
                    return ShowMetadata(name="Unknown Show")
                
                show_name = show.name
                station_url = station.website_url
                calendar_url = station.calendar_url
                
                # Initialize metadata with existing database info
                metadata = ShowMetadata(
                    name=show_name,
                    description=show.description,
                    host=show.host,
                    last_updated=datetime.now()
                )
                
                logger.info(f"Starting metadata detection for show '{show_name}' on station '{station.name}'")
                
            finally:
                db.close()
            
            # Step 1: Extract metadata from calendar
            calendar_metadata = self._extract_calendar_metadata(show_name, calendar_url, station_id)
            if calendar_metadata:
                logger.info(f"Found calendar metadata for '{show_name}'")
                metadata = self._merge_metadata(metadata, calendar_metadata, prefer_source='calendar')
            
            # Step 2: Crawl station website for show page
            if not metadata.long_description or not metadata.image_url:
                website_metadata = self._extract_website_metadata(show_name, station_url)
                if website_metadata:
                    logger.info(f"Found website metadata for '{show_name}'")
                    metadata = self._merge_metadata(metadata, website_metadata, prefer_source='website')
            
            # Step 3: Apply fallback hierarchy for missing fields
            metadata = self._apply_metadata_fallbacks(metadata, station_id)
            
            # Step 4: Save metadata to database
            self._save_metadata_to_database(show_id, metadata)
            
            logger.info(f"Completed metadata detection for '{show_name}': description_source={metadata.description_source}, image_source={metadata.image_source}")
            return metadata
            
        except Exception as e:
            logger.error(f"Error in metadata detection for show {show_id}: {e}")
            return ShowMetadata(name="Unknown Show", last_updated=datetime.now())
    
    def _extract_calendar_metadata(self, show_name: str, calendar_url: str, station_id: int) -> Optional[ShowMetadata]:
        """Extract show metadata from calendar feed"""
        if not calendar_url:
            return None
        
        try:
            # Parse the calendar using existing calendar parser
            shows = self.calendar_parser.parse_station_schedule(calendar_url, station_id)
            
            # Find matching show by name
            matching_show = None
            show_name_lower = show_name.lower().strip()
            
            for schedule_show in shows:
                if schedule_show.name.lower().strip() == show_name_lower:
                    matching_show = schedule_show
                    break
            
            # Try fuzzy matching if exact match not found
            if not matching_show:
                for schedule_show in shows:
                    if self._fuzzy_match_show_names(show_name, schedule_show.name):
                        matching_show = schedule_show
                        break
            
            if matching_show:
                # Extract image from calendar event (for iCal ATTACH or custom properties)
                image_url = self._extract_calendar_image(calendar_url, matching_show.name)
                
                return ShowMetadata(
                    name=matching_show.name,
                    description=matching_show.description,
                    host=matching_show.host,
                    genre=matching_show.genre,
                    image_url=image_url,
                    description_source='calendar',
                    image_source='calendar' if image_url else None,
                    last_updated=datetime.now()
                )
            
        except Exception as e:
            logger.warning(f"Error extracting calendar metadata for '{show_name}': {e}")
        
        return None
    
    def _extract_website_metadata(self, show_name: str, station_url: str) -> Optional[ShowMetadata]:
        """Extract show metadata from station website"""
        if not station_url:
            return None
        
        try:
            # First, try to find the show's dedicated page
            show_page_url = self._find_show_page_url(show_name, station_url)
            
            if show_page_url:
                logger.info(f"Found show page URL: {show_page_url}")
                return self._extract_metadata_from_page(show_page_url, show_name)
            else:
                # Fallback: search the main station page for show information
                logger.info(f"No dedicated show page found, searching main station page")
                return self._extract_metadata_from_page(station_url, show_name, is_main_page=True)
        
        except Exception as e:
            logger.warning(f"Error extracting website metadata for '{show_name}': {e}")
        
        return None
    
    def _find_show_page_url(self, show_name: str, station_url: str) -> Optional[str]:
        """Find the dedicated URL for a show's page"""
        try:
            # Generate potential URL patterns
            show_slug = self._create_url_slug(show_name)
            base_url = f"{urlparse(station_url).scheme}://{urlparse(station_url).netloc}"
            
            urls_to_try = []
            
            # Generate URLs from patterns
            for pattern in self.show_url_patterns:
                url = urljoin(base_url, pattern.format(show_name=show_slug))
                urls_to_try.append(url)
                
                # Also try with original show name (URL encoded)
                encoded_name = quote(show_name.replace(' ', '-').lower())
                url = urljoin(base_url, pattern.format(show_name=encoded_name))
                urls_to_try.append(url)
            
            # Try each URL
            for url in urls_to_try:
                try:
                    response = self.session.head(url, timeout=10, allow_redirects=True)
                    if response.status_code == 200:
                        logger.info(f"Found show page: {url}")
                        return url
                except requests.RequestException:
                    continue
            
            # If no direct URLs work, crawl the station site for show links
            return self._crawl_for_show_links(show_name, station_url)
        
        except Exception as e:
            logger.warning(f"Error finding show page URL for '{show_name}': {e}")
            return None
    
    def _crawl_for_show_links(self, show_name: str, station_url: str) -> Optional[str]:
        """Crawl station website to find links to the show page"""
        try:
            response = self.session.get(station_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            show_name_lower = show_name.lower()
            
            # Look for links containing the show name
            for link in soup.find_all('a', href=True):
                link_text = link.get_text().lower().strip()
                href = link.get('href')
                
                # Check if link text matches show name
                if (show_name_lower in link_text or 
                    self._fuzzy_match_show_names(show_name, link_text)):
                    
                    full_url = urljoin(station_url, href)
                    logger.info(f"Found potential show link: {full_url}")
                    return full_url
            
            # Look for show information in the page structure
            show_sections = soup.find_all(['div', 'section'], 
                                        text=re.compile(re.escape(show_name), re.I))
            for section in show_sections:
                # Look for nearby links
                parent = section.parent if section.parent else section
                show_link = parent.find('a', href=True)
                if show_link:
                    full_url = urljoin(station_url, show_link.get('href'))
                    logger.info(f"Found show section link: {full_url}")
                    return full_url
        
        except Exception as e:
            logger.warning(f"Error crawling for show links: {e}")
        
        return None
    
    def _extract_metadata_from_page(self, page_url: str, show_name: str, is_main_page: bool = False) -> Optional[ShowMetadata]:
        """Extract metadata from a specific page"""
        try:
            response = self.session.get(page_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Extract Open Graph and meta description
            og_description = self._extract_meta_content(soup, 'meta[property="og:description"]')
            meta_description = self._extract_meta_content(soup, 'meta[name="description"]')
            twitter_description = self._extract_meta_content(soup, 'meta[name="twitter:description"]')
            
            # Extract images
            og_image = self._extract_meta_content(soup, 'meta[property="og:image"]')
            twitter_image = self._extract_meta_content(soup, 'meta[name="twitter:image"]')
            
            # Extract structured description content
            long_description = self._extract_structured_description(soup, show_name, is_main_page)
            
            # Extract host information
            host = self._extract_host_info(soup, show_name, is_main_page)
            
            # Find best image
            image_url = self._find_best_image(soup, page_url, og_image, twitter_image)
            
            # Choose best description
            description = og_description or twitter_description or meta_description or long_description
            
            if description or image_url or host:
                return ShowMetadata(
                    name=show_name,
                    description=description[:500] if description else None,  # Limit length
                    long_description=long_description[:1000] if long_description else None,
                    host=host,
                    image_url=image_url,
                    website_url=page_url if not is_main_page else None,
                    description_source='website',
                    image_source='website' if image_url else None,
                    last_updated=datetime.now()
                )
        
        except Exception as e:
            logger.warning(f"Error extracting metadata from page {page_url}: {e}")
        
        return None
    
    def _extract_structured_description(self, soup: BeautifulSoup, show_name: str, is_main_page: bool) -> Optional[str]:
        """Extract structured description content from page"""
        # If it's a main page, look for show-specific content
        if is_main_page:
            show_name_lower = show_name.lower()
            
            # Look for sections containing the show name
            for element in soup.find_all(['div', 'section', 'article', 'p']):
                element_text = element.get_text().lower()
                if show_name_lower in element_text:
                    # Get the parent container for more context
                    description_container = element.parent if element.parent else element
                    description_text = description_container.get_text().strip()
                    
                    # Clean up the text
                    description_text = re.sub(r'\s+', ' ', description_text)
                    
                    # If it's a reasonable length, use it
                    if 50 <= len(description_text) <= 1000:
                        return description_text
            
            return None
        
        # For dedicated show pages, look for description content
        for selector in self.description_selectors:
            elements = soup.select(selector)
            for element in elements:
                if element.name == 'meta':
                    content = element.get('content', '').strip()
                else:
                    content = element.get_text().strip()
                
                if content and len(content) > 20:
                    # Clean up the text
                    content = re.sub(r'\s+', ' ', content)
                    return content
        
        # Fallback: look for the first substantial paragraph
        paragraphs = soup.find_all('p')
        for p in paragraphs:
            text = p.get_text().strip()
            if 50 <= len(text) <= 1000:
                text = re.sub(r'\s+', ' ', text)
                return text
        
        return None
    
    def _extract_host_info(self, soup: BeautifulSoup, show_name: str, is_main_page: bool) -> Optional[str]:
        """Extract host information from page"""
        # Look for host-related content
        host_keywords = ['host', 'hosted by', 'with', 'presenter', 'dj']
        
        for keyword in host_keywords:
            # Look for text containing host keywords
            elements = soup.find_all(text=re.compile(keyword, re.I))
            for element in elements:
                text = element.strip()
                # Extract name after host keyword
                match = re.search(rf'{keyword}\s+(.+?)(?:\.|,|$)', text, re.I)
                if match:
                    host_name = match.group(1).strip()
                    if 2 < len(host_name) < 50:  # Reasonable name length
                        return host_name
        
        return None
    
    def _find_best_image(self, soup: BeautifulSoup, page_url: str, og_image: str = None, twitter_image: str = None) -> Optional[str]:
        """Find the best image for the show"""
        # Prioritize Open Graph and Twitter images
        if og_image:
            return self._resolve_image_url(og_image, page_url)
        if twitter_image:
            return self._resolve_image_url(twitter_image, page_url)
        
        # Look for images using selectors
        for selector in self.image_selectors:
            elements = soup.select(selector)
            for element in elements:
                if element.name == 'meta':
                    image_url = element.get('content', '')
                else:
                    image_url = element.get('src', '')
                
                if image_url:
                    return self._resolve_image_url(image_url, page_url)
        
        return None
    
    def _resolve_image_url(self, image_url: str, base_url: str) -> str:
        """Resolve relative image URLs to absolute URLs"""
        if not image_url:
            return None
        
        # If already absolute, return as-is
        if image_url.startswith(('http://', 'https://')):
            return image_url
        
        # Resolve relative URLs
        return urljoin(base_url, image_url)
    
    def _extract_meta_content(self, soup: BeautifulSoup, selector: str) -> Optional[str]:
        """Extract content from meta tags"""
        element = soup.select_one(selector)
        if element:
            return element.get('content', '').strip()
        return None
    
    def _extract_calendar_image(self, calendar_url: str, show_name: str) -> Optional[str]:
        """Extract image from calendar event (for iCal ATTACH property)"""
        # This would require parsing iCal ATTACH properties
        # For now, return None as most calendar feeds don't include images
        return None
    
    def _apply_metadata_fallbacks(self, metadata: ShowMetadata, station_id: int) -> ShowMetadata:
        """Apply fallback hierarchy for missing metadata fields"""
        # Get station information for fallbacks
        try:
            db = SessionLocal()
            try:
                station = db.query(Station).filter(Station.id == station_id).first()
                station_logo = station.logo_url if station else None
                station_name = station.name if station else "Unknown Station"
            finally:
                db.close()
        except Exception as e:
            logger.warning(f"Error getting station info for fallbacks: {e}")
            station_logo = None
            station_name = "Unknown Station"
        
        # Image fallback hierarchy: show image → station logo → default
        if not metadata.image_url:
            if station_logo:
                metadata.image_url = station_logo
                metadata.image_source = 'station'
            else:
                # Use system default image
                metadata.image_url = '/assets/images/default-show.png'
                metadata.image_source = 'default'
        
        # Description fallback
        if not metadata.description:
            metadata.description = f"Radio program on {station_name}"
            metadata.description_source = 'generated'
        
        return metadata
    
    def _save_metadata_to_database(self, show_id: int, metadata: ShowMetadata) -> bool:
        """Save metadata to database"""
        try:
            db = SessionLocal()
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                if show:
                    # Update all metadata fields
                    if metadata.description:
                        show.description = metadata.description
                    if metadata.long_description:
                        show.long_description = metadata.long_description
                    if metadata.host:
                        show.host = metadata.host
                    if metadata.genre:
                        show.genre = metadata.genre
                    if metadata.image_url:
                        show.image_url = metadata.image_url
                    if metadata.website_url:
                        show.website_url = metadata.website_url
                    
                    # Update metadata tracking fields
                    show.description_source = metadata.description_source
                    show.image_source = metadata.image_source
                    show.metadata_updated = metadata.last_updated
                    
                    # Store extended metadata as JSON
                    extended_metadata = {
                        'social_links': metadata.social_links,
                        'tags': metadata.tags,
                        'auto_detected': True,
                        'detection_timestamp': metadata.last_updated.isoformat() if metadata.last_updated else None
                    }
                    show.metadata_json = json.dumps(extended_metadata)
                    
                    db.commit()
                    logger.info(f"Saved metadata to database for show {show_id}")
                    return True
                else:
                    logger.error(f"Show {show_id} not found for metadata save")
                    return False
            finally:
                db.close()
        except Exception as e:
            logger.error(f"Error saving metadata to database: {e}")
            return False
    
    def _merge_metadata(self, base: ShowMetadata, new: ShowMetadata, prefer_source: str) -> ShowMetadata:
        """Merge two metadata objects, preferring specified source"""
        # Keep existing values unless new source is preferred or field is empty
        result = ShowMetadata(
            name=new.name or base.name,
            description=new.description or base.description,
            long_description=new.long_description or base.long_description,
            host=new.host or base.host,
            genre=new.genre or base.genre,
            image_url=new.image_url or base.image_url,
            website_url=new.website_url or base.website_url,
            description_source=new.description_source or base.description_source,
            image_source=new.image_source or base.image_source,
            last_updated=datetime.now()
        )
        
        # If preferring new source and it has data, override
        if prefer_source == 'calendar' and new.description:
            result.description = new.description
            result.description_source = 'calendar'
        elif prefer_source == 'website' and new.long_description:
            result.description = new.long_description[:500]  # Truncate for description field
            result.long_description = new.long_description
            result.description_source = 'website'
        
        return result
    
    def _fuzzy_match_show_names(self, name1: str, name2: str) -> bool:
        """Fuzzy match show names (simple similarity check)"""
        name1 = name1.lower().strip()
        name2 = name2.lower().strip()
        
        # Exact match
        if name1 == name2:
            return True
        
        # Check if one contains the other
        if name1 in name2 or name2 in name1:
            return True
        
        # Remove common words and check again
        common_words = ['the', 'show', 'radio', 'program', 'hour', 'with']
        clean_name1 = name1
        clean_name2 = name2
        
        for word in common_words:
            clean_name1 = clean_name1.replace(f' {word} ', ' ').replace(f'{word} ', ' ').replace(f' {word}', ' ')
            clean_name2 = clean_name2.replace(f' {word} ', ' ').replace(f'{word} ', ' ').replace(f' {word}', ' ')
        
        clean_name1 = clean_name1.strip()
        clean_name2 = clean_name2.strip()
        
        if clean_name1 == clean_name2:
            return True
        if clean_name1 in clean_name2 or clean_name2 in clean_name1:
            return True
        
        return False
    
    def _create_url_slug(self, text: str) -> str:
        """Create a URL-friendly slug from text"""
        # Convert to lowercase and replace spaces/special chars with hyphens
        slug = re.sub(r'[^\w\s-]', '', text.lower())
        slug = re.sub(r'[-\s]+', '-', slug)
        slug = slug.strip('-')
        return slug


def detect_show_metadata_batch(station_id: int) -> List[Dict[str, Any]]:
    """
    Detect metadata for all shows of a station
    
    Args:
        station_id: Database station ID
        
    Returns:
        List of metadata detection results
    """
    detector = ShowMetadataDetector()
    results = []
    
    try:
        db = SessionLocal()
        try:
            shows = db.query(Show).filter(Show.station_id == station_id, Show.active == True).all()
            logger.info(f"Processing metadata detection for {len(shows)} shows on station {station_id}")
            
            for show in shows:
                try:
                    metadata = detector.detect_and_enrich_show_metadata(show.id, station_id)
                    result = {
                        'show_id': show.id,
                        'show_name': show.name,
                        'success': True,
                        'metadata': metadata.to_dict()
                    }
                    results.append(result)
                    logger.info(f"✅ Processed show: {show.name}")
                    
                except Exception as e:
                    logger.error(f"❌ Error processing show {show.name}: {e}")
                    result = {
                        'show_id': show.id,
                        'show_name': show.name,
                        'success': False,
                        'error': str(e)
                    }
                    results.append(result)
            
        finally:
            db.close()
            
    except Exception as e:
        logger.error(f"Error in batch metadata detection: {e}")
    
    return results


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Show Metadata Detection Service')
    parser.add_argument('--show-id', type=int, help='Detect metadata for specific show ID')
    parser.add_argument('--station-id', type=int, help='Station ID (required with --show-id or --batch)')
    parser.add_argument('--batch', action='store_true', help='Process all shows for a station')
    
    args = parser.parse_args()
    
    logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
    
    if args.show_id and args.station_id:
        # Single show processing
        detector = ShowMetadataDetector()
        metadata = detector.detect_and_enrich_show_metadata(args.show_id, args.station_id)
        print(f"Metadata for show {args.show_id}:")
        print(json.dumps(metadata.to_dict(), indent=2, default=str))
        
    elif args.batch and args.station_id:
        # Batch processing for station
        results = detect_show_metadata_batch(args.station_id)
        print(f"Processed {len(results)} shows:")
        for result in results:
            print(f"  {result['show_name']}: {'✅' if result['success'] else '❌'}")
            
    else:
        parser.print_help()