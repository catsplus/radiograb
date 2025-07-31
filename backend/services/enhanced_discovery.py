"""
Discovers streaming URLs for radio stations with enhanced, deeper searching.

This service uses multiple strategies to find streaming URLs, including scanning
the main page, dedicated listen pages, JavaScript variables, and common streaming
endpoints. It is designed to be more robust than the basic discovery methods.

Key Variables:
- `website_url`: The URL of the station's website.

Inter-script Communication:
- This script is used by the frontend API and other services to find stream URLs.
- It does not directly interact with the database.
"""

import requests
from bs4 import BeautifulSoup
import re
from urllib.parse import urljoin, urlparse
import json
import logging
from typing import Dict, List, Optional

logger = logging.getLogger(__name__)

class EnhancedStationDiscovery:
    """Enhanced discovery with deeper searching and modern web support"""
    
    def __init__(self, timeout: int = 10):
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        })
    
    def discover_streaming_urls(self, website_url: str) -> List[str]:
        """
        Enhanced streaming URL discovery with multiple strategies
        """
        all_urls = set()
        
        # Strategy 1: Scan main page
        main_urls = self._scan_page_for_streams(website_url)
        all_urls.update(main_urls)
        
        # Strategy 2: Look for dedicated streaming/listen pages
        listen_page_urls = self._find_listen_pages(website_url)
        for listen_url in listen_page_urls:
            listen_streams = self._scan_page_for_streams(listen_url)
            all_urls.update(listen_streams)
        
        # Strategy 3: Check JavaScript and JSON data
        js_urls = self._extract_from_javascript(website_url)
        all_urls.update(js_urls)
        
        # Strategy 4: Common streaming endpoints
        common_urls = self._try_common_endpoints(website_url)
        all_urls.update(common_urls)
        
        return list(all_urls)
    
    def _scan_page_for_streams(self, url: str) -> List[str]:
        """Scan a specific page for streaming URLs"""
        try:
            response = self.session.get(url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')
            
            streams = set()
            
            # Look in all text content
            page_text = response.text
            stream_patterns = [
                r'https?://[^"\s\']*\.(?:mp3|m3u|pls|aac|ogg)(?:\?[^"\s\']*)?',
                r'https?://[^"\s\']*:(?:8000|8080|8443)/[^"\s\']*',
                r'https?://[^"\s\']*(?:icecast|shoutcast|streamguys|tritondigital|radiojar)[^"\s\']*',
                r'https?://[^"\s\']*stream[^"\s\']*\.(?:mp3|m3u|pls)',
                r'https?://[^"\s\']*radio[^"\s\']*\.mp3',
            ]
            
            for pattern in stream_patterns:
                matches = re.findall(pattern, page_text, re.IGNORECASE)
                for match in matches:
                    # Clean up the URL
                    clean_url = re.sub(r'["\'>].*$', '', match)
                    if self._is_likely_stream_url(clean_url):
                        streams.add(clean_url)
            
            # Look in HTML attributes
            for tag in soup.find_all(['a', 'audio', 'source', 'embed', 'iframe']):
                for attr in ['href', 'src', 'data-src', 'data-stream', 'data-url']:
                    value = tag.get(attr)
                    if value and self._is_likely_stream_url(value):
                        full_url = urljoin(url, value)
                        streams.add(full_url)
            
            # Look for data in script tags
            for script in soup.find_all('script'):
                if script.string:
                    for pattern in stream_patterns:
                        matches = re.findall(pattern, script.string, re.IGNORECASE)
                        streams.update(matches)
            
            return list(streams)
            
        except Exception as e:
            logger.error(f"Error scanning {url}: {str(e)}")
            return []
    
    def _find_listen_pages(self, website_url: str) -> List[str]:
        """Find dedicated listen/streaming pages"""
        try:
            response = self.session.get(website_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')
            
            listen_urls = set()
            
            # Look for links with listen-related text
            listen_keywords = [
                'listen', 'stream', 'live', 'player', 'radio', 'on-air', 'now playing'
            ]
            
            for keyword in listen_keywords:
                # Find links with keyword in text
                for link in soup.find_all('a', text=re.compile(keyword, re.I)):
                    href = link.get('href')
                    if href:
                        full_url = urljoin(website_url, href)
                        listen_urls.add(full_url)
                
                # Find links with keyword in title or alt
                for link in soup.find_all('a', attrs={'title': re.compile(keyword, re.I)}):
                    href = link.get('href')
                    if href:
                        full_url = urljoin(website_url, href)
                        listen_urls.add(full_url)
            
            # Common listen page paths
            base_domain = f"{urlparse(website_url).scheme}://{urlparse(website_url).netloc}"
            common_paths = [
                '/listen', '/stream', '/live', '/player', '/radio', '/on-air'
            ]
            
            for path in common_paths:
                test_url = base_domain + path
                # Quick check if page exists
                try:
                    test_response = self.session.head(test_url, timeout=5)
                    if test_response.status_code == 200:
                        listen_urls.add(test_url)
                except:
                    pass
            
            return list(listen_urls)
            
        except Exception as e:
            logger.error(f"Error finding listen pages for {website_url}: {str(e)}")
            return []
    
    def _extract_from_javascript(self, website_url: str) -> List[str]:
        """Extract streaming URLs from JavaScript and JSON data"""
        try:
            response = self.session.get(website_url, timeout=self.timeout)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')
            
            streams = set()
            
            # Look for JSON data in script tags
            for script in soup.find_all('script', type='application/json'):
                if script.string:
                    try:
                        data = json.loads(script.string)
                        urls = self._extract_urls_from_json(data)
                        streams.update(urls)
                    except json.JSONDecodeError:
                        pass
            
            # Look for streaming URLs in regular script tags
            for script in soup.find_all('script'):
                if script.string:
                    script_content = script.string
                    
                    # Look for variable assignments with streaming URLs
                    patterns = [
                        r'(?:stream_?url|radio_?url|live_?url)\s*[=:]\s*["\']([^"\']+)["\']',
                        r'(?:src|url)\s*[=:]\s*["\']([^"\']*(?:mp3|m3u|pls|stream)[^"\']*)["\']',
                    ]
                    
                    for pattern in patterns:
                        matches = re.findall(pattern, script_content, re.IGNORECASE)
                        for match in matches:
                            if self._is_likely_stream_url(match):
                                streams.add(match)
            
            return list(streams)
            
        except Exception as e:
            logger.error(f"Error extracting from JavaScript for {website_url}: {str(e)}")
            return []
    
    def _extract_urls_from_json(self, data) -> List[str]:
        """Recursively extract URLs from JSON data"""
        urls = []
        
        if isinstance(data, dict):
            for key, value in data.items():
                if isinstance(value, str) and self._is_likely_stream_url(value):
                    urls.append(value)
                elif isinstance(value, (dict, list)):
                    urls.extend(self._extract_urls_from_json(value))
        elif isinstance(data, list):
            for item in data:
                if isinstance(item, str) and self._is_likely_stream_url(item):
                    urls.append(item)
                elif isinstance(item, (dict, list)):
                    urls.extend(self._extract_urls_from_json(item))
        
        return urls
    
    def _try_common_endpoints(self, website_url: str) -> List[str]:
        """Try common streaming endpoint patterns"""
        streams = set()
        parsed = urlparse(website_url)
        domain = parsed.netloc
        
        # Remove 'www.' if present
        if domain.startswith('www.'):
            base_domain = domain[4:]
        else:
            base_domain = domain
        
        # Common streaming subdomain patterns
        streaming_subdomains = [
            f"stream.{base_domain}",
            f"radio.{base_domain}",
            f"live.{base_domain}",
            f"audio.{base_domain}",
            base_domain
        ]
        
        # Common streaming paths and ports
        stream_endpoints = [
            ":8000/stream",
            ":8000/live",
            ":8000/radio",
            ":8080/stream",
            ":8080/live",
            "/stream.mp3",
            "/live.mp3",
            "/radio.mp3",
            "/stream.m3u",
            "/live.m3u"
        ]
        
        for subdomain in streaming_subdomains:
            for endpoint in stream_endpoints:
                test_url = f"http://{subdomain}{endpoint}"
                # Quick validation test
                try:
                    test_response = self.session.head(test_url, timeout=3)
                    content_type = test_response.headers.get('content-type', '').lower()
                    if ('audio' in content_type or 
                        test_response.status_code == 200 and 
                        any(audio_type in content_type for audio_type in ['audio', 'mp3', 'mpeg'])):
                        streams.add(test_url)
                except:
                    pass
        
        return list(streams)
    
    def _is_likely_stream_url(self, url: str) -> bool:
        """Enhanced check for streaming URLs"""
        if not url or not url.startswith(('http://', 'https://')):
            return False
        
        url_lower = url.lower()
        
        # Audio file extensions
        if any(ext in url_lower for ext in ['.mp3', '.m3u', '.pls', '.aac', '.ogg']):
            return True
        
        # Streaming ports
        if any(port in url for port in [':8000', ':8080', ':8443']):
            return True
        
        # Streaming services
        streaming_services = [
            'icecast', 'shoutcast', 'streamguys', 'tritondigital', 
            'radiojar', 'tunein', 'live365', 'radionomy'
        ]
        if any(service in url_lower for service in streaming_services):
            return True
        
        # Streaming keywords in path
        streaming_keywords = ['stream', 'live', 'radio', 'listen', 'play']
        if any(keyword in url_lower for keyword in streaming_keywords):
            # But exclude obvious non-streaming URLs
            if any(exclude in url_lower for exclude in [
                'youtube', 'facebook', 'twitter', 'instagram', 'spotify',
                '.html', '.php', '.asp', '.jsp', 'search', 'contact', 'about'
            ]):
                return False
            return True
        
        return False

def test_enhanced_discovery():
    """Test the enhanced discovery service"""
    discovery = EnhancedStationDiscovery()
    
    test_stations = [
        'https://kexp.org',
        'https://wfpk.org'
    ]
    
    for station_url in test_stations:
        print(f"\n=== Enhanced Discovery Test: {station_url} ===")
        streams = discovery.discover_streaming_urls(station_url)
        
        print(f"Found {len(streams)} potential streaming URLs:")
        for i, stream in enumerate(streams, 1):
            print(f"  {i}. {stream}")

if __name__ == "__main__":
    test_enhanced_discovery()