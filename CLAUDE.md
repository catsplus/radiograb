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

## 📺 RECORDING STATUS SYSTEM

- **Smart Indicators**: Small `🔴 Recording` badges only appear for actively recording shows
- **Real-time Updates**: JavaScript checks every 30s via `/api/recording-status.php`
- **Compact Progress**: Minimal progress bars showing remaining time only
- **Contextual Banners**: Recording notifications only when shows are actually recording
- **Multiple Recording Support**: Clear display of simultaneous recordings with show names
- **Clean UI**: Removed redundant "Scheduled for automatic recording" messages

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

## 🆕 RECENT UPDATES (August 2025)

### ✅ Major Features Completed
- **🎵 Comprehensive Playlist Enhancement System**: Complete overhaul with drag-and-drop, URL/YouTube support, enhanced player (August 1, 2025)
- **Enhanced RSS Feed System**: Comprehensive RSS/podcast architecture with multiple feed types (July 31, 2025)
- **Playlist Upload System**: Multi-format audio uploads with MP3 conversion and metadata tagging
- **Multiple Show Airings**: "Mon 7PM and Thu 3PM" parsing with priority system
- **Enhanced Shows Management**: Comprehensive filtering and sorting system (August 1, 2025)
- **Recording Status UI**: Clean, minimal recording indicators with smart visibility (August 1, 2025)
- **TTL Management**: Configurable recording retention with automatic cleanup
- **Enhanced Stream Discovery**: Radio Browser API integration with intelligent matching
- **Call Sign Implementation**: WYSO_ShowName_timestamp.mp3 format
- **Logo & Social Media**: Local storage with Facebook fallback
- **Schedule Verification**: Automated weekly checking with change detection
- **Database Backups**: Weekly automated backups with 3-week retention

### ✅ System Improvements
- **Calendar Discovery Filtering**: Navigation elements (e.g., "Shows A-Z") filtered out, requires valid time schedules
- **User-Controlled Show Activation**: New shows inactive by default - users manually choose which to activate
- **Enhanced Deployment Script**: Intelligent code change detection for reliable deployments

## 🎵 Comprehensive Playlist Enhancement System (COMPLETED August 1, 2025)

### 🚀 **Major Upload Features**
RadioGrab now provides a complete playlist management system with multiple upload methods:

#### **Drag-and-Drop Upload System**
- **Full-Page Drop Zone**: Drop audio files anywhere on the playlist page
- **Multi-File Support**: Drop multiple files for batch processing
- **Playlist Selection**: Auto-detects playlists and allows user selection
- **Visual Feedback**: Animated overlay with upload instructions
- **Format Support**: MP3, WAV, M4A, AAC, OGG, FLAC (up to 100MB each)

#### **URL & YouTube Integration**
- **Direct Audio URLs**: Support for MP3, WAV, and other audio formats
- **YouTube Conversion**: Automatic video-to-MP3 conversion using yt-dlp
- **Quality Settings**: High-quality audio extraction (best available)
- **Timeout Protection**: 5-minute download timeout with progress feedback
- **Metadata Extraction**: Automatic title/description from YouTube videos

#### **Enhanced Upload Modal**
- **Dual Input Methods**: Toggle between file picker and URL input
- **Real-Time Validation**: File type and URL format checking
- **Upload Progress**: Visual progress bars and status messages
- **Metadata Fields**: Title, description, and track information
- **Auto-Fill**: Intelligent title extraction from filenames and metadata

### 🎛️ **Enhanced Audio Player**
The playlist player now includes professional-grade controls:

#### **Advanced Playback Controls**
- **Play/Pause**: Standard playback toggle with visual feedback
- **Track Navigation**: Previous/Next track with seamless transitions
- **Rewind/Fast-Forward**: 15-second skip functionality
- **Progress Seeking**: Click-to-seek on progress bar
- **Keyboard Shortcuts**: Spacebar (play/pause), arrows (skip), etc.

#### **Visual Interface**
- **Now Playing Display**: Current track title, description, and info
- **Progress Visualization**: Real-time progress bar with time display
- **Track List**: Interactive sidebar with click-to-play functionality
- **Active Indicators**: Visual highlighting of currently playing track
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### 🏷️ **ID3v2 Metadata System**
Every uploaded and recorded audio file receives comprehensive metadata:

#### **Automatic Tag Embedding**
- **Artist**: Show name (e.g., "Morning Edition")
- **Album**: Station name (e.g., "WYSO 91.3 FM")
- **Title**: Recording title or auto-generated name
- **Date**: Recording date in YYYY-MM-DD format
- **Comment**: Show description or upload description
- **Track Number**: Sequential numbering for playlist tracks
- **UTF-8 Encoding**: Full Unicode support for international characters

#### **Technical Implementation**
- **FFmpeg Integration**: Uses FFmpeg for reliable metadata writing
- **Post-Processing Validation**: Verifies tags after writing
- **Database Storage**: All metadata fields stored in database
- **Backward Compatibility**: Works with existing recordings

### 🔧 **Track Management System**

#### **Drag-and-Drop Reordering**
- **Visual Interface**: Drag handles with intuitive UX
- **Real-Time Updates**: Immediate visual feedback during reordering
- **Database Sync**: Track numbers updated in real-time
- **Sortable.js Integration**: Professional drag-and-drop library
- **Save Confirmation**: Manual save to prevent accidental changes

#### **Track Information Display**
- **Comprehensive Details**: Title, description, duration, file size
- **Visual Track Numbers**: Properly formatted (01, 02, 03...)
- **File Management**: Individual track deletion with confirmation
- **Batch Operations**: Multi-track management capabilities

### 📱 **User Experience Enhancements**

#### **Responsive Interface**
- **Mobile Optimized**: Touch-friendly controls and layouts
- **Progressive Enhancement**: Works without JavaScript (basic functionality)
- **Fast Loading**: Optimized asset loading and caching
- **Error Handling**: Comprehensive error messages and recovery

#### **Visual Design**
- **Modern UI**: Bootstrap 5 with custom styling
- **Animated Elements**: Smooth transitions and micro-interactions
- **Icon System**: FontAwesome icons for clear visual communication
- **Color Coding**: Status indicators (active/inactive, success/error)

### 🛠️ **Technical Architecture**

#### **Backend Services** (`upload_service.py`)
```python
class AudioUploadService:
    - upload_file()       # Handle direct file uploads
    - upload_url()        # Process URL downloads
    - _download_youtube() # YouTube-to-MP3 conversion
    - _download_direct_url() # Direct audio URL downloads
    - _write_mp3_metadata() # ID3v2 tag embedding
```

#### **Frontend JavaScript** (`playlists.js`)
```javascript
// Key Features:
- initializeDropZones()    # Full-page drag-and-drop
- handleUpload()          # Dual-method upload processing
- loadPlaylistTracks()    # Track management interface
- PlaylistPlayer class    # Enhanced audio player
- Real-time CSRF integration
```

#### **API Endpoints**
- **`/api/upload.php`**: Multi-action upload handler (file, URL, playlist creation)
- **`/api/playlist-tracks.php`**: Track listing and reordering
- **Enhanced CSRF Protection**: All operations token-validated

### 🔒 **Security & Validation**

#### **Upload Security**
- **File Type Validation**: MIME type checking with ffprobe verification
- **Size Limits**: Configurable per-playlist (default 100MB)
- **URL Validation**: Domain filtering and content-type checking
- **CSRF Protection**: All upload operations require valid tokens
- **Path Sanitization**: Secure filename generation and storage

#### **Audio Processing**
- **Format Conversion**: Automatic conversion to MP3 for consistency
- **Quality Validation**: Duration and file size verification
- **Metadata Extraction**: Safe parsing with error handling
- **Temporary File Cleanup**: Automatic cleanup of processing files

### 📊 **Database Integration**

#### **Enhanced Schema**
```sql
-- Track ordering and metadata
recordings.track_number      # Sequential track ordering
recordings.original_filename # Source filename preservation
recordings.source_type      # 'uploaded' vs 'recorded' distinction

-- Playlist-specific fields
shows.show_type            # 'playlist' vs 'scheduled'
shows.allow_uploads        # Upload permission flag
shows.max_file_size_mb     # Size limit configuration
```

#### **Data Management**
- **Automatic Indexing**: Optimized queries for large playlists
- **Referential Integrity**: Foreign key constraints and cleanup
- **Backup Integration**: Playlist data included in system backups

### 🧪 **Testing & Quality Assurance**

#### **Browser Testing Requirements**
- **Chromium Integration**: All features tested via actual browser workflows
- **JavaScript Execution**: Selenium WebDriver for dynamic testing
- **Real User Simulation**: Drag-and-drop, form submission, audio playback
- **CSRF Token Workflows**: Complete authentication flow testing
- **Cross-Platform**: Desktop, tablet, and mobile compatibility

### 🚀 **Performance Optimizations**

#### **Upload Processing**
- **Chunked Uploads**: Large file support with progress tracking
- **Background Processing**: Non-blocking upload handling
- **Caching Strategy**: Intelligent metadata caching
- **Resource Management**: Memory-efficient audio processing

#### **Player Performance**
- **Lazy Loading**: Tracks loaded on-demand
- **Audio Preloading**: Next track preparation for seamless playback
- **State Management**: Efficient track switching and memory usage
- **Network Optimization**: Optimized audio streaming

## 🎛️ Enhanced Shows Management System (COMPLETED August 1, 2025)

### 📊 **Comprehensive Filtering & Sorting**
The shows management interface now provides powerful filtering and sorting capabilities:

#### **Multi-Criteria Filtering**
- **Search**: Full-text search across show names, descriptions, and station names
- **Station Filter**: Filter shows by specific radio station
- **Status Filter**: Show only active or inactive shows
- **Genre Filter**: Filter by show genre/category
- **Tags Filter**: Filter by show tags for content organization

#### **Advanced Sorting Options**
- **Show Name**: Alphabetical sorting of show titles
- **Station**: Group shows by radio station
- **Genre**: Sort by show category/genre
- **Tags**: Organize by content tags
- **Next Air Date**: Sort by upcoming recording schedule
- **Recording Count**: Sort by number of recorded episodes
- **Latest Recording**: Sort by most recently recorded content
- **Order**: Ascending or descending for all sort criteria

#### **Enhanced User Experience**
- **Two-Row Filter Layout**: Organized filter form with logical grouping
- **Clear Filters**: One-click reset to default view
- **Persistent Filtering**: URL parameters maintain filter state across page refreshes
- **Responsive Design**: Mobile-friendly filter interface

### 🔧 **Technical Implementation**
- **Backend Integration**: Seamless integration with existing database queries
- **SQL Optimization**: Efficient WHERE and ORDER BY clause construction
- **Parameter Validation**: Secure input handling and validation
- **Performance**: Optimized queries with proper indexing support

## 🎯 Enhanced RSS Feed System (COMPLETED July 31, 2025)

### 📡 **Comprehensive Feed Architecture**
RadioGrab now provides a complete RSS/podcast feed system with multiple feed types:

#### **Universal Feeds**
- **All Shows Feed**: Complete collection of all radio show recordings (excludes playlists)
- **All Playlists Feed**: Complete collection of all user-created playlist tracks
- **URL Format**: `/api/enhanced-feeds.php?type=universal&slug=all-shows|all-playlists`

#### **Station Feeds**  
- **Automatic Generation**: RSS feeds for each station including all of its shows
- **Custom Metadata**: Station-specific titles, descriptions, and images
- **URL Format**: `/api/enhanced-feeds.php?type=station&id=STATION_ID`

#### **Custom Feeds**
- **User-Created**: Select specific shows to combine into custom feeds
- **Custom Metadata**: User-defined titles, descriptions, and cover images
- **URL Format**: `/api/enhanced-feeds.php?type=custom&slug=CUSTOM_SLUG`
- **Management Interface**: Complete web UI for creating and managing custom feeds

#### **Playlist Feeds**
- **Manual Ordering**: User-created playlists with drag & drop track ordering
- **Track Sequencing**: Ordered by track_number ASC, then recorded_at ASC
- **URL Format**: `/api/enhanced-feeds.php?type=playlist&id=SHOW_ID`

#### **Individual Show Feeds**
- **Enhanced Metadata**: Improved show-specific RSS feeds with feed metadata fields
- **Show Type Support**: Both regular shows and playlists
- **URL Format**: `/api/enhanced-feeds.php?type=show&id=SHOW_ID`

### 🎨 **Web Interface Features**

#### **Tabbed Navigation Interface** (`/feeds.php`)
- **Universal Feeds Tab**: Access to "All Shows" and "All Playlists" feeds
- **Station Feeds Tab**: Grid view of all station feeds with statistics
- **Show Feeds Tab**: Individual show feeds with regeneration capability
- **Playlist Feeds Tab**: User-created playlist feeds with management links
- **Custom Feeds Tab**: Link to custom feed management interface

#### **Custom Feed Management** (`/custom-feeds.php`)
- **Feed Creation Modal**: Select shows, set metadata, and generate feeds
- **Show Selection**: Grouped by station with checkbox selection
- **Custom Metadata**: Feed title, description, and cover image URL
- **Feed Management**: View, copy URLs, and delete custom feeds
- **URL Sharing**: One-click copy to clipboard functionality

### 🔧 **Technical Implementation**

#### **Database Schema**
```sql
-- Custom feeds with metadata and slug-based URLs
custom_feeds: id, name, description, slug, custom_title, custom_description, 
              custom_image_url, feed_type, is_public, created_at, updated_at

-- Many-to-many relationship between custom feeds and shows
custom_feed_shows: id, custom_feed_id, show_id, sort_order, created_at

-- Pre-configured station feed settings
station_feeds: id, station_id, custom_title, custom_description, 
               custom_image_url, is_active, created_at, updated_at

-- Feed generation tracking and monitoring
feed_generation_log: id, feed_type, feed_id, feed_identifier, status, 
                     error_message, generation_time_ms, items_count, 
                     file_size_bytes, generated_at

-- Enhanced shows table with RSS metadata
shows: ..., feed_title, feed_description, feed_image_url, 
       feed_category, feed_explicit, feed_author
```

#### **API Architecture**
- **Unified Endpoint**: `/api/enhanced-feeds.php` handles all feed types
- **Type-Based Routing**: Query parameter `type` determines feed logic
- **Content Ordering**: 
  - Playlists: `track_number ASC, recorded_at ASC` (manual ordering)
  - Shows: `recorded_at DESC` (chronological ordering)
- **Error Handling**: Comprehensive HTTP status codes and XML error responses

#### **Feed Image Fallback Logic**
```php
function getFeedImage($custom_image, $show_image, $station_image) {
    if ($custom_image) return $custom_image;      // Custom feed image
    if ($show_image) return $show_image;          // Show-specific image
    if ($station_image) return $station_image;    // Station logo
    return '/assets/images/default-podcast-artwork.png'; // System default
}
```

#### **iTunes Podcast Compatibility**
- **Complete XML Structure**: Proper RSS 2.0 with iTunes namespace
- **Podcast Metadata**: Author, summary, explicit rating, category
- **Episode Data**: Duration, description, publication date, GUID
- **Audio Enclosures**: Proper MIME types and file size information

### 🚀 **Usage Examples**

#### **API Endpoints**
```bash
# Universal feeds
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=universal&slug=all-shows"
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=universal&slug=all-playlists"

# Station feeds (all shows from station)
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=station&id=1"

# Individual show/playlist feeds
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=show&id=5"
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=playlist&id=10"

# Custom feeds (user-created combinations)
curl "https://radiograb.svaha.com/api/enhanced-feeds.php?type=custom&slug=my-favorites"
```

#### **Web Interface Access**
- **Feed Management**: Visit `/feeds.php` for comprehensive feed overview
- **Custom Feeds**: Visit `/custom-feeds.php` to create and manage custom feeds
- **Feed URLs**: All feeds provide copy-to-clipboard functionality
- **QR Codes**: Generate QR codes for easy mobile podcast app subscription

### 📊 **Database Migration Applied**
```bash
# Migration successfully applied on production
ssh radiograb@167.71.84.143 "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < /opt/radiograb/database/migrations/add_enhanced_feed_system.sql"

# Verification - all tables created successfully:
# ✅ custom_feeds
# ✅ custom_feed_shows  
# ✅ station_feeds
# ✅ feed_generation_log
# ✅ shows table enhanced with RSS metadata fields
```

### 🔄 **Automatic Feed Updates**
- **RSS Service Integration**: Existing RSS updater service (`radiograb-rss-updater-1`) automatically incorporates new feed types
- **Recording Integration**: New recordings automatically appear in relevant feeds
- **Feed Refresh**: 15-minute update cycle ensures feeds stay current
- **Cache Management**: Intelligent caching prevents unnecessary regeneration
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