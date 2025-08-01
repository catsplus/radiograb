# RadioGrab Troubleshooting Guide

## Common Python Module Errors

### "ModuleNotFoundError: No module named 'X'"

**Problem**: Python script fails with missing module error
**Cause**: Script is being executed with system Python instead of virtual environment
**Solution**: Always use the virtual environment path

```bash
# ❌ WRONG - will fail with module errors
python3 /opt/radiograb/backend/services/script.py
docker exec container python3 script.py

# ✅ CORRECT - uses virtual environment
docker exec container /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/script.py
docker exec container bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/script.py"
```

### PHP Calling Python Scripts

**Problem**: PHP calls to Python scripts fail with module errors
**Solution**: Update PHP commands to use virtual environment

```php
// ❌ WRONG
$command = "python3 $script_path $args 2>&1";

// ✅ CORRECT
$command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $script_path $args 2>&1";
```

### Installing New Python Packages

```bash
# Install in the correct virtual environment
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/pip install package_name

# Verify installation
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/pip list | grep package_name

# Example: Install requests (already done on 2025-07-23)
docker exec radiograb-web-1 /opt/radiograb/venv/bin/pip install requests
```

**⚠️ Important**: Document any manually installed packages in CONTAINER_SETUP.md for future container rebuilds.

## RSS Feed Issues

### "Feed regeneration completed with warnings"

**Common Causes**:
1. Python module not found (see above)
2. Database connection issues
3. Missing recordings directory
4. File permission problems

**Debugging Steps**:
1. Check RSS manager logs: `docker logs radiograb-rss-updater-1 --tail 50`
2. Test RSS manager manually: `docker exec radiograb-web-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/rss_manager.py --action update-all`
3. Check file permissions: `docker exec radiograb-web-1 ls -la /var/radiograb/feeds/`

## Recording Issues

### Empty Recording Files

**Problem**: Recordings are 0 bytes
**Common Causes**:
1. Wrong recording tool for stream type
2. StreamTheWorld redirect URLs
3. Stream authentication issues

**Solution**: Check station's recommended recording tool in database

### Recording Service Not Starting

```bash
# Check recording service logs
docker logs radiograb-recorder-1 --tail 50

# Test recording service manually
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/recording_service.py --test
```

## Database Connection Issues

### "Can't connect to MySQL server"

**Check container connectivity**:
```bash
# Verify MySQL container is running
docker ps | grep mysql

# Test database connection from web container
docker exec radiograb-web-1 mysql -h mysql -u radiograb -p radiograb
```

### Database Schema Mismatches

**Problem**: "'Station' object has no attribute 'field_name'"
**Solution**: Update model classes to match database schema

## Web Interface Issues

### CSS/Assets Not Loading

**Problem**: Website shows unstyled content
**Solution**: Check asset paths and nginx configuration

```bash
# Check if CSS file exists
docker exec radiograb-web-1 ls -la /opt/radiograb/frontend/public/assets/css/radiograb.css

# Verify nginx is serving assets
curl -I http://radiograb.svaha.com/assets/css/radiograb.css
```

### Audio Files Not Playing

**Problem**: Play buttons don't work
**Cause**: Nginx location conflicts
**Solution**: Check nginx.conf for proper /recordings/ handling

## Container Issues

### Container Won't Start

```bash
# Check container logs for errors
docker logs radiograb-container-name

# Check docker-compose configuration
docker compose config

# Rebuild containers if needed
docker compose down && docker compose up --build -d
```

### File Deployment Issues

```bash
# Verify file copied to container
docker exec container ls -la /opt/radiograb/path/to/file

# Check file permissions
docker exec container ls -la /opt/radiograb/

# Restart services after file changes
docker compose restart service-name
```

## Debugging Commands

### Quick Health Check
```bash
# Check all container status
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Check recent logs from all services
docker compose logs --tail 20

# Test database connectivity
docker exec radiograb-web-1 /opt/radiograb/venv/bin/python -c "from backend.config.database import DatabaseManager; db = DatabaseManager(); print('DB Connected:', db.test_connection())"
```

### Python Environment Verification
```bash
# Check Python version and path
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python --version

# List installed packages
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/pip list

# Test imports
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python -c "import requests, sqlalchemy, pymysql; print('All modules imported successfully')"
```

### File System Check
```bash
# Check volume mounts
docker exec radiograb-recorder-1 ls -la /var/radiograb/

# Check application files
docker exec radiograb-web-1 ls -la /opt/radiograb/backend/services/

# Check recordings directory
docker exec radiograb-recorder-1 find /var/radiograb/recordings/ -name "*.mp3" | head -10
```

## DJ Audio Recording Issues

### "Microphone access denied" or "Permission denied"

**Problem**: Browser cannot access microphone for voice recording
**Causes**:
1. User denied microphone permission
2. Site not using HTTPS (required for WebRTC)
3. Browser doesn't support WebRTC MediaRecorder API

**Solutions**:
```javascript
// Check browser support
if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    console.error('WebRTC not supported in this browser');
}

// Request microphone permission explicitly
navigator.mediaDevices.getUserMedia({audio: true})
    .then(stream => console.log('Microphone access granted'))
    .catch(err => console.error('Microphone access denied:', err));
```

**Browser-specific fixes**:
- **Chrome**: Click lock icon in address bar → Allow microphone
- **Firefox**: Click shield icon → Allow microphone access
- **Safari**: Preferences → Websites → Microphone → Allow for your domain
- **Mobile Safari**: Settings → Safari → Camera & Microphone Access

### "Recording failed" or "MediaRecorder error"

**Problem**: Recording starts but fails during capture
**Common Causes**:
1. Browser doesn't support WebM audio format
2. Memory constraints on mobile devices
3. Background tab loses audio stream focus

**Debugging Steps**:
```bash
# Check upload API logs
docker exec radiograb-web-1 tail -20 /var/log/php8.1-fpm.log

# Check for WebM files in temp directory
docker exec radiograb-web-1 ls -la /var/radiograb/temp/ | grep webm

# Verify upload service can handle WebM
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/upload_service.py --help
```

**Solutions**:
- Ensure HTTPS is properly configured (required for WebRTC)
- Test recording in different browsers
- Check browser console for JavaScript errors
- Verify microphone is not being used by other applications

### "Upload failed: Unsupported audio format"

**Problem**: Voice clip uploads fail with format errors
**Cause**: WebM audio format not recognized by upload API

**Solution**: Verify WebM support is enabled in upload.php:
```php
$allowed_types = [
    'audio/mpeg', 'audio/mp3',
    'audio/wav', 'audio/wave',
    'audio/mp4', 'audio/m4a',
    'audio/aac',
    'audio/ogg',
    'audio/flac',
    'audio/webm'  // This line must be present
];
```

### Voice clips not appearing in playlist

**Problem**: Recording completes but doesn't show in playlist
**Debugging**:
```bash
# Check if recording was saved to database
docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e "SELECT id, title, source_type, filename FROM recordings WHERE source_type = 'voice_clip' ORDER BY recorded_at DESC LIMIT 5;"

# Check playlist-tracks API response
curl -s "https://radiograb.svaha.com/api/playlist-tracks.php?show_id=YOUR_PLAYLIST_ID" | jq .
```

**Common fixes**:
- Verify `source_type = 'voice_clip'` is being set in upload API
- Check playlist ID is correct in recording modal
- Ensure playlist-tracks.php includes voice_clip in source type filter

### Mobile recording issues

**Problem**: Voice recording doesn't work on mobile devices
**Mobile-specific considerations**:
- iOS Safari requires user gesture to start recording
- Android Chrome may have different microphone permissions
- Mobile browsers may have stricter WebRTC limitations

**Mobile debugging**:
```javascript
// Test mobile WebRTC support
console.log('Mobile detection:', /iPhone|iPad|iPod|Android/i.test(navigator.userAgent));
console.log('MediaRecorder support:', typeof MediaRecorder !== 'undefined');
console.log('getUserMedia support:', !!navigator.mediaDevices?.getUserMedia);
```

**Solutions**:
- Ensure recording button requires user interaction (no auto-start)
- Test on actual mobile devices, not desktop browser mobile simulation
- Provide clear instructions for mobile microphone permissions

## Prevention Tips

1. **Always use virtual environment paths** when adding new Python script calls
2. **Test Python scripts manually** before integrating with PHP
3. **Check container logs** regularly for early warning signs
4. **Use proper file deployment process** (scp → docker cp → restart)
5. **Document new dependencies** in CONTAINER_SETUP.md when adding packages
6. **Verify database schema matches models** when adding new fields
7. **Test changes on live server** after deployment
8. **Test voice recording on multiple browsers and devices** before deploying
9. **Verify HTTPS is working** (required for WebRTC microphone access)
10. **Check WebM audio format support** in upload APIs when adding voice features