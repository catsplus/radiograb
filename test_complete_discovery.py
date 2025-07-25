#!/usr/bin/env python3
"""
Complete test of station discovery and validation
"""
import sys
sys.path.append('.')

from backend.services.enhanced_discovery import EnhancedStationDiscovery
from backend.utils.stream_validator import StreamValidator

def complete_discovery_test():
    """Test complete discovery and validation workflow"""
    print("=== Complete Station Discovery Test ===")
    
    discovery = EnhancedStationDiscovery()
    validator = StreamValidator()
    
    # Test station
    station_url = 'https://wfpk.org'
    print(f"Testing: {station_url}")
    
    # Discover streams
    print("\n1. Discovering streaming URLs...")
    streams = discovery.discover_streaming_urls(station_url)
    print(f"Found {len(streams)} potential streams")
    
    # Validate each stream
    print("\n2. Validating streams...")
    valid_streams = []
    
    for i, stream_url in enumerate(streams[:5], 1):  # Test first 5 only
        print(f"\n--- Stream {i}: {stream_url} ---")
        
        # Quick validation
        result = validator.validate_stream(stream_url)
        quality_score = validator.get_stream_quality_score(result)
        
        print(f"Valid: {result['is_valid']}")
        if result['is_valid']:
            print(f"Content Type: {result['content_type']}")
            print(f"Station Name: {result['station_name']}")
            print(f"Bitrate: {result['bitrate']}")
            print(f"Quality Score: {quality_score}")
            
            valid_streams.append({
                'url': stream_url,
                'quality_score': quality_score,
                'metadata': result
            })
        else:
            print(f"Error: {result['error']}")
    
    # Show best streams
    if valid_streams:
        print(f"\n3. Best Valid Streams ({len(valid_streams)} found):")
        valid_streams.sort(key=lambda x: x['quality_score'], reverse=True)
        
        for i, stream in enumerate(valid_streams, 1):
            print(f"{i}. {stream['url']}")
            print(f"   Quality: {stream['quality_score']}/100")
            print(f"   Station: {stream['metadata'].get('station_name', 'Unknown')}")
            print(f"   Format: {stream['metadata'].get('content_type', 'Unknown')}")
    else:
        print("\n3. No valid streams found")

def test_known_good_urls():
    """Test with known working streaming URLs"""
    print("\n=== Testing Known Good URLs ===")
    
    validator = StreamValidator()
    
    # These should work (famous radio streams)
    known_streams = [
        'http://kexp-mp3-128.streamguys1.com/kexp128.mp3',
        'https://lpm.streamguys1.com/wfpk-popup.aac',
        'http://ice1.somafm.com/groovesalad-256-mp3',
    ]
    
    for stream_url in known_streams:
        print(f"\nTesting: {stream_url}")
        result = validator.validate_stream(stream_url)
        
        if result['is_valid']:
            print(f"✅ VALID - Quality: {validator.get_stream_quality_score(result)}/100")
            print(f"   Station: {result.get('station_name', 'Unknown')}")
            print(f"   Format: {result.get('content_type', 'Unknown')}")
        else:
            print(f"❌ INVALID - {result.get('error', 'Unknown error')}")

if __name__ == "__main__":
    test_known_good_urls()
    complete_discovery_test()