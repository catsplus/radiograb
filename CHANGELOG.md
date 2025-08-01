# RadioGrab Changelog

## [3.12.1] - 2025-08-01 - Streaming vs Download Controls System

### 🔒 DMCA Compliance & Content Controls
- **Stream-Only Mode**: Toggle to restrict downloads for copyrighted content (music shows, syndicated programs)
- **Content Type Classification**: Automatic categorization system (Music, Talk/Spoken Word, Mixed Content, Unknown)
- **Syndicated Show Detection**: Auto-identification of NPR, BBC, nationally distributed content
- **Admin Interface**: DMCA & Content Controls section in add-show.php and edit-show.php
- **Visual Compliance**: Warning-styled interface with clear legal guidance and recommendations

### 🔐 JavaScript Path Obfuscation
- **Base64 Encoding**: Client-side path obfuscation with URL-safe character substitution
- **Secure Download API**: Token-based file access through `/api/get-recording.php`
- **Database Verification**: Ensures recording exists before serving file
- **Access Logging**: Comprehensive monitoring for compliance tracking
- **Security Boundaries**: Only serves files within recordings directory

### 🎛️ Conditional Download Controls
- **Smart UI**: Download buttons only appear for non-stream-only content
- **Visual Indicators**: Stream-only shows display disabled "Stream Only" button with tooltips
- **Database Integration**: Enhanced recordings.php query includes stream_only and content classification
- **API Enforcement**: Stream-only recordings blocked at API level with proper error messages

### 🎵 Auto-Categorization System
- **Music Show Detection**: Keywords like "music", "jazz", "rock", "blues" → automatic stream-only
- **Talk Show Identification**: "news", "talk", "interview", "morning" → downloads allowed
- **Syndicated Content**: NPR, BBC, national programs → automatic stream-only restriction
- **Smart Defaults**: Music and syndicated shows default to stream-only for legal safety

### 🛡️ DMCA Compliance Features
- **Stream-Only Enforcement**: Recordings cannot be downloaded through any interface
- **Obfuscated File Paths**: Prevents direct URL discovery and sharing
- **Content Type Awareness**: Intelligent identification of potentially copyrighted material
- **Admin Control Granularity**: Fine-tuned content policies per show
- **Access Monitoring**: Comprehensive logging for compliance auditing

### 🔧 Technical Implementation
- **Database Migration**: `add_streaming_download_controls.sql` with intelligent defaults
- **Interface Integration**: DMCA controls embedded in show management workflows
- **API Security**: Token-based download system with comprehensive validation
- **JavaScript Enhancement**: Client-side path obfuscation utilities (radiograb.js)
- **Error Handling**: Proper HTTP status codes and user-friendly error messages

## [3.12.0] - 2025-08-01 - DJ Audio Snippet Recording System

### 🎙️ Complete Browser-Based Audio Recording
- **WebRTC MediaRecorder API**: Native browser audio recording with high-quality output
- **Professional Recording Interface**: Real-time controls with start/stop functionality and visual timer
- **Audio Preview System**: Built-in playback before saving with metadata editing capabilities
- **5-Minute Recording Limit**: Automatic stop with warning to prevent excessive file sizes
- **Mobile Compatibility**: Full functionality on iOS Safari, Android Chrome, and mobile browsers

### 🎨 Recording User Experience
- **Professional Recording Modal**: Two-column layout with controls and tips panel
- **Recording Tips**: Best practices for DJ voice recording with browser compatibility info
- **Visual Feedback**: Animated recording status, progress tracking, and color-coded alerts
- **Error Handling**: Comprehensive error messages with troubleshooting guidance
- **Touch-Optimized**: Responsive design with mobile-friendly controls

### 🔧 Technical Implementation
- **New JavaScript Module**: `audio-recorder.js` with complete WebRTC integration
- **Enhanced Upload API**: Added `voice_clip` source type support in upload.php
- **Python Service Updates**: Modified `upload_service.py` for voice clip handling
- **Database Extensions**: Extended recordings table with source_type differentiation
- **File Format Support**: Added WebM audio format for browser recordings

### 🎵 Playlist Integration
- **Voice Clip Visual Differentiation**: Green badges, microphone icons, and border styling
- **Drag-and-Drop Support**: Voice clips work with existing track reordering system
- **Source Type Tracking**: Database distinguishes voice clips from regular uploads
- **Playlist API Updates**: Enhanced playlist-tracks.php to include source_type information

### 🎯 Perfect DJ Use Cases
- **Station IDs**: "You're listening to WXYZ 101.5 FM"
- **Show Intros/Outros**: Welcome messages and sign-offs
- **Transitions**: "Coming up next..." or "That was [artist] with [song]"
- **Custom Drops**: Personalized DJ voice drops, stings, and promotional announcements

## [3.11.1] - 2025-08-01 - Shows Table View & Playlist Management

### 📋 Shows Table View System
- **Complete Table Implementation**: Sortable columns (Show Name, Station, Recordings)
- **View Toggle Functionality**: Switch between card and table views with URL persistence
- **Responsive Design**: Mobile-optimized table with Bootstrap styling
- **Interactive Sorting**: Visual indicators for ascending/descending sort order
- **Hyperlink Integration**: Show names link to individual show detail pages

### 🎵 Playlist Management Enhancement
- **Bug Fix**: Resolved "Failed to load tracks: Show ID Required" error
- **Dedicated Editor**: New `edit-playlist.php` with playlist-specific interface
- **Simplified Fields**: Removed schedule, duration, host, retention fields for playlists
- **Playlist Image Field**: Changed from "Show Image/Logo" to "Playlist Image/Logo"
- **API Corrections**: Fixed parameter mismatch in playlist-tracks.php API

## [3.9.0] - 2025-07-31 - Enhanced RSS Feed System

### 📡 Comprehensive RSS/Podcast Feed Architecture
- **Multiple Feed Types**: Complete RSS feed system with 5 distinct feed types
  - **Universal Feeds**: "All Shows" and "All Playlists" aggregated collections
  - **Station Feeds**: Automatically generated feeds for each station including all shows  
  - **Custom Feeds**: User-created feeds by selecting specific shows with custom metadata
  - **Playlist Feeds**: Dedicated feeds for user-created playlists with manual track ordering
  - **Individual Show Feeds**: Enhanced show-specific feeds with improved metadata

### 🎨 Enhanced Web Interface
- **Tabbed Navigation**: Complete redesign of `/feeds.php` with tabbed interface
  - Universal Feeds tab with "All Shows" and "All Playlists" cards
  - Station Feeds tab with grid view and statistics
  - Show Feeds tab with regeneration capability  
  - Playlist Feeds tab with management links
  - Custom Feeds tab linking to management interface
- **Custom Feed Management**: New `/custom-feeds.php` interface
  - Modal-based feed creation with show selection
  - Grouped show selection by station with checkboxes
  - Custom metadata fields (title, description, cover image)
  - Feed management with copy URLs and delete functionality
  - One-click URL copying to clipboard

### 🔧 Technical Implementation
- **Database Schema**: 4 new tables for comprehensive feed management
  - `custom_feeds`: Feed definitions with slug-based URLs and metadata
  - `custom_feed_shows`: Junction table for many-to-many show relationships
  - `station_feeds`: Pre-configured station feed settings
  - `feed_generation_log`: Feed generation tracking and monitoring
  - Enhanced `shows` table with RSS metadata fields
- **Unified API**: `/api/enhanced-feeds.php` endpoint with type-based routing
  - Supports all feed types through query parameters
  - Content ordering: playlists by track_number, shows chronologically
  - iTunes podcast compatibility with proper XML structure
  - Feed image fallback logic: Show → Station → Default hierarchy
- **MySQL 8.0 Compatibility**: Fixed database migration syntax issues
- **Function Conflicts**: Resolved duplicate function definitions between files

### 🚀 iTunes & Podcast App Integration
- **Complete XML Structure**: Proper RSS 2.0 with iTunes namespace
- **Podcast Metadata**: Author, summary, explicit rating, category information
- **Episode Data**: Duration, description, publication date, unique GUIDs
- **Audio Enclosures**: Proper MIME types and file size information
- **Feed Artwork**: Image fallback system with consistent sizing

### 🛠️ Deployment & Infrastructure
- **Enhanced Deployment Script**: Intelligent code change detection
  - Quick mode now detects PHP/Python/JS/CSS changes and rebuilds containers
  - Documentation-only changes use simple container restart  
  - Robust error handling and deployment verification
  - Better change analysis with file type detection

### 📊 Database Migration
- Successfully applied comprehensive database migration
- All new tables created and populated with default data
- Universal feed entries automatically created
- Station feeds pre-configured for existing stations
- Enhanced shows table with RSS metadata fields

## [2.12.1] - 2025-07-30 - Frontend Refactoring

### 🧹 Frontend Code Cleanup
- **Shared Header/Footer**: Refactored multiple pages to use centralized `header.php` and `footer.php` includes. This improves maintainability and ensures a consistent look and feel across the application.
- **Affected Pages**: 
  - `add-playlist.php`
  - `add-show.php`
  - `add-station.php`
  - `admin-metadata.php`
  - `edit-show.php`
  - `feeds.php`
  - `manage-ttl.php`
  - `recordings.php`
  - `schedule-test.php`
  - `settings.php`
- **Code Consistency**: Removed redundant HTML boilerplate from individual files, making the codebase cleaner and easier to manage.

## [2.12.0] - 2025-07-29 - Logo & Social Media System

### 🎨 Visual Enhancement System
- **Local Logo Storage**: All station logos downloaded and stored locally for consistent performance
  - Automatic download and optimization of station logos to max 400x400px
  - Local storage in `/var/radiograb/logos/` with proper file naming and caching
  - Database tracking of logo source (website/facebook), update timestamps, and file paths
- **Facebook Logo Extraction**: Automatic fallback to Facebook profile pictures when website logos unavailable
  - Smart extraction from Facebook Open Graph meta tags
  - Support for multiple Facebook URL formats and page structures
  - Fallback logo system with priority: website > facebook > default
- **Consistent Logo Sizing**: All logos displayed at uniform 60x60px with proper aspect ratio maintenance
  - CSS improvements for consistent visual presentation
  - Object-fit containment for proper aspect ratio preservation
  - Background styling for visual consistency across different logo formats

### 📱 Social Media Integration
- **Multi-Platform Detection**: Detection and display of 10+ social platforms
  - Facebook, Twitter/X, Instagram, YouTube, LinkedIn, Spotify, TikTok, Discord, Twitch, SoundCloud
  - Smart URL pattern matching with platform-specific validation
  - Automatic extraction from website link structures and meta tags  
- **Visual Social Icons**: Colored social media icons with hover effects and proper platform branding
  - Font Awesome icons with platform-specific colors
  - Responsive design with hover effects and scaling
  - Proper accessibility with platform names and external link indicators
- **Database Integration**: JSON storage for social media links with platform metadata
  - Structured storage of social links with icon, color, and platform information
  - Update tracking and timestamp management for social media changes

### 🛠️ Technical Infrastructure
- **New Backend Services**:
  - `logo_storage_service.py`: Download, optimize, and store station logos locally
  - `facebook_logo_extractor.py`: Extract profile pictures from Facebook pages
  - `social_media_detector.py`: Detect and categorize social media links from websites
  - `station-logo-update.php`: API for bulk and individual station logo/social media updates
- **Database Schema Extensions**:
  - `facebook_url`, `local_logo_path`, `logo_source`, `logo_updated_at` fields in stations table
  - `social_media_links` JSON field for structured social media storage
  - `social_media_updated_at` timestamp field for update tracking
- **Nginx Configuration**: New `/logos/` location block for serving local logos with caching
- **Image Processing**: PIL/Pillow integration for image optimization and format conversion

### 🔧 System Improvements
- **Station Logo Optimization**: All existing station logos downloaded and optimized locally
  - WEHC: Facebook profile picture extraction and local storage (241x257px optimized)
  - WTBR: Logo size issues resolved with local optimization (250x150px)
  - WERU, WYSO, KULT: All logos optimized and stored locally for consistent performance
- **Social Media Discovery**: Comprehensive social media link detection across all stations
  - WEHC: Facebook, Instagram, Spotify links detected and displayed
  - WYSO: Facebook, Instagram, YouTube, LinkedIn links detected and displayed
  - Enhanced station information display with proper social media integration

## [2.11.0] - 2025-07-29 - Playlist System & MP3 Metadata

### 🎵 MP3 Metadata Implementation
- **Automatic Metadata Writing**: All recordings now include comprehensive MP3 tags
  - Artist: Show name
  - Album: Station name  
  - Title: Show name + recording date
  - Comment: Show description
  - Date: Recording date
  - Genre: Show genre (if available)
- **Upload Metadata Enhancement**: User uploads preserve existing metadata and enhance with show/station information
- **Backend Service**: New `mp3_metadata_service.py` for automated metadata management using FFmpeg
- **Integration**: Metadata writing integrated into recording service and upload service

### 📁 Playlist Upload System  
- **User Upload Functionality**: Complete audio file upload system with drag & drop interface
- **Multi-Format Support**: Upload MP3, WAV, M4A, AAC, OGG, FLAC with automatic MP3 conversion
- **File Validation**: Comprehensive validation including size limits, format validation, and audio stream verification
- **Track Ordering**: Sequential track numbering with drag & drop reordering capability
- **Playlist Management Modal**: Dedicated interface for managing playlist track order
- **Upload Modal**: User-friendly upload interface with progress tracking and error handling

### 🗄️ Database Schema Extensions
- **Playlist Support**: New `show_type` field (scheduled/playlist) in shows table
- **Upload Configuration**: Added `allow_uploads`, `max_file_size_mb` fields for playlist shows
- **Upload Tracking**: New fields in recordings table:
  - `source_type`: 'recorded' or 'uploaded'
  - `original_filename`: Preserve original upload filename
  - `uploaded_by`: Track upload user (reserved for future multi-user support)
  - `track_number`: Sequential ordering for playlist tracks
- **Metadata Fields**: Extended show metadata with genre, image_url, long_description fields
- **Migration Scripts**: Proper database migrations for schema updates

### 🎛️ Enhanced Web Interface
- **Show Type Selection**: Radio buttons to choose between 'Scheduled Show' and 'Playlist/Upload'
- **Conditional Forms**: Dynamic form fields based on show type selection
- **Upload Actions**: New upload and playlist management buttons for playlist-type shows
- **Playlist Display**: Enhanced shows page with upload functionality and track management
- **JavaScript Integration**: Complete AJAX integration for file uploads and playlist management

### 📡 RSS Feed Enhancements
- **Playlist Support**: RSS feeds now include uploaded tracks with proper ordering
- **Track Ordering**: Playlist tracks ordered by track_number field for proper playback sequence
- **Mixed Content**: RSS feeds support both recorded shows and uploaded playlists
- **Metadata Integration**: RSS items include enhanced MP3 metadata in descriptions

### 🔧 Backend Service Architecture
- **Upload Service**: New `upload_service.py` for handling file uploads, validation, and conversion
- **MP3 Metadata Service**: Dedicated service for reading and writing MP3 metadata
- **Integration Points**: Services integrated with existing recording and RSS systems
- **Error Handling**: Comprehensive error handling and validation throughout upload pipeline

### ⚖️ Legal Compliance Updates
- **Terminology Cleanup**: Replaced all "TiVo for Radio" references with legally neutral "Radio Recorder"
- **Documentation Updates**: Updated all PHP files, documentation, and frontend text
- **Footer Updates**: Changed website footer text for clear legal positioning
- **RSS Generator**: Updated RSS feed generator descriptions to use neutral language

### 🐛 User Interface Improvements
- **Empty Show Hiding**: On-Demand Recording shows with 0 recordings are now hidden from shows page
- **Timezone Removal**: Removed timezone display from show blocks for cleaner interface
- **Upload Progress**: Real-time upload progress indicators with status updates
- **Drag & Drop**: Intuitive track reordering with visual feedback and automatic numbering

### 🚀 Performance & Reliability
- **Audio Validation**: Upload files validated for audio content and format compatibility
- **Automatic Conversion**: Non-MP3 uploads automatically converted to MP3 format
- **Database Integrity**: Proper foreign key relationships and data validation
- **Session Management**: Enhanced CSRF protection for all upload and management operations

## [2.1.0] - 2025-07-25 - Call Sign Implementation & System Fixes

### 📞 Call Sign Implementation
- **Human-Readable Filenames**: Recording files now use 4-letter call signs instead of numeric station IDs
  - Test recordings: `WEHC_test_2025-07-25-070014.mp3` (was `1_test_2025-07-25-070014.mp3`)
  - Scheduled recordings: `WEHC_Morning_Show_20250725_0800.mp3`
  - On-demand recordings: `WEHC_on-demand_2025-07-25-070014.mp3`
- **Station Configuration**: All stations configured with proper call signs
  - WEHC 90.7 FM → WEHC
  - WERU → WERU  
  - WTBR - 89.7 FM → WTBR
  - WYSO → WYSO
- **Backward Compatibility**: Old numeric filename format still supported for existing recordings
- **Database Enhancement**: Added `call_letters` field to stations table with proper indexing

### ⏰ Timezone Synchronization
- **Container Timezone Fix**: All Docker containers now use `America/New_York` (Eastern Time)
- **Timestamp Accuracy**: Recording timestamps now match local time instead of being 4 hours ahead
- **Dockerfile Updates**: Added `TZ=America/New_York` environment variable to all services
- **docker-compose.yml**: Added timezone environment variables to all containers

### 🔽 Download Security & Functionality
- **Fixed MP3 Downloads**: Test recordings now download as proper MP3 files instead of HTML
- **Security Validation**: Added comprehensive filename format validation to prevent directory traversal
- **Proper Headers**: Fixed content-type headers (`audio/mpeg`) and download disposition
- **API Enhancement**: Added dedicated download action to `test-recordings.php` API endpoint
- **Session Management**: Downloads now work properly with session-based authentication

### 🗄️ Database Environment Variables Fix
- **Critical PHP-FPM Fix**: Changed from `$_ENV` to `$_SERVER` for environment variable access
- **Container Configuration**: Enabled `clear_env = no` in PHP-FPM pool configuration
- **Connection Stability**: All MySQL connections now use environment variables correctly
- **Error Resolution**: Fixed `SQLSTATE[HY000] [2002] No such file or directory` database errors

### 🔧 API Improvements
- **Enhanced Test Recordings API**: Support for both old and new filename formats
- **Improved Error Handling**: Better error messages and validation
- **Response Format**: Added `call_letters` field to API responses for better frontend integration
- **Download Endpoint**: New secure download functionality with proper file serving

### 📚 Documentation Updates
- **CLAUDE.md**: Added recent updates section with implementation details
- **CHANGELOG.md**: Comprehensive changelog with technical details
- **SYSTEM_ARCHITECTURE.md**: Updated filename conventions and technical specifications
- **Container Documentation**: Updated with timezone and environment variable configurations

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
