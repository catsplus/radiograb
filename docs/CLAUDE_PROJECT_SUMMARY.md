# RadioGrab Project Summary & Goals

## Project Overview
**RadioGrab** is a comprehensive radio station recording and management system - essentially a "Radio Recording System for radio stations." It automatically discovers, schedules, and records radio shows from streaming stations, supports user playlist uploads with comprehensive metadata management, and makes everything available via RSS feeds for podcast consumption.

## Core Functionality
- **Station Discovery**: Automatically crawl radio station websites to discover streaming URLs, logos, schedules, and metadata
- **Show Scheduling**: Import show schedules and convert them to automated recording schedules
- **Automated Recording**: Record radio shows using streamripper based on cron schedules
- **Playlist Upload System**: User audio file uploads with multi-format support and drag & drop track ordering
- **MP3 Metadata Management**: Comprehensive metadata writing for all recordings and uploads (artist=show name, album=station name, etc.)
- **Logo & Social Media System**: Local logo storage with Facebook fallback and social media integration for 10+ platforms
- **RSS Feed Generation**: Create podcast-style RSS feeds of recorded shows and playlists
- **Web Management Interface**: Complete web UI for managing stations, shows, recordings, and playlists with visual social media integration

## Technical Architecture ⚠️ DOCKER CONTAINERS ⚠️
- **Frontend**: PHP-based web interface with Bootstrap UI (RUNS IN `radiograb-web-1` CONTAINER)
- **Backend**: Python services for discovery, recording, upload processing, MP3 metadata management, and RSS generation (RUNS IN CONTAINERS)
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
  - `mp3_metadata_service.py`: MP3 metadata writing with FFmpeg
  - `upload_service.py`: Audio file upload processing and validation
  - `recording_service.py`: Automated recording management
  - `rss_service.py`: RSS feed generation with playlist support
  - `logo_storage_service.py`: Local logo download, optimization, and storage
  - `facebook_logo_extractor.py`: Facebook profile picture extraction
  - `social_media_detector.py`: Social media link detection and categorization
- **Database Config**: `/opt/radiograb/backend/config/database.py`

**radiograb-recorder-1 Container:**
- **Application Root**: `/opt/radiograb/`
- **Recording Service**: `/opt/radiograb/backend/services/recording_service.py`
- **Station Discovery**: `/opt/radiograb/backend/services/station_discovery.py`
- **Models**: `/opt/radiograb/backend/models/station.py`
- **Recordings Directory**: `/var/radiograb/recordings/`
- **Temp Directory**: `/var/radiograb/temp/`
- **Logs Directory**: `/var/radiograb/logs/`
- **Logos Directory**: `/var/radiograb/logos/` (local station logos and social media images)

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
- **mutagen**: MP3 metadata reading and writing for uploaded files
- **ffmpeg-python**: FFmpeg integration for audio processing and metadata writing

**System Dependencies:**
- **Python 3.10**: Runtime environment
- **MySQL 8.0**: Database server
- **Ubuntu 22.04**: Base container OS

## Major Features Implemented

### Playlist Upload & Management System
- **Multi-Format Upload Support**: Upload MP3, WAV, M4A, AAC, OGG, FLAC with automatic MP3 conversion
- **Drag & Drop Track Ordering**: Real-time playlist management with track reordering interface
- **Upload Progress Tracking**: Visual progress indicators with comprehensive error handling
- **File Validation**: Audio format validation, size limits, and quality verification
- **Playlist Interface**: Dedicated management modal with drag & drop and manual track numbering

### MP3 Metadata Management
- **Comprehensive Metadata Writing**: All recordings tagged with artist=show name, album=station name, recording date, description
- **Upload Metadata Enhancement**: Preserves existing metadata while adding show/station information
- **FFmpeg Integration**: Backend service using FFmpeg for reliable metadata writing
- **Automatic Tagging**: Genre support and metadata source tracking
- **Service Architecture**: Dedicated `mp3_metadata_service.py` for consistent metadata management

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
- **Automatic RSS generation**: Create podcast feeds for each show and playlist
- **Master feed**: Combined RSS feed of all shows and playlists for single subscription
- **Playlist Support**: RSS feeds include uploaded tracks with proper track ordering
- **iTunes-compatible**: Proper podcast metadata and enclosures with enhanced MP3 metadata
- **Feed updates**: Regular RSS feed updates with new recordings and uploads
- **Web access**: Direct HTTP access to recordings, uploads, and feeds
- **Copy-to-clipboard**: Robust clipboard functionality for easy feed sharing
- **QR codes**: Mobile-friendly QR codes for podcast app subscriptions

## Key Business Goals
1. **Ease of Use**: Users should only need to provide a station website URL
2. **Automation**: System should discover and set up everything automatically
3. **Reliability**: Consistent recording and feed generation
4. **Scalability**: Support for many stations without manual configuration
5. **Flexibility**: Handle different station formats and schedule types

## Recent Major Accomplishments
- **Complete Playlist Upload System**: Implemented multi-format audio file uploads (MP3, WAV, M4A, AAC, OGG, FLAC) with drag & drop track ordering, automatic MP3 conversion, and comprehensive file validation
- **MP3 Metadata Implementation**: Added comprehensive metadata writing for all recordings and uploads using FFmpeg integration (artist=show name, album=station name, recording date, description, genre)
- **Database Schema Extensions**: Extended database with playlist support fields (show_type, allow_uploads, max_file_size_mb, source_type, track_number, original_filename) and proper migrations
- **Enhanced Web Interface**: Added show type selection (scheduled/playlist), upload functionality, playlist management modal with drag & drop reordering, and real-time progress tracking
- **RSS Feed Playlist Support**: Enhanced RSS generation to include uploaded tracks with proper track ordering and metadata integration
- **Legal Compliance Updates**: Replaced all "TiVo for Radio" references with legally neutral "Radio Recorder" terminology throughout codebase and documentation
- **UI Improvements**: Hidden empty On-Demand Recording shows, removed timezone display from show blocks, enhanced upload/playlist management interface
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
- **Master RSS Feed**: Added combined RSS feed functionality that aggregates all recorded shows and playlists into a single chronological feed for easier podcast subscription management
- **Enhanced Copy-to-Clipboard**: Implemented robust clipboard functionality with fallback support for HTTP/HTTPS and cross-browser compatibility
- **JavaScript-Aware Schedule Parsing**: Complete Selenium-based solution for parsing dynamic calendar content, including embedded Google Sheets, WordPress plugins, and AJAX-loaded schedules - solved parsing issues for stations like WTBR that use JavaScript-rendered calendars

## Current Status
- **Deployment**: Live on radiograb.svaha.com with Docker containers
- **Functionality**: COMPLETE END-TO-END SYSTEM OPERATIONAL with playlist upload support
- **Live Recording**: Successfully verified with ALL stations (WEHC/WERU/WYSO)
- **Playlist System**: Full upload/management functionality with MP3 metadata integration
- **Stream Discovery**: Headless Chrome+Selenium solution for JavaScript players
- **Recording Tools**: Multi-tool support (streamripper/wget/ffmpeg) for universal compatibility
- **Stream Testing**: Automatic compatibility validation with 100% success rate prediction
- **Performance**: Handles real-world station formats, schedules, and user uploads
- **Version**: v2.11.0 with comprehensive playlist and metadata capabilities

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
- **Frontend**: `frontend/public/` - PHP web interface with playlist upload functionality
- **Backend**: `backend/services/` - Python services
  - **MP3 Metadata**: `backend/services/mp3_metadata_service.py`
  - **Upload Processing**: `backend/services/upload_service.py`
  - **Discovery**: `backend/services/station_discovery.py`
  - **Calendar Parsing**: `backend/services/calendar_parser.py`, `backend/services/js_calendar_parser.py`
  - **Schedule Import**: `backend/services/schedule_importer.py`
  - **RSS Management**: `backend/services/rss_manager.py`, `backend/services/rss_service.py`
- **API Endpoints**: 
  - `frontend/public/api/feeds.php`, `frontend/public/api/master-feed.php`
  - `frontend/public/api/upload.php` - Audio file upload processing
  - `frontend/public/api/playlist-tracks.php` - Playlist management
- **Database**: `database/schema.sql` with playlist support extensions
- **Config**: `docker-compose.yml`, `.env`, `requirements.txt` with new dependencies

## Success Metrics
- **Station addition time**: Should take <5 minutes from URL to working recordings
- **Discovery accuracy**: >90% success rate for finding streams and schedules
- **Recording reliability**: Consistent, scheduled recordings without manual intervention
- **User experience**: Intuitive interface requiring minimal technical knowledge

This is a sophisticated system that transforms radio stations into on-demand podcast-style content through automation and intelligent discovery.