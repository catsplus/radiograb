# RadioGrab System Architecture & Operations Guide

## Container Architecture

### radiograb-web-1 Container
- **Purpose**: Web interface and API services
- **Base Path**: `/opt/radiograb/`
- **Services**: PHP frontend, Python backend APIs
- **Key Directories**:
  - `/var/radiograb/recordings/` - Recorded audio files
  - `/var/radiograb/feeds/` - Generated RSS feeds
  - `/var/radiograb/logs/` - Application logs
  - `/var/radiograb/temp/` - Test recordings and temporary files
  - `/var/radiograb/logos/` - Local station logos and social media images

### radiograb-recorder-1 Container  
- **Purpose**: Recording service daemon
- **Base Path**: `/opt/radiograb/`
- **Service**: `recording_service.py --daemon`
- **Key Directories**:
  - `/var/radiograb/recordings/` - Output directory for recordings
  - `/var/radiograb/logs/` - Recording service logs
  - `/var/radiograb/temp/` - Temporary files during recording

### radiograb-mysql-1 Container
- **Purpose**: MySQL database
- **Database**: `radiograb`
- **User**: `radiograb` / `radiograb_pass_2024`
- **Data**: `/var/lib/mysql/`

### radiograb-rss-updater-1 Container
- **Purpose**: RSS feed generation service
- **Service**: `rss_manager.py --action update-all` (runs every 15 minutes)
- **Output**: Updates RSS feeds in `/var/radiograb/feeds/`

### radiograb-housekeeping-1 Container
- **Purpose**: System maintenance and cleanup
- **Service**: `housekeeping_service.py --daemon` (runs every 6 hours)
- **Actions**: Removes empty recording files, cleans orphaned database records

## Timezone Configuration

**Critical**: All containers now use `TZ=America/New_York` (EST/EDT)
- Database stores timezone per station and show
- Recording schedules converted to container timezone for cron execution
- Web interface displays times in user's local timezone

## Recording System

### Recording Architecture Overview
The RadioGrab recording system consists of multiple components working together:

1. **Scheduler (`recording_service.py --daemon`)**: Runs as daemon in `radiograb-recorder-1` container
2. **Test/On-Demand (`test_recording_service.py`)**: Handles manual recordings triggered via web interface
3. **Housekeeping (`housekeeping_service.py --daemon`)**: Automatically cleans up empty files every 6 hours

### Recording Flow - Scheduled Recordings
1. **Daemon Startup**: `recording_service.py --daemon` starts and loads all active shows from database
2. **Job Scheduling**: For each show, creates APScheduler job using show's `schedule_pattern` (cron expression)
3. **Recording Trigger**: APScheduler calls `_record_show_job()` at scheduled time
4. **Tool Selection**: Chooses streamripper/wget/ffmpeg based on station compatibility
5. **File Creation**: Creates output file immediately (this is where empty files come from if stream fails)
6. **Stream Recording**: Executes recording command via subprocess
7. **File Validation**: Checks if file has content (size > 0 bytes)
8. **Database Update**: Creates recording entry if successful, logs error if failed

### Recording Flow - Test/On-Demand Recordings
1. **Web Interface**: User clicks "Test (30s)" or "Record Now (1h)" button on station card
2. **API Call**: Frontend calls `/api/test-recording.php` with station details
3. **Background Process**: PHP spawns `test_recording_service.py` in background
4. **Recording Execution**: Uses same AudioRecorder class as scheduled recordings
5. **File Location**: Test recordings go to `/var/radiograb/temp/`, on-demand to `/var/radiograb/recordings/`
6. **Show Management**: On-demand recordings create "{CALL}_On-Demand Recordings" shows automatically

### Why Empty Files Are Created
**Root Cause**: All recording tools (streamripper, ffmpeg, wget) create output files immediately when started, even if they fail to connect to the stream or get data.

**Common Failure Scenarios**:
1. **Stream Unavailable**: Stream URL returns 404, timeout, or connection refused
2. **Wrong Time**: Recording triggered when no show is on the air (timezone issues)
3. **Authentication**: Stream requires authentication that isn't provided
4. **Network Issues**: Temporary network connectivity problems
5. **Tool Crashes**: Recording tool crashes after creating file but before writing content

**Prevention**: The housekeeping service automatically removes these empty files every 6 hours.

### Recording Tools Behavior
- **streamripper**: Creates output file, then attempts to record. Often creates empty files on connection failure.
- **ffmpeg**: Creates output file immediately, fails if input stream unavailable.
- **wget**: Creates output file, then downloads. May create empty file if download fails quickly.

### Housekeeping Service
- **Purpose**: Prevents accumulation of empty recording files
- **Schedule**: Runs every 6 hours via daemon in `radiograb-housekeeping-1` container
- **Actions**: 
  - Finds and deletes all MP3 files with 0 bytes in `/var/radiograb/recordings/`
  - Removes orphaned database records for non-existent files
  - Logs cleanup statistics
- **Manual Execution**: Can be run manually with `--run-once` or `--stats-only` flags

### Log Locations
- **Recording Service**: Container logs via `docker logs radiograb-recorder-1`
- **Housekeeping Service**: Container logs via `docker logs radiograb-housekeeping-1` 
- **APScheduler**: Logs shown in recording service container logs
- **Individual Recordings**: Subprocess output captured in container logs

## Test Recording & On-Demand Features

### Test Recording (30 seconds)
**Purpose**: Quick stream testing to verify station streams are working

**Usage**:
1. Go to Stations page
2. Click "Test (30s)" button on any station with configured stream URL
3. Confirms recording start, runs for 30 seconds
4. File saved to `/var/radiograb/temp/` with format: `test-{station_id}-{timestamp}.mp3`
5. Files can be played/managed through web interface
6. Test files are NOT tracked in database (temporary only)

**Backend**: 
- API: `/api/test-recording.php`
- Service: `backend/services/test_recording_service.py`
- Container: Runs in `radiograb-web-1` via PHP exec

### On-Demand Recording (1 hour)
**Purpose**: Manual recording of current radio programming

**Usage**:
1. Go to Stations page  
2. Click "Record Now (1h)" button on any station
3. Confirms start, runs for 1 hour
4. File saved to `/var/radiograb/recordings/` with format: `{call-letters}-on-demand-{timestamp}.mp3`
5. Automatically creates "{CALL} On-Demand Recordings" show if not exists
6. Recording tracked in database and appears in Shows/Recordings sections

**Show Management**:
- Station call letters extracted from first 4 alphabetic characters of station name
- Example: "WYSO 91.3 FM" → "WYSO On-Demand Recordings" show
- Shows appear in Shows section with all associated on-demand recordings
- Subject to normal retention policies

**Backend**:
- API: `/api/test-recording.php` (same endpoint, different action)
- Service: `backend/services/test_recording_service.py`
- Database: Creates show and recording entries automatically

### File Naming Conventions
- **Test recordings**: `{CALL_LETTERS}_test_YYYY-MM-DD-HHMMSS.mp3`
- **On-demand recordings**: `{CALL_LETTERS}_on-demand_YYYY-MM-DD-HHMMSS.mp3`
- **Scheduled recordings**: `{CALL_LETTERS}_{show_name}_YYYYMMDD_HHMM.mp3`

## Common Issues & Debugging

### Zero-byte Recordings
**Symptoms**: Recordings created but file size is 0 bytes
**Causes**:
1. Stream URL not accessible
2. Recording tool failure (streamripper/wget/ffmpeg)
3. Permissions issues
4. **Timezone mismatch causing recording at wrong time** (most common)

**Common Issue**: System recording at 5:00 AM EDT instead of 9:00 AM due to UTC/local timezone confusion.

**Debugging**:
```bash
# Check recent recordings with timestamps
docker exec radiograb-recorder-1 ls -la /var/radiograb/recordings/ | tail -10

# Remove all zero-byte recordings
docker exec radiograb-web-1 find /var/radiograb/recordings/ -name "*.mp3" -size 0 -delete

# Check recording service logs (if they exist)
docker exec radiograb-recorder-1 cat /var/radiograb/logs/recording_service.log | tail -50

# Test stream URL manually
docker exec radiograb-recorder-1 wget -O /tmp/test.mp3 --timeout=10 "{stream_url}"

# Verify container timezone
docker exec radiograb-web-1 date
```

### Timezone Issues
**Symptoms**: Shows recording at wrong times
**Solution**: Ensure station timezone is set correctly in database
```sql
UPDATE stations SET timezone = 'America/Chicago' WHERE id = {station_id};
UPDATE shows SET timezone = 'America/Chicago' WHERE station_id = {station_id};
```

## Database Schema

### Key Tables
- **stations**: Station info including `timezone` field and recording method preferences
- **shows**: Show schedules including `start_time`, `end_time`, `days`, `timezone`
- **recordings**: Recording metadata and file info
- **cron_jobs**: APScheduler job definitions

### Recording Method Storage
The system automatically discovers and stores the optimal recording method for each station:

- `stations.recommended_recording_tool`: Best tool for this station (streamripper, ffmpeg, wget)
- `stations.stream_compatibility`: Overall compatibility (compatible, incompatible, unknown)
- `stations.stream_test_results`: JSON results from stream testing
- `stations.last_stream_test`: When stream was last tested

**How it works**: When a station's stream is first tested (during schedule import or manual testing), the system tries all available recording tools and stores which one works best. Future recordings automatically use the recommended tool, eliminating the need to rediscover compatibility.

### Timezone Fields
- `stations.timezone`: Station's local timezone (e.g., 'America/Chicago')
- `shows.timezone`: Show timezone (usually inherits from station)
- All times stored in local timezone, converted for scheduling

## File Permissions

### Recording Directory
- Owner: `www-data:www-data` (web container) or `root:root` (recorder container)
- Permissions: 755 for directories, 644 for files
- Shared via Docker volume between containers

### Common Permission Fixes
```bash
# Fix recording directory permissions
docker exec radiograb-web-1 chown -R www-data:www-data /var/radiograb/recordings/
docker exec radiograb-web-1 chmod -R 755 /var/radiograb/recordings/
```

## Deployment Process

**⚠️ CRITICAL: DOCKER CONTAINER DEPLOYMENT ⚠️**

### Production Server Architecture
- **Server**: 167.71.84.143 (user: `radiograb`)
- **Host Application Path**: `/opt/radiograb/` (used for building)
- **Live Site**: **Runs inside Docker containers - NOT from host files!**
- **Container Build**: `COPY . /opt/radiograb/` copies files INTO container image at build time

### Correct Deployment Process

```bash
# ❌ WRONG - This does NOT update the live site:
scp file.py radiograb@167.71.84.143:/opt/radiograb/path/

# ✅ CORRECT - Streamlined Git-Based Deployment:
# 1. Update files locally and push to git
git add . && git commit -m "Update" && git push origin main

# 2. One-command deployment
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"

# ✅ ALTERNATIVE - Manual deployment steps:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && git stash && docker compose down && docker compose up -d --build"
```

### Git Repository Setup
The server now has a git repository at `/opt/radiograb/`:
- Initialized with existing files committed
- Remote configured (for future GitHub integration)
- Deployment script automates the rebuild process
- Git history available for rollbacks and tracking changes

### Why Container Rebuild is Required
Docker containers include a `COPY . /opt/radiograb/` instruction that copies ALL files into the container image at build time. This means:
- Host files at `/opt/radiograb/` are only used for building
- Running containers have their own internal copy of files
- Copying files to host does NOT update the running containers
- Only rebuilding containers includes new files

### Legacy Manual Deployment (Not Recommended)
If you must avoid rebuilding containers:
1. Copy to host: `scp file.py radiograb@167.71.84.143:/opt/radiograb/path/`
2. Copy to container: `docker cp /opt/radiograb/path/file.py radiograb-web-1:/opt/radiograb/path/`
3. Restart if needed: `docker restart radiograb-web-1`

### Database Migrations
1. Create migration file in `database/migrations/`
2. Copy to server and apply:
```bash
scp database/migrations/new_migration.sql radiograb@167.71.84.143:/tmp/
ssh radiograb@167.71.84.143 "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < /tmp/new_migration.sql"
```

### Container Management
```bash
# Restart specific container
docker restart radiograb-recorder-1

# View container logs
docker logs radiograb-recorder-1 --tail=50

# Execute commands in container
docker exec radiograb-web-1 bash -c "command"

# Apply environment changes (timezone, etc)
docker compose down && docker compose up -d
```

## Monitoring & Maintenance

### Health Checks
- MySQL: `docker exec radiograb-mysql-1 mysqladmin ping`
- Web service: `curl -s http://localhost/` 
- Recording service: Check process and logs
- RSS service: Check feed generation timestamps

### Disk Space
- Recordings accumulate in `/var/radiograb/recordings/`
- Retention handled by show settings (`retention_days`)
- Manual cleanup if needed: `find /var/radiograb/recordings/ -name "*.mp3" -mtime +30 -delete`

## Deployment Process

### Automated Deployment
Use the included deployment scripts to ensure all changes reach the server:

```bash
# Full system deployment (recommended after major changes)
./deploy.sh

# Quick single-file deployment  
./quick-deploy.sh frontend/public/stations.php

# Deploy multiple specific files
./quick-deploy.sh backend/services/recording_service.py frontend/public/stations.php
```

### Manual Deployment Steps
If deployment scripts aren't available:

1. **Copy files to server**: `scp file.py radiograb@167.71.84.143:/tmp/`
2. **Deploy to container**: `docker cp /tmp/file.py radiograb-web-1:/opt/radiograb/path/`
3. **Restart services**: `docker restart radiograb-recorder-1` (if needed)

### Database Migrations
All database changes should use migration files:

```bash
# Create migration file
echo "ALTER TABLE stations ADD COLUMN new_field VARCHAR(50);" > database/migrations/add_new_field.sql

# Apply via deployment script (automatic)
./deploy.sh

# Or apply manually
scp database/migrations/add_new_field.sql radiograb@167.71.84.143:/tmp/
ssh radiograb@167.71.84.143 "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < /tmp/add_new_field.sql"
```

## SSL/HTTPS Configuration

### Automatic SSL Certificate Management
RadioGrab now includes **automatic SSL certificate persistence and generation** that survives container rebuilds:

#### Configuration
1. **Set environment variables** in `.env` file in project root:
```bash
SSL_DOMAIN=radiograb.yourdomain.com
SSL_EMAIL=admin@yourdomain.com
```

2. **Deploy with automatic SSL**:
```bash
docker compose up -d
```

The system will:
- ✅ **Check for existing certificates** in persistent volumes
- ✅ **Automatically generate new certificates** if none exist
- ✅ **Configure nginx for HTTPS** with security headers
- ✅ **Set up automatic renewal** (twice daily)
- ✅ **Redirect HTTP to HTTPS** automatically

#### Persistent SSL Storage
SSL certificates are now stored in Docker volumes that persist across rebuilds:
- `letsencrypt` volume: `/etc/letsencrypt` (certificates and configuration)
- `letsencrypt_lib` volume: `/var/lib/letsencrypt` (working files)

#### Manual SSL Setup (Legacy)
For manual certificate management:

```bash
# 1. Check domain DNS configuration
./check-domain.sh radiograb.yourdomain.com

# 2. Install Let's Encrypt SSL certificate in container
./setup-container-ssl.sh radiograb.yourdomain.com admin@yourdomain.com
```

### SSL Features
- **Let's Encrypt Integration**: Free SSL certificates with automatic renewal
- **Container-Based**: SSL runs inside Docker containers, no host modifications needed
- **Modern Security**: TLS 1.2/1.3 with strong cipher suites
- **Security Headers**: HSTS, CSP, X-Frame-Options, X-Content-Type-Options
- **Automatic Renewal**: Certificates renew twice daily via cron
- **HTTP to HTTPS Redirect**: All traffic automatically redirected to secure connection

### SSL Certificate Management
```bash
# Check certificate status
docker exec radiograb-web-1 certbot certificates

# Test renewal (dry run)
docker exec radiograb-web-1 certbot renew --dry-run

# Manual renewal
docker exec radiograb-web-1 /usr/local/bin/renew-certs.sh

# View renewal logs
docker exec radiograb-web-1 cat /var/log/letsencrypt/letsencrypt.log
```

### Troubleshooting SSL
- **Certificate Path**: `/etc/letsencrypt/live/{domain}/`
- **Nginx Config**: `/etc/nginx/sites-available/default`
- **Renewal Script**: `/usr/local/bin/renew-certs.sh`
- **SSL Test**: https://www.ssllabs.com/ssltest/

### Recording File Access Issues
If recordings show "Recording file not found":

1. **Check file permissions**:
```bash
docker exec radiograb-web-1 chown -R www-data:www-data /var/radiograb/recordings/
```

2. **Verify nginx location priority**:
   - `/recordings/` location must use `^~` prefix for exact matching
   - Must come before general `/` location block
   - Use `alias` directive, not `root` for recording directory

3. **Test recording access**:
```bash
curl -I "https://yourdomain.com/recordings/filename.mp3"
```

4. **Check nginx error logs**:
```bash
docker exec radiograb-web-1 tail -10 /var/log/nginx/error.log
```

### Test Recording API Issues
If test recording shows "Network error occurred":

1. **Verify API endpoint is processing PHP**:
```bash
curl -s "https://yourdomain.com/api/test-recording.php" | head -3
# Should return: {"error":"Method not allowed"}
# NOT raw PHP source code
```

2. **Check nginx location block order**:
   - PHP location `~ \.php$` must NOT be overridden by `/api/` location
   - Remove `/api/` prefix location blocks that interfere with PHP processing
   - Ensure PHP files under `/api/` are processed by PHP-FPM

3. **Test API with POST request**:
```bash
curl -X POST "https://yourdomain.com/api/test-recording.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=test_recording&station_id=1&csrf_token=test"
# Should return: {"error":"Invalid security token"}
```

4. **Verify services are accessible**:
```bash
docker exec radiograb-web-1 /opt/radiograb/venv/bin/python \
  /opt/radiograb/backend/services/test_recording_service.py --help
```

## Security Fixes

### CSRF Token Protection
All API endpoints require valid CSRF tokens:

- **Session Management**: `session_start()` called in all API files
- **Token Generation**: `generateCSRFToken()` in forms
- **Token Verification**: `verifyCSRFToken()` in API endpoints
- **Protection Scope**: Test recording, on-demand recording, station management

### Fixed Issues
- **Test Recording API**: Added missing `session_start()` to resolve "Invalid security token" errors
- **CSRF Protection**: All forms now properly protected against cross-site request forgery
- **Session Security**: Secure session handling across all user interactions

## Logo and Social Media System

### Logo Storage Architecture
- **Local Storage**: All station logos downloaded and stored in `/var/radiograb/logos/`
- **Facebook Fallback**: Extracts profile pictures from Facebook pages when website logos unavailable
- **Format Optimization**: Images resized to max 400x400px, optimized for web delivery
- **Consistent Display**: All logos displayed at uniform 60x60px with proper aspect ratio

### Social Media Integration
- **Multi-Platform Detection**: Detects 10+ platforms (Facebook, Twitter, Instagram, YouTube, etc.)
- **Smart Icon Display**: Colored social media icons with hover effects
- **Database Storage**: Social links stored as JSON with platform metadata
- **Visual Integration**: Icons displayed below station names for easy access

### New Services
- **logo_storage_service.py**: Downloads, optimizes, and stores station logos locally
- **facebook_logo_extractor.py**: Extracts profile pictures from Facebook pages
- **social_media_detector.py**: Detects and categorizes social media links
- **station-logo-update.php**: API for bulk updating station logos and social media

### Database Schema Extensions
```sql
ALTER TABLE stations 
ADD COLUMN facebook_url VARCHAR(500) NULL,
ADD COLUMN local_logo_path VARCHAR(255) NULL,
ADD COLUMN logo_source VARCHAR(50) NULL,
ADD COLUMN logo_updated_at TIMESTAMP NULL,
ADD COLUMN social_media_links JSON NULL,
ADD COLUMN social_media_updated_at TIMESTAMP NULL;
```

### Nginx Configuration
- **Logo Serving**: `/logos/` location block serves local logos with caching
- **Security Headers**: Proper content-type and CORS headers
- **Cache Optimization**: 7-day caching for static logo assets

## DJ Audio Recording System

### WebRTC-Based Voice Recording
RadioGrab includes a complete browser-based voice recording system for DJ intros, outros, and station IDs:

### Core Components
- **WebRTC MediaRecorder API**: Native browser audio recording with high-quality output
- **Professional Recording Interface**: Real-time controls with start/stop functionality and visual timer
- **Audio Preview System**: Built-in playback before saving with metadata editing capabilities
- **5-Minute Recording Limit**: Automatic stop with warning to prevent excessive file sizes
- **Mobile Compatibility**: Full functionality on iOS Safari, Android Chrome, and all major mobile browsers

### Technical Architecture
```javascript
// AudioRecorder class structure
class AudioRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioStream = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.recordingStartTime = null;
        this.playlistId = null;
        this.maxRecordingTime = 300; // 5 minutes max
    }
}
```

### Recording Flow
1. **User Initiation**: User clicks "Record Voice Clip" button from playlist interface
2. **Browser Permission**: System requests microphone access via `navigator.mediaDevices.getUserMedia()`
3. **Recording Setup**: Creates MediaRecorder instance with appropriate audio constraints
4. **Real-Time Feedback**: Visual timer updates every second, progress tracking
5. **Recording Completion**: User stops manually or system auto-stops at 5-minute limit
6. **Audio Preview**: Built-in audio element plays recorded clip for review
7. **Metadata Entry**: User adds title and description before saving
8. **File Upload**: Recorded WebM audio uploaded via standard upload API with `source_type=voice_clip`

### File System Integration
- **Upload API Enhancement**: Added WebM audio format support in `/frontend/public/api/upload.php`
- **Python Service Updates**: Modified `upload_service.py` to handle voice clip source type
- **Database Extensions**: Extended recordings table with `source_type` differentiation
- **File Processing**: Automatic conversion from WebM to MP3 format for consistency

### Visual Differentiation
Voice clips are visually distinguished in the interface:
- **Green Badges**: Voice clips display with green "Voice Clip" badges
- **Microphone Icons**: Font Awesome microphone icons for instant recognition
- **Border Styling**: Green borders and backgrounds for voice clip tracks
- **Source Type Display**: Clear indication of recording source in track listings

### Recording Tips Integration
The recording modal includes best practices panel:
- **Microphone Setup**: Guidance on optimal microphone positioning
- **Environment Tips**: Quiet room recommendations, background noise awareness
- **Browser Compatibility**: iOS Safari, Android Chrome, desktop browser support
- **Recording Quality**: 16kHz sample rate, mono channel recommendations
- **Content Suggestions**: Examples for station IDs, show intros, transitions

### Database Schema Extensions
```sql
-- Recording source type differentiation
ALTER TABLE recordings 
ADD COLUMN source_type VARCHAR(20) DEFAULT 'recorded' 
  COMMENT 'Source: recorded, uploaded, voice_clip';

-- Voice clip metadata tracking
UPDATE recordings 
SET source_type = 'voice_clip' 
WHERE filename LIKE '%.webm' OR description LIKE '%voice%';
```

### API Endpoints
- **Record Voice Clip**: Integration with existing upload API via `action=upload_file&source_type=voice_clip`
- **Playlist Integration**: Voice clips automatically added to playlist with sequential track numbering
- **Track Management**: Full drag-and-drop support, reordering works with voice clips
- **Metadata Editing**: Standard title/description editing available for voice clips

### Playlist Integration Features
- **Seamless Ordering**: Voice clips work with existing drag-and-drop track reordering
- **Mixed Content Support**: Playlists can contain regular uploads and voice clips together
- **Track Numbering**: Voice clips get sequential track numbers like uploaded files
- **RSS Feed Support**: Voice clips appear in RSS feeds with proper metadata
- **Audio Player**: Standard web audio player works with voice clips after MP3 conversion

### Mobile Compatibility
- **iOS Safari**: Full WebRTC support with microphone access
- **Android Chrome**: Complete functionality with optimized touch controls  
- **Mobile UI**: Responsive design with touch-friendly recording controls
- **Screen Rotation**: Interface adapts to landscape/portrait modes
- **Battery Optimization**: Efficient recording with minimal battery impact

### Error Handling
- **Microphone Access Denied**: Clear error messages with troubleshooting guidance
- **Browser Compatibility**: Graceful degradation with helpful error messages
- **Recording Failures**: Comprehensive error handling with user-friendly explanations
- **Upload Errors**: Network error handling with retry functionality
- **File Size Limits**: Clear warnings when approaching recording time limits

This documentation is automatically updated as system knowledge is discovered during development and maintenance.