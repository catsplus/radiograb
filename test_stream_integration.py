#!/usr/bin/env python3
"""
Test script for stream testing integration
Tests the complete workflow: discovery → stream testing → database storage
"""

import sys
import os
import json

# Add backend services to path
sys.path.append(os.path.join(os.path.dirname(__file__), 'backend', 'services'))

from station_discovery import StationDiscovery
from stream_tester import StreamTester, generate_test_report

def test_station_with_testing(website_url: str):
    """Test complete station discovery with stream testing"""
    print(f"🔍 Testing complete workflow for: {website_url}")
    print("="*80)
    
    # Initialize discovery service with stream testing enabled
    discovery = StationDiscovery(test_streams=True)
    
    # Run discovery
    print("1. Running station discovery...")
    results = discovery.discover_station(website_url)
    
    if not results['success']:
        print("❌ Discovery failed:")
        for error in results['errors']:
            print(f"   - {error}")
        return
    
    print(f"✅ Discovery successful!")
    print(f"   Station: {results['station_name']}")
    print(f"   Stream URL: {results['stream_url']}")
    print(f"   Stream Compatibility: {results['stream_compatibility']}")
    print(f"   Recommended Tool: {results['recommended_recording_tool']}")
    
    # Show detailed stream test results if available
    if results.get('stream_test_results'):
        test_results = results['stream_test_results']
        print("\n📊 Stream Test Summary:")
        print(f"   Compatible: {'✅ Yes' if test_results.get('compatible', False) else '❌ No'}")
        if test_results.get('recommended_tool'):
            print(f"   Best Tool: {test_results['recommended_tool']}")
        if test_results.get('file_size'):
            print(f"   Test File Size: {test_results['file_size']} bytes")
        if test_results.get('quality_score'):
            print(f"   Quality Score: {test_results['quality_score']}")
        if test_results.get('error'):
            print(f"   Error: {test_results['error']}")
    
    # Test comprehensive stream analysis for primary URL
    if results['stream_url']:
        print("\n2. Running comprehensive stream analysis...")
        tester = StreamTester()
        comprehensive_results = tester.test_stream_comprehensive(
            results['stream_url'], 
            results['station_name'] or 'Unknown Station'
        )
        
        print("\n📋 Comprehensive Test Report:")
        report = generate_test_report(comprehensive_results)
        print(report)
    
    print("\n" + "="*80)
    print("🎯 Integration Test Complete!")

def main():
    """Test with known working stations"""
    test_stations = [
        "https://www.emoryhenry.edu/wehc/",  # WEHC - Known working with streamripper
        "https://weru.org/",                 # WERU - Requires ffmpeg
        "https://www.wyso.org/"              # WYSO - Requires wget
    ]
    
    print("🚀 Testing RadioGrab Stream Testing Integration")
    print("This tests the complete workflow from discovery to stream testing")
    print()
    
    if len(sys.argv) > 1:
        # Test specific URL if provided
        test_station_with_testing(sys.argv[1])
    else:
        # Test all known stations
        for i, station_url in enumerate(test_stations, 1):
            print(f"\n{'='*20} TEST {i}/3 {'='*20}")
            test_station_with_testing(station_url)
            if i < len(test_stations):
                print("\n" + "-"*60 + "\n")

if __name__ == "__main__":
    main()