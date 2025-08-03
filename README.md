# RadioGrab 📻

[![Docker](https://img.shields.io/badge/docker-supported-blue)](https://www.docker.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Python](https://img.shields.io/badge/python-3.8+-blue)](https://www.python.org/)
[![PHP](https://img.shields.io/badge/php-8.1+-blue)](https://www.php.net/)

**Radio Recording System** - Automatically record radio shows based on schedules and generate podcast feeds

RadioGrab is a comprehensive radio show recording and podcast generation system that turns any radio station's programming into a personal podcast archive. It automatically schedules and records shows at specified times, discovers streaming URLs, and generates RSS feeds - all with a beautiful web interface.

## 📅 Current Version: v3.15.0 (August 3, 2025)
**Latest Features**: 🌐 **AWS S3 PRIMARY STORAGE** - Complete cloud storage integration with direct file serving from public S3 buckets, auto-upload, and migration tools. 🎤 **MULTI-PROVIDER TRANSCRIPTION** - AI-powered transcription with 7+ providers including OpenAI Whisper, DeepInfra, BorgCloud, AssemblyAI with cost optimization. 🔑 **API KEYS MANAGEMENT SYSTEM** - Enterprise-grade secure API key storage with AES-256-GCM encryption. 🎯 **STATION TEMPLATE SHARING** - Community-driven template sharing with verification system.

### 🌐 **AWS S3 Primary Storage Integration** (August 3, 2025)
**🎯 Issues #13 & #41 COMPLETED**: Complete cloud storage solution with primary storage mode where recordings are stored directly in S3 and served via public URLs with no local file retention.

**✅ KEY FEATURES:**
- **Primary Storage Mode**: Recordings uploaded directly to S3 bucket `radiograb42` for immediate serving
- **Direct File Serving**: Public S3 URLs for audio streaming/download without server load
- **Auto-Upload**: Automatic upload of new recordings and playlists to configured S3 storage
- **Multi-Provider Support**: AWS S3, DigitalOcean Spaces, Wasabi, Backblaze B2 compatibility
- **Usage Tracking**: Upload statistics, bandwidth monitoring, and cost management
- **Migration Tools**: (In development) Migrate existing recordings to cloud storage

### 🎤 **Multi-Provider AI Transcription System** (August 3, 2025)
**🎯 Issue #25 COMPLETED**: Comprehensive transcription service supporting 7+ AI providers with unified interface, cost optimization, and real-time progress tracking.

**✅ KEY FEATURES:**
- **Multiple Providers**: OpenAI Whisper, DeepInfra ($0.0006/min), BorgCloud, AssemblyAI, Groq, Replicate, Hugging Face
- **Cost Optimization**: Real-time pricing comparison and cost estimation before transcription
- **Web Interface**: Integrated transcription buttons with provider selection and progress tracking
- **Quality Settings**: Provider-specific quality levels and model selection for accuracy vs cost
- **Secure API Management**: Encrypted credential storage with user-friendly configuration interface
- **Results Storage**: Transcription results stored in database with timestamps and confidence scores

### 🔐 **User Authentication & Admin Access System** (August 2, 2025)
**🎯 Issue #6 COMPLETED**: Comprehensive multi-user authentication system with secure user registration, email verification, session management, user-scoped data isolation, and admin dashboard. All user data is properly isolated by user accounts while maintaining backward compatibility.

**✅ KEY FEATURES:**
- **User Registration**: Email verification workflow with modern UI and password strength validation
- **Secure Authentication**: Login/logout with session management and CSRF protection
- **Data Isolation**: All stations, shows, and recordings scoped to individual user accounts
- **Admin Dashboard**: System administration with user management and activity monitoring
- **Database Migration**: Successfully deployed with existing data preservation

### 🎯 **Station Template Sharing System** (August 2, 2025)
**🌐 Issue #38 Phase 1 COMPLETED**: Community-driven station template sharing system that allows users to browse, copy, and contribute verified station configurations, dramatically reducing setup time for new stations.

**✅ KEY FEATURES:**
- **Browse Templates Interface**: Advanced search and filtering by genre, country, category, verification status
- **Rich Template Cards**: Station logos, ratings, usage statistics, verification badges, contributor attribution
- **One-Click Copying**: Copy any template to your station collection with optional custom naming
- **Template Details Modal**: Comprehensive view with technical specs, reviews, categories, working status
- **Community Verification**: Admin-verified templates with usage tracking and rating system
- **Category Organization**: Templates organized by type (Public Radio, Community, Music, News/Talk, etc.)
- **Authentication Integration**: Full integration with user authentication system with copy prevention

### 🎉 **GitHub Issues #29-36 FULLY TESTED & COMPLETED** (August 2, 2025)
**🚀 COMPREHENSIVE TESTING VERIFICATION**: All 8 GitHub issues have been systematically tested using real user workflows through the live production site. Every enhancement has been verified to work correctly following the comprehensive testing methodology documented in [TESTING.md](TESTING.md).

**✅ VERIFIED WORKING IN PRODUCTION:**
- **Issue #31**: Enhanced RSS Feeds with tabbed navigation, QR codes, clipboard copying
- **Issue #32**: Dashboard hover animations and improved statistics cards  
- **Issue #33**: Settings panel with session management and authentication (including critical bug fix)
- **Issue #34**: Global search functionality across all content types with advanced filtering
- **Issue #35**: Add Station form with auto-discovery, stream testing, and logo preview
- **Issue #36**: Comprehensive form validation and enhanced user experience

**🔧 CRITICAL BUG FIXED**: Resolved PHP syntax error in settings.php that was causing 500 Internal Server Error. All functionality now working correctly in production.

See [CHANGELOG.md](CHANGELOG.md) for full details.

## ✨ Features

### 🎯 **Core Functionality**
- **🔍 Global Search System**: Comprehensive search functionality across all content types (stations, shows, recordings, playlists) with advanced filtering, categorized results, and direct navigation links
- **🎨 Enhanced User Interface**: Modern dashboard with hover animations, improved statistics cards, enhanced forms with real-time validation, and responsive design improvements
- **🔒 Streaming vs Download Controls**: Comprehensive DMCA compliance system with stream-only mode for copyrighted content, JavaScript path obfuscation, automatic content type detection (music/talk/mixed), syndicated show identification, and secure token-based download API with access logging
- **🎙️ DJ Audio Snippet Recording**: Complete browser-based voice recording system using WebRTC MediaRecorder API with professional recording interface, 5-minute recording limit, audio preview, mobile compatibility, and seamless playlist integration for DJ intros, outros, station IDs, and custom drops
- **📋 Shows Table View System**: Complete table view implementation for shows page with sortable columns (Show Name, Station, Recordings), responsive design, view toggle buttons (cards/table), and hyperlinks to individual show detail pages
- **🎵 Playlist Management Enhancement**: Fixed "Failed to load tracks: Show ID Required" error and created dedicated edit-playlist.php page with playlist-specific interface removing schedule/duration/host fields
- **🌐 Friendly URL Routing System**: SEO-friendly URLs with individual pages for stations (`/weru`), shows (`/weru/fresh_air`), users (`/user/mattbaya`), and playlists (`/user/mattbaya/my_mix`) featuring comprehensive detail pages, advanced audio players, statistics dashboards, and responsive Bootstrap design
- **🔧 Production Bug Fixes & QA Testing**: Enhanced orphaned recording cleanup with dynamic delete UI, comprehensive manual browser testing, and production deployment verification
- **📅 Manual Schedule Import System**: AI-powered schedule conversion workflow using ChatGPT/Claude/Grok with ICS file upload and parsing for fallback when automatic discovery fails
- **✏️ Station & Show Edit Functionality**: Complete CRUD interfaces with live preview, comprehensive field editing (name, description, logo, stream URL, calendar URL, timezone), and backend integration
- **🎛️ Enhanced Shows Management**: Comprehensive filtering and sorting system with multi-criteria filtering (search, station, status, genre, tags) and advanced sorting options (show name, station, genre, tags, next air date, recording count, latest recording)
- **🎯 Station Schedule Discovery**: Automatically discover and display station programming schedules in Add Show interface
- **📋 Smart Show Management**: Click "Find Shows" to browse station's published schedule with multiple airings support
- **🖱️ One-Click Show Addition**: Click Add on discovered shows to pre-fill all form fields (name, schedule, description, host, genre)
- **⏰ Multiple Airings Support**: Shows with repeat broadcasts display all air times separately with individual Add buttons
- **🗣️ Natural Language Conversion**: Converts parsed schedule data to user-friendly format ("every Monday at 7:00 PM")
- **🏗️ Generic Architecture**: 100% station-agnostic parsers - no hardcoded station logic anywhere
- **ISO Timestamp Parser**: Handles any timezone-aware JSON calendar format (`_parse_iso_timestamp_json_schedule`)
- **Show Links Parser**: Works with any HTML structure with show links (`_parse_show_links_schedule`)
- **StreamTheWorld Fallback**: Generic HD2→HD1→base quality fallback for any station
- **Smart Logo Detection**: Intelligent scoring system with homepage priority and path analysis
- **🎵 Comprehensive Playlist Enhancement System**: Complete drag-and-drop upload system with full-page drop zones, URL/YouTube audio conversion, enhanced player with rewind/fast-forward controls, and professional track management
- **🎛️ Enhanced Audio Player**: Professional-grade playback controls with 15-second skip, click-to-seek progress bar, keyboard shortcuts, and seamless track transitions
- **🏷️ ID3v2 Metadata System**: Automatic metadata embedding for all recordings (Artist=Show, Album=Station, Date, Comment) with UTF-8 encoding and database validation
- **🔗 URL & YouTube Integration**: Direct audio URL support and automatic YouTube-to-MP3 conversion using yt-dlp with quality optimization
- **📱 Drag-and-Drop Upload**: Full-page drop zones with multi-file support, playlist auto-detection, and visual upload feedback
- **🎼 Multi-Format Audio Support**: Upload MP3, WAV, M4A, AAC, OGG, FLAC with automatic MP3 conversion and format validation
- **Multiple Show Airings**: Support for original + repeat broadcasts with natural language scheduling ("Mondays at 7 PM and Thursdays at 3 PM")
- **Smart Recording Status**: Clean, unobtrusive recording indicators that only appear for actively recording shows with compact progress tracking
- **Automatic Show Recording**: APScheduler-based system that automatically records shows at scheduled times
- **TTL Recording Management**: Configurable expiry periods (days/weeks/months/indefinite) with automatic cleanup
- **Schedule Management**: Web interface for adding/editing show schedules with automatic scheduler integration
- **Smart Discovery**: Extract streaming URLs and schedules from station websites with User-Agent support
- **Enhanced RSS Feed System**: Comprehensive podcast feed architecture with multiple feed types
  - **Station Feeds**: Automatically generate RSS feeds for each station including all shows
  - **Custom Feeds**: User-defined feeds by selecting specific shows with custom metadata
  - **Playlist Feeds**: Separate RSS feeds for user-created playlists with manual ordering
  - **Universal Feeds**: "All Shows" and "All Playlists" aggregated feeds
  - **Feed Image Fallback**: Show image → Station image → System default hierarchy
  - **iTunes Compatibility**: Full podcast metadata support with proper XML structure
- **Test Recording**: 30-second test recordings with automated cleanup (4 hour retention)
- **On-Demand Recording**: Manual 1-hour recordings with quality validation

### 🔧 **Technical Features**
- **Frontend Refactoring**: Centralized header and footer includes for improved maintainability and a consistent UI across all pages.
- **MP3 Metadata Service**: Automated metadata writing with FFmpeg integration for comprehensive audio tagging
- **Upload Service Architecture**: Audio file validation, format conversion, and playlist integration
- **Database Schema Extensions**: New fields for playlist support, track ordering, and metadata management
- **Enhanced Recording Architecture**: Complete rewrite with database-driven design and duplicate prevention
- **User-Agent Support**: Saved User-Agent per station for HTTP 403 handling and stream compatibility
- **Call Sign File Naming**: Human-readable 4-letter call signs (WEHC, WERU, WTBR, WYSO) instead of numeric IDs
- **Multi-Tool Recording**: Automatic tool selection (streamripper/ffmpeg/wget) with quality validation (2KB/sec minimum)
- **JavaScript-Aware Parsing**: Selenium WebDriver with Chromium browser handles dynamic calendar pages and AJAX content
- **Docker Containerized**: Complete Docker setup with 5 specialized containers
- **SSL/HTTPS Ready**: Automatic Let's Encrypt certificate management
- **Timezone Synchronized**: All containers use Eastern Time for consistent timestamps
- **Test Interface Fixed**: Test recordings now appear properly in web interface without duplicates
- **Responsive Web UI**: Modern Bootstrap interface with real-time updates

### 🎨 **Visual & Social Features**
- **🎯 Enhanced Feed Management**: One-click URL copying with clipboard API, QR code generation for easy mobile subscription, feed testing capability, and live statistics with toast notifications
- **🎛️ Interactive Forms**: Stream URL testing, logo preview functionality, comprehensive form validation with real-time feedback, and enhanced discovery workflows with loading states
- **⚙️ Advanced Settings Panel**: Session management with 30-minute timeout, automatic logout functionality, enhanced admin authentication with environment variable support, and improved security logging
- **Local Logo Storage**: All station logos downloaded and stored locally for consistent performance and sizing
- **Facebook Logo Extraction**: Automatic fallback to Facebook profile pictures when website logos unavailable
- **Consistent Logo Sizing**: All logos displayed at uniform 60x60px with proper aspect ratio maintenance
- **Image Optimization**: Logos resized to max 400x400px and optimized for web delivery with format conversion
- **Social Media Integration**: Detection and display of 10+ social platforms (Facebook, Twitter, Instagram, YouTube, LinkedIn, Spotify, etc.)
- **Smart Social Detection**: Automatic extraction of social media links from station websites with platform recognition
- **Visual Social Icons**: Colored social media icons with hover effects and proper platform branding
- **Database-Cached Logos**: JSON storage for social media links with platform metadata and update tracking

### 📊 **Smart Automation**
- **Automatic MP3 Metadata**: All recordings tagged with artist=show name, album=station name, recording date, description
- **Upload Metadata Enhancement**: User uploads preserve existing metadata and enhance with show/station information
- **Playlist Track Ordering**: Automatic sequential track numbering with drag & drop reordering support
- **Audio Format Conversion**: Automatic conversion of uploaded files (WAV, M4A, AAC, OGG, FLAC) to MP3 format
- **Multiple Airings Detection**: Automatically parses complex schedules like "Original Monday 7PM, repeat Thursday 3PM, encore Sunday 6PM"
- **Refined Status Updates**: JavaScript checks recording status every 30 seconds with minimal, compact progress indicators
- **Smart Browser Integration**: Page title updates with 🔴 indicator and contextual recording notifications only for active recordings
- **TTL Cleanup System**: Daily cron job removes expired recordings based on configurable retention policies
- **APScheduler Integration**: Cron-based automatic recording of shows at scheduled times with proper day-of-week conversion
- **Schedule Synchronization**: Real-time scheduler updates when shows are added/modified
- **Enhanced Schedule Parsing**: Natural language support for "Mondays at 7 PM", "noon", "midnight" formats and multiple separators
- **Priority-Based Scheduling**: Original broadcasts get priority 1, repeats and encores get sequential priorities
- **Weekly Schedule Verification**: Automated verification of all station schedules with change detection
- **Show Management**: Active/inactive toggle with automatic scheduler integration
- **Tags System**: Categorize shows with custom tags for better organization
- **Test Recording Cleanup**: Automated cleanup of test recordings after 4 hours
- **Next Recordings Widget**: Dashboard display of upcoming 3 scheduled recordings with real-time countdown timers
- **Database Backup System**: Automated weekly backups with 3-week retention and compressed storage
- **Enhanced Recording Service**: Database-driven recording with 30-minute duplicate prevention window
- **Quality Validation**: File size and format verification (2KB/sec minimum for recordings)
- **User-Agent Persistence**: Automatically saves working User-Agents for stations with HTTP 403 issues
- **Automatic Housekeeping**: Cleans up empty recordings every 6 hours with retention policies
- **Stream Testing**: Validates streams before recording attempts with comprehensive error handling
- **Schedule Caching**: Remembers successful parsing methods per station
- **RSS Updates**: Refreshes podcast feeds every 15 minutes with playlist support

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- A server with internet access
- Domain name (for SSL/HTTPS) *optional*

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/radiograb.git
   cd radiograb
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your settings:
   # - Database passwords
   # - Domain name (for SSL)
   # - Email address (for SSL)
   ```

3. **Start the application**
   ```bash
   docker-compose up -d
   ```

4. **Access the web interface**
   ```
   http://localhost        (development)
   https://your-domain.com (production with SSL)
   ```

## 📖 Documentation

### 📋 **Quick Setup Guides**
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment and installation
- [Database Setup](DATABASE_SETUP.md) - Fresh database initialization and migration guide
- [SSL Setup](docs/SSL_PRESERVATION_GUIDE.md) - HTTPS configuration

### 🔧 **Technical Documentation**
- [System Architecture](docs/SYSTEM_ARCHITECTURE.md) - How RadioGrab works
- [Container Setup](docs/CONTAINER_SETUP.md) - Docker configuration
- [Recording Tools Guide](docs/RECORDING_TOOLS_GUIDE.md) - Multi-tool recording system
- [Project Overview](docs/PROJECT_OVERVIEW.md) - Complete project overview

### 🛠️ **Advanced Topics**
- [Stream Testing](docs/STREAM_TESTING_INTEGRATION.md) - Automatic stream validation
- [Schedule Discovery](docs/STREAM_URL_DISCOVERY.md) - Website parsing system
- [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues and solutions

## 🏗️ Architecture

RadioGrab uses a 5-container Docker architecture:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Server    │    │    Recorder     │    │   RSS Updater   │
│ (nginx + PHP)   │    │ (Python daemon) │    │ (15min cron)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
         ┌─────────────────┬─────┴─────┐
         │   Housekeeping  │   MySQL   │
         │   (6hr cron)    │ Database  │
         └─────────────────┴───────────┘
```

### Key Components
- **Web Interface**: PHP/JavaScript frontend with Bootstrap UI, playlist management, and upload functionality
- **MP3 Metadata Service**: Automated metadata writing for all recordings with FFmpeg integration
- **Upload Service**: Audio file validation, format conversion, and playlist integration
- **Enhanced Recording Engine**: Python services with database-driven architecture, User-Agent support, and duplicate prevention
- **Schedule Parser**: JavaScript-aware calendar extraction with Chromium WebDriver for dynamic content
- **Calendar Verification**: Browser-tested verification system with detailed error reporting and fallback parsing
- **Stream Validator**: Tests streams with comprehensive error handling and User-Agent persistence
- **RSS Generator**: Creates podcast feeds from recordings with playlist support and proper call letters naming

## 🎛️ Usage

### Adding a Radio Station
1. Go to **Stations** → **Add Station**
2. Enter the station's website URL
3. Click **Discover** to automatically extract:
   - Station name and logo
   - Streaming URL
   - Schedule calendar URL
4. Save the station

### Setting Up Show Recording
1. Go to **Shows** → **Add Show**
2. Select your station
3. Choose show type:
   - **Scheduled Show**: Automatic recording based on schedule
   - **Playlist**: User-uploaded audio files with track ordering
4. Configure:
   - Show name and description
   - Schedule pattern (for scheduled shows, e.g., "Monday 9:00 AM")
   - Recording duration and retention period
   - Upload settings (for playlists: max file size, allowed formats)

### Managing Playlists
1. Create a **Playlist** type show
2. Click **Upload Audio** to add files or **Record Voice Clip** for DJ snippets
3. Upload supported formats: MP3, WAV, M4A, AAC, OGG, FLAC
4. **DJ Voice Recording**: Record intros, outros, station IDs directly in browser
   - Professional recording interface with real-time controls
   - 5-minute maximum recording with visual timer
   - Audio preview before saving with metadata editing
   - Mobile browser compatible (iOS Safari, Android Chrome, etc.)
5. Use **Order** button to manage track sequence
6. Drag & drop tracks to reorder or edit track numbers manually
7. Voice clips display with green badges and microphone icons
8. All uploads automatically include MP3 metadata (artist=show name, album=station name)

### Testing Streams
- Use **Test Recording** buttons to verify streams work
- 10-second test recordings help debug issues
- All test recordings are saved and playable

### Accessing Recordings
- **Web Interface**: Listen and download via the Recordings page
- **Enhanced RSS Feeds**: Multiple feed types with comprehensive management
  - **Universal Feeds**: "All Shows" and "All Playlists" for complete collections
  - **Station Feeds**: All shows from a specific station in one feed
  - **Individual Show Feeds**: Dedicated feeds for specific shows or playlists
  - **Custom Feeds**: User-created feeds combining selected shows with custom metadata
- **Feed Management Interface**: Create and manage custom feeds with tabbed navigation
- **iTunes Integration**: Full podcast app compatibility with proper metadata and artwork
- **Direct Files**: Access recordings via `/recordings/` URL

## 📡 Recording Compatibility

RadioGrab handles virtually any stream type through intelligent tool selection:

| Stream Type | Tool Used | Compatibility |
|-------------|-----------|---------------|
| Direct MP3/AAC | streamripper | ✅ Excellent |
| HLS/DASH Streams | ffmpeg | ✅ Excellent |
| Authenticated Streams | ffmpeg | ✅ Good |
| Redirect URLs | wget | ✅ Good |
| HTTPS/SSL Streams | ffmpeg | ✅ Excellent |

## 🔐 Security Features

- **CSRF Protection**: All forms protected against cross-site attacks
- **Session Management**: Secure PHP session handling
- **SSL/TLS Ready**: Automatic Let's Encrypt certificate management
- **Container Isolation**: Docker security boundaries
- **Environment Variables**: Sensitive data in environment files

## 🛠️ Development

### Local Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/radiograb.git
cd radiograb

# Create local environment
cp .env.example .env
# Edit .env with development settings

# Start development containers
docker-compose up -d

# View logs
docker-compose logs -f
```

### Project Structure
```
radiograb/
├── frontend/           # Web interface (PHP/JavaScript)
│   ├── public/        # Web root
│   ├── includes/      # PHP shared code
│   └── assets/        # CSS/JS/images
├── backend/           # Python services
│   ├── services/      # Recording/parsing services
│   ├── models/        # Database models
│   └── utils/         # Utility functions
├── database/          # SQL schema and migrations
├── docker/            # Docker configuration files
└── docs/             # Documentation
```

### Contributing
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `your_root_password` |
| `MYSQL_PASSWORD` | MySQL user password | `your_db_password` |
| `DB_PASSWORD` | Application database password | `your_db_password` |
| `SSL_DOMAIN` | Domain for SSL certificate | `your-domain.com` |
| `SSL_EMAIL` | Email for Let's Encrypt | `admin@your-domain.com` |

### Database Configuration
RadioGrab uses MySQL 8.0 with a comprehensive 13-table schema:

#### Core Tables
- **stations**: Radio station information with User-Agent and streaming data
- **shows**: Show definitions, schedules, and playlist configuration (show_type, allow_uploads, max_file_size_mb)
- **recordings**: Individual recording entries with metadata, track ordering, and source tracking (source_type, track_number, original_filename)
- **cron_jobs**: APScheduler job tracking for automated recordings

#### Enhanced Features
- **custom_feeds / custom_feed_shows**: Custom RSS feed system with user-selected shows
- **station_feeds**: Pre-configured RSS feeds per station
- **show_schedules**: Multiple airings support (original + repeat broadcasts)

#### Admin & System
- **users**: Admin authentication system
- **site_settings**: Customizable branding and configuration
- **stream_tests**: Stream testing history and validation results
- **system_info**: System metadata and version tracking
- **schema_migrations**: Database migration tracking
- **feed_generation_log**: RSS generation monitoring and debugging

For detailed database setup information, see [DATABASE_SETUP.md](DATABASE_SETUP.md).

## 🆘 Troubleshooting

### Common Issues

**"Network error occurred during test recording"**
- Check if the stream URL is accessible
- Verify Docker containers are running: `docker-compose ps`
- Check logs: `docker-compose logs recorder`

**"Recording file not found"**
- Ensure recordings directory has proper permissions
- Check if recording completed successfully
- Verify nginx configuration for `/recordings/` path

**CSRF Token Issues**
- Clear browser cache and cookies
- Check PHP session configuration
- Verify CSRF debug endpoint: `/api/debug-csrf.php`

**Calendar Verification Errors**
- Check if station has proper call letters configured
- Verify calendar URL is accessible and not JavaScript-protected
- Use "Re-check calendar now" button for manual verification
- Check logs: `docker logs radiograb-web-1 --tail 50`

For more troubleshooting help, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## 📊 System Requirements

### Minimum Requirements
- **CPU**: 1 vCPU (2+ recommended for multiple concurrent recordings)
- **RAM**: 1GB (2GB+ recommended)
- **Storage**: 10GB+ (depends on retention settings)
- **Network**: Stable internet connection

### Recommended Production Setup
- **CPU**: 2+ vCPUs
- **RAM**: 4GB
- **Storage**: 100GB+ SSD
- **Network**: High-bandwidth connection for multiple streams

## 🔄 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Streamripper**: For reliable MP3 stream recording
- **FFmpeg**: For handling complex streaming protocols
- **Bootstrap**: For the responsive web interface
- **Docker**: For containerization and easy deployment
- **Let's Encrypt**: For free SSL certificates

## 📞 Support

- **Documentation**: Check the `/docs` directory
- **Issues**: Open an issue on GitHub
- **Discussions**: Use GitHub Discussions for questions

---

**Made with ❤️ for radio enthusiasts and podcast lovers**