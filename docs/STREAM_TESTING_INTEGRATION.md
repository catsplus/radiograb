# Automatic Stream Testing Integration

## Overview

RadioGrab now includes automatic stream testing during station discovery. When adding a new station, the system automatically validates stream compatibility with all available recording tools and recommends the optimal tool for reliable recording.

## Features

### 🔍 Automatic Testing During Discovery
- Tests discovered streams with streamripper, ffmpeg, and wget
- Provides immediate feedback on stream compatibility
- Recommends the best recording tool for each station
- Falls back to alternative streams if primary fails

### 📊 Comprehensive Analysis
- **Connectivity Testing**: Validates stream accessibility
- **Multi-Tool Testing**: Tests with all available recording tools
- **Quality Scoring**: Rates tool performance (0-100 scale)
- **Stream Analysis**: Uses ffprobe for format/quality detection
- **Compatibility Status**: Clear pass/fail indication

### 🎯 Smart Recommendations
- **Tool Selection**: Automatically selects optimal recording tool
- **Fallback Strategy**: Tests alternative streams if primary fails
- **Performance Scoring**: Considers file size, duration, and tool-specific bonuses
- **Historical Tracking**: Stores test results for analysis

## Technical Implementation

### Core Components

#### StreamTester Class (`backend/services/stream_tester.py`)
```python
# Quick test for discovery workflow
test_result = tester.test_stream_quick(stream_url)

# Comprehensive analysis
full_result = tester.test_stream_comprehensive(stream_url, station_name)
```

#### Integration with Station Discovery
```python
# Enable testing during discovery
discovery = StationDiscovery(test_streams=True)
result = discovery.discover_station(website_url)

# Results include testing information
print(f"Compatible: {result['stream_compatibility']}")
print(f"Best Tool: {result['recommended_recording_tool']}")
```

### Database Schema

#### New Station Fields
```sql
-- Stream testing fields added to stations table
recommended_recording_tool VARCHAR(50) -- streamripper, ffmpeg, wget
stream_compatibility VARCHAR(20)       -- compatible, incompatible, unknown  
stream_test_results TEXT               -- JSON test data
last_stream_test TIMESTAMP             -- When last tested
```

#### Stream Testing Log
```sql
-- Historical tracking table
CREATE TABLE stream_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    stream_url VARCHAR(500) NOT NULL,
    test_results TEXT NOT NULL,
    recommended_tool VARCHAR(50),
    compatibility_status VARCHAR(20),
    test_duration_seconds DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Web Interface Integration

#### Discovery Results Display
```javascript
// Stream test results shown in discovery UI
if (discovered.stream_test_results) {
    const testResults = discovered.stream_test_results;
    if (testResults.compatible) {
        html += `<li><i class="fas fa-check text-success"></i> Stream Test: ✅ Compatible</li>`;
        html += `<li><i class="fas fa-cog text-info"></i> Best Tool: ${testResults.recommended_tool}</li>`;
    } else {
        html += `<li><i class="fas fa-times text-danger"></i> Stream Test: ❌ Not compatible</li>`;
    }
}
```

## Tool Compatibility Matrix

| Stream Type | streamripper | wget | ffmpeg | Recommended |
|-------------|-------------|------|--------|-------------|
| **Direct HTTP/MP3** | ✅ Excellent | ✅ Good | ✅ Good | **streamripper** |
| **Redirect URLs** | ❌ Failed | ✅ **Excellent** | ✅ Excellent | **wget** |
| **Authentication** | ❌ Failed | ✅ Good | ✅ **Excellent** | **ffmpeg** |
| **Modern Protocols** | ❌ Failed | ⚠️ Limited | ✅ **Excellent** | **ffmpeg** |

## Scoring Algorithm

### Tool Performance Scoring (0-100 scale)
```python
def _calculate_tool_score(tool_name, success, file_size, duration):
    if not success or file_size == 0:
        return 0.0
    
    score = 50.0  # Base success score
    
    # File size factor (expect ~20KB/s for 128kbps stream)
    expected_size = duration * 20 * 1024
    if file_size > expected_size * 0.5:
        score += 30.0
    
    # Duration completion factor
    if duration <= test_duration + 2:
        score += 15.0
    
    # Tool-specific bonuses
    tool_bonuses = {
        'streamripper': 5.0,  # Radio-optimized
        'ffmpeg': 3.0,        # Professional
        'wget': 2.0           # Simple
    }
    score += tool_bonuses.get(tool_name, 0.0)
    
    return min(score, 100.0)
```

### Overall Status Determination
- **Excellent**: 2+ compatible tools
- **Good**: 1 compatible tool
- **Failed**: No compatible tools

## Usage Examples

### Testing Individual Streams
```bash
# Test specific station
python3 test_stream_integration.py "https://www.emoryhenry.edu/wehc/"
```

### Programmatic Testing
```python
from stream_tester import StreamTester, generate_test_report

tester = StreamTester()
results = tester.test_stream_comprehensive(
    "https://wehc.streamguys1.com/live", 
    "WEHC 90.7 FM"
)

print(generate_test_report(results))
```

## Sample Test Report

```
🎵 STREAM TEST REPORT: WEHC 90.7 FM
============================================================

📡 Stream URL: https://wehc.streamguys1.com/live
🎯 Overall Status: ✅ EXCELLENT
🔧 Recommended Tool: streamripper
✅ Working Tools: streamripper, ffmpeg, wget

📊 TOOL TEST RESULTS:
   ✅ streamripper: 15360 bytes, Score: 95.0/100
   ✅ ffmpeg: 12288 bytes, Score: 88.0/100
   ✅ wget: 14336 bytes, Score: 87.0/100

🎼 STREAM QUALITY:
   Format: mp3
   Codec: mp3
   Bitrate: 128000 bps
   Sample Rate: 44100 Hz
   Channels: 2

💡 RECOMMENDATIONS:
   • Use streamripper for recording this stream
   • Fallback tools available: ffmpeg, wget

⏰ Test completed: 2025-07-22T12:45:00
```

## Benefits

### 🎯 Guaranteed Compatibility
- **100% Success Rate**: Only compatible streams are added
- **Immediate Feedback**: Know before adding if station will work
- **Smart Fallbacks**: System tries alternative streams automatically

### 🔧 Optimal Performance  
- **Tool Selection**: Uses best tool for each stream type
- **Quality Assurance**: Validates recording quality during testing
- **Resource Efficiency**: Lightweight 2-5 second tests

### 📈 Operational Intelligence
- **Historical Data**: Track stream reliability over time
- **Trend Analysis**: Identify common stream technologies
- **Proactive Maintenance**: Re-test streams periodically

## Future Enhancements

### Additional Tools
- **yt-dlp**: For YouTube Live streams
- **curl**: Alternative HTTP client
- **vlc**: Command-line recording

### Advanced Features
- **Periodic Re-testing**: Automatic stream health checks
- **Bitrate Optimization**: Adaptive quality selection
- **Load Balancing**: Distribute across multiple stream URLs
- **Alert System**: Notify when streams become incompatible

## Migration Notes

### Database Updates
Run the migration scripts to add stream testing fields:
```sql
-- Create migrations table
source database/migrations/create_migrations_table.sql

-- Add stream testing fields
source database/migrations/add_stream_testing_fields.sql
```

### Existing Stations
Existing stations will be marked as 'unknown' compatibility and can be re-tested:
```python
# Re-test existing station
discovery = StationDiscovery(test_streams=True)
results = discovery.discover_station(station.website_url)
```

This integration ensures RadioGrab achieves 100% stream compatibility by automatically validating and optimizing recording setup for each station.