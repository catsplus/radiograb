# CLAUDE.md - RadioGrab System Reference

## 🚨 PRODUCTION SERVER & DEPLOYMENT

### Server Details
- **Domain**: https://radiograb.svaha.com
- **Server**: 167.71.84.143 (AlmaLinux 9)
- **SSH Access**: 
  - `root@167.71.84.143` - Full root access for system administration
  - `radiograb@167.71.84.143` - Limited user for deployments
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
- **Test & On-Demand**: 10-second tests + manual recordings with duplicate prevention
- **Call Letters Format**: `WYSO_ShowName_20250727_1400.mp3` naming
- **RSS Feeds**: Individual show feeds + master combined feed
- **User Authentication**: Multi-user system with admin access and data isolation
- **Cloud Storage Integration**: AWS S3/Backblaze B2 primary storage with auto-upload
- **Transcription Services**: Multi-provider AI transcription (OpenAI, DeepInfra, etc.)
- **Retention Policies**: Configurable TTL with automatic cleanup
- **Real-time Status**: ON-AIR indicators, progress tracking, browser notifications

### Generic Architecture
- **No Station-Specific Code**: All parsers completely generic and reusable
- **ISO Timestamp Parser**: `_parse_iso_timestamp_json_schedule()` for timezone-aware JSON calendars
- **Show Links Parser**: `_parse_show_links_schedule()` for HTML with show links/program elements  
- **StreamTheWorld Fallback**: Generic HD2→HD1→base quality fallback
- **Smart Logo Detection**: Intelligent scoring system with homepage priority
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

## 🌐 JAVASCRIPT-AWARE SCHEDULE DISCOVERY

### Calendar Parsing System
- **Chromium WebDriver**: Uses `google-chrome-stable` for JavaScript execution
- **Dynamic Content**: Handles calendars that load via JavaScript/AJAX
- **WordPress Support**: Specialized parsers for Calendarize It, The Events Calendar, FullCalendar
- **Fallback Strategy**: Gracefully falls back to standard HTML parsing if WebDriver fails
- **Manual ICS Import**: AI-powered schedule conversion workflow when automatic discovery fails

### API Endpoints
- **`/api/discover-station-schedule.php`**: Station schedule discovery
- **`/api/schedule-verification.php`**: Calendar verification and testing
- **`/api/import-schedule-ics.php`**: Manual ICS file upload and parsing

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

# Email (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_FROM=noreply@yourdomain.com
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password

# Cloud Storage & API Security
API_ENCRYPTION_KEY=<base64-encoded-encryption-key>
```

### Key Dependencies
- **APScheduler**: Job scheduling
- **SQLAlchemy + pymysql**: Database ORM
- **BeautifulSoup4**: HTML parsing
- **Selenium**: JavaScript-aware parsing
- **google-chrome-stable**: Required for Selenium WebDriver (⚠️ CRITICAL: Ubuntu chromium-browser is BROKEN)
- **webdriver-manager**: Automatically downloads compatible ChromeDriver
- **requests**: HTTP client
- **Pillow**: Image processing for logo optimization
- **python-dateutil**: ISO timestamp parsing with timezone support
- **boto3**: AWS S3 cloud storage integration
- **deepinfra**: AI transcription service client
- **cryptography**: API key encryption and security
- **rclone**: Multi-backend remote storage (Google Drive, SFTP, Dropbox, OneDrive)
- **msmtp**: SMTP email relay for container

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
- User authentication with admin access
- API key encryption (AES-256-GCM)

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

# MySQL Access
ssh radiograb@167.71.84.143 "docker exec -it radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb"
```

## 🖼️ FILE STRUCTURE

### Key Services
- **recording_service.py**: Main daemon with APScheduler
- **test_recording_service.py**: 10-second tests and on-demand recording
- **stream_discovery.py**: Radio Browser API + web scraping
- **station_auto_test.py**: Automated testing with rediscovery
- **rss_manager.py**: RSS feed generation
- **housekeeping_service.py**: File cleanup
- **js_calendar_parser.py**: JavaScript-aware calendar parsing
- **s3_upload_service.py**: Cloud storage integration
- **transcription_service.py**: Multi-provider AI transcription

### Web Interface
- **Main Pages**: index.php, stations.php, shows.php, recordings.php, playlists.php, feeds.php
- **API Endpoints**: test-recording.php, get-csrf-token.php, discover-station.php
- **Assets**: radiograb.css, radiograb.js (audio player, CSRF, modals)

### Database Schema
- **stations**: id, user_id, call_letters, stream_url, last_tested, last_test_result, is_private
- **shows**: id, station_id, schedule_pattern, retention_days, user_id, stream_only, content_type
- **recordings**: id, show_id, filename, recorded_at, file_size_bytes, track_number, source_type
- **users**: id, username, email, password_hash, is_admin, email_verified_at
- **user_api_keys**: id, user_id, service_type, encrypted_credentials, is_active
- **user_s3_configs**: id, user_id, provider, bucket_name, region, storage_mode
- **custom_feeds**: id, name, description, slug, custom_title, feed_type, is_public
- **show_schedules**: id, show_id, schedule_pattern, priority, description

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

## ⚠️ UBUNTU 22.04 CHROMIUM DEPENDENCY ISSUE

### 🚨 CRITICAL: Broken Chromium Package
Ubuntu 22.04's `chromium-browser` package is **BROKEN** and requires Snap installation.

### ✅ SOLUTION: Use Google Chrome
```bash
# ✅ CORRECT - Use in Dockerfile
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome-keyring.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome-keyring.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list
apt-get update && apt-get install -y google-chrome-stable
```

## 🌐 FRIENDLY URL ROUTING SYSTEM

### URL Structure
- **Station pages**: `/{call_letters}` (e.g., `/weru`, `/wehc`)
- **Show pages**: `/{call_letters}/{show_slug}` (e.g., `/weru/fresh_air`)
- **User pages**: `/user/{username}` (e.g., `/user/mattbaya`)
- **Playlist pages**: `/user/{username}/{playlist_slug}`

### Technical Implementation
- **PHP Routing**: RadioGrabRouter class handles URL parsing
- **Database Slugs**: URL-friendly slugs for stations, shows, users
- **SEO Optimization**: Clean URLs with proper meta tags and Open Graph data

## 🌐 CLOUD STORAGE SYSTEM

### Supported Providers
- **S3-Compatible**: AWS S3, Backblaze B2, DigitalOcean Spaces, Wasabi
- **Rclone Backends**: Google Drive, SFTP, Dropbox, OneDrive, Box, pCloud

### Storage Modes
- **Primary**: Recordings stored directly in cloud, served via public URLs
- **Backup**: Local files with cloud backup copies
- **Off**: No cloud interaction (default for new users)

### Technical Implementation
- **S3 Upload Service**: `s3_upload_service.py` with boto3 integration
- **Rclone Service**: `rclone_service.py` with multi-backend support
- **API Key Management**: Secure credential storage with AES-256-GCM encryption
- **Auto-Upload**: Automatic background upload of new recordings

## 🎤 TRANSCRIPTION SYSTEM

### Supported Providers
- **OpenAI Whisper**: $0.006/minute (premium accuracy)
- **DeepInfra Whisper**: $0.0006/minute (cost-effective)
- **BorgCloud**: Custom pricing
- **AssemblyAI**: $0.0025/minute (real-time support)
- **Groq**: Fast inference with competitive pricing

### Technical Architecture
- **Unified Service**: `transcription_service.py` with provider abstraction
- **Database Integration**: `transcription_jobs` table with progress tracking
- **API Key Management**: Secure multi-provider credential storage
- **Cost Estimation**: Real-time pricing calculation before transcription

## 📧 EMAIL SYSTEM

### SMTP Email Functionality
- **Password Reset**: HTML email templates with secure token-based reset
- **Email Verification**: User registration verification with 24-hour token expiration
- **Admin Testing**: Comprehensive testing utility at `/admin/test-email.php`
- **Multiple Providers**: Support for Gmail, SendGrid, Amazon SES, Mailgun

### Container Configuration
- **msmtp**: Lightweight SMTP relay installed in Docker
- **Environment Variables**: All credentials stored securely in environment
- **TLS Encryption**: All SMTP connections use TLS/SSL encryption

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

### Testing Requirements
**CRITICAL**: All tests should simulate actual user browser interactions using Chrome browser:
- **Calendar Verification**: Test through web interface (not direct API calls)
- **Browser CSRF Workflow**: Use actual browser-based token workflows
- **User Interaction Simulation**: Actual clicks, form submissions, page interactions
- **JavaScript Execution**: Selenium WebDriver with Chrome for dynamic content

---

**🚨 CRITICAL REMINDERS**
- **Deployment**: 1) git push 2) `./deploy-from-git.sh` 3) verify site
- **Python**: Always use `/opt/radiograb/venv/bin/python` with `PYTHONPATH=/opt/radiograb`
- **Database**: Use environment variables (DB_HOST=mysql)
- **Files**: Call sign format (WYSO_ShowName_timestamp.mp3)
- **Containers**: Host changes require rebuild - files are baked in!