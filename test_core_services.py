"""
Tests core recording services without database dependencies.

This script provides unit-like tests for fundamental components such as the
`ScheduleParser` and `AudioRecorder`. It verifies their individual functionalities
and demonstrates their usage in isolation.

Key Variables:
- `test_schedules`: A list of natural language schedule descriptions for testing.
- `test_filenames`: A list of filenames for testing sanitization.

Inter-script Communication:
- This script directly imports and tests `backend.services.schedule_parser.ScheduleParser`.
- It directly imports and tests `backend.services.recording_service.AudioRecorder`.
"""
"""
Test core recording services without database dependencies
"""
import sys
sys.path.append('.')

from backend.services.schedule_parser import ScheduleParser
from backend.services.recording_service import AudioRecorder
import logging

def test_schedule_parser_enhanced():
    """Test enhanced schedule parser"""
    print("=== Testing Enhanced Schedule Parser ===")
    
    parser = ScheduleParser()
    
    # Add special time keywords to the parser
    special_times = {
        'noon': (12, 0),
        'midnight': (0, 0),
        '12:00 pm': (12, 0),
        '12:00 am': (0, 0)
    }
    
    test_schedules = [
        "Every Tuesday at 7 PM",
        "Daily at 6:30 AM",
        "Weekdays at 9 AM", 
        "Monday and Friday at 8:00 PM",
        "Every weekend at 10 AM",
        "Record at 15:00 every day",
        "Record Monday through Friday at 6 PM",
        "Every day at 12:00 PM",  # Test noon
        "Daily at midnight",       # This will fail, but shows where to improve
        "Saturday and Sunday at 2:30 PM"
    ]
    
    success_count = 0
    for schedule in test_schedules:
        print(f"\nInput: '{schedule}'")
        result = parser.parse_schedule(schedule)
        
        if result['success']:
            print(f"✅ Cron: {result['cron_expression']}")
            print(f"   Description: {result['description']}")
            print(f"   Valid: {parser.validate_cron_expression(result['cron_expression'])}")
            success_count += 1
        else:
            print(f"❌ Error: {result['error']}")
    
    print(f"\nSchedule Parser Results: {success_count}/{len(test_schedules)} successful")

def test_audio_recorder():
    """Test audio recorder functionality"""
    print("\n=== Testing Audio Recorder ===")
    
    recorder = AudioRecorder()
    
    # Test streamripper availability
    if recorder._check_streamripper():
        print("✅ Streamripper found and available")
        
        # Test filename sanitization
        test_filenames = [
            "Normal Show Name",
            "Show with / invalid : chars",
            "Very_Long_Show_Name_That_Might_Exceed_Filesystem_Limits_And_Needs_To_Be_Truncated_For_Safety",
            "Show with émojis and spëcial chars"
        ]
        
        print("\nTesting filename sanitization:")
        for filename in test_filenames:
            sanitized = recorder._sanitize_filename(filename)
            print(f"'{filename}' -> '{sanitized}'")
        
        # Test command building
        print("\nTesting streamripper command building:")
        cmd = recorder._build_streamripper_command(
            "http://example.com/stream.mp3",
            recorder.recordings_dir,
            3600,
            "test_show.mp3"
        )
        print(f"Command: {' '.join(cmd)}")
        
    else:
        print("❌ Streamripper not found")
        print("   On Ubuntu/Debian: sudo apt-get install streamripper")
        print("   On macOS: brew install streamripper")
        print("   On CentOS/RHEL: sudo yum install streamripper")

def test_cron_validation():
    """Test cron expression validation"""
    print("\n=== Testing Cron Validation ===")
    
    parser = ScheduleParser()
    
    test_crons = [
        ("0 9 * * 1-5", True),      # Valid: 9 AM weekdays
        ("30 14 * * 0", True),      # Valid: 2:30 PM Sundays
        ("0 25 * * *", False),      # Invalid: hour 25
        ("60 12 * * *", False),     # Invalid: minute 60
        ("0 9 * * 8", False),       # Invalid: day of week 8
        ("*/15 * * * *", True),     # Valid: every 15 minutes (basic support)
        ("0 9", False),             # Invalid: too few fields
    ]
    
    for cron_expr, expected in test_crons:
        result = parser.validate_cron_expression(cron_expr)
        status = "✅" if result == expected else "❌"
        print(f"{status} '{cron_expr}' -> {result} (expected {expected})")

def demonstrate_workflow():
    """Demonstrate the complete RadioGrab workflow"""
    print("\n=== RadioGrab Workflow Demonstration ===")
    
    print("""
RadioGrab Radio Recording Workflow:

1. 📡 Station Discovery:
   User provides: https://kexp.org
   System finds: http://kexp-mp3-128.streamguys1.com/kexp128.mp3
   
2. 📅 Schedule Setup:
   User says: "Record every weekday at 6 PM"
   System creates: Cron job "0 18 * * 1-5"
   
3. 🎵 Automatic Recording:
   System runs: streamripper http://stream.url -l 3600 -a show_20250119_1800.mp3
   
4. 📂 File Management:
   Creates: recordings/Morning_Show_20250119_1800.mp3
   Database: Recording metadata with title, duration, file size
   
5. 📱 Web Interface:
   User browses: Web player with recordings list
   Features: Play, download, search, RSS feeds
   
6. 🗑️ Cleanup:
   System deletes: Recordings older than retention period
   """)

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_schedule_parser_enhanced()
    test_audio_recorder()
    test_cron_validation()
    demonstrate_workflow()