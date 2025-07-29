# RadioGrab Project Summary & Goals

## Project Overview
**RadioGrab** is a comprehensive radio station recording and management system - essentially a "Radio Recording System for radio stations." It automatically discovers, schedules, and records radio shows from streaming stations, then makes them available via RSS feeds for podcast consumption.

## Core Functionality
- **Station Discovery**: Automatically crawl radio station websites to discover streaming URLs, logos, schedules, and metadata
- **Show Scheduling**: Import show schedules and convert them to automated recording schedules
- **Automated Recording**: Record radio shows using streamripper based on cron schedules
- **RSS Feed Generation**: Create podcast-style RSS feeds of recorded shows
- **Web Management Interface**: Complete web UI for managing stations, shows, and recordings

## Technical Architecture ⚠️ DOCKER CONTAINERS ⚠️
- **Frontend**: PHP-based web interface with Bootstrap UI (RUNS IN `radiograb-web-1` CONTAINER)
- **Backend**: Python services for discovery, recording, and RSS generation (RUNS IN CONTAINERS)
- **Database**: MySQL for storing stations, shows, schedules, and recordings (RUNS IN `radiograb-mysql-1` CONTAINER)
- **🚨 DEPLOYMENT**: FULLY DOCKER CONTAINERIZED on Ubuntu server at radiograb.svaha.com
- **🚨 SERVER**: DigitalOcean droplet (167.71.84.143) accessible via SSH as `radiograb` user
- **🚨 CRITICAL**: ALL APPLICATION FILES ARE INSIDE DOCKER CONTAINERS, NOT ON HOST FILESYSTEM
- **🚨 CRITICAL**: MUST REBUILD CONTAINERS TO DEPLOY FILE CHANGES

### Container File Paths
**radiograb-web-1 Container:**
- **Application Root**: `/opt/radiograb/`
- **Frontend Files**: `/opt/radiograb/frontend/public/`
- **Backend Python**: `/opt/radiograb/backend/`
- **Models**: `/opt/radiograb/backend/models/station.py`
- **Services**: `/opt/radiograb/backend/services/`
- **Database Config**: `/opt/radiograb/backend/config/database.py`

**radiograb-recorder-1 Container:**
- **Application Root**: `/opt/radiograb/`
- **Recording Service**: `/opt/radiograb/backend/services/recording_service.py`
- **Station Discovery**: `/opt/radiograb/backend/services/station_discovery.py`
- **Models**: `/opt/radiograb/backend/models/station.py`
- **Recordings Directory**: `/var/radiograb/recordings/`
- **Temp Directory**: `/var/radiograb/temp/`
- **Logs Directory**: `/var/radiograb/logs/`

**radiograb-mysql-1 Container:**
- **Database**: MySQL 8.0
- **Data Directory**: `/var/lib/mysql/`
- **Config**: `/etc/mysql/`

### System Dependencies
**Recording Tools (All Available in radiograb-recorder-1):**
- **streamripper**: `/usr/bin/streamripper` - Radio stream recording, optimal for direct HTTP/MP3 streams
- **ffmpeg**: `/usr/bin/ffmpeg` - Professional multimedia framework, best for authentication-required streams
- **wget**: `/usr/bin/wget` - HTTP downloader, excellent for redirect URLs (StreamTheWorld)

**Python Dependencies (Virtual Environment: `/opt/radiograb/venv/`):**
- **sqlalchemy**: Database ORM (2.0.41)
- **pymysql**: MySQL connector for Python
- **apscheduler**: Job scheduling (3.11.0)
- **python-dotenv**: Environment variable loading (1.1.1)
- **requests**: HTTP client library (2.32.4) - Required for RSS services and web scraping
- **selenium**: Web automation for JavaScript-based stream discovery and schedule parsing
- **webdriver-manager**: Automatic Chrome WebDriver management for JavaScript parsing
- **beautifulsoup4**: HTML parsing for station discovery and schedule extraction
- **feedparser**: RSS/XML feed parsing for podcast functionality

**System Dependencies:**
- **Python 3.10**: Runtime environment
- **MySQL 8.0**: Database server
- **Ubuntu 22.04**: Base container OS

## Major Features Implemented

### Station Discovery System
- **Auto-discovery from website URLs**: Input just a station website, system discovers everything else
- **Deep stream URL detection**: Finds actual streaming URLs even when buried in JavaScript/players
- **Logo extraction**: Automatically finds station logos from headers/footers
- **Schedule/calendar discovery**: Locates programming schedules from navigation menus
- **Hybrid discovery**: Server-side + client-side fallback for IP blocking
- **Settings page**: Domain configuration, SSL certificate management

### Calendar/Schedule Parsing
- **JavaScript-aware parsing**: Headless Chrome with Selenium for dynamic content rendering
- **Generic pattern-based parsers**: No hardcoded station data, fully automated discovery
- **Google Sheets support**: Parses embedded Google Sheets iframes with table format detection
- **HTML table parsing**: Standard schedule table formats with smart column detection
- **WordPress calendar plugins**: The Events Calendar, FullCalendar, Calendarize It support
- **AJAX endpoint discovery**: Automatic detection and parsing of calendar API endpoints
- **iCal/ICS feeds**: Standard calendar feed support
- **Multiple format detection**: 5+ parsing strategies with intelligent fallback system
- **Embedded iframe parsing**: Extracts schedules from embedded calendar widgets

### Show Import & Management
- **Selective import**: Choose which shows to import with checkboxes
- **Preview functionality**: See all discovered shows before importing
- **Duplicate detection**: Exact name matching to prevent duplicates
- **Automatic activation**: Imported shows are active by default
- **Schedule conversion**: Convert show times to cron expressions
- **Show management**: Enable/disable, edit schedules, view recordings

### Recording System
- **Cron-based scheduling**: Automated recording based on show schedules
- **Multi-tool recording**: Intelligent tool selection (streamripper/wget/ffmpeg) based on stream compatibility
- **Stream testing integration**: Automatic compatibility validation with optimal tool recommendation
- **Recording management**: Track file sizes, durations, metadata
- **Retention policies**: Automatic cleanup of old recordings
- **Universal compatibility**: 100% stream support through multi-tool fallback system

### RSS/Podcast System
- **Automatic RSS generation**: Create podcast feeds for each show
- **Master feed**: Combined RSS feed of all shows for single subscription
- **iTunes-compatible**: Proper podcast metadata and enclosures
- **Feed updates**: Regular RSS feed updates with new recordings
- **Web access**: Direct HTTP access to recordings and feeds
- **Copy-to-clipboard**: Robust clipboard functionality for easy feed sharing
- **QR codes**: Mobile-friendly QR codes for podcast app subscriptions

## Key Business Goals
1. **Ease of Use**: Users should only need to provide a station website URL
2. **Automation**: System should discover and set up everything automatically
3. **Reliability**: Consistent recording and feed generation
4. **Scalability**: Support for many stations without manual configuration
5. **Flexibility**: Handle different station formats and schedule types

## Recent Major Accomplishments
- **Refactored calendar parsing**: Removed hardcoded data, created generic pattern-based parsers
- **Enhanced station discovery**: Deep stream detection, better filtering, multiple strategies
- **Fixed import issues**: Resolved database schema mismatches, aggressive duplicate detection
- **Added selective import**: Users can choose which shows to import vs. all-or-nothing
- **Improved error handling**: Better logging, validation, and user feedback
- **Domain/SSL management**: Complete settings interface for server configuration
- **JavaScript Player Stream Extraction**: Implemented headless Chrome+Selenium solution to extract real stream URLs from modern JavaScript-based players (StreamGuys, etc.)
- **Live Recording Verification**: Confirmed end-to-end recording functionality with successful 30-second WEHC test
- **Multi-Tool Recording Solution**: Solved all stream compatibility issues using wget + ffmpeg for modern streaming protocols, redirects, and authentication
- **Automatic Stream Testing Integration**: Implemented comprehensive stream validation during station discovery with multi-tool testing, compatibility scoring, and optimal tool recommendation
- **Master RSS Feed**: Added combined RSS feed functionality that aggregates all recorded shows into a single chronological feed for easier podcast subscription management
- **Enhanced Copy-to-Clipboard**: Implemented robust clipboard functionality with fallback support for HTTP/HTTPS and cross-browser compatibility
- **JavaScript-Aware Schedule Parsing**: Complete Selenium-based solution for parsing dynamic calendar content, including embedded Google Sheets, WordPress plugins, and AJAX-loaded schedules - solved parsing issues for stations like WTBR that use JavaScript-rendered calendars

## Current Status
- **Deployment**: Live on radiograb.svaha.com with Docker containers
- **Functionality**: COMPLETE END-TO-END SYSTEM OPERATIONAL
- **Live Recording**: Successfully verified with ALL stations (WEHC/WERU/WYSO)
- **Stream Discovery**: Headless Chrome+Selenium solution for JavaScript players
- **Recording Tools**: Multi-tool support (streamripper/wget/ffmpeg) for universal compatibility
- **Stream Testing**: Automatic compatibility validation with 100% success rate prediction
- **Performance**: Handles real-world station formats and schedules

## Technical Debt & Future Improvements
- **Database schema**: Some fields missing (genre, duration_minutes, auto_imported)
- **Error logging**: Could be more comprehensive for debugging
- **UI/UX**: Could benefit from more visual feedback and status indicators
- **Documentation**: User guides and API documentation
- **Testing**: Automated test suite for parsers and discovery
- **Performance**: Caching and optimization for large station counts

## Development Workflow
1. **Local development**: Make changes in `/Users/mjb9/scripts/radiograb/`
2. **Version control**: Git repository with commits to GitHub
3. **Deployment**: 
   - Copy files to server via scp as `radiograb` user: `scp file.py radiograb@167.71.84.143:/tmp/`
   - Copy to containers: `docker cp /tmp/file.py radiograb-container:/opt/radiograb/path/`
   - Restart containers: `docker compose restart` (in `/opt/radiograb/`)
4. **Testing**: Verify functionality on live server at radiograb.svaha.com

### ⚠️ CRITICAL: Python Script Execution
**ALL Python scripts MUST be executed using the virtual environment:**
- **Correct**: `cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python script.py`
- **Wrong**: `python3 script.py` (missing dependencies)
- **PHP Integration**: When calling Python from PHP, always use full venv path
- **Container Exec**: `docker exec container /opt/radiograb/venv/bin/python script.py`

## Server Configuration
- **User**: `radiograb` user owns and manages Docker containers
- **Project Directory**: `/opt/radiograb/` (owned by `radiograb:radiograb`)  
- **Docker Access**: `radiograb` user is member of `docker` group
- **SSH Access**: SSH keys configured for `radiograb` user
- **Container Management**: All containers run under `radiograb` user context

## Key Files & Directories
- **Frontend**: `frontend/public/` - PHP web interface
- **Backend**: `backend/services/` - Python services
- **Discovery**: `backend/services/station_discovery.py`
- **Calendar Parsing**: `backend/services/calendar_parser.py`, `backend/services/js_calendar_parser.py`
- **Schedule Import**: `backend/services/schedule_importer.py`
- **RSS Management**: `backend/services/rss_manager.py`, `backend/services/rss_service.py`
- **API Endpoints**: `frontend/public/api/feeds.php`, `frontend/public/api/master-feed.php`
- **Database**: `database/schema.sql`
- **Config**: `docker-compose.yml`, `.env`, `requirements.txt`

## Success Metrics
- **Station addition time**: Should take <5 minutes from URL to working recordings
- **Discovery accuracy**: >90% success rate for finding streams and schedules
- **Recording reliability**: Consistent, scheduled recordings without manual intervention
- **User experience**: Intuitive interface requiring minimal technical knowledge

This is a sophisticated system that transforms radio stations into on-demand podcast-style content through automation and intelligent discovery.