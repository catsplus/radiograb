#!/usr/bin/env python3
"""
Test script for RadioGrab station discovery
"""
import sys
sys.path.append('.')

from backend.services.station_discovery import StationDiscovery
from backend.utils.stream_validator import StreamValidator

def test_with_known_streams():
    """Test with known working streaming URLs"""
    print("=== Testing Stream Validator with Known URLs ===")
    validator = StreamValidator()
    
    # Known working streaming URLs
    test_streams = [
        'http://kexp-mp3-128.streamguys1.com/kexp128.mp3',
        'https://live.str3am.com:2199/tunein/kexplow.pls',
        'http://radio.wfpk.org:8000/wfpk128.mp3',
        'https://invalid-stream-url.example.com/test.mp3'
    ]
    
    for stream_url in test_streams:
        print(f"\n--- Testing: {stream_url} ---")
        result = validator.validate_stream(stream_url)
        
        print(f"Valid: {result['is_valid']}")
        print(f"Content Type: {result['content_type']}")
        print(f"Station Name: {result['station_name']}")
        print(f"Bitrate: {result['bitrate']}")
        print(f"Quality Score: {validator.get_stream_quality_score(result)}")
        if result['error']:
            print(f"Error: {result['error']}")

def test_discovery_detailed():
    """Test discovery with detailed output"""
    print("\n=== Testing Station Discovery (Detailed) ===")
    discovery = StationDiscovery()
    
    # Test with KEXP which should have streaming info
    result = discovery.discover_station('https://kexp.org')
    
    print(f"Station Name: {result['station_name']}")
    print(f"Description: {result['description']}")
    print(f"Logo URL: {result['logo_url']}")
    print(f"Calendar URL: {result['calendar_url']}")
    print(f"Selected Stream URL: {result['stream_url']}")
    print(f"\nAll discovered URLs ({len(result['stream_urls'])}):")
    for i, url in enumerate(result['stream_urls'], 1):
        print(f"  {i}. {url}")
    
    if result['errors']:
        print(f"\nErrors: {result['errors']}")

def test_manual_patterns():
    """Test specific streaming patterns manually"""
    print("\n=== Testing Manual Pattern Matching ===")
    
    # Common streaming patterns to look for
    test_html = """
    <html>
    <head><title>KEXP 90.3 FM</title></head>
    <body>
        <a href="http://kexp-mp3-128.streamguys1.com/kexp128.mp3">Listen Live</a>
        <audio src="https://live.str3am.com:2199/tunein/kexplow.pls"></audio>
        <div class="player" data-stream="http://radio.example.com:8000/live"></div>
        <link rel="alternate" type="application/rss+xml" href="http://radio.example.com/schedule.xml">
    </body>
    </html>
    """
    
    from bs4 import BeautifulSoup
    import re
    from backend.services.station_discovery import StreamingURLPattern
    
    soup = BeautifulSoup(test_html, 'html.parser')
    
    # Test pattern matching
    patterns = StreamingURLPattern.get_stream_patterns()
    found_urls = set()
    
    for pattern in patterns:
        matches = re.findall(pattern, test_html, re.IGNORECASE)
        found_urls.update(matches)
    
    print("Patterns found in test HTML:")
    for url in found_urls:
        print(f"  - {url}")
    
    # Test HTML attribute searching
    print("\nHTML attributes found:")
    for tag in soup.find_all(['a', 'audio', 'source']):
        for attr in ['href', 'src', 'data-stream']:
            value = tag.get(attr)
            if value and ('stream' in value.lower() or '.mp3' in value.lower() or ':8000' in value):
                print(f"  - {value} (from {tag.name}[{attr}])")

if __name__ == "__main__":
    test_manual_patterns()
    test_with_known_streams()
    test_discovery_detailed()