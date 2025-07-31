#!/usr/bin/env python3
"""
Tests the complete recording system, including schedule parsing and audio recording.

This script performs integration tests for key components of the RadioGrab recording
system. It verifies the functionality of the `ScheduleParser` and `AudioRecorder`,
and demonstrates a basic recording workflow.

Key Variables:
- `test_schedules`: A list of natural language schedule descriptions for testing.
- `test_stream`: A sample stream URL used for recording tests.

Inter-script Communication:
- This script directly imports and tests `backend.services.schedule_parser.ScheduleParser`.
- It directly imports and tests `backend.services.recording_service.AudioRecorder`.
- It directly imports and tests `backend.services.show_manager.ShowManager`.
"""

import sys
sys.path.append('.')

from backend.services.show_manager import ShowManager
from backend.services.recording_service import AudioRecorder
from backend.services.schedule_parser import ScheduleParser
import logging

def test_complete_recording_system():
    """Test the complete recording system"""
    print("=== Testing Complete Recording System ===")
    
    # Test 1: Schedule Parser
    print("\n1. Testing Schedule Parser...")
    parser = ScheduleParser()
    
    test_schedules = [
        "Every Tuesday at 7 PM",
        "Daily at 6:30 AM", 
        "Weekdays at 9 AM",
        "Monday and Friday at 8:00 PM"
    ]
    
    for schedule in test_schedules:
        result = parser.parse_schedule(schedule)
        if result['success']:
            print(f"✅ '{schedule}' -> {result['cron_expression']}")
            print(f"   {result['description']}")
        else:
            print(f"❌ '{schedule}' -> {result['error']}")
    
    # Test 2: Audio Recorder (short test)
    print("\n2. Testing Audio Recorder...")
    recorder = AudioRecorder()
    
    # Check if streamripper is available
    if recorder._check_streamripper():
        print("✅ Streamripper found and ready")
        
        # Test very short recording
        test_stream = "http://kexp-mp3-128.streamguys1.com/kexp128.mp3"
        print(f"Testing 5-second recording from KEXP...")
        
        result = recorder.record_stream(
            stream_url=test_stream,
            duration_seconds=5,
            output_filename="test_recording_5sec.mp3",
            title="5-Second Test",
            description="Test recording for RadioGrab"
        )
        
        if result['success']:
            print(f"✅ Recording successful: {result['file_size']} bytes")
            # Clean up test file
            import os
            if os.path.exists(result['output_file']):
                os.remove(result['output_file'])
                print("   Test file cleaned up")
        else:
            print(f"❌ Recording failed: {result['error']}")
    else:
        print("❌ Streamripper not found - recording tests skipped")
        print("   Install streamripper: sudo apt-get install streamripper")
    
    # Test 3: Show Manager (without actual scheduling)
    print("\n3. Testing Show Manager...")
    manager = ShowManager()
    
    # Test schedule parsing through show manager
    test_show_schedules = [
        "Record every weekday at 8 AM",
        "Monday, Wednesday, Friday at 6 PM"
    ]
    
    for schedule in test_show_schedules:
        result = manager.schedule_parser.parse_schedule(schedule)
        if result['success']:
            print(f"✅ Show schedule: '{schedule}'")
            print(f"   Cron: {result['cron_expression']}")
            print(f"   Description: {result['description']}")
        else:
            print(f"❌ Show schedule failed: {result['error']}")
    
    manager.shutdown()
    print("\n=== Recording System Test Complete ===")

def test_enhanced_schedule_parser():
    """Test schedule parser with edge cases"""
    print("\n=== Enhanced Schedule Parser Tests ===")
    
    parser = ScheduleParser()
    
    # Add support for common time expressions
    enhanced_schedules = [
        "Every day at noon",
        "Daily at midnight", 
        "Weekdays at 12:00 PM",
        "Saturday and Sunday at 10:30 AM",
        "Every Monday at 7:45 PM"
    ]
    
    for schedule in enhanced_schedules:
        print(f"\nTesting: '{schedule}'")
        result = parser.parse_schedule(schedule)
        if result['success']:
            print(f"✅ Cron: {result['cron_expression']}")
            print(f"   Description: {result['description']}")
        else:
            print(f"❌ Error: {result['error']}")

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_enhanced_schedule_parser()
    test_complete_recording_system()