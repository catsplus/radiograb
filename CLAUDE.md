# CLAUDE.md - Complete RadioGrab System Reference

**⚠️ CRITICAL: READ THIS FIRST - COMPLETE SYSTEM ARCHITECTURE ⚠️**

## 🚨 PRODUCTION SERVER DETAILS 🚨

- **Domain**: https://radiograb.svaha.com
- **Server IP**: 167.71.84.143
- **SSH Access**: `root@167.71.84.143` (SSH key authentication - FULL ROOT ACCESS for system administration)
- **Alternative SSH**: `radiograb@167.71.84.143` (limited privileges for application-only tasks)
- **Project Directory**: `/opt/radiograb/` (owned by `radiograb:radiograb`)
- **Platform**: AlmaLinux 9 on DigitalOcean droplet
- **Firewall**: CSF (ConfigServer Security & Firewall) - allows only ports 22, 80, 443

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
- **Radio Recording System**: Automatically record radio shows based on configured schedules and generate podcast feeds
- **Enhanced Recording Service v2.5**: Database-driven recording with APScheduler integration for automatic scheduling
- **Automatic Show Recording**: APScheduler-based cron scheduling system that automatically records shows at scheduled times
- **Schedule Management**: Web interface for adding/editing show schedules with automatic scheduler integration
- **Station Discovery**: Extract streaming URLs, logos, schedules from website URLs
- **JavaScript-Aware Parsing**: Selenium WebDriver for dynamic calendars
- **Multi-Tool Recording**: streamripper/wget/ffmpeg with automatic tool selection and User-Agent support
- **Test & On-Demand Recording**: 30-second tests + 1-hour manual recordings with duplicate prevention
- **RSS Feeds**: Individual show feeds + master feed (all shows combined)
- **Automatic Housekeeping**: Cleans empty files every 6 hours with retention policies
- **Station Testing Tracking**: Last tested date, success/failure status on every recording
- **Automated Station Testing**: Periodic testing service to verify all stations

### Station Testing System
- **Automatic Test Tracking**: Every successful recording updates station's `last_tested` timestamp
- **Quality Validation**: File size and format verification on recording completion
- **Status Tracking**: `last_test_result` (success/failed/error) and `last_test_error` fields
- **Web Interface Display**: Last tested status with icons in stations page
- **Automated Testing Service**: `station_auto_test.py` for periodic station verification

### Enhanced Recording System (radiograb-recorder-1 container)
- **Multi-Tool Strategy**: streamripper/ffmpeg/wget with automatic tool selection
- **User-Agent Support**: Saved User-Agent per station for HTTP 403 handling
- **Duplicate Prevention**: Built-in 30-minute window duplicate detection
- **Quality Validation**: File size and format validation (2KB/sec minimum)
- **Database Integration**: Full synchronization with station settings and test results
- **APScheduler**: Cron-based show scheduling with timezone support
- **Call Letters Format**: WYSO_ShowName_20250727_1400.mp3 naming convention
- **Retention Policies**: Automatic cleanup based on show-specific retention days

## 🕐 AUTOMATIC RECORDING SYSTEM

### ✅ Complete Implementation Status
**AUTOMATIC SHOW RECORDING IS FULLY IMPLEMENTED AND OPERATIONAL**

The RadioGrab system includes a complete automatic recording system that records shows based on configured schedules. Here's how it works:

### 🏗️ Architecture Overview

#### Core Components:
1. **RecordingScheduler** (`recording_service.py`) - APScheduler-based cron job management
2. **ScheduleManager** (`schedule_manager.py`) - Web interface integration for schedule management  
3. **Show Database** - Stores `schedule_pattern` (cron format) and `schedule_description` fields
4. **Supervisor Process** - Runs `recording_service.py --daemon` continuously in radiograb-recorder-1 container

### 📋 How Automatic Recording Works

#### 1. Show Creation & Scheduling
```php
// When a show is added via add-show.php:
1. User enters schedule in plain English: "every Tuesday at 7 PM"
2. schedule_parser.py converts to cron: "0 19 * * 2"  
3. Show saved to database with schedule_pattern field
4. schedule_manager.py --add-show automatically schedules the job
5. APScheduler creates recurring cron job for the show
```

#### 2. Daemon Process (radiograb-recorder-1)
```ini
# Supervisor runs this continuously:
[program:radiograb-recorder]
command=/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon
# This starts APScheduler and schedules all active shows
```

#### 3. Automatic Recording Execution
```python
# At scheduled time, APScheduler triggers:
def _recording_job(self, show_id: int):
    # 1. Load show and station from database
    # 2. Check for duplicate recordings (30-min window)
    # 3. Generate filename: WYSO_ShowName_20250728_1900.mp3
    # 4. Call perform_recording() with stream URL and User-Agent
    # 5. Validate recording quality (file size, format)
    # 6. Save recording to database with metadata
    # 7. Update station test status
    # 8. Apply retention policy cleanup
```

### 🎛️ Schedule Management Interface

#### Web Interface Integration:
- **Add Show** (`/add-show.php`): Automatically schedules shows after creation
- **Show List** (`/shows.php`): Shows schedule status indicators  
- **Schedule Test** (`/schedule-test.php`): Monitor and test scheduler status
- **API Endpoints** (`/api/schedule-manager.php`): Programmatic schedule management

#### Schedule Status Indicators:
- ✅ **Green "Scheduled for automatic recording"** - Show has valid schedule_pattern
- ⚠️ **Yellow "No schedule configured"** - Show exists but no schedule_pattern
- 🕐 **Schedule Button** - Manage individual show schedules

### 🔧 Schedule Management Commands

#### Python CLI Commands:
```bash
# View all scheduled jobs and status
/opt/radiograb/venv/bin/python backend/services/recording_service.py --schedule-status

# Refresh all schedules (reschedule all active shows)
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --refresh-all

# Add specific show to scheduler
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --add-show 5

# Update show schedule (remove old, add new)
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --update-show 5

# Remove show from scheduler
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --remove-show 5

# Get detailed scheduling status
/opt/radiograb/venv/bin/python backend/services/schedule_manager.py --status
```

### 📊 Database Schema Integration

#### Shows Table Fields:
```sql
schedule_pattern VARCHAR(255) NOT NULL    -- Cron pattern: "0 19 * * 2" 
schedule_description VARCHAR(500) NULL    -- Human readable: "every Tuesday at 7 PM"
active BOOLEAN DEFAULT TRUE               -- Only active shows are scheduled
retention_days INT DEFAULT 30             -- Auto-cleanup policy
```

#### Automatic Scheduling Flow:
```
User Input → schedule_parser.py → Database → ScheduleManager → APScheduler → Recording
"Tuesday 7PM" → "0 19 * * 2" → shows.schedule_pattern → add_show_schedule() → CronTrigger → _recording_job()
```

### 🔄 Real-Time Schedule Synchronization

#### When Shows Are Modified:
- **Add Show**: Automatically calls `schedule_manager.py --add-show`
- **Edit Show**: Should call `schedule_manager.py --update-show` (integration point)
- **Delete Show**: Should call `schedule_manager.py --remove-show` (integration point)
- **Deactivate Show**: APScheduler automatically excludes inactive shows

#### Scheduler Persistence:
- **Container Restart**: `recording_service.py --daemon` reschedules all active shows on startup
- **Database Changes**: Manual refresh via `--refresh-all` or web interface
- **Error Recovery**: Scheduler continues running even if individual jobs fail

### 🧪 Testing Automatic Recording

#### Test Interface (`/schedule-test.php`):
- **Real-time Status**: Shows active shows, scheduled jobs, next run times
- **Manual Testing**: Test individual show recordings without waiting for schedule  
- **Schedule Refresh**: Force reload all schedules from database
- **Job Monitoring**: See which shows are scheduled vs. unscheduled

#### Verification Commands:
```bash
# Check if recording service daemon is running
docker exec radiograb-recorder-1 ps aux | grep recording_service

# View scheduler logs
docker logs radiograb-recorder-1 --tail 50 | grep -i schedule

# Test manual recording
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/recording_service.py --test-show 5

# Check APScheduler job status
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/recording_service.py --schedule-status
```

### ⚠️ Common Issues & Solutions

#### Issue: "Show not scheduled" despite having schedule_pattern
**Solution**: Run schedule refresh:
```bash
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/schedule_manager.py --refresh-all
```

#### Issue: Recording service not running
**Solution**: Check supervisor and restart if needed:
```bash
docker exec radiograb-recorder-1 supervisorctl status radiograb-recorder
docker exec radiograb-recorder-1 supervisorctl restart radiograb-recorder
```

#### Issue: Scheduled recordings not happening
**Solution**: Check timezone configuration and cron parsing:
```bash
# Verify timezone
docker exec radiograb-recorder-1 date
# Should show America/New_York time

# Test schedule parsing
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python backend/services/schedule_parser.py "every Tuesday at 7 PM"
```

### 🎯 Key Success Indicators

✅ **Scheduler Status**: `--schedule-status` shows active jobs  
✅ **Web Integration**: Shows display "Scheduled for automatic recording"  
✅ **Automatic Recording**: Files appear in `/var/radiograb/recordings/` at scheduled times  
✅ **Database Updates**: Recording entries created with proper metadata  
✅ **RSS Integration**: New recordings automatically added to RSS feeds  
✅ **Quality Validation**: Recordings pass file size and format checks

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
/var/radiograb/logos/             # Local station logos and social media images (2025-07-29)
```

## 📺 ON-AIR INDICATOR SYSTEM

### Real-Time Recording Status
The ON-AIR indicator system provides visual feedback for shows currently being recorded:

**✅ Features:**
- **Real-time Status Updates**: JavaScript checks recording status every 30 seconds
- **Animated ON-AIR Badges**: Pulsing red indicators next to Active/Inactive status
- **Progress Tracking**: Shows elapsed time, remaining time, and completion percentage
- **Page-wide Banners**: Site-wide recording notifications at top of pages
- **Browser Title Updates**: 🔴 icon appears in browser tab during recordings

**🔧 Technical Components:**
```bash
# API Endpoint:
/api/recording-status.php?action=current_recordings

# CSS Styling:
/assets/css/on-air.css              # Animated badges and progress bars

# JavaScript Manager:
/assets/js/on-air-status.js         # Real-time status updates

# Integration:
shows.php                           # ON-AIR functionality enabled
```

**📊 API Response Format:**
```json
{
  "success": true,
  "current_recordings": [
    {
      "show_id": 50,
      "show_name": "Show Name",
      "station_name": "Station Name",
      "call_letters": "CALL",
      "start_time": "2025-07-28 19:00:00",
      "end_time": "2025-07-28 20:00:00",
      "duration_minutes": 60,
      "elapsed_seconds": 1800,
      "remaining_seconds": 1800,
      "progress_percent": 50.0
    }
  ],
  "count": 1,
  "timestamp": "2025-07-28 19:30:00"
}
```

**🎯 Visual Placement:**
- ON-AIR badge appears below the Active/Inactive status badge
- Progress bar and timing info display in the middle of show cards
- No overlap with show titles or action buttons
- Clean separation from delete/edit controls

## 📺 MULTIPLE SHOW AIRINGS SYSTEM

### Original + Repeat Broadcasting Support
The system now supports shows with multiple airings (original broadcasts plus repeats):

**✅ Features:**
- **Natural Language Parsing**: "Mondays at 7 PM and Thursdays at 3 PM"
- **Keyword Detection**: Recognizes "original", "repeat", "encore", "rerun", "also"
- **Multiple Separators**: Handles "and", commas, "also", "plus" in schedule text
- **Priority Assignment**: Original broadcasts get priority 1, repeats get 2+
- **Complex Scheduling**: "Original Wednesday 9AM, repeat Thursday 2PM, encore Sunday 6PM"
- **Database Normalization**: Separate `show_schedules` table for multiple patterns per show

**🔧 Technical Components:**
```bash
# Database Schema:
show_schedules                          # Multiple schedule patterns per show
├── schedule_pattern (cron)            # Individual cron pattern for this airing
├── airing_type (original/repeat/special)
├── priority (1=highest)               # Primary airing priority
└── active (boolean)                   # Enable/disable specific airings

# Parser Services:
backend/services/multiple_airings_parser.py    # Detect multiple airings from text
backend/services/show_schedules_manager.py     # Manage multiple schedule patterns

# Database Migration:
database/migrations/add_multiple_show_schedules.sql

# Testing:
test_multiple_airings.py               # Comprehensive test suite
```

**📊 Supported Schedule Formats:**
```text
# Single Airings (unchanged)
"Mondays at 7 PM"                     → 1 schedule
"Weekdays at 6:30 AM"                 → 1 schedule

# Multiple Airings with Separators
"Mondays at 7 PM and Thursdays at 3 PM"     → 2 schedules
"Mon 7PM, Thu 3PM"                           → 2 schedules
"Tuesday 9AM, Saturday 2PM"                  → 2 schedules

# Multiple Airings with Keywords
"Mondays at 7 PM, repeat on Thursdays at 3 PM"      → 2 schedules (original + repeat)
"Original broadcast Tuesday 9 AM, encore Friday 6 PM" → 2 schedules (original + repeat)
"Live Wednesdays at noon, rerun Sundays at 8 PM"     → 2 schedules (original + repeat)

# Complex Multiple Airings
"Original Wednesday 9AM, repeat Thursday 2PM, encore Sunday 6PM" → 3 schedules
"First airing Monday 7PM, also Tuesday 3PM and Saturday noon"   → 3 schedules
```

**🎯 Database Structure:**
```sql
-- Example: Show with multiple airings
show_schedules:
  id=1, show_id=50, schedule_pattern="0 19 * * 1", airing_type="original", priority=1
  id=2, show_id=50, schedule_pattern="0 15 * * 4", airing_type="repeat", priority=2
  id=3, show_id=50, schedule_pattern="0 18 * * 0", airing_type="repeat", priority=3

-- Shows table updated with flag
shows.uses_multiple_schedules = TRUE  (when show has >1 schedule)
```

**🚀 Management Commands:**
```bash
# Test the parser with various inputs
python test_multiple_airings.py

# Migrate legacy single schedules to new system
python backend/services/show_schedules_manager.py --migrate

# Add multiple schedules for a show
python backend/services/show_schedules_manager.py --show-id 50 --schedule-text "Mon 7PM and Thu 3PM"

# List all active schedules
python backend/services/show_schedules_manager.py --list-schedules
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

### Docker Troubleshooting (Root Access Required)
```bash
# ⚠️ CRITICAL: Use root@167.71.84.143 for Docker system issues
# Restart Docker service (requires root)
ssh root@167.71.84.143 "systemctl restart docker"

# Clean Docker system (requires root)
ssh root@167.71.84.143 "docker system prune -f"

# Fix Docker networking issues (requires root)
ssh root@167.71.84.143 "systemctl restart docker && cd /opt/radiograb && docker compose up -d"

# Emergency container restart with full rebuild
ssh root@167.71.84.143 "cd /opt/radiograb && docker compose down && docker system prune -f && docker compose up -d --build"
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
- **CHANGELOG.md** - Version history and changes
- **VERSION** - Current deployment version with timestamp and description
- **requirements.txt** - Python dependencies (pymysql, sqlalchemy, requests, etc.)
- **docker-compose.yml** - Container orchestration configuration
- **deploy-from-git.sh** - Automated deployment script (pulls from GitHub)
- **.env** - Environment variables (SSL_DOMAIN, SSL_EMAIL)

### 📚 Documentation Directory (docs/)
**IMPORTANT FOR CLAUDE CODE: When working with RadioGrab, automatically read ALL files in the docs/ folder for complete context.**

The comprehensive documentation is organized in the `docs/` folder:

- **docs/SYSTEM_ARCHITECTURE.md** - Detailed technical architecture and container interactions
- **docs/DEPLOYMENT.md** - Installation and deployment guide with step-by-step instructions
- **docs/CLAUDE_PROJECT_SUMMARY.md** - Project goals, features, and high-level overview
- **docs/CONTAINER_SETUP.md** - Docker container configuration and networking
- **docs/SSL_PRESERVATION_GUIDE.md** - SSL certificate management with Let's Encrypt
- **docs/TROUBLESHOOTING.md** - Common issues and solutions with diagnostics
- **docs/STREAM_TESTING_INTEGRATION.md** - Automatic stream testing system details
- **docs/RECORDING_TOOLS_GUIDE.md** - Multi-tool recording compatibility (streamripper/ffmpeg/wget)
- **docs/STREAM_URL_DISCOVERY.md** - Website parsing and stream discovery methods
- **docs/PROJECT_OVERVIEW.md** - High-level project overview and goals

#### 🤖 Claude Code Integration Instructions
**When working on RadioGrab tasks, the system should automatically:**
1. Read CLAUDE.md (this file) for complete architecture overview
2. Read ALL files in the docs/ folder for comprehensive context
3. Reference the appropriate documentation based on the task at hand
4. Use the consolidated information to provide accurate assistance

**Example usage for comprehensive understanding:**
```bash
# Read all documentation for complete project context
Read: CLAUDE.md, docs/SYSTEM_ARCHITECTURE.md, docs/DEPLOYMENT.md, 
      docs/TROUBLESHOOTING.md, docs/CONTAINER_SETUP.md, etc.
```

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

### ✅ Playlist Upload System & MP3 Metadata Implementation (Completed July 29, 2025)
- **Complete Playlist System**: Multi-format audio file uploads (MP3, WAV, M4A, AAC, OGG, FLAC) with automatic MP3 conversion
- **Drag & Drop Track Ordering**: Real-time playlist management with track reordering interface and sequential numbering
- **Comprehensive MP3 Metadata**: All recordings tagged with artist=show name, album=station name, recording date, description, genre
- **Upload Metadata Enhancement**: User uploads preserve existing metadata while adding show/station information
- **Database Schema Extensions**: Added playlist support fields (show_type, allow_uploads, track_number, source_type, original_filename)
- **Enhanced Web Interface**: Show type selection, upload functionality, playlist management modal with progress tracking
- **RSS Feed Playlist Support**: Enhanced RSS generation to include uploaded tracks with proper track ordering
- **FFmpeg Integration**: Backend service architecture for reliable metadata writing and audio processing
- **File Validation**: Comprehensive audio format validation, size limits, and quality verification
- **Legal Compliance**: Replaced all "TiVo for Radio" references with legally neutral "Radio Recorder" terminology

### ✅ UI Improvements & User Experience (Completed July 29, 2025)
- **Empty Show Hiding**: On-Demand Recording shows with 0 recordings are now hidden from shows page for cleaner interface
- **Timezone Display Removal**: Removed timezone display from show blocks to reduce visual clutter
- **Upload Progress Tracking**: Real-time progress indicators with comprehensive error handling and status updates
- **Playlist Management Interface**: Dedicated modal with drag & drop functionality and visual feedback
- **Enhanced Show Cards**: Improved layout with better spacing and information hierarchy
- **Real-time Updates**: AJAX-powered interface updates without page reloads

### ✅ Multiple Show Airings System (Completed July 29, 2025)
- **Original + Repeat Support**: Shows can now have multiple airings (original + repeat broadcasts)
- **Natural Language Parser**: "Mondays at 7 PM and Thursdays at 3 PM" → 2 separate schedules
- **Keyword Detection**: Recognizes "original", "repeat", "encore", "rerun", "also" keywords
- **Complex Scheduling**: "Original Wed 9AM, repeat Thu 2PM, encore Sun 6PM" → 3 schedules
- **Database Normalization**: New `show_schedules` table for multiple patterns per show
- **Priority System**: Original broadcasts get priority 1, repeats get 2+
- **Backward Compatibility**: Existing single schedules continue working unchanged
- **Comprehensive Testing**: 17 test cases covering all parsing scenarios

### ✅ ON-AIR Indicator System (Completed July 28, 2025)
- **Real-Time Recording Status**: Live visual indicators for shows currently being recorded
- **Animated UI Elements**: Pulsing red ON-AIR badges with progress tracking
- **Smart Placement**: Positioned below Active/Inactive status to avoid title overlap
- **Progress Tracking**: Shows elapsed time, remaining time, and completion percentage
- **API Integration**: `/api/recording-status.php` provides real-time recording data
- **JavaScript Manager**: Automatic status updates every 30 seconds
- **Browser Integration**: Page title updates with 🔴 indicator during recordings
- **Site-wide Banners**: Recording notifications appear across all pages
- **Stations Page Integration**: ON-AIR functionality extended to stations view

### ✅ TTL (Time-to-Live) Recording Management (Completed July 25, 2025)
- **Configurable Expiry**: Default 2-week retention with days/weeks/months/indefinite options
- **Per-Show Defaults**: Individual show TTL settings with override capabilities
- **Per-Recording Override**: Individual recording TTL management via UI
- **Automatic Cleanup**: Daily cron job removes expired recordings
- **Database Integration**: TTL columns added to recordings and shows tables
- **Management Interface**: Full UI for TTL configuration and monitoring

### ✅ Enhanced Schedule Parsing (Completed July 25, 2025)
- **Natural Language Support**: Handles "Mondays at 7 PM", "noon", "midnight" formats
- **Improved Accuracy**: Fixed parsing edge cases and time format issues
- **Database Caching**: Stores successful parsing strategies per station
- **Wombats & Music Fix**: Updated schedule from Saturdays 2 PM to Mondays 7 PM

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

### ✅ Enhanced Recording Service v2.0 (Completed)
- **Complete Rewrite**: Rewrote recording_service.py with database-driven architecture
- **Integration**: Fully integrated with proven test recording service strategies
- **Duplicate Prevention**: Built-in duplicate recording detection prevents concurrent recordings (30-min window)
- **Quality Validation**: Enhanced recording quality validation and error handling (2KB/sec minimum)
- **User-Agent Support**: Integrated User-Agent persistence and stream discovery from test service
- **Architecture Sync**: Full synchronization with app architecture and test service patterns
- **Command Line Tools**: `--stats`, `--schedule-status`, `--test-show`, `--manual-show` options
- **Database Cleanup**: Removed duplicate recordings, maintaining only one valid recording per show/time

### 🛠️ Enhanced Recording Service Architecture

#### Database-Driven Design:
```python
# Uses same proven recording function as test service
from backend.services.test_recording_service import perform_recording

# Key features:
- Database station settings (User-Agent, call letters, stream URLs)
- Duplicate detection (prevents recordings within 30 minutes)
- Quality validation (file size, format verification)  
- Call letters filename format (WYSO_ShowName_20250727_1400.mp3)
- Retention policy cleanup (show-specific retention days)
- Station test status updates on every recording
```

#### Command Line Interface:
```bash
# Enhanced recording service commands:
/opt/radiograb/venv/bin/python backend/services/recording_service.py --stats
/opt/radiograb/venv/bin/python backend/services/recording_service.py --schedule-status
/opt/radiograb/venv/bin/python backend/services/recording_service.py --test-show 5
/opt/radiograb/venv/bin/python backend/services/recording_service.py --manual-show 5 --duration 600
/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon

# Stats output example:
=== Recording Statistics ===
Total recordings: 1
Total size: 54.99 MB
Recent recordings (7 days): 1
```

#### Integration Benefits:
- **Unified Architecture**: Uses same recording strategies as working test service
- **Error Handling**: Comprehensive error tracking and station status updates
- **Multi-Tool Support**: streamripper, ffmpeg, wget with automatic tool selection
- **User-Agent Handling**: Integrated saved User-Agent support for HTTP 403 issues
- **Stream Discovery**: Full integration with Radio Browser API and stream rediscovery
- **File Management**: Proper call letters naming, AAC-to-MP3 conversion, quality validation

### ✅ WYSO Schedule Fixes & Day-of-Week Calculation Bug (Completed - July 28, 2025)
- **Critical Bug Fixed**: APScheduler day-of-week numbering conflict resolved 
  - Issue: APScheduler uses 0=Monday, cron uses 0=Sunday
  - Fix: Added conversion function `apscheduler_day = (cron_day - 1) % 7`
  - Result: Next recordings now show correct times instead of all showing same 9 AM
- **WYSO Schedule Research**: Researched actual WYSO schedules via web search
  - "The Dear Green Place": Fixed from weekdays 9 AM to Sunday 1-3 PM
  - "Down Home Bluegrass": Fixed from weekdays 9 AM to Saturday 6-8 PM  
  - "Rise When the Rooster Crows": Fixed from weekdays 9 AM to Sunday 6-8 AM
- **Production Verification**: All changes deployed and working correctly

### ✅ Weekly Schedule Verification System (Completed - July 28, 2025)
- **Automated Verification**: Created `schedule_verification_service.py` with weekly automated checking
- **Schedule Change Detection**: Automatically detects schedule changes, adds new shows, deactivates removed ones
- **Web Interface Integration**: Added schedule verification widget to shows page with real-time status
- **Verification Status Tracking**: Database tracking of last_tested, last_test_result, last_test_error per station
- **Manual Verification**: "Verify All Now" button for immediate schedule checking
- **Comprehensive Logging**: JSON change tracking and detailed verification logs
- **Cron Integration**: Weekly verification every Sunday at 2 AM, monthly forced verification

### ✅ Database Backup System (Completed - July 28, 2025) 
- **Automated Weekly Backups**: Every Sunday at 3 AM with 3-week retention
- **Backup Format**: Timestamped and gzipped (`radiograb_backup_YYYYMMDD_HHMMSS.sql.gz`)
- **Storage Location**: `/var/radiograb/backups/` with automatic cleanup
- **Manual Backup Capability**: Script can be run manually for immediate backups
- **Comprehensive Logging**: Backup operations logged to `/var/radiograb/backups/backup.log`
- **Error Handling**: Complete error checking and validation of backup integrity
- **Local Download**: Manual database backup completed and downloaded locally

### ✅ Enhanced Station Management (In Progress - July 28, 2025)
- **Calendar Verification Status**: Added "Calendar verified" display to stations page
- **Manual Re-check Buttons**: "Re-check calendar now" buttons for each station
- **Git Repository Permissions**: Fixed radiograb user git permissions for proper deployment workflow
- **File Permissions**: Corrected ownership issues to enable non-root deployment operations

### ✅ Logo and Social Media System (Completed July 2025)
- **Local Logo Storage**: All station logos downloaded and stored in `/var/radiograb/logos/`
- **Facebook Logo Extraction**: Automatic fallback to Facebook profile pictures when website logos unavailable
- **Social Media Integration**: Detection and display of 10+ social platforms (Facebook, Twitter, Instagram, YouTube, etc.)
- **Consistent Logo Sizing**: All logos displayed at uniform 60x60px with proper aspect ratio
- **Image Optimization**: Logos resized to max 400x400px and optimized for web delivery
- **Database Extensions**: JSON storage for social media links with platform metadata
- **API Management**: Bulk and individual station logo/social media updates via API

### New Services (2025-07-29)
- **logo_storage_service.py**: Downloads, optimizes, and stores station logos locally
- **facebook_logo_extractor.py**: Extracts profile pictures from Facebook pages  
- **social_media_detector.py**: Detects and categorizes social media links
- **station-logo-update.php**: API for bulk updating station logos and social media

### ✅ Logo and Social Media System (COMPLETED July 29, 2025)
- **Local Logo Storage**: ✅ All station logos downloaded and stored in `/var/radiograb/logos/`
  - WEHC: Facebook profile picture extracted and optimized (241x257px)
  - WTBR: Logo size issue resolved with local optimization (250x150px)  
  - WERU, WYSO, KULT: All logos optimized and stored locally
- **Facebook Logo Extraction**: ✅ Automatic fallback to Facebook profile pictures fully operational
- **Social Media Integration**: ✅ Detection and display of 10+ social platforms
  - WEHC: Facebook, Instagram, Spotify icons active
  - WYSO: Facebook, Instagram, YouTube, LinkedIn icons active
  - All other stations: Website links displayed
- **Consistent Logo Sizing**: ✅ All logos display at uniform 60x60px with proper aspect ratio
- **Image Optimization**: ✅ Logos resized to max 400x400px and optimized for web delivery
- **Database Extensions**: ✅ JSON storage for social media links with platform metadata
- **API Management**: ✅ Bulk and individual station logo/social media updates via API

### 🔄 Known Outstanding Issues
- **Schedule Parser Integration**: Need to fix `parse_station_schedule` method for full verification functionality
- **Calendar URL Configuration**: Some stations lack calendar URLs for automated verification

---

**🔄 Remember: Docker containers = isolated filesystem. Host file copies ≠ live site updates!**
**✅ PUBLIC REPOSITORY: `./deploy-from-git.sh` pulls from GitHub automatically - no manual file copying needed!**
**📋 DEPLOYMENT CHECKLIST: 1) git push 2) deploy script 3) verify site works**
**🐍 Always: `/opt/radiograb/venv/bin/python` for Python execution**
**🔒 SSL: Persistent volumes ensure certificates survive container rebuilds**
**🚨 Database: All MySQL connections must use environment variables (DB_HOST=mysql, DB_PORT=3306)**
**📞 Call Signs: All new recordings use 4-letter call signs (WEHC, WERU, WTBR, WYSO) for easy identification**