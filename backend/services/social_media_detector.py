"""
Social Media Link Detection Service
Extracts social media links from radio station websites
"""
import re
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
from typing import Dict, List, Optional
import logging

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class SocialMediaDetector:
    """Detect and extract social media links from websites"""
    
    def __init__(self):
        # Social media platforms with their URL patterns and display info
        self.platforms = {
            'facebook': {
                'patterns': [
                    r'facebook\.com/[^/?#\s]+',
                    r'fb\.com/[^/?#\s]+',
                    r'm\.facebook\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-facebook-f',
                'name': 'Facebook',
                'color': '#1877f2'
            },
            'twitter': {
                'patterns': [
                    r'twitter\.com/[^/?#\s]+',
                    r'x\.com/[^/?#\s]+',
                    r'mobile\.twitter\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-twitter',
                'name': 'Twitter/X',
                'color': '#1da1f2'
            },
            'instagram': {
                'patterns': [
                    r'instagram\.com/[^/?#\s]+',
                    r'instagr\.am/[^/?#\s]+'
                ],
                'icon': 'fab fa-instagram',
                'name': 'Instagram',
                'color': '#e4405f'
            },
            'youtube': {
                'patterns': [
                    r'youtube\.com/(?:c/|channel/|user/|@)[^/?#\s]+',
                    r'youtu\.be/[^/?#\s]+',
                    r'youtube\.com/[^/?#\s]+',
                    r'm\.youtube\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-youtube',
                'name': 'YouTube',
                'color': '#ff0000'
            },
            'linkedin': {
                'patterns': [
                    r'linkedin\.com/(?:company/|in/)[^/?#\s]+',
                    r'linkedin\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-linkedin-in',
                'name': 'LinkedIn',
                'color': '#0077b5'
            },
            'tiktok': {
                'patterns': [
                    r'tiktok\.com/@[^/?#\s]+',
                    r'tiktok\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-tiktok',
                'name': 'TikTok',
                'color': '#000000'
            },
            'soundcloud': {
                'patterns': [
                    r'soundcloud\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-soundcloud',
                'name': 'SoundCloud',
                'color': '#ff5500'
            },
            'spotify': {
                'patterns': [
                    r'spotify\.com/[^/?#\s]+',
                    r'open\.spotify\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-spotify',
                'name': 'Spotify',
                'color': '#1db954'
            },
            'apple_music': {
                'patterns': [
                    r'music\.apple\.com/[^/?#\s]+',
                    r'itunes\.apple\.com/[^/?#\s]+'
                ],
                'icon': 'fab fa-apple',
                'name': 'Apple Music',
                'color': '#000000'
            },
            'discord': {
                'patterns': [
                    r'discord\.gg/[^/?#\s]+',
                    r'discord\.com/invite/[^/?#\s]+'
                ],
                'icon': 'fab fa-discord',
                'name': 'Discord',
                'color': '#5865f2'
            },
            'twitch': {
                'patterns': [
                    r'twitch\.tv/[^/?#\s]+'
                ],
                'icon': 'fab fa-twitch',
                'name': 'Twitch',
                'color': '#9146ff'
            }
        }
    
    def extract_social_media_links(self, soup: BeautifulSoup, base_url: str) -> Dict[str, Dict]:
        """
        Extract social media links from a webpage
        
        Args:
            soup: BeautifulSoup object of the webpage
            base_url: Base URL for resolving relative links
            
        Returns:
            Dict mapping platform names to their info and URLs
        """
        social_links = {}
        
        # Find all links on the page
        links = soup.find_all('a', href=True)
        
        # Also check for social media URLs in text content and other attributes
        page_text = soup.get_text()
        
        # Check meta tags for social media links
        meta_links = self._extract_from_meta_tags(soup)
        
        # Combine all potential sources
        all_urls = []
        
        # From href attributes
        for link in links:
            href = link['href']
            if href:
                absolute_url = urljoin(base_url, href)
                all_urls.append(absolute_url)
        
        # From meta tags
        all_urls.extend(meta_links)
        
        # From page text (find URLs in text)
        text_urls = re.findall(r'https?://[^\s<>"]+', page_text)
        all_urls.extend(text_urls)
        
        # From data attributes and other sources
        for element in soup.find_all(attrs={'data-url': True}):
            all_urls.append(element['data-url'])
        
        # Process all found URLs
        for url in all_urls:
            platform = self._identify_platform(url)
            if platform and platform not in social_links:
                clean_url = self._clean_social_url(url, platform)
                if clean_url:
                    social_links[platform] = {
                        'url': clean_url,
                        'icon': self.platforms[platform]['icon'],
                        'name': self.platforms[platform]['name'],
                        'color': self.platforms[platform]['color']
                    }
        
        logger.info(f"Found {len(social_links)} social media links: {list(social_links.keys())}")
        return social_links
    
    def _extract_from_meta_tags(self, soup: BeautifulSoup) -> List[str]:
        """Extract social media URLs from meta tags"""
        urls = []
        
        # Check Open Graph and Twitter meta tags
        meta_tags = soup.find_all('meta', attrs={
            'property': re.compile(r'og:url|twitter:url'),
            'name': re.compile(r'twitter:url'),
            'content': True
        })
        
        for meta in meta_tags:
            content = meta.get('content')
            if content:
                urls.append(content)
        
        return urls
    
    def _identify_platform(self, url: str) -> Optional[str]:
        """Identify which social media platform a URL belongs to"""
        if not url:
            return None
        
        url_lower = url.lower()
        
        for platform, config in self.platforms.items():
            for pattern in config['patterns']:
                if re.search(pattern, url_lower):
                    return platform
        
        return None
    
    def _clean_social_url(self, url: str, platform: str) -> str:
        """Clean and normalize social media URL"""
        if not url:
            return url
        
        # Remove tracking parameters and clean up
        url = url.split('?')[0].split('#')[0]
        
        # Ensure HTTPS
        if url.startswith('http://'):
            url = url.replace('http://', 'https://', 1)
        
        # Platform-specific cleaning
        if platform == 'facebook':
            # Remove /posts/, /photos/, etc. to get main page
            url = re.sub(r'/(?:posts|photos|videos|events)/.*$', '', url)
            # Normalize mobile URLs
            url = url.replace('m.facebook.com', 'www.facebook.com')
            url = url.replace('fb.com', 'www.facebook.com')
        
        elif platform == 'twitter':
            # Handle X.com vs Twitter.com
            if 'x.com' in url:
                url = url.replace('x.com', 'twitter.com')
            # Remove status/tweet URLs to get profile
            url = re.sub(r'/status/.*$', '', url)
            url = url.replace('mobile.twitter.com', 'twitter.com')
        
        elif platform == 'instagram':
            # Remove post URLs to get profile
            url = re.sub(r'/p/.*$', '', url)
            url = re.sub(r'/reel/.*$', '', url)
            url = url.replace('instagr.am', 'instagram.com')
        
        elif platform == 'youtube':
            # Normalize YouTube URLs
            if '/watch?v=' in url:
                # This is a video, try to get channel instead
                return None  # Skip individual videos
            url = url.replace('m.youtube.com', 'www.youtube.com')
        
        # Remove trailing slashes
        url = url.rstrip('/')
        
        return url
    
    def get_website_icon_info(self) -> Dict:
        """Get icon info for the main website link"""
        return {
            'icon': 'fas fa-globe',
            'name': 'Website',
            'color': '#6c757d'
        }

# Test function
if __name__ == "__main__":
    detector = SocialMediaDetector()
    
    # Test HTML with social media links
    test_html = """
    <html>
        <body>
            <a href="https://www.facebook.com/90.7wehc">Facebook</a>
            <a href="https://twitter.com/wehcfm">Twitter</a>
            <a href="https://instagram.com/wehc907">Instagram</a>
            <p>Follow us on https://youtube.com/c/wehcfm</p>
        </body>
    </html>
    """
    
    from bs4 import BeautifulSoup
    soup = BeautifulSoup(test_html, 'html.parser')
    
    result = detector.extract_social_media_links(soup, "https://wehc.com")
    print(f"Detected social media links: {result}")