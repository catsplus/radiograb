# CLAUDE.md - Complete RadioGrab System Reference

**⚠️ CRITICAL: READ THIS FIRST - COMPLETE SYSTEM ARCHITECTURE ⚠️**

## 🚨 PRODUCTION SERVER DETAILS 🚨

- **Domain**: https://radiograb.svaha.com
- **Server IP**: 167.71.84.143
- **SSH User**: `radiograb` (SSH key authentication)
- **Project Directory**: `/opt/radiograb/` (owned by `radiograb:radiograb`)
- **Platform**: Ubuntu 22.04 on DigitalOcean droplet

## 🚨 CRITICAL DEPLOYMENT ARCHITECTURE 🚨

### Docker Container Architecture
**THE ENTIRE APPLICATION RUNS IN DOCKER CONTAINERS - NOT ON HOST FILES!**

```yaml
# 5-Container Architecture
radiograb-web-1:        # Web interface + API (nginx + PHP-FPM + Python)
radiograb-recorder-1:   # Recording daemon service
radiograb-mysql-1:      # Database (MySQL 8.0)
radiograb-rss-updater-1: # RSS feed generation (every 15 min)
radiograb-housekeeping-1: # Cleanup service (every 6 hours)
```

### File Architecture - CRITICAL UNDERSTANDING
```bash
# Files are BAKED INTO containers at build time via:
COPY . /opt/radiograb/

# Host filesystem: /opt/radiograb/ (used for BUILDING containers)
# Container filesystem: /opt/radiograb/ (WHERE THE APPLICATION ACTUALLY RUNS)
# 🚨 CRITICAL: Host file changes do NOT affect running containers
# 🚨 CRITICAL: Must REBUILD containers to deploy file changes
```

## ✅ STREAMLINED DEPLOYMENT WORKFLOW

### ✅ PUBLIC REPOSITORY: Direct GitHub Deployment
**THE PRODUCTION SERVER CAN NOW PULL FROM THE PUBLIC RADIOGRAB REPOSITORY.**

The RadioGrab repository is now public, which simplifies deployment:

#### Benefits of Public Repository:
- No authentication required for `git pull`
- The `./deploy-from-git.sh` script can pull latest changes directly from GitHub
- Server repository stays synchronized with latest commits
- **CRITICAL**: Deployment workflow is now streamlined and reliable

#### Simple Deployment Process:
- Push changes to GitHub ✅
- Run `./deploy-from-git.sh` ✅ (pulls from GitHub automatically)
- Container rebuilds with latest code ✅
- Changes are deployed and working ✅

### ✅ PREFERRED: Git-Based Deployment
```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Deploy using the automated script
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"
```

### ✅ SIMPLE DEPLOYMENT WORKFLOW

**STREAMLINED PROCESS FOR PUBLIC REPOSITORY:**

```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Deploy using the automated script (pulls from GitHub automatically)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"

# 3. Script automatically:
#    - Pulls latest changes from GitHub
#    - Rebuilds containers with new code
#    - Restarts all services

# 4. Verify deployment worked
curl -I https://radiograb.svaha.com/
```

### ✅ DEPLOYMENT VERIFICATION CHECKLIST

**VERIFY your deployment worked:**
- [ ] Did you push changes to GitHub first?
- [ ] Did the deploy script show "Pulling from GitHub" (not "Using local repository")?
- [ ] Did containers rebuild successfully?
- [ ] Did you test your changes work on the live site?
- [ ] Check logs if something seems wrong: `docker logs radiograb-web-1 --tail 20`

### Alternative: Direct Server File Management
```bash
# 1. Edit files directly on server in /opt/radiograb/
ssh radiograb@167.71.84.143 "nano /opt/radiograb/path/to/file.php"

# 2. Rebuild containers (REQUIRED!)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"
```

### Emergency Hotfix Process
```bash
# 1. Edit files directly on server in /opt/radiograb/
# 2. Test changes work
# 3. Rebuild containers: docker compose down && docker compose up -d --build
# 4. Copy changes back to local and commit to avoid losing them
```

### 🔧 Future Fix: Git Authentication Setup
To enable proper git-based deployment, the server needs:
- SSH key setup for GitHub authentication, OR
- Personal Access Token configuration, OR
- Deploy key configuration for the repository

## 🎯 SYSTEM CAPABILITIES & FEATURES

### Core Functionality
- **TiVo for Radio**: Automatically record radio shows and generate podcast feeds
- **Station Discovery**: Extract streaming URLs, logos, schedules from website URLs
- **JavaScript-Aware Parsing**: Selenium WebDriver for dynamic calendars
- **Multi-Tool Recording**: streamripper/wget/ffmpeg with automatic tool selection
- **Test & On-Demand Recording**: 30-second tests + 1-hour manual recordings
- **RSS Feeds**: Individual show feeds + master feed (all shows combined)
- **Automatic Housekeeping**: Cleans empty files every 6 hours
- **Station Testing Tracking**: Last tested date, success/failure status on every recording
- **Automated Station Testing**: Periodic testing service to verify all stations

### Station Testing System
- **Automatic Test Tracking**: Every successful recording updates station's `last_tested` timestamp
- **Quality Validation**: File size and format verification on recording completion
- **Status Tracking**: `last_test_result` (success/failed/error) and `last_test_error` fields
- **Web Interface Display**: Last tested status with icons in stations page
- **Automated Testing Service**: `station_auto_test.py` for periodic station verification

### Recording Tools (All in radiograb-recorder-1 container)
- **streamripper** (`/usr/bin/streamripper`): Direct HTTP/MP3 streams
- **ffmpeg** (`/usr/bin/ffmpeg`): Authentication, modern protocols  
- **wget** (`/usr/bin/wget`): Redirect URLs (StreamTheWorld)

### Key Directories
```bash
# Inside Containers (where application runs):
/opt/radiograb/                    # Application root
/opt/radiograb/frontend/public/    # Web interface
/opt/radiograb/backend/services/   # Python services
/opt/radiograb/venv/               # Python virtual environment (CRITICAL!)
/var/radiograb/recordings/         # Recorded audio files
/var/radiograb/feeds/             # RSS feeds
/var/radiograb/temp/              # Test recordings
/var/radiograb/logs/              # Application logs
```

## 🔧 TECHNICAL REQUIREMENTS

### Python Execution (CRITICAL!)
```bash
# ❌ WRONG - will fail with module errors:
python3 script.py
docker exec radiograb-recorder-1 python3 script.py

# ✅ CORRECT - uses virtual environment:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/script.py

# ✅ CORRECT - with environment:
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/script.py"
```

### Supervisor Configuration (CRITICAL!)
**THE SUPERVISOR SERVICES REQUIRE SPECIFIC ENVIRONMENT VARIABLES TO FUNCTION:**

```bash
# ALL Python services in supervisor MUST include these environment variables:
environment=PATH="/opt/radiograb/venv/bin",PYTHONPATH="/opt/radiograb",DB_HOST="mysql",DB_PORT="3306",DB_USER="radiograb",DB_PASSWORD="radiograb_pass_2024",DB_NAME="radiograb",TZ="America/New_York"

# Critical Services:
# - radiograb-recorder: runs recording_service.py --daemon (NOT recorder.py!)
# - radiograb-rss: runs rss_manager.py --update-all every 15 minutes

# ❌ WRONG supervisor config (missing environment):
[program:radiograb-recorder]
command=/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon

# ✅ CORRECT supervisor config (with environment):
[program:radiograb-recorder]
command=/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon
directory=/opt/radiograb
user=www-data
environment=PATH="/opt/radiograb/venv/bin",PYTHONPATH="/opt/radiograb",DB_HOST="mysql",DB_PORT="3306",DB_USER="radiograb",DB_PASSWORD="radiograb_pass_2024",DB_NAME="radiograb",TZ="America/New_York"
```

### Database Connection
```bash
# Environment Variables in Containers:
DB_HOST=mysql
DB_PORT=3306
DB_USER=radiograb
DB_PASSWORD=radiograb_pass_2024
DB_NAME=radiograb

# Direct MySQL access:
ssh radiograb@167.71.84.143 "docker exec -it radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb"
```

### Timezone Configuration
```bash
# All containers use Eastern Time (America/New_York)
TZ=America/New_York

# Configured in:
# - Dockerfile: ENV TZ=America/New_York
# - docker-compose.yml: TZ=America/New_York for all services
# - Ensures recording timestamps match local time zone

# Verify timezone in containers:
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 date"
ssh radiograb@167.71.84.143 "docker exec radiograb-recorder-1 date"
```

### Python Dependencies (Virtual Environment Required)
```bash
# Critical packages (from requirements.txt):
APScheduler==3.11.0         # Job scheduling
beautifulsoup4==4.13.4      # HTML parsing
mysql-connector-python==9.4.0  # MySQL connector (main)
pymysql>=1.0.0              # MySQL connector (SQLAlchemy driver)
SQLAlchemy==2.0.41          # Database ORM
requests==2.32.4            # HTTP client
selenium==4.x               # JavaScript parsing (manually installed)

# Database Connection Details:
# - SQLAlchemy uses mysql+pymysql:// connection string
# - Requires both mysql-connector-python AND pymysql packages
# - Connection: mysql+pymysql://radiograb:radiograb_pass_2024@mysql:3306/radiograb
```

### Nginx Configuration (CRITICAL!)
**NGINX CONFIGURATION MUST BE COMPLETE AND NOT REFERENCE MISSING FILES:**

```nginx
# ❌ WRONG - references non-existent file:
include /etc/nginx/conf.d/radiograb-locations.conf;

# ✅ CORRECT - complete nginx configuration with all location blocks:
# PHP handling
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass 127.0.0.1:9000;
    # ... full PHP configuration
}

# Test recordings directory access (CRITICAL for audio player!)
location ^~ /temp/ {
    alias /var/radiograb/temp/;
    add_header Content-Type audio/mpeg;
    add_header Access-Control-Allow-Origin "*";
    add_header Accept-Ranges bytes;
}

# Key Configuration Notes:
# - HTTP server on port 80 for development/testing
# - HTTPS server uses nginx-ssl.conf for production
# - /temp/ location block REQUIRED for test recording playback
# - PHP-FPM on 127.0.0.1:9000 (NOT socket)
```

## 🔐 SSL/SECURITY CONFIGURATION

### Automatic SSL Management
```bash
# Environment configuration in .env:
SSL_DOMAIN=radiograb.svaha.com
SSL_EMAIL=admin@svaha.com

# Persistent SSL storage in Docker volumes:
letsencrypt:/etc/letsencrypt        # Certificates and config
letsencrypt_lib:/var/lib/letsencrypt # Working files

# SSL Management Scripts:
./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
./check-domain.sh radiograb.svaha.com
./backup-ssl.sh
```

### Security Features
- Let's Encrypt certificates with automatic renewal (twice daily)
- CSRF protection on all forms with session management
- HTTP to HTTPS redirect
- Security headers (HSTS, CSP, X-Frame-Options)
- A+ SSL Labs rating

## 📋 COMMON OPERATIONS

### Container Management
```bash
# Container status
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose ps"

# View logs
ssh radiograb@167.71.84.143 "docker logs radiograb-web-1 --tail 50"

# Restart specific service
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose restart radiograb-recorder-1"

# Execute commands in container
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 command"
```

### Testing & Verification
```bash
# Website accessibility
curl -I https://radiograb.svaha.com/

# CSRF token API
curl -s https://radiograb.svaha.com/api/get-csrf-token.php | jq .

# Test recording workflow
TOKEN=$(curl -s -c /tmp/cookies.txt "https://radiograb.svaha.com/api/get-csrf-token.php" | jq -r '.csrf_token')
curl -s -b /tmp/cookies.txt -X POST "https://radiograb.svaha.com/api/test-recording.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=test_recording&station_id=1&csrf_token=$TOKEN"

# CSRF debugging tools
# Browser debug page: https://radiograb.svaha.com/csrf-debug.php
# API debug endpoint: https://radiograb.svaha.com/api/debug-csrf.php
```

### Version Management
```bash
# VERSION file: /opt/radiograb/VERSION
# Format: YYYY-MM-DD HH:MM:SS - Description
# Displayed in website footer for tracking deployments

# Current version:
2025-07-24 02:30:00 - SECURITY & SSL FIXES - Fixed CSRF token validation, implemented production SSL certificates with Let's Encrypt, resolved recording file access issues, and established proper Docker container deployment workflow with comprehensive debugging.
```

## 🗂️ COMPLETE FILE STRUCTURE & DOCUMENTATION

### Project Root Files
- **CLAUDE.md** (this file) - Complete system reference (auto-read by Claude Code)
- **README.md** - Project overview and quick start
- **VERSION** - Current deployment version with timestamp and description
- **requirements.txt** - Python dependencies (pymysql, sqlalchemy, requests, etc.)
- **docker-compose.yml** - Container orchestration configuration
- **deploy-from-git.sh** - Automated deployment script (pulls from GitHub)
- **.env** - Environment variables (SSL_DOMAIN, SSL_EMAIL)

### Documentation Files
- **SYSTEM_ARCHITECTURE.md** - Detailed technical architecture
- **DEPLOYMENT.md** - Installation and deployment guide
- **CLAUDE_SERVER_CONFIG.md** - Server configuration details
- **CLAUDE_PROJECT_SUMMARY.md** - Project goals and features
- **CONTAINER_SETUP.md** - Docker container configuration
- **SSL_PRESERVATION_GUIDE.md** - SSL certificate management
- **CONFIGURATION_PRESERVATION_CHECKLIST.md** - Deployment safety checklist
- **TROUBLESHOOTING.md** - Common issues and solutions
- **STREAM_TESTING_INTEGRATION.md** - Automatic stream testing system
- **RECORDING_TOOLS_GUIDE.md** - Multi-tool recording compatibility
- **PROJECT_OVERVIEW.md** - High-level project overview
- **CHANGELOG.md** - Version history and changes

### 🐍 Backend Python Services (/opt/radiograb/backend/)

#### Core Services (backend/services/)
- **recording_service.py** - Main recording daemon with quality validation and AAC conversion
- **test_recording_service.py** - 10-second test recordings and on-demand recordings
- **station_auto_test.py** - Automated station testing with rediscovery (NEW)
- **stream_discovery.py** - Multi-strategy stream discovery using Radio Browser API (NEW)
- **rss_manager.py** - RSS feed generation (runs every 15 minutes)
- **housekeeping_service.py** - Cleanup service (runs every 6 hours)

#### Database Layer (backend/config/)
- **database.py** - SQLAlchemy configuration with mysql+pymysql:// connection

#### Models (backend/models/)
- **station.py** - Station model with test tracking fields (last_tested, last_test_result, last_test_error)

### 🌐 Frontend Web Interface (/opt/radiograb/frontend/public/)

#### Main Pages
- **index.php** - Dashboard homepage
- **stations.php** - Station management with visual test status indicators
- **shows.php** - Show management
- **recordings.php** - Recording listings
- **feeds.php** - RSS feed listings
- **settings.php** - System settings
- **add-station.php** - Add new station form
- **add-show.php** - Add new show form

#### API Endpoints (frontend/public/api/)
- **test-recording.php** - Start test/on-demand recordings (async)
- **test-recording-status.php** - Synchronous test recording with detailed diagnostics (NEW)
- **test-recordings.php** - List test recordings with download capability
- **get-csrf-token.php** - CSRF token generation
- **discover-station.php** - Station discovery from URLs
- **import-schedule.php** - Schedule import functionality
- **feeds.php** - RSS feed API
- **master-feed.php** - Combined RSS feed
- **system-info.php** - System information API
- **debug-csrf.php** - CSRF debugging
- **env-test.php** - Environment variable testing

#### Debug/Admin Tools
- **csrf-debug.php** - CSRF debugging interface

#### Assets (frontend/assets/)
- **css/radiograb.css** - Main stylesheet
- **js/radiograb.js** - Main JavaScript functionality including:
  - Audio player controls with keyboard shortcuts (spacebar, arrow keys)
  - Station/show form handling with CSRF protection  
  - Test recording functionality with detailed error modal popups
  - Station discovery from website URLs
  - Auto-refresh dashboard statistics
  - Bootstrap modal management for error diagnostics
- **images/default-station-logo.png** - Default station logo

### 🐳 Docker Configuration (/opt/radiograb/docker/)

#### Container Configs
- **nginx.conf** - Main nginx configuration
- **nginx-ssl.conf** - SSL nginx configuration  
- **radiograb-locations.conf** - Nginx location blocks
- **supervisord.conf** - Supervisor service management
- **station-health-cron** - Cron job for station monitoring
- **start.sh** - Container startup script

#### SSL Management Scripts (Root Directory)
- **setup-container-ssl.sh** - SSL certificate setup
- **check-domain.sh** - Domain verification
- **backup-ssl.sh** - SSL backup utility

### 📊 Critical File Locations

#### Test Recordings
- **Container Path**: `/var/radiograb/temp/` 
- **File Format**: `{CALL_LETTERS}_test_{TIMESTAMP}.mp3` (or `.mp3.mp3` after AAC conversion)
- **Examples**: `WYSO_test_2025-07-27-094052.mp3`, `WEHC_test_2025-07-27-001928.mp3`
- **Access URL**: `https://radiograb.svaha.com/temp/{filename}`
- **Note**: AAC streams are automatically converted to MP3, creating `.mp3.mp3` files

#### Main Recordings  
- **Container Path**: `/var/radiograb/recordings/`
- **File Format**: `{CALL_LETTERS}_{show_name}_{TIMESTAMP}.mp3`
- **Examples**: `WEHC_MorningShow_2025-07-27-0600.mp3`, `WYSO_NewsHour_2025-07-27-1800.mp3`

#### RSS Feeds
- **Container Path**: `/var/radiograb/feeds/`
- **Individual**: `{station_id}_feed.xml`
- **Master**: `master_feed.xml`

#### Logs
- **Container Path**: `/var/radiograb/logs/`
- **Test Recording Logs**: `test_recording_{station_id}_{timestamp}.log`

### 🔍 User Interface Elements

#### Station List Interface (stations.php)
- **Test Status Icons**: ✅ Success, ❌ Failed, ⚠️ Never tested, 🕐 Outdated
- **Last Tested Display**: Human-readable timestamps
- **Error Tooltips**: Hover details for failed tests
- **Action Buttons**: Test Recording, Import Schedule, Edit, Delete

#### Test Recording Workflow
1. **Test Button Click** → `/api/test-recording.php` (POST with CSRF)
2. **Recording Service** → `test_recording_service.py` (10-second test)
3. **File Creation** → `/var/radiograb/temp/{CALL_LETTERS}_test_{timestamp}.mp3`
4. **Database Update** → Station test status tracking
5. **Auto-Discovery** → On failure, triggers `stream_discovery.py`

### 📈 Enhanced Discovery System Flow

#### Multi-Strategy Search Process
1. **Direct Name Search** → Radio Browser API exact match
2. **Call Letters Search** → Extract and search WTBR, WEHC, etc.
3. **Frequency Search** → Extract 89.7 FM, 90.7 FM and search
4. **Location + Frequency** → "Pittsfield 89.7 FM" search
5. **Simplified Name** → Remove descriptive words and retry
6. **Location Only** → Final fallback for community stations

#### Stream Quality Scoring
- **Call Letter Match**: +0.8 points
- **Frequency Match**: +0.7 points (exact), +0.4 points (close)
- **Location Match**: +0.5 points
- **Working Status**: +0.3 points (working), -0.2 points (broken)
- **High Bitrate**: +0.15 points (≥128kbps)
- **Recent Activity**: +0.1 points (<30 days)

### 🛠️ Service Integration Points

#### Supervisor Services (docker/supervisord.conf)
```ini
[program:radiograb-recorder]
command=/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon
environment=PATH="/opt/radiograb/venv/bin",PYTHONPATH="/opt/radiograb",DB_HOST="mysql"...

[program:radiograb-rss]  
command=/opt/radiograb/venv/bin/python backend/services/rss_manager.py --update-all
environment=PATH="/opt/radiograb/venv/bin",PYTHONPATH="/opt/radiograb",DB_HOST="mysql"...
```

#### Database Schema (Key Tables)
- **stations** - id, name, call_letters, stream_url, last_tested, last_test_result, last_test_error
- **shows** - id, station_id, name, schedule_pattern, retention_days
- **recordings** - id, show_id, filename, recorded_at, duration_seconds, file_size_bytes

#### Environment Variables (All Containers)
```bash
DB_HOST=mysql
DB_PORT=3306  
DB_USER=radiograb
DB_PASSWORD=radiograb_pass_2024
DB_NAME=radiograb
TZ=America/New_York
PYTHONPATH=/opt/radiograb
```

### Key Technical Insights from Documentation
1. **JavaScript-Aware Schedule Parsing**: Selenium WebDriver handles dynamic calendars
2. **Multi-Tool Recording Strategy**: Automatic tool selection for 100% stream compatibility
3. **Test & On-Demand System**: 30-second tests + 1-hour manual recordings
4. **Housekeeping Service**: Automatic cleanup prevents empty file accumulation
5. **Master RSS Feed**: Combined feed of all shows in chronological order
6. **Enhanced Station Discovery**: Deep stream URL detection from JavaScript players
7. **Database-Cached Parsing Methods**: Stores successful parsing strategies per station
8. **Container-Based SSL**: Persistent certificates with automatic renewal
9. **Call Sign File Naming**: Human-readable 4-letter call signs replace numeric station IDs
10. **Timezone Synchronization**: All containers use America/New_York for consistent timestamps
11. **Enhanced Download Security**: Proper MP3 headers with filename validation and security checks
12. **🆕 Radio Browser API Integration**: Comprehensive US station database for stream discovery
13. **🆕 Automated Station Testing**: Continuous monitoring with automatic rediscovery on failures
14. **🆕 Intelligent Stream Matching**: Call letter extraction and confidence scoring for stream discovery

## 📡 ENHANCED STREAM DISCOVERY & TESTING SYSTEM

### 🔍 Radio Browser API Integration
**COMPREHENSIVE US STATION DATABASE FOR STREAM DISCOVERY**

The system now integrates with Radio Browser (radio-browser.info), a comprehensive database of radio stations worldwide with a focus on US stations.

#### Key Features:
- **Primary Source**: Radio Browser API serves as the first lookup method before traditional web scraping
- **Comprehensive Coverage**: Database of 50,000+ radio stations with verified stream URLs
- **Real-time Status**: Tracks which streams are currently working (lastcheckok status)
- **Quality Metadata**: Bitrate, codec, and popularity information for stream selection

#### Implementation:
```python
# Stream Discovery Service: backend/services/stream_discovery.py
from backend.services.stream_discovery import RadioStreamDiscovery

discovery = RadioStreamDiscovery()
stream_info = discovery.find_best_stream_match("WERU Community Radio")

# Returns structured stream information with confidence scoring
{
    'source': 'radio_browser',
    'stream_url': 'https://stream.weru.org:8000/weru-128',
    'confidence': 0.95,
    'bitrate': 128,
    'codec': 'MP3'
}
```

### 🎯 Intelligent Stream Matching Algorithm
**CONFIDENCE-BASED STREAM SELECTION**

#### Matching Criteria (Weighted Scoring):
1. **Exact Name Match**: 1.0 points (highest priority)
2. **Call Letter Match**: 0.8 points (WERU, KTBR, etc.)
3. **Word Similarity**: 0.6 points (based on common words)
4. **US Location**: +0.2 bonus (countrycode: US)
5. **Working Status**: +0.3 bonus (lastcheckok: 1)
6. **High Quality**: +0.2 bonus (bitrate ≥ 128)
7. **Popularity**: +0.1 bonus (clickcount > 100)

#### Call Letter Extraction:
- Detects 4-letter call signs: `WXYZ` pattern
- Handles 3-letter with numbers: `KNX`, `WGN`
- Fallback search by call letters if name search fails

### 📊 Automated Station Testing System
**CONTINUOUS MONITORING WITH AUTOMATIC REMEDIATION**

#### Station Test Tracking:
```sql
-- New station fields for test tracking:
last_tested        DATETIME     -- When station was last tested
last_test_result   ENUM         -- 'success', 'failed', 'error'
last_test_error    TEXT         -- Error details for failed tests
```

#### Testing Integration:
- **All Recording Operations**: Test, scheduled, and on-demand recordings update station test status
- **Quality Validation**: File size, format verification, and AAC-to-MP3 conversion
- **Automatic Rediscovery**: Failed stations trigger stream rediscovery and retesting
- **Web Interface Updates**: Visual status indicators with success/failure icons

#### Automated Test Service:
```bash
# Manual testing of all stations:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py

# Test stations not tested in 24 hours:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py --max-age 24

# Daemon mode (test every 6 hours):
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py --daemon

# Status summary only:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py --summary-only
```

### 🔄 Automatic Stream Rediscovery
**INTELLIGENT FAILURE RECOVERY**

#### Rediscovery Workflow:
1. **Test Failure Detection**: Station test fails with stream error
2. **Radio Browser Lookup**: Search for updated stream URL
3. **Stream Quality Assessment**: Validate new stream works better
4. **Database Update**: Replace old stream with new URL + metadata
5. **Retest**: Automatically retry test with new stream
6. **Success Tracking**: Log rediscovery source and confidence

#### Rediscovery Commands:
```bash
# Rediscover all failed stations:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/stream_discovery.py --rediscover-failed

# Rediscover specific station:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/stream_discovery.py --station-id 2

# Test search without updating:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/stream_discovery.py --test-search "WERU Community Radio"
```

### 📈 Enhanced Recording Quality
**AAC-TO-MP3 CONVERSION & VALIDATION**

#### Audio Processing:
- **Automatic AAC Detection**: Identifies AAC streams that need conversion
- **FFmpeg Conversion**: Converts AAC to MP3 with proper headers
- **Quality Validation**: Checks file size (minimum thresholds) and format verification
- **Error Handling**: Failed conversions marked appropriately in database

#### Recording Tool Selection:
- **Multi-Tool Strategy**: streamripper → ffmpeg → wget fallback
- **Stream Compatibility**: Different tools for different stream types
- **Tool Persistence**: Successful tool saved per station for future recordings

### 🌐 Web Interface Integration
**VISUAL STATION STATUS MONITORING**

#### Station List Enhancements:
- **Test Status Icons**: ✅ Success, ❌ Failed, ⚠️ Never tested, 🕐 Outdated
- **Last Tested Display**: Human-readable timestamps (e.g., "2 hours ago")
- **Error Tooltips**: Hover details for failed test reasons
- **Auto-refresh**: Real-time status updates during testing

#### API Endpoints:
- **Station Status**: Get current test status for all stations
- **Test History**: Historical test results and trends
- **Stream Metadata**: Discovery source and confidence information

### 🛠️ System Maintenance
**MONITORING & TROUBLESHOOTING**

#### Health Monitoring:
```bash
# Check station test status summary:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py --summary-only

# Monitor container logs:
docker logs radiograb-recorder-1 --tail 50 -f

# Database connection test:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python -c "from backend.config.database import SessionLocal; print('DB OK' if SessionLocal() else 'DB FAIL')"
```

#### Configuration Requirements:
- **Environment Variables**: All services require proper DB_HOST, PYTHONPATH configuration
- **Virtual Environment**: Critical `/opt/radiograb/venv/bin/python` usage
- **Network Access**: Radio Browser API requires outbound HTTPS access
- **Disk Space**: Test recordings in `/var/radiograb/temp/` (auto-cleanup on success)

## 🚨 CRITICAL SUCCESS FACTORS

### Deployment Requirements
- ✅ **Use `./deploy-from-git.sh` script for all deployments (public repo = no auth required)**
- ✅ Commit and push to GitHub before deploying: `git add . && git commit -m "..." && git push origin main`
- ✅ Deploy with: `ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"`
- ✅ **Script automatically pulls from GitHub and rebuilds containers**
- ✅ Verify deployment worked by testing the live site
- ✅ Use `/opt/radiograb/venv/bin/python` for all Python execution
- ✅ Set `PYTHONPATH=/opt/radiograb` for proper module imports
- ✅ Update VERSION file with each deployment
- ✅ Verify SSL certificates are in persistent Docker volumes

### System Monitoring
- ✅ Check container health: `docker compose ps`
- ✅ Monitor SSL certificate expiration
- ✅ Verify CSRF token API functionality
- ✅ Test recording system periodically
- ✅ Check disk space in `/var/radiograb/recordings/`

### Emergency Recovery
```bash
# If containers fail to start:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d"

# If SSL certificates are lost:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com"

# If git repository is corrupted:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && git status && git stash && git pull origin main"
```

## 🆕 RECENT UPDATES (July 2025)

### ✅ Call Sign Implementation (Completed)
- **Filename Format Change**: Recording files now use 4-letter call signs instead of numeric station IDs
  - New format: `WEHC_test_2025-07-25-070014.mp3` (instead of `1_test_2025-07-25-070014.mp3`)
  - Backward compatibility maintained for existing files
  - All stations configured with proper call signs: WEHC, WERU, WTBR, WYSO

### ✅ Timezone Fixes (Completed) 
- **Container Timezone**: All Docker containers now use `America/New_York` (EDT)
- **Timestamp Accuracy**: Recording timestamps now match local time instead of being 4 hours ahead
- **Configuration**: Added `TZ=America/New_York` environment variable to all services

### ✅ Download Security Enhancements (Completed)
- **Proper MP3 Downloads**: Test recordings now download as MP3 files instead of HTML
- **Security Validation**: Added filename format validation to prevent directory traversal
- **Headers Fixed**: Proper `audio/mpeg` content-type and download headers
- **API Enhancement**: Added dedicated download action to test-recordings.php API

### ✅ Database Environment Variables (Completed)
- **Critical Fix**: Changed from `$_ENV` to `$_SERVER` for PHP-FPM environment variable access
- **Container Configuration**: Enabled `clear_env = no` in PHP-FPM for proper variable passing
- **Connection Stability**: All MySQL connections now use environment variables correctly

### ✅ Enhanced Stream Discovery & Testing System (Completed)
- **Radio Browser API Integration**: Primary stream discovery using comprehensive US station database
- **Automated Station Testing**: Continuous monitoring with last_tested tracking for all stations
- **Intelligent Stream Matching**: Call letter extraction and confidence-based stream selection
- **Automatic Rediscovery**: Failed stations trigger Radio Browser lookup and stream replacement
- **AAC-to-MP3 Conversion**: Automatic format conversion with quality validation
- **Web Interface Updates**: Visual status indicators showing test results and last tested times
- **Recording Quality Validation**: File size and format verification for all recordings

### ✅ Test Recording Interface Fixes (Completed) 
- **Issue Resolved**: Test recordings now appear properly in web interface after creation
- **Root Cause Fixed**: AAC conversion was creating `.mp3.mp3` files that weren't found by original glob pattern
- **Duplicate Prevention**: Fixed duplicate recordings appearing by separating `.mp3` and `.mp3.mp3` file patterns
- **Clean Extensions**: Fixed AAC conversion to use same filename when file already has `.mp3` extension
- **Call Letters Only**: Cleaned up all code to only support call letters format, removed legacy station ID format

### 🔄 Known Outstanding Issues
- **JavaScript Integration**: Frontend-backend integration could be enhanced for real-time updates

---

**🔄 Remember: Docker containers = isolated filesystem. Host file copies ≠ live site updates!**
**✅ PUBLIC REPOSITORY: `./deploy-from-git.sh` pulls from GitHub automatically - no manual file copying needed!**
**📋 DEPLOYMENT CHECKLIST: 1) git push 2) deploy script 3) verify site works**
**🐍 Always: `/opt/radiograb/venv/bin/python` for Python execution**
**🔒 SSL: Persistent volumes ensure certificates survive container rebuilds**
**🚨 Database: All MySQL connections must use environment variables (DB_HOST=mysql, DB_PORT=3306)**
**📞 Call Signs: All new recordings use 4-letter call signs (WEHC, WERU, WTBR, WYSO) for easy identification**