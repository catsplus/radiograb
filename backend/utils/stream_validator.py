"""
Stream Validation Utilities
Validates and tests streaming URLs for quality and accessibility
"""
import requests
import re
from urllib.parse import urlparse
from typing import Dict, List, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class StreamValidator:
    """Validates streaming URLs and extracts metadata"""
    
    def __init__(self, timeout: int = 10):
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'RadioGrab/1.0 Stream Validator',
            'Icy-MetaData': '1'  # Request metadata from Icecast streams
        })
    
    def validate_stream(self, stream_url: str) -> Dict:
        """
        Validate a streaming URL and extract metadata
        
        Args:
            stream_url: The streaming URL to validate
            
        Returns:
            Dictionary with validation results and metadata
        """
        result = {
            'url': stream_url,
            'is_valid': False,
            'content_type': None,
            'bitrate': None,
            'sample_rate': None,
            'station_name': None,
            'current_track': None,
            'genre': None,
            'error': None
        }
        
        try:
            # Test the stream
            response = self.session.get(
                stream_url, 
                timeout=self.timeout,
                stream=True,
                headers={'Range': 'bytes=0-1024'}  # Just get first 1KB
            )
            
            result['is_valid'] = response.status_code == 200
            
            if result['is_valid']:
                # Extract content type
                result['content_type'] = response.headers.get('content-type', '')
                
                # Extract Icecast metadata
                self._extract_icecast_metadata(response.headers, result)
                
                # Validate it's actually audio
                if not self._is_audio_stream(result['content_type']):
                    result['is_valid'] = False
                    result['error'] = f"Not an audio stream: {result['content_type']}"
            else:
                result['error'] = f"HTTP {response.status_code}"
                
        except requests.exceptions.Timeout:
            result['error'] = "Stream timeout"
        except requests.exceptions.ConnectionError:
            result['error'] = "Connection failed"
        except Exception as e:
            result['error'] = f"Validation error: {str(e)}"
        
        return result
    
    def _extract_icecast_metadata(self, headers: Dict, result: Dict):
        """Extract metadata from Icecast stream headers"""
        # Common Icecast headers
        header_mappings = {
            'icy-name': 'station_name',
            'ice-name': 'station_name',
            'icy-description': 'description',
            'icy-genre': 'genre',
            'icy-br': 'bitrate',
            'ice-bitrate': 'bitrate',
            'icy-sr': 'sample_rate',
            'ice-samplerate': 'sample_rate'
        }
        
        for header_key, result_key in header_mappings.items():
            value = headers.get(header_key)
            if value:
                result[result_key] = value.strip()
    
    def _is_audio_stream(self, content_type: str) -> bool:
        """Check if content type indicates an audio stream"""
        if not content_type:
            return False
            
        audio_types = [
            'audio/',
            'application/ogg',
            'video/mp4',  # Sometimes used for audio-only streams
            'application/octet-stream'  # Generic binary, could be audio
        ]
        
        return any(audio_type in content_type.lower() for audio_type in audio_types)
    
    def get_stream_quality_score(self, validation_result: Dict) -> int:
        """
        Calculate a quality score for a stream (0-100)
        
        Args:
            validation_result: Result from validate_stream()
            
        Returns:
            Quality score from 0-100
        """
        if not validation_result['is_valid']:
            return 0
        
        score = 50  # Base score for valid stream
        
        # Bonus for good content types
        content_type = validation_result.get('content_type', '').lower()
        if 'audio/mpeg' in content_type or 'mp3' in content_type:
            score += 20
        elif 'audio/aac' in content_type:
            score += 15
        elif 'audio/ogg' in content_type:
            score += 10
        
        # Bonus for bitrate information
        bitrate = validation_result.get('bitrate')
        if bitrate:
            try:
                br = int(bitrate)
                if br >= 128:
                    score += 15
                elif br >= 96:
                    score += 10
                elif br >= 64:
                    score += 5
            except (ValueError, TypeError):
                pass
        
        # Bonus for metadata
        if validation_result.get('station_name'):
            score += 10
        if validation_result.get('genre'):
            score += 5
        
        return min(100, score)

class PlaylistParser:
    """Parse playlist files (M3U, PLS) to extract streaming URLs"""
    
    @staticmethod
    def parse_m3u(content: str) -> List[str]:
        """Parse M3U playlist content"""
        urls = []
        lines = content.strip().split('\n')
        
        for line in lines:
            line = line.strip()
            if line and not line.startswith('#') and line.startswith('http'):
                urls.append(line)
        
        return urls
    
    @staticmethod
    def parse_pls(content: str) -> List[str]:
        """Parse PLS playlist content"""
        urls = []
        lines = content.strip().split('\n')
        
        for line in lines:
            line = line.strip()
            if line.startswith('File') and '=' in line:
                url = line.split('=', 1)[1].strip()
                if url.startswith('http'):
                    urls.append(url)
        
        return urls
    
    @staticmethod
    def parse_playlist_url(url: str, session: requests.Session = None) -> List[str]:
        """
        Download and parse a playlist URL
        
        Args:
            url: Playlist URL to download and parse
            session: Optional requests session
            
        Returns:
            List of streaming URLs found in playlist
        """
        if session is None:
            session = requests.Session()
        
        try:
            response = session.get(url, timeout=10)
            response.raise_for_status()
            content = response.text
            
            # Determine playlist type and parse
            if url.lower().endswith('.m3u') or 'audio/x-mpegurl' in response.headers.get('content-type', ''):
                return PlaylistParser.parse_m3u(content)
            elif url.lower().endswith('.pls') or 'audio/x-scpls' in response.headers.get('content-type', ''):
                return PlaylistParser.parse_pls(content)
            else:
                # Try both parsers
                urls = PlaylistParser.parse_m3u(content)
                if not urls:
                    urls = PlaylistParser.parse_pls(content)
                return urls
                
        except Exception as e:
            logger.error(f"Error parsing playlist {url}: {str(e)}")
            return []

def test_stream_validation():
    """Test function for stream validation"""
    validator = StreamValidator()
    
    # Test URLs (replace with actual streaming URLs for testing)
    test_urls = [
        'http://stream.wehc.com:8000/wehc',
        'http://kexp-mp3-128.streamguys1.com/kexp128.mp3',
        'https://example.com/invalid-stream'
    ]
    
    for url in test_urls:
        print(f"\n=== Testing {url} ===")
        result = validator.validate_stream(url)
        
        print(f"Valid: {result['is_valid']}")
        print(f"Content Type: {result['content_type']}")
        print(f"Station: {result['station_name']}")
        print(f"Bitrate: {result['bitrate']}")
        print(f"Quality Score: {validator.get_stream_quality_score(result)}")
        if result['error']:
            print(f"Error: {result['error']}")

if __name__ == "__main__":
    test_stream_validation()