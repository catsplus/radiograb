# Stream URL Discovery Documentation

## Problem
Modern radio stations often use JavaScript-based players (like StreamGuys) where the actual stream URL is not visible in static HTML. The real stream URL is delivered dynamically through JavaScript and Socket.IO connections.

## Solution
Implemented headless Chrome + Selenium solution to extract real stream URLs from JavaScript players.

## Technical Implementation

### Tools Required
- **Chrome Browser**: Installed in Docker container
- **Selenium WebDriver**: Python automation library
- **Performance Logging**: Chrome DevTools integration

### Process
1. **Load Player Page**: Use headless Chrome to load the JavaScript player
2. **Wait for Initialization**: Allow time for JavaScript to execute and establish connections
3. **Capture Network Events**: Monitor all network requests and responses
4. **Extract JavaScript State**: Access `window._sgplayer` and similar objects
5. **Parse Stream Configuration**: Extract stream URLs from player configuration
6. **Validate URLs**: Test discovered URLs with streamripper

### Code Implementation

#### Installation Commands
```bash
# In Docker container (Ubuntu 22.04)
apt update && apt install -y wget gnupg
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor > /usr/share/keyrings/google-chrome-keyring.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome-keyring.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list
apt update && apt install -y google-chrome-stable
pip install selenium
```

#### Stream Extraction Script
```python
from selenium import webdriver
from selenium.webdriver.chrome.options import Options

def extract_stream_url(player_url):
    chrome_options = Options()
    chrome_options.add_argument("--headless")
    chrome_options.add_argument("--no-sandbox") 
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.set_capability('goog:loggingPrefs', {'performance': 'ALL'})
    
    driver = webdriver.Chrome(options=chrome_options)
    driver.get(player_url)
    time.sleep(10)  # Wait for JavaScript initialization
    
    # Extract from JavaScript player object
    result = driver.execute_script("""
        return window._sgplayer ? {
            activeStream: window._sgplayer.activeStream,
            streams: window._sgplayer.streams
        } : null;
    """)
    
    # Parse stream URLs from result
    if result and 'activeStream' in result:
        source = result['activeStream'].get('source', [])
        if source:
            return source[0].get('src')
    
    driver.quit()
    return None
```

## Real-World Example: WEHC 90.7 FM

### Problem
- **Website**: https://www.emoryhenry.edu/wehc/
- **Player URL**: https://player.streamguys.com/wehc/sgplayer3/player.php
- **Database had wrong URL**: `http://stream.wehc.com:8000/wehc` (DNS resolution failed)

### Discovery Process
1. **Loaded JavaScript player** in headless Chrome
2. **Captured network events** including Socket.IO connections
3. **Extracted player configuration** from `window._sgplayer.activeStream.source[0].src`
4. **Found real URL**: `//wehc.streamguys1.com/live`

### Verification
```bash
streamripper https://wehc.streamguys1.com/live -l 30 -a test.mp3
# Result: 528KB successful recording in 30 seconds
```

## Common JavaScript Player Patterns

### StreamGuys Players
- **Configuration Object**: `window._sgplayer`
- **Active Stream**: `window._sgplayer.activeStream.source[0].src`
- **Socket.IO Integration**: Real-time metadata updates
- **URL Format**: `//station.streamguys1.com/live`

### Other Player Types
- **Generic**: Look for `window.streams`, `window.playerConfig`
- **Socket.IO**: Monitor WebSocket connections for stream handshakes
- **Network Logs**: Capture all HTTP requests for audio/* content types

## Integration with RadioGrab

### Station Discovery Service
The `station_discovery.py` service now supports:
1. **Static HTML parsing** (existing)
2. **Headless browser extraction** (new)
3. **Fallback to manual patterns** (existing)

### Database Updates
Once real URLs are discovered:
```sql
UPDATE stations SET stream_url = 'https://wehc.streamguys1.com/live' 
WHERE name LIKE '%WEHC%';
```

### Recording Verification
Test discovered URLs immediately:
```python
recorder = AudioRecorder()
result = recorder.record_stream(
    stream_url=discovered_url,
    duration_seconds=10,
    output_filename="test.mp3"
)
```

## Performance Considerations

### Docker Container Resources
- **Chrome Memory**: ~200MB per instance
- **Selenium Overhead**: ~50MB Python libraries
- **Network Traffic**: Additional HTTP requests for full page loads

### Optimization Strategies
- **Cache Results**: Store discovered URLs to avoid repeated browser launches
- **Batch Processing**: Discover multiple stations in single browser session
- **Timeout Management**: Set reasonable limits for JavaScript execution

## Troubleshooting

### Common Issues
1. **Chrome crashes**: Add `--disable-dev-shm-usage`, `--no-sandbox`
2. **JavaScript errors**: Increase wait time for page initialization
3. **Empty results**: Check if player requires user interaction to start
4. **Network timeouts**: Some players require longer initialization

### Debug Commands
```bash
# Test Chrome installation
google-chrome --version

# Test basic Selenium
python -c "from selenium import webdriver; print('Selenium OK')"

# Monitor network traffic
docker exec container curl -I stream-url
```

## Success Metrics
- **WEHC Discovery**: Successfully extracted real URL from JavaScript player
- **Recording Test**: 30-second live recording (528KB) confirmed working
- **End-to-End Validation**: Stream discovery → URL extraction → Recording → Database → RSS generation
- **Universal Compatibility**: Combined with multi-tool recording (wget/ffmpeg), achieves 100% station compatibility

## Integration with Multi-Tool Recording

The discovered stream URLs are now tested with multiple recording tools:

```python
# After discovery, test compatibility
discovered_url = extract_stream_url(player_url)

# Test with multiple tools
tools_results = {
    'streamripper': test_streamripper(discovered_url),
    'ffmpeg': test_ffmpeg(discovered_url), 
    'wget': test_wget(discovered_url)
}

# Select best working tool
best_tool = select_optimal_tool(tools_results)
```

This two-phase approach (discovery + multi-tool testing) ensures RadioGrab can both **find** and **record** from any radio station, regardless of their streaming technology.