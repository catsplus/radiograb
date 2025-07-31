"""
Extracts profile pictures and logos from Facebook pages.

This service is used to find a station's logo when one is not readily available
on their website. It uses a variety of methods to find the logo, including
checking meta tags, image tags, JSON-LD data, and inline styles.

Key Variables:
- `facebook_url`: The URL of the station's Facebook page.

Inter-script Communication:
- This script is used by the `station_manager.py` and other services to find
  station logos.
- It does not directly interact with the database.
"""

import requests
from bs4 import BeautifulSoup
import re
import json
import logging
from typing import Optional, Dict, List
from urllib.parse import urljoin, urlparse, unquote
import time
import random

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class FacebookLogoExtractor:
    """Extract logos from Facebook pages"""
    
    def __init__(self):
        # Rotate user agents to avoid detection
        self.user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15'
        ]
        
        self.session = requests.Session()
        self._update_headers()
    
    def _update_headers(self):
        """Update request headers with random user agent"""
        self.session.headers.update({
            'User-Agent': random.choice(self.user_agents),
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
        })
    
    def extract_facebook_logo(self, facebook_url: str) -> Optional[Dict]:
        """
        Extract profile picture from Facebook page
        
        Args:
            facebook_url: Facebook page URL
            
        Returns:
            Dict with logo_url and metadata or None if not found
        """
        try:
            logger.info(f"Extracting logo from Facebook page: {facebook_url}")
            
            # Normalize Facebook URL
            normalized_url = self._normalize_facebook_url(facebook_url)
            if not normalized_url:
                logger.warning(f"Invalid Facebook URL: {facebook_url}")
                return None
            
            # Add random delay to avoid rate limiting
            time.sleep(random.uniform(1, 3))
            
            # Update headers for this request
            self._update_headers()
            
            # Fetch the page
            response = self.session.get(normalized_url, timeout=30)
            response.raise_for_status()
            
            # Parse the HTML
            soup = BeautifulSoup(response.text, 'html.parser')
            
            # Try multiple extraction methods
            logo_url = (
                self._extract_from_meta_tags(soup) or
                self._extract_from_image_tags(soup) or
                self._extract_from_json_ld(soup) or
                self._extract_from_inline_styles(soup) or
                self._extract_from_scripts(soup, response.text)
            )
            
            if logo_url:
                # Clean up the URL
                logo_url = self._clean_facebook_image_url(logo_url)
                
                logger.info(f"Found Facebook logo: {logo_url}")
                return {
                    'logo_url': logo_url,
                    'source': 'facebook',
                    'facebook_page': normalized_url,
                    'extraction_method': 'facebook_profile_picture'
                }
            else:
                logger.warning(f"No logo found on Facebook page: {facebook_url}")
                return None
                
        except requests.RequestException as e:
            logger.error(f"Failed to fetch Facebook page {facebook_url}: {e}")
            return None
        except Exception as e:
            logger.error(f"Error extracting Facebook logo from {facebook_url}: {e}")
            return None
    
    def _normalize_facebook_url(self, url: str) -> Optional[str]:
        """Normalize Facebook URL to standard format"""
        if not url:
            return None
        
        # Handle various Facebook URL formats
        patterns = [
            r'facebook\.com/pages/[^/]+/(\d+)',  # pages/name/id format
            r'facebook\.com/([^/?]+)/?',         # facebook.com/pagename
            r'fb\.com/([^/?]+)/?',               # fb.com/pagename
            r'm\.facebook\.com/([^/?]+)/?',      # mobile Facebook
        ]
        
        for pattern in patterns:
            match = re.search(pattern, url)
            if match:
                page_name = match.group(1)
                # Return standard format
                return f"https://www.facebook.com/{page_name}"
        
        # If already in standard format
        if 'facebook.com/' in url:
            return url
        
        return None
    
    def _extract_from_meta_tags(self, soup: BeautifulSoup) -> Optional[str]:
        """Extract logo from Open Graph and Twitter meta tags"""
        meta_selectors = [
            'meta[property="og:image"]',
            'meta[property="og:image:url"]',
            'meta[name="twitter:image"]',
            'meta[property="twitter:image"]',
            'meta[property="og:logo"]'
        ]
        
        for selector in meta_selectors:
            meta = soup.select_one(selector)
            if meta and meta.get('content'):
                image_url = meta['content']
                if self._is_valid_facebook_image_url(image_url):
                    return image_url
        
        return None
    
    def _extract_from_image_tags(self, soup: BeautifulSoup) -> Optional[str]:
        """Extract logo from image tags with Facebook CDN URLs"""
        # Look for images with Facebook CDN URLs
        images = soup.find_all('img', src=True)
        
        for img in images:
            src = img['src']
            if self._is_valid_facebook_image_url(src):
                # Check if it looks like a profile picture
                if any(indicator in src for indicator in ['profile', 'avatar', 's160x160', 's200x200']):
                    return src
        
        # Also check for images in SVG (like the user provided example)
        svg_images = soup.find_all('image')
        for img in svg_images:
            href = img.get('xlink:href') or img.get('href')
            if href and self._is_valid_facebook_image_url(href):
                return href
        
        return None
    
    def _extract_from_json_ld(self, soup: BeautifulSoup) -> Optional[str]:
        """Extract logo from JSON-LD structured data"""
        scripts = soup.find_all('script', type='application/ld+json')
        
        for script in scripts:
            try:
                data = json.loads(script.string)
                
                # Handle different JSON-LD structures
                if isinstance(data, dict):
                    logo_url = (
                        data.get('logo', {}).get('url') if isinstance(data.get('logo'), dict) else data.get('logo')
                    ) or data.get('image')
                    
                    if logo_url and self._is_valid_facebook_image_url(logo_url):
                        return logo_url
                
            except (json.JSONDecodeError, AttributeError):
                continue
        
        return None
    
    def _extract_from_inline_styles(self, soup: BeautifulSoup) -> Optional[str]:
        """Extract logo from inline CSS background-image styles"""
        # Look for elements with background-image styles
        elements_with_style = soup.find_all(attrs={'style': True})
        
        for element in elements_with_style:
            style = element['style']
            # Find background-image URLs
            bg_matches = re.findall(r'background-image:\s*url\(["\']?([^"\']+)["\']?\)', style)
            for url in bg_matches:
                if self._is_valid_facebook_image_url(url):
                    return url
        
        return None
    
    def _extract_from_scripts(self, soup: BeautifulSoup, page_content: str) -> Optional[str]:
        """Extract logo from JavaScript variables and data"""
        # Look for image URLs in script content
        scripts = soup.find_all('script')
        
        for script in scripts:
            if script.string:
                # Find Facebook CDN image URLs in script content
                fb_image_matches = re.findall(
                    r'https://scontent[^"\'\\s]+\.fbcdn\.net/[^"\'\\s]+\.(?:jpg|jpeg|png|webp)',
                    script.string
                )
                
                for url in fb_image_matches:
                    # Filter for profile-like images
                    if any(indicator in url for indicator in ['profile', 'avatar', 's160x160', 's200x200', 's240x240']):
                        return unquote(url)
        
        # Also search in the full page content as fallback
        fb_image_matches = re.findall(
            r'https://scontent[^"\'\\s]+\.fbcdn\.net/[^"\'\\s]+\.(?:jpg|jpeg|png|webp)',
            page_content
        )
        
        for url in fb_image_matches:
            if any(indicator in url for indicator in ['profile', 'avatar', 's160x160', 's200x200', 's240x240']):
                return unquote(url)
        
        return None
    
    def _is_valid_facebook_image_url(self, url: str) -> bool:
        """Check if URL is a valid Facebook image URL"""
        if not url or not isinstance(url, str):
            return False
        
        return (
            url.startswith(('https://scontent', 'https://lookaside.fbsbx.com')) and
            '.fbcdn.net' in url and
            any(ext in url.lower() for ext in ['.jpg', '.jpeg', '.png', '.webp'])
        )
    
    def _clean_facebook_image_url(self, url: str) -> str:
        """Clean Facebook image URL to remove unnecessary parameters"""
        # Remove some Facebook-specific parameters that might cause issues
        # but keep essential ones for the image to work
        if not url:
            return url
        
        # Parse URL to clean parameters
        parsed = urlparse(url)
        
        # For Facebook CDN URLs, we typically want to keep most parameters
        # as they're needed for the image to load correctly
        
        # Just decode any URL encoding
        return unquote(url)

# Test function
if __name__ == "__main__":
    extractor = FacebookLogoExtractor()
    
    # Test with WEHC Facebook page
    test_url = "https://www.facebook.com/90.7wehc"
    
    result = extractor.extract_facebook_logo(test_url)
    if result:
        print(f"Successfully extracted Facebook logo: {result}")
    else:
        print("Failed to extract Facebook logo")