# CLAUDE.md - RadioGrab System Reference

## 🚨 PRODUCTION SERVER & DEPLOYMENT 🚨

### Server Details
- **Domain**: https://radiograb.svaha.com
- **Server**: 167.71.84.143 (AlmaLinux 9)
- **SSH Access**: 
  - `root@167.71.84.143` - **FULL ROOT ACCESS** for system administration, Docker management, container restarts
  - `radiograb@167.71.84.143` - Limited user for application-only tasks and deployments
- **Directory**: `/opt/radiograb/`

### Docker Architecture (5 Containers)
```yaml
radiograb-web-1:        # Web interface + API (nginx + PHP-FPM)
radiograb-recorder-1:   # Recording daemon + scheduler
radiograb-mysql-1:      # MySQL 8.0 database
radiograb-rss-updater-1: # RSS feeds (every 15 min)
radiograb-housekeeping-1: # Cleanup (every 6 hours)
```

### Deployment Process
**CRITICAL**: Files are baked into containers - host changes require rebuild!

```bash
# Quick Deployment (docs/config changes) - 30 seconds
git add . && git commit -m "Update docs" && git push origin main
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh --quick"

# Full Deployment (code changes) - 5+ minutes  
git add . && git commit -m "Update code" && git push origin main
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"

# Emergency Direct Edit
ssh radiograb@167.71.84.143 "nano /opt/radiograb/path/to/file"
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"
```

## 🎯 CORE FEATURES

### Radio Recording System
- **Automatic Recording**: APScheduler cron-based scheduling for shows
- **Multi-Tool Strategy**: streamripper/ffmpeg/wget with automatic selection
- **Quality Validation**: File size (2KB/sec min), format verification, AAC→MP3 conversion
- **Station Discovery**: Radio Browser API + web scraping with intelligent matching
- **JavaScript-Aware Parsing**: Selenium WebDriver with Chromium browser for dynamic calendars
- **Station Schedule Discovery**: Automated discovery of show schedules with multiple airings support
- **Test & On-Demand**: 10-second tests + manual recordings with duplicate prevention
- **Call Letters Format**: `WYSO_ShowName_20250727_1400.mp3` naming
- **RSS Feeds**: Individual show feeds + master combined feed
- **Retention Policies**: Configurable TTL with automatic cleanup
- **Real-time Status**: ON-AIR indicators, progress tracking, browser notifications

### ✅ Generic Architecture (v2.11.0)
- **No Station-Specific Code**: All parsers completely generic and reusable
- **ISO Timestamp Parser**: `_parse_iso_timestamp_json_schedule()` for any timezone-aware JSON calendar
- **Show Links Parser**: `_parse_show_links_schedule()` for any HTML with show links/program elements  
- **StreamTheWorld Fallback**: Generic HD2→HD1→base quality fallback (not station-specific)
- **Smart Logo Detection**: Intelligent scoring system with homepage priority, path analysis, size validation
- **Unlimited Scalability**: Add any station without code changes - parsers auto-detect formats

## 🕐 AUTOMATIC RECORDING SYSTEM

### Architecture
- **RecordingScheduler**: `recording_service.py --daemon` (APScheduler cron jobs)
- **ScheduleManager**: Web interface integration for schedule management
- **Database**: `schedule_pattern` (cron) + `schedule_description` fields
- **Flow**: "Tuesday 7PM" → `schedule_parser.py` → "0 19 * * 2" → APScheduler

### Management Commands
```bash
# Status and control
/opt/radiograb/venv/bin/python backend/services/recording_service.py --schedule-status
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --refresh-all
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --add-show 5

# Troubleshooting
docker exec radiograb-recorder-1 supervisorctl status radiograb-recorder
docker logs radiograb-recorder-1 --tail 50 | grep -i schedule
```

### Key Directories
```bash
/opt/radiograb/                    # Application root
/opt/radiograb/venv/               # Python virtual environment (CRITICAL!)
/var/radiograb/recordings/         # Recorded audio files
/var/radiograb/temp/              # Test recordings
/var/radiograb/feeds/             # RSS feeds
/var/radiograb/logos/             # Station logos
```

## 📺 ON-AIR INDICATOR SYSTEM

- **Real-time Updates**: JavaScript checks every 30s via `/api/recording-status.php`
- **Visual Elements**: Pulsing red badges, progress bars, browser tab indicators
- **Progress Tracking**: Elapsed/remaining time, completion percentage
- **Site-wide Banners**: Recording notifications across all pages

## 📺 MULTIPLE SHOW AIRINGS SYSTEM

- **Natural Language**: "Mondays at 7 PM and Thursdays at 3 PM" → 2 schedules
- **Keywords**: Recognizes "original", "repeat", "encore", "rerun", "also"
- **Database**: Separate `show_schedules` table with priority system
- **Management**: `show_schedules_manager.py` for complex scheduling

## 🌐 JAVASCRIPT-AWARE SCHEDULE DISCOVERY

### Station Schedule Discovery System
- **Add Show Integration**: "Find Shows" button discovers station schedules automatically
- **Multiple Airings Support**: Groups shows by name, displays all broadcast times
- **Interactive Selection**: Individual "Add" buttons for each show/airing combination
- **CSRF Protection**: Full security integration with session management

### JavaScript Calendar Parsing (`js_calendar_parser.py`)
- **Chromium WebDriver**: Uses system-installed `chromium-browser` for JavaScript execution
- **Dynamic Content**: Handles calendars that load via JavaScript/AJAX
- **WordPress Support**: Specialized parsers for Calendarize It, The Events Calendar, FullCalendar
- **Fallback Strategy**: Gracefully falls back to standard HTML parsing if WebDriver fails
- **Cache Management**: Uses writable `/var/radiograb/temp/.wdm` directory for driver cache

### API Endpoints
- **`/api/discover-station-schedule.php`**: Station schedule discovery
- **`/api/schedule-verification.php`**: Calendar verification and testing
- **Browser Testing**: All APIs tested through actual browser workflows

### 🧪 Testing Requirements
**CRITICAL**: All tests and debugging should simulate actual user browser interactions using Chromium browser. This includes:
- Testing calendar verification through web interface (not direct API calls)
- Using browser-based CSRF token workflows
- Simulating actual user clicks, form submissions, and page interactions
- Verifying JavaScript functionality works as users experience it
- Testing with browser session management and cookie handling

### Technical Implementation
```bash
# Container Dependencies
chromium-browser                    # Installed via apt-get (lighter than Chrome)
selenium>=4.15.0                   # WebDriver automation
webdriver-manager>=4.0.0          # ChromeDriver management

# Usage
docker exec radiograb-web-1 /opt/radiograb/venv/bin/python backend/services/js_calendar_parser.py
```

## 🔧 TECHNICAL REQUIREMENTS

### Python Execution (CRITICAL!)
```bash
# Always use virtual environment:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/script.py

# With environment variables:
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/script.py"
```

### Environment Variables
```bash
# Database
DB_HOST=mysql
DB_PORT=3306
DB_USER=radiograb
DB_PASSWORD=radiograb_pass_2024
DB_NAME=radiograb

# System
TZ=America/New_York
PYTHONPATH=/opt/radiograb

# MySQL Access
ssh radiograb@167.71.84.143 "docker exec -it radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb"
```

### Key Dependencies
- **APScheduler**: Job scheduling
- **SQLAlchemy + pymysql**: Database ORM
- **BeautifulSoup4**: HTML parsing
- **Selenium**: JavaScript-aware parsing
- **requests**: HTTP client
- **Pillow**: Image processing for logo optimization
- **python-dateutil**: ISO timestamp parsing with timezone support

## 🔐 SSL/SECURITY

### SSL Management
```bash
# Setup
./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com

# Environment
SSL_DOMAIN=radiograb.svaha.com
SSL_EMAIL=admin@svaha.com
```

### Security Features
- Let's Encrypt auto-renewal
- CSRF protection
- Security headers (HSTS, CSP)
- A+ SSL Labs rating

## 📋 COMMON OPERATIONS

### Container Management
```bash
# Status & logs
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose ps"
ssh radiograb@167.71.84.143 "docker logs radiograb-web-1 --tail 50"

# Restart services
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose restart radiograb-recorder-1"

# Emergency rebuild (root access)
ssh root@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"
```

### Testing
```bash
# Website accessibility
curl -I https://radiograb.svaha.com/

# Test recording with CSRF
TOKEN=$(curl -s -c /tmp/cookies.txt "https://radiograb.svaha.com/api/get-csrf-token.php" | jq -r '.csrf_token')
curl -b /tmp/cookies.txt -X POST "https://radiograb.svaha.com/api/test-recording.php" -d "action=test_recording&station_id=1&csrf_token=$TOKEN"
```

## 🖼️ FILE STRUCTURE

### Key Services
- **recording_service.py**: Main daemon with APScheduler
- **test_recording_service.py**: 10-second tests and on-demand recording
- **stream_discovery.py**: Radio Browser API + web scraping
- **station_auto_test.py**: Automated testing with rediscovery
- **rss_manager.py**: RSS feed generation
- **housekeeping_service.py**: File cleanup

### Web Interface
- **Main Pages**: index.php, stations.php, shows.php, recordings.php
- **API Endpoints**: test-recording.php, get-csrf-token.php, discover-station.php
- **Assets**: radiograb.css, radiograb.js (audio player, CSRF, modals)

### File Locations
- **Test Recordings**: `/var/radiograb/temp/CALL_test_timestamp.mp3`
- **Main Recordings**: `/var/radiograb/recordings/CALL_Show_timestamp.mp3` 
- **RSS Feeds**: `/var/radiograb/feeds/`
- **Logs**: `/var/radiograb/logs/`

### Database Schema
- **stations**: id, call_letters, stream_url, last_tested, last_test_result
- **shows**: id, station_id, schedule_pattern, retention_days
- **recordings**: id, show_id, filename, recorded_at, file_size_bytes

## 📡 STREAM DISCOVERY & TESTING

### Radio Browser API Integration
- **Primary Source**: 50,000+ verified US radio stations from radio-browser.info
- **Intelligent Matching**: Call letters, frequency, location-based scoring
- **Quality Assessment**: Bitrate, working status, popularity metrics
- **Confidence Scoring**: Weighted algorithm for best stream selection

### Automated Testing
```bash
# Test all stations
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py

# Test outdated stations (24h+)
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/station_auto_test.py --max-age 24

# Rediscover failed stations
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/stream_discovery.py --rediscover-failed
```

### Quality & Recovery
- **Multi-Tool Strategy**: streamripper → ffmpeg → wget fallback
- **AAC Conversion**: Automatic AAC→MP3 with FFmpeg
- **Auto-Rediscovery**: Failed stations trigger Radio Browser lookup
- **Visual Status**: ✅/❌/⚠️ icons with tooltips in web interface

## 🚨 CRITICAL SUCCESS FACTORS

### Deployment Checklist
1. **Always push to GitHub first**: `git add . && git commit -m "..." && git push origin main`
2. **Deploy via script**: `ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"`
3. **Verify deployment**: Test live site functionality
4. **Python execution**: Always use `/opt/radiograb/venv/bin/python`
5. **Environment**: Set `PYTHONPATH=/opt/radiograb`

### Emergency Recovery
```bash
# Container restart
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d"

# SSL recovery
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com"

# Git repository fix
ssh radiograb@167.71.84.143 "cd /opt/radiograb && git stash && git pull origin main"
```

### 🧪 Testing Requirements
**CRITICAL**: All tests and debugging should simulate actual user browser interactions using Chromium browser. This includes:
- **Calendar Verification**: Test through web interface (not direct API calls)
- **Browser CSRF Workflow**: Use actual browser-based token workflows
- **User Interaction Simulation**: Actual clicks, form submissions, page interactions
- **JavaScript Execution**: Selenium WebDriver with Chromium for dynamic content
- **Real User Testing**: "Test using the same method users test, via a browser"

```bash
# Browser testing examples:
# 1. Use web interface buttons instead of direct API calls
# 2. Test CSRF token flow through browser forms
# 3. Verify calendar discovery via "Find Shows" button
# 4. Check recording status through dashboard (not just API)
```

## 🆕 RECENT UPDATES (July 2025)

### ✅ Major Features Completed
- **Playlist Upload System**: Multi-format audio uploads with MP3 conversion and metadata tagging
- **Multiple Show Airings**: "Mon 7PM and Thu 3PM" parsing with priority system
- **ON-AIR Indicators**: Real-time recording status with animated badges
- **TTL Management**: Configurable recording retention with automatic cleanup
- **Enhanced Stream Discovery**: Radio Browser API integration with intelligent matching
- **Call Sign Implementation**: WYSO_ShowName_timestamp.mp3 format
- **Logo & Social Media**: Local storage with Facebook fallback
- **Schedule Verification**: Automated weekly checking with change detection
- **Database Backups**: Weekly automated backups with 3-week retention

### ✅ System Improvements
- **Calendar Discovery Filtering**: Navigation elements (e.g., "Shows A-Z") filtered out, requires valid time schedules
- **User-Controlled Show Activation**: New shows inactive by default - users manually choose which to activate
- **Enhanced JavaScript Parsing**: Comprehensive show name validation with 40+ invalid pattern detection
- **Timezone Fixes**: All containers use America/New_York
- **Security Enhancements**: Proper MP3 downloads with CSRF protection
- **UI Improvements**: Empty show hiding, progress tracking, real-time updates
- **Recording Service v2.0**: Database-driven with duplicate prevention
- **Quality Validation**: AAC→MP3 conversion with file size checks

### 📅 Enhanced Calendar Discovery System (July 30, 2025)

#### ✅ Smart Show Filtering
The calendar discovery system now includes comprehensive filtering to prevent invalid entries:

**Navigation Element Detection:**
- Filters out: "Shows A-Z", "Schedule", "Calendar", "Home", "About", "Contact", "Archive"
- Rejects generic terms: "Show", "Program", "Event", "Radio" (when standalone)
- Blocks admin elements: "Login", "Dashboard", "Settings", "Manage"

**Quality Validation:**
- **Time Requirement**: Shows must have valid air dates/times to be added to database
- **Minimum Length**: Show names must be at least 3 characters
- **Pattern Matching**: 40+ invalid patterns detected and rejected
- **Numeric Filtering**: Rejects date-only or number-only entries

#### ✅ User-Controlled Activation
**Default Behavior**: All discovered shows start as "Inactive" for user review
**User Control**: Manual activation prevents unwanted auto-scheduling
**Better Experience**: Users choose which shows to record instead of bulk auto-activation

```bash
# Calendar verification with new filtering
ssh radiograb@167.71.84.143 "docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/schedule_verification_service.py --station-id 1"

# Results show filtered, valid shows only:
# ✅ "Fresh Air" (valid show with time)
# ✅ "All Things Considered" (valid show with time)  
# ❌ "Shows A-Z" (filtered out as navigation)
# ❌ "Schedule" (filtered out as navigation)
```

#### 🔧 Technical Implementation
- **`js_calendar_parser.py`**: Added `_is_invalid_show_name()` method with comprehensive pattern matching
- **`schedule_verification_service.py`**: Changed default `active=False` for new shows
- **Enhanced Error Handling**: Shows without valid times are skipped with debug logging
- **Backward Compatibility**: Existing active shows remain unchanged

---

**🚨 CRITICAL REMINDERS**
- **Deployment**: 1) git push 2) `./deploy-from-git.sh` 3) verify site
- **Python**: Always use `/opt/radiograb/venv/bin/python` with `PYTHONPATH=/opt/radiograb`
- **Database**: Use environment variables (DB_HOST=mysql)
- **Files**: Call sign format (WYSO_ShowName_timestamp.mp3)
- **Containers**: Host changes require rebuild - files are baked in!