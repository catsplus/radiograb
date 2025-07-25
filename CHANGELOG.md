# RadioGrab Changelog

## [2.0.1] - 2025-07-23 - Security & SSL Fixes

### 🔒 Security Fixes
- **Fixed CSRF Token Issue**: Added missing `session_start()` to test-recording.php API
- **Fixed Frontend CSRF Token Handling**: Modified JavaScript to fetch fresh CSRF tokens from API endpoint instead of using static page tokens
- **Resolved "Invalid security token" errors** in test recording and on-demand recording
- **Enhanced API Security**: All endpoints now properly protected with CSRF tokens and proper session management

### 🛡️ SSL/HTTPS Implementation
- **Complete SSL Setup**: Automated Let's Encrypt certificate installation
- **Production-Ready Security**: Modern TLS 1.2/1.3 with A+ SSL Labs rating
- **Container-Based SSL**: No host system modifications required
- **Automatic Renewal**: Certificates auto-renew twice daily via cron
- **Security Headers**: HSTS, CSP, X-Frame-Options, X-Content-Type-Options
- **HTTP to HTTPS Redirect**: Automatic secure connection enforcement

### 🔧 SSL Management Tools
- `check-domain.sh`: DNS configuration verification
- `setup-container-ssl.sh`: Complete SSL certificate installation
- `setup-ssl.sh`: Alternative host-based SSL setup
- Certificate management commands and troubleshooting guides

### 🔧 API and File Access Fixes
- **Fixed "Network error occurred during test recording"**: Removed conflicting `/api/` location block
- **PHP Processing**: Ensured all `.php` files (including `/api/*.php`) are processed by PHP-FPM
- **API Endpoint**: Test recording API now returns proper JSON responses instead of raw PHP
- **Fixed "Recording file not found" errors**: Corrected nginx location block priority
- **Nginx Configuration**: Proper `^~` prefix for exact location matching  
- **File Permissions**: Automated www-data ownership for all recording files
- **Location Priority**: Ensured `/recordings/` comes before general `/` location

### 📚 Documentation Updates
- **SSL Setup Guide**: Complete production SSL configuration process
- **Security Documentation**: CSRF protection and session management details
- **Troubleshooting**: SSL certificate management and recording file access issues
- **Updated README**: Production deployment with SSL instructions

## [2.0.0] - 2025-07-23 - Major System Overhaul

### 🎉 Major New Features
- **Test Recording System**: 30-second stream testing with one-click buttons on station cards
- **On-Demand Recording**: 1-hour manual recordings with automatic show creation
- **Automatic Housekeeping**: Service runs every 6 hours to clean empty files and orphaned records
- **JavaScript-Aware Schedule Parsing**: Selenium WebDriver support for dynamic calendars
- **Recording Method Intelligence**: Database storage of optimal recording tools per station

### 🏗️ Architecture Improvements
- **5-Container Docker Architecture**: Separated services for better isolation and reliability
  - `radiograb-web-1`: Web interface and APIs
  - `radiograb-recorder-1`: Recording daemon service
  - `radiograb-mysql-1`: Database with timezone support
  - `radiograb-rss-updater-1`: RSS feed generation (every 15 min)
  - `radiograb-housekeeping-1`: Automatic cleanup (every 6 hours)
- **Container-Wide Timezone**: All services use `TZ=America/New_York` for consistency
- **APScheduler Integration**: Python-based scheduling (not system cron)

### 🎯 Problem Resolutions
- **Fixed "No shows found" issue**: JavaScript-aware parsing handles WordPress calendars, FullCalendar, Google Sheets iframes
- **Eliminated 33,000+ empty files**: Housekeeping service prevents accumulation of zero-byte recordings
- **Resolved timezone confusion**: Per-station timezone storage with proper conversion
- **Recording tool optimization**: System stores and reuses optimal method per station

### 📁 File Organization
- **Station Call Sign Naming**: All files now use 4-letter call signs for easy identification
  - Test recordings: `{CALL_LETTERS}_test_YYYY-MM-DD-HHMMSS.mp3`
  - On-demand recordings: `{CALL_LETTERS}_on-demand_YYYY-MM-DD-HHMMSS.mp3`
  - Scheduled recordings: `{CALL_LETTERS}_{show_name}_YYYYMMDD_HHMM.mp3`

### 🚀 Deployment Automation
- **Full Deployment Script**: `./deploy.sh` - Comprehensive system deployment with verification
- **Quick Deployment Script**: `./quick-deploy.sh` - Single file deployment for rapid updates
- **Database Migration System**: Automatic migration application and verification
- **Container Health Monitoring**: Deployment verification and service restart automation

### 📊 Database Enhancements
- **Recording Method Storage**: 
  - `stations.recommended_recording_tool`: Optimal tool (streamripper/wget/ffmpeg)
  - `stations.stream_compatibility`: Compatibility status tracking
  - `stations.stream_test_results`: JSON test results storage
  - `stations.last_stream_test`: Test timestamp tracking
- **Timezone Fields**: Per-station and per-show timezone storage
- **Migration System**: Structured database schema updates

### 🌐 Web Interface Improvements
- **Test Recording Buttons**: 30-second stream testing from station cards
- **Record Now Buttons**: 1-hour on-demand recording with progress indication
- **Timezone Display**: Show station timezones in web interface
- **On-Demand Show Management**: Automatic creation of "{CALL} On-Demand Recordings" shows

### 🔧 Technical Improvements
- **JavaScript Calendar Support**: 5 parsing strategies with automatic fallback:
  1. Direct iframe content extraction
  2. JavaScript-rendered calendar parsing
  3. WordPress plugin detection (The Events Calendar, FullCalendar, Calendarize It)
  4. Google Sheets table parsing
  5. Generic calendar element detection
- **Multi-Tool Recording**: Intelligence system for optimal tool selection
- **Stream Testing**: Comprehensive compatibility validation with database storage
- **Error Prevention**: Proactive empty file prevention and cleanup

### 📚 Documentation Updates
- **Complete System Architecture Guide**: Comprehensive recording system documentation
- **Project Overview**: Mission, innovations, and technical architecture
- **Deployment Process**: Automated deployment with verification steps
- **GitHub README**: Updated with new features, architecture, and usage examples

### 🛠️ Development Tools
- **Automated Deployment**: Eliminates manual file copying and missed updates
- **Container-Aware Distribution**: Intelligent file routing to correct containers
- **Service Management**: Automatic container restart and health verification
- **Development Workflow**: Quick iteration with `./quick-deploy.sh` for single files

## [1.0.0] - Previous Version
- Basic radio station recording functionality
- Manual schedule parsing
- Single-container deployment
- PHP/Python hybrid architecture

---

**Breaking Changes**: 
- Database schema changes require migration application
- Container architecture requires `docker compose up -d` restart
- File naming conventions changed (station ID prefixed)

**Migration Path**: 
1. Apply database migrations: `./deploy.sh`
2. Restart containers: `docker compose restart`
3. Verify services: `docker compose ps`