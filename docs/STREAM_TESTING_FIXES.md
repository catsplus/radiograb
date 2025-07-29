# Stream Testing Fixes and Solutions

**RadioGrab Stream Testing Enhancement Guide**

This document describes the comprehensive fixes and enhancements made to RadioGrab's stream testing system to resolve common compatibility issues and improve stream discovery.

## 🚨 Critical Issues Resolved

### 1. Tool Path Resolution Issue

**Problem**: Stream tests were failing with `[Errno 2] No such file or directory: 'streamripper'`

**Root Cause**: Recording functions were calling tools by name (e.g., `streamripper`) instead of using full paths (e.g., `/usr/bin/streamripper`), causing failures in certain execution environments.

**Solution**: Updated all recording functions to use verified full paths:

```python
# ❌ Before (caused failures)
cmd = ['streamripper', stream_url, '-l', str(duration)]

# ✅ After (works reliably)  
cmd = ['/usr/bin/streamripper', stream_url, '-l', str(duration)]
```

**Files Modified**:
- `backend/services/test_recording_service.py`: Updated all tool functions
- Added tool path verification with fallback mechanisms

### 2. Enhanced Error Recovery System

**Problem**: Streams failing with HTTP 403, connection issues, or tool incompatibilities had no recovery mechanisms.

**Solution**: Implemented multi-strategy testing approach:

1. **Saved User-Agent Strategy**: Use previously successful User-Agent for the station
2. **Default Strategy**: Try without User-Agent first
3. **User-Agent Rotation**: Cycle through multiple User-Agents for HTTP 403 errors
4. **Alternative URL Discovery**: Try variations of problematic stream URLs
5. **Multi-Tool Fallback**: Try different recording tools if primary fails

## 🔧 Technical Enhancements

### Tool Path Verification System

```python
def fix_tool_path_issues(self) -> Dict[str, str]:
    """Fix common tool path issues by verifying tool locations"""
    verified_tools = {}
    
    for tool_name, default_path in self.recording_tools.items():
        # Try default path first
        if os.path.exists(default_path) and os.access(default_path, os.X_OK):
            verified_tools[tool_name] = default_path
            continue
        
        # Fallback to 'which' command
        try:
            result = subprocess.run(['which', tool_name], capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                tool_path = result.stdout.strip()
                if os.path.exists(tool_path) and os.access(tool_path, os.X_OK):
                    verified_tools[tool_name] = tool_path
                    continue
        except Exception:
            pass
        
        # Try alternative paths
        alt_paths = [f'/usr/local/bin/{tool_name}', f'/bin/{tool_name}', f'/usr/sbin/{tool_name}']
        for alt_path in alt_paths:
            if os.path.exists(alt_path) and os.access(alt_path, os.X_OK):
                verified_tools[tool_name] = alt_path
                break
    
    return verified_tools
```

### Smart User-Agent Management

The system now includes a comprehensive User-Agent rotation system for problematic streams:

```python
user_agents = [
    None,  # Default (no User-Agent)
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'iTunes/12.0.0 (Macintosh; OS X 10.10.5) AppleWebKit/600.8.9',
    'VLC/3.0.0 LibVLC/3.0.0',
    'Radio-Browser/1.0',
    'Winamp/5.0'
]
```

**Key Features**:
- Automatic User-Agent persistence (saves successful User-Agents per station)
- HTTP 403 error detection with automatic User-Agent rotation
- Tool-specific User-Agent support (ffmpeg and wget support User-Agents, streamripper doesn't)

### Alternative Stream URL Discovery

For known problematic stream types, the system now tries URL variations:

```python
def _discover_alternative_stream_urls(self, original_url: str) -> List[str]:
    """Discover alternative stream URLs for problematic streams"""
    alternatives = []
    
    # StreamTheWorld URL variations
    if 'streamtheworld.com' in original_url.lower():
        if '/WYSOHD2.mp3' in original_url:
            alternatives.extend([
                original_url.replace('/WYSOHD2.mp3', '/WYSO.mp3'),
                original_url.replace('/WYSOHD2.mp3', '/WYSOHD1.mp3'),
                original_url.replace('https://', 'http://'),
                original_url.replace(':443', ':80')
            ])
    
    # Icecast/Shoutcast variations
    if any(keyword in original_url.lower() for keyword in ['icecast', 'shoutcast', '.streamguys1.com']):
        if '/live' in original_url:
            alternatives.extend([
                original_url.replace('/live', '/stream'),
                original_url.replace('/live', '/listen'),
                original_url.replace('/live', '/radio')
            ])
    
    return alternatives
```

## 🏥 Diagnosis and Testing Tools

### Enhanced Test Function

The new `test_stream_with_enhanced_error_handling()` function provides detailed diagnostics:

```python
success, message, details = fixer.test_stream_with_enhanced_error_handling(
    stream_url, duration=10, station_id=station_id
)

# Returns detailed information:
# details = {
#     'tools_tried': ['streamripper', 'ffmpeg'],
#     'user_agents_tried': ['Mozilla/5.0...', 'iTunes/12.0.0...'],
#     'final_tool': 'streamripper',
#     'final_user_agent': None,
#     'file_size': 208000,
#     'strategies_used': ['saved_user_agent', 'default_no_user_agent']
# }
```

### Command Line Testing

Test specific streams with the new diagnostic tool:

```bash
# Test a specific stream URL
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/stream_testing_fixes.py --test-url "https://wehc.streamguys1.com/live" --station-id 1

# Apply fixes to all stations with failed tests  
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/stream_testing_fixes.py --apply-fixes
```

## 📊 Before vs After Results

### WEHC Stream (Primary Example)

**Before Fix**:
```
❌ FAILED: [Errno 2] No such file or directory: 'streamripper'
Last Test: failed (9 hours ago)
```

**After Fix**:
```
✅ SUCCESS: Recording successful: 208000 bytes
Strategy: Using streamripper at /usr/bin/streamripper
Last Test: success (just now)
```

### System-Wide Improvement

**Before**: 1 out of 5 stations failing (WEHC)
**After**: 5 out of 5 stations successful (100% success rate)

```
Station Status Summary:
✅ KULT      - success
✅ WEHC      - success (FIXED)
✅ WERU      - success  
✅ WTBR      - success
✅ WYSO      - success
```

## 🔄 Automatic Recovery Features

### Self-Healing Stream Discovery

The system now includes automatic stream rediscovery for failed tests:

1. **Initial Test**: Try with current stream URL and settings
2. **User-Agent Rotation**: If HTTP 403, try different User-Agents  
3. **URL Discovery**: Try alternative URLs for known problematic patterns
4. **Tool Fallback**: Try different recording tools
5. **Persistence**: Save successful configurations for future use

### Continuous Monitoring

The station auto-test service now runs enhanced tests every 24 hours with:
- Automatic retry with enhanced error handling
- User-Agent persistence across test cycles
- Stream URL rediscovery for continued failures
- Detailed logging for troubleshooting

## 🛠️ Deployment Integration

### Automatic Application

The fixes are automatically applied during deployment:

1. **Container Build**: New test_recording_service.py with full tool paths
2. **Enhanced Discovery**: stream_testing_fixes.py module available for manual diagnosis
3. **Database Updates**: Station test statuses updated with new results
4. **Monitoring**: Continuous testing with enhanced error recovery

### Verification Commands

Verify the fixes are working:

```bash
# Check all station test statuses
docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 -e 'SELECT name, last_test_result, last_tested FROM stations ORDER BY name' radiograb

# Run enhanced test on specific station
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python -c "
import sys; sys.path.append('/opt/radiograb')
from backend.services.stream_testing_fixes import StreamTestingFixes
fixer = StreamTestingFixes()
success, message, details = fixer.test_stream_with_enhanced_error_handling('https://wehc.streamguys1.com/live', station_id=1)
print(f'Result: {\"SUCCESS\" if success else \"FAILED\"}: {message}')
print(f'Details: {details}')
"

# Test recording tools are properly accessible
docker exec radiograb-recorder-1 /usr/bin/streamripper --help
docker exec radiograb-recorder-1 /usr/bin/ffmpeg -version  
docker exec radiograb-recorder-1 /usr/bin/wget --version
```

## 🔮 Future Enhancements

### Planned Improvements

1. **Calendar URL Discovery**: Add missing calendar URLs for WERU, WTBR, and WEHC
2. **Stream Quality Detection**: Automatically detect and prefer higher quality streams
3. **Format-Specific Tools**: Enhanced tool selection based on stream audio format
4. **Bandwidth Optimization**: Adaptive quality selection based on available bandwidth
5. **Regional Variations**: Support for geo-restricted streams with proxy rotation

### Integration Points

The enhanced stream testing system integrates with:
- **Station Discovery**: Automatic stream URL updates
- **Recording Service**: Reliable tool execution for scheduled recordings  
- **Health Monitoring**: Real-time stream availability tracking
- **User Interface**: Better error reporting and stream status display

## 📝 Maintenance Notes

### Regular Maintenance

1. **Monitor Test Results**: Check for new failure patterns
2. **Update User-Agents**: Add new User-Agents as needed for compatibility
3. **Stream URL Updates**: Update alternative URL patterns for new stream types
4. **Tool Updates**: Verify tool paths after system updates

### Troubleshooting

If streams still fail after these fixes:

1. **Check Tool Installation**: Verify streamripper, ffmpeg, wget are installed
2. **Network Connectivity**: Test basic connectivity to stream URLs
3. **Permission Issues**: Ensure www-data user can execute recording tools
4. **Log Analysis**: Check detailed error messages in test results
5. **Manual Testing**: Use the diagnostic tools for detailed analysis

---

**Status**: ✅ All critical stream testing issues resolved  
**Success Rate**: 100% (5/5 stations working)  
**Next Priority**: Add missing calendar URLs for enhanced schedule discovery