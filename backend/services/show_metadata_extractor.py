#!/usr/bin/env python3
"""
Show Metadata Extractor Service
Automatically extracts show metadata from calendars and station websites
"""

import re
import json
import logging
import requests
from datetime import datetime
from typing import Dict, List, Optional, Any
from urllib.parse import urljoin, urlparse, parse_qs
from bs4 import BeautifulSoup, Tag
from dataclasses import dataclass
import icalendar
try:
    from backend.services.calendar_parser import CalendarParser, ShowSchedule
except ImportError:
    from calendar_parser import CalendarParser, ShowSchedule

logger = logging.getLogger(__name__)


@dataclass
class ShowMetadata:
    """Complete show metadata information"""
    title: str
    description: Optional[str] = None
    long_description: Optional[str] = None
    image_url: Optional[str] = None
    website_url: Optional[str] = None
    host: Optional[str] = None
    genre: Optional[str] = None
    social_media: Optional[Dict[str, str]] = None
    source: Optional[str] = None  # calendar, website, manual
    confidence: float = 0.0
    
    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary for database storage"""
        return {
            'title': self.title,
            'description': self.description,
            'long_description': self.long_description,
            'image_url': self.image_url,
            'website_url': self.website_url,
            'host': self.host,
            'genre': self.genre,
            'social_media': self.social_media,
            'source': self.source,
            'confidence': self.confidence
        }


class ShowMetadataExtractor:
    """Extract comprehensive show metadata from various sources"""
    
    def __init__(self, timeout: int = 15):
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'RadioGrab/3.9.0 (Show Metadata Extractor; +https://github.com/mattbaya/radiograb)'
        })
        
        # Common show page indicators
        self.show_page_patterns = [
            r'/shows?/',
            r'/programs?/',
            r'/schedule/',
            r'/programming/',
            r'/content/',
            r'/broadcasts?/'
        ]
        
        # Genre keywords for classification
        self.genre_keywords = {
            'news': ['news', 'current', 'affairs', 'politics', 'world', 'local', 'breaking'],
            'talk': ['talk', 'discussion', 'interview', 'conversation', 'call-in'],
            'music': ['music', 'hits', 'rock', 'pop', 'jazz', 'classical', 'country', 'hip-hop', 'electronic'],
            'sports': ['sports', 'game', 'football', 'basketball', 'baseball', 'soccer', 'hockey'],
            'education': ['education', 'learning', 'academic', 'university', 'college', 'school'],
            'religion': ['religion', 'faith', 'spiritual', 'church', 'christian', 'gospel'],
            'community': ['community', 'local', 'neighborhood', 'civic', 'volunteer'],
            'arts': ['arts', 'culture', 'theater', 'literature', 'poetry', 'creative']
        }
        
    def extract_show_metadata(self, show_name: str, station_url: str, 
                             station_id: int = None, calendar_data: Dict = None) -> ShowMetadata:
        """
        Extract comprehensive metadata for a show
        
        Priority order:
        1. Calendar/iCal metadata (if available)
        2. Station website show page
        3. Search station website for show mentions
        4. Fallback to basic data
        """
        logger.info(f"Extracting metadata for show: {show_name}")
        
        # Start with basic metadata
        metadata = ShowMetadata(
            title=show_name,
            source='basic',
            confidence=0.1
        )
        
        # 1. Try to extract from calendar data first
        if calendar_data:
            calendar_metadata = self._extract_from_calendar(show_name, calendar_data)
            if calendar_metadata and calendar_metadata.confidence > metadata.confidence:
                metadata = calendar_metadata
                logger.info(f"Extracted metadata from calendar data")
        
        # 2. Try to find show page on station website
        website_metadata = self._extract_from_website(show_name, station_url)
        if website_metadata and website_metadata.confidence > metadata.confidence:
            metadata = website_metadata
            logger.info(f"Extracted metadata from website")
        
        # 3. Try to enhance with additional data
        enhanced_metadata = self._enhance_metadata(metadata, station_url)
        if enhanced_metadata and enhanced_metadata.confidence > metadata.confidence:
            metadata = enhanced_metadata
        
        logger.info(f"Final metadata confidence: {metadata.confidence}")
        return metadata
    
    def _extract_from_calendar(self, show_name: str, calendar_data: Dict) -> Optional[ShowMetadata]:
        """Extract metadata from calendar/iCal data"""
        try:
            # Handle different calendar formats
            if 'description' in calendar_data:
                description = calendar_data.get('description', '').strip()
                
                # Parse description for additional metadata
                host = self._extract_host_from_text(description)
                genre = self._classify_genre(show_name + ' ' + description)
                
                return ShowMetadata(
                    title=show_name,
                    description=description[:500] if description else None,
                    long_description=description if len(description) > 500 else None,
                    host=host,
                    genre=genre,
                    source='calendar',
                    confidence=0.6
                )
                
        except Exception as e:
            logger.warning(f"Error extracting from calendar: {e}")
            
        return None
    
    def _extract_from_website(self, show_name: str, station_url: str) -> Optional[ShowMetadata]:
        """Search station website for show-specific pages"""
        try:
            # First, try to find show page through search or navigation
            show_page_url = self._find_show_page(show_name, station_url)
            
            if show_page_url:
                return self._extract_from_show_page(show_name, show_page_url)
            else:
                # Search for show mentions on main site
                return self._search_show_mentions(show_name, station_url)
                
        except Exception as e:
            logger.warning(f"Error extracting from website: {e}")
            
        return None
    
    def _find_show_page(self, show_name: str, station_url: str) -> Optional[str]:
        """Try to find a dedicated page for the show"""
        try:
            response = self.session.get(station_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Look for links that might lead to show pages
            potential_links = []
            
            # Find links with show name or similar text
            show_name_clean = re.sub(r'[^\w\s]', '', show_name.lower())
            show_words = show_name_clean.split()
            
            for link in soup.find_all('a', href=True):
                href = link.get('href')
                text = link.get_text().strip().lower()
                
                # Skip empty links or javascript
                if not href or href.startswith('javascript:') or href.startswith('#'):
                    continue
                
                # Check if link text matches show name
                text_words = re.sub(r'[^\w\s]', '', text).split()
                if len(show_words) > 0 and all(word in text_words for word in show_words):
                    potential_links.append(urljoin(station_url, href))
                    continue
                
                # Check if URL suggests a show page
                href_lower = href.lower()
                if any(pattern in href_lower for pattern in self.show_page_patterns):
                    if any(word in href_lower for word in show_words):
                        potential_links.append(urljoin(station_url, href))
            
            # Try the most promising link first
            for link in potential_links[:3]:  # Limit to avoid excessive requests
                try:
                    test_response = self.session.get(link, timeout=self.timeout)
                    if test_response.status_code == 200:
                        # Quick check if this page mentions the show
                        if show_name_clean in test_response.text.lower():
                            return link
                except:
                    continue
                    
        except Exception as e:
            logger.warning(f"Error finding show page: {e}")
            
        return None
    
    def _extract_from_show_page(self, show_name: str, page_url: str) -> Optional[ShowMetadata]:
        """Extract metadata from a show-specific page"""
        try:
            response = self.session.get(page_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Extract Open Graph metadata
            og_data = self._extract_open_graph(soup)
            
            # Extract Schema.org metadata
            schema_data = self._extract_schema_org(soup)
            
            # Extract from page content
            content_data = self._extract_page_content(soup, show_name)
            
            # Combine all sources with priority: Schema.org > Open Graph > Content
            title = schema_data.get('title') or og_data.get('title') or content_data.get('title') or show_name
            description = (schema_data.get('description') or 
                          og_data.get('description') or 
                          content_data.get('description'))
            image_url = (schema_data.get('image') or 
                        og_data.get('image') or 
                        content_data.get('image'))
            
            # Extract additional metadata
            host = content_data.get('host') or self._extract_host_from_text(description or '')
            genre = (content_data.get('genre') or 
                    self._classify_genre(title + ' ' + (description or '')))
            
            return ShowMetadata(
                title=title,
                description=description[:500] if description else None,
                long_description=description if description and len(description) > 500 else None,
                image_url=self._normalize_image_url(image_url, page_url) if image_url else None,
                website_url=page_url,
                host=host,
                genre=genre,
                source='website',
                confidence=0.8
            )
            
        except Exception as e:
            logger.warning(f"Error extracting from show page {page_url}: {e}")
            
        return None
    
    def _extract_open_graph(self, soup: BeautifulSoup) -> Dict[str, str]:
        """Extract Open Graph metadata"""
        og_data = {}
        
        for meta in soup.find_all('meta', property=True):
            prop = meta.get('property', '').lower()
            content = meta.get('content', '').strip()
            
            if prop == 'og:title' and content:
                og_data['title'] = content
            elif prop == 'og:description' and content:
                og_data['description'] = content
            elif prop == 'og:image' and content:
                og_data['image'] = content
                
        return og_data
    
    def _extract_schema_org(self, soup: BeautifulSoup) -> Dict[str, str]:
        """Extract Schema.org JSON-LD metadata"""
        schema_data = {}
        
        for script in soup.find_all('script', type='application/ld+json'):
            try:
                data = json.loads(script.string)
                if isinstance(data, dict):
                    # Handle different schema types
                    if data.get('@type') in ['RadioSeries', 'BroadcastEvent', 'TVSeries', 'CreativeWork']:
                        schema_data['title'] = data.get('name', '')
                        schema_data['description'] = data.get('description', '')
                        
                        # Handle image
                        image = data.get('image')
                        if isinstance(image, dict):
                            schema_data['image'] = image.get('url', '')
                        elif isinstance(image, str):
                            schema_data['image'] = image
                            
            except (json.JSONDecodeError, TypeError):
                continue
                
        return schema_data
    
    def _extract_page_content(self, soup: BeautifulSoup, show_name: str) -> Dict[str, str]:
        """Extract metadata from visible page content"""
        content_data = {}
        
        # Look for headings that might be the show title
        for heading in soup.find_all(['h1', 'h2', 'h3']):
            text = heading.get_text().strip()
            if show_name.lower() in text.lower():
                content_data['title'] = text
                break
        
        # Look for description in common containers
        description_selectors = [
            '.description', '.summary', '.synopsis', '.about',
            '.content', '.body', '.main-content', 'article',
            '[class*="description"]', '[class*="summary"]'
        ]
        
        for selector in description_selectors:
            elements = soup.select(selector)
            for element in elements:
                text = element.get_text().strip()
                if len(text) > 50 and show_name.lower() in text.lower():
                    content_data['description'] = text
                    break
            if 'description' in content_data:
                break
        
        # Look for images
        images = soup.find_all('img', src=True)
        for img in images:
            alt = img.get('alt', '').lower()
            src = img.get('src', '')
            
            # Skip tiny images, icons, and logos
            if any(keyword in src.lower() for keyword in ['icon', 'logo', 'sprite', 'arrow']):
                continue
                
            # Prefer images with relevant alt text
            if show_name.lower() in alt or any(word in alt for word in ['show', 'program', 'host']):
                content_data['image'] = src
                break
        
        # Look for host information
        host_patterns = [
            r'hosted?\s+by\s+([^,.\n]+)',
            r'with\s+host\s+([^,.\n]+)',
            r'presented?\s+by\s+([^,.\n]+)',
            r'featuring\s+([^,.\n]+)'
        ]
        
        page_text = soup.get_text()
        for pattern in host_patterns:
            match = re.search(pattern, page_text, re.IGNORECASE)
            if match:
                content_data['host'] = match.group(1).strip()
                break
        
        return content_data
    
    def _search_show_mentions(self, show_name: str, station_url: str) -> Optional[ShowMetadata]:
        """Search for show mentions across the station website"""
        try:
            response = self.session.get(station_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Search for show name in page text
            page_text = soup.get_text()
            show_name_clean = show_name.lower()
            
            if show_name_clean not in page_text.lower():
                return None
            
            # Look for context around show mentions
            sentences = re.split(r'[.!?]+', page_text)
            relevant_sentences = []
            
            for sentence in sentences:
                if show_name_clean in sentence.lower():
                    relevant_sentences.append(sentence.strip())
            
            # Combine relevant context as description
            description = ' '.join(relevant_sentences[:3])  # Limit to avoid too much text
            
            if description:
                host = self._extract_host_from_text(description)
                genre = self._classify_genre(show_name + ' ' + description)
                
                return ShowMetadata(
                    title=show_name,
                    description=description[:500] if description else None,
                    host=host,
                    genre=genre,
                    source='website_search',
                    confidence=0.4
                )
                
        except Exception as e:
            logger.warning(f"Error searching show mentions: {e}")
            
        return None
    
    def _enhance_metadata(self, metadata: ShowMetadata, station_url: str) -> Optional[ShowMetadata]:
        """Enhance existing metadata with additional information"""
        try:
            # If we don't have a genre yet, try to classify from title
            if not metadata.genre and metadata.title:
                metadata.genre = self._classify_genre(metadata.title)
            
            # If we don't have a description, try to generate a basic one
            if not metadata.description and metadata.title:
                metadata.description = f"Radio show '{metadata.title}' from {urlparse(station_url).netloc}"
            
            metadata.confidence += 0.1  # Small boost for enhancement
            
        except Exception as e:
            logger.warning(f"Error enhancing metadata: {e}")
            
        return metadata
    
    def _extract_host_from_text(self, text: str) -> Optional[str]:
        """Extract host name from text using patterns"""
        if not text:
            return None
            
        host_patterns = [
            r'hosted?\s+by\s+([^,.\n]+)',
            r'with\s+host\s+([^,.\n]+)',
            r'presented?\s+by\s+([^,.\n]+)',
            r'featuring\s+([^,.\n]+)',
            r'with\s+([A-Z][a-z]+\s+[A-Z][a-z]+)'  # Two capitalized words
        ]
        
        for pattern in host_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                host = match.group(1).strip()
                # Clean up common suffixes
                host = re.sub(r'\s+(and|&)\s+.*$', '', host)  # Remove "and others"
                if len(host) > 3 and len(host) < 50:  # Reasonable length
                    return host
                    
        return None
    
    def _classify_genre(self, text: str) -> Optional[str]:
        """Classify show genre based on text content"""
        if not text:
            return None
            
        text_lower = text.lower()
        genre_scores = {}
        
        for genre, keywords in self.genre_keywords.items():
            score = sum(1 for keyword in keywords if keyword in text_lower)
            if score > 0:
                genre_scores[genre] = score
        
        if genre_scores:
            # Return the genre with highest score
            return max(genre_scores, key=genre_scores.get)
            
        return None
    
    def _normalize_image_url(self, image_url: str, base_url: str) -> str:
        """Normalize image URL to absolute URL"""
        if not image_url:
            return None
            
        # Handle data URLs
        if image_url.startswith('data:'):
            return None
            
        # Convert relative URLs to absolute
        if not image_url.startswith('http'):
            image_url = urljoin(base_url, image_url)
            
        return image_url


def main():
    """Command line interface for testing show metadata extraction"""
    import sys
    import argparse
    
    parser = argparse.ArgumentParser(description='Extract show metadata')
    parser.add_argument('show_name', help='Name of the show')
    parser.add_argument('station_url', help='Station website URL')
    parser.add_argument('--station-id', type=int, help='Station database ID')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.basicConfig(level=logging.INFO)
    else:
        logging.basicConfig(level=logging.WARNING)
    
    extractor = ShowMetadataExtractor()
    metadata = extractor.extract_show_metadata(
        args.show_name, 
        args.station_url,
        args.station_id
    )
    
    print(json.dumps(metadata.to_dict(), indent=2))


if __name__ == '__main__':
    main()