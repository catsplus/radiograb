# RadioGrab Container Configuration

## Server Setup
- **Host**: DigitalOcean Droplet (167.71.84.143)
- **OS**: Ubuntu 22.04
- **Domain**: radiograb.svaha.com
- **User**: `radiograb` user (member of `docker` group)
- **Project Directory**: `/opt/radiograb/` (owned by `radiograb:radiograb`)

## Container Architecture

### radiograb-web-1
- **Purpose**: Web interface and frontend services  
- **Base Image**: Ubuntu 22.04
- **Application Path**: `/opt/radiograb/`
- **Web Root**: `/opt/radiograb/frontend/public/`
- **Ports**: 80, 443
- **Health Check**: HTTP endpoint verification

### radiograb-recorder-1
- **Purpose**: Background recording and scheduling services
- **Base Image**: Ubuntu 22.04  
- **Application Path**: `/opt/radiograb/`
- **Recording Service**: `/opt/radiograb/backend/services/recording_service.py`
- **Data Directories**: 
  - Recordings: `/var/radiograb/recordings/`
  - Temp: `/var/radiograb/temp/`
  - Logs: `/var/radiograb/logs/`
  - Logos: `/var/radiograb/logos/`

### radiograb-rss-updater-1
- **Purpose**: RSS feed generation and updates (individual + master feeds)
- **Base Image**: Ubuntu 22.04
- **Application Path**: `/opt/radiograb/`
- **RSS Services**: 
  - Individual show feeds: `/opt/radiograb/backend/services/rss_service.py`
  - Master feed: Combined feed of all shows (chronological order)
  - Feed directory: `/var/radiograb/feeds/` (includes master.xml)

### radiograb-mysql-1  
- **Purpose**: Database server
- **Base Image**: MySQL 8.0
- **Port**: 3306
- **Database**: `radiograb`
- **User**: `radiograb`

## Recording Tools Configuration

All recording tools are installed in `radiograb-recorder-1`:

### streamripper
- **Path**: `/usr/bin/streamripper`
- **Use Case**: Direct HTTP/MP3 streams
- **Optimal For**: Traditional radio streams

### ffmpeg  
- **Path**: `/usr/bin/ffmpeg`
- **Use Case**: Authentication-required streams, modern protocols
- **Optimal For**: Complex streaming setups

### wget
- **Path**: `/usr/bin/wget`
- **Use Case**: Redirect URLs (StreamTheWorld)
- **Optimal For**: URL redirects and simple HTTP downloads

## Python Dependencies

**Virtual Environment**: `/opt/radiograb/venv/` (CRITICAL: All Python scripts must use this)

**Core Dependencies (from requirements.txt):**
- **APScheduler** (3.11.0): Job scheduling and cron management
- **beautifulsoup4** (4.13.4): HTML parsing for station discovery and schedule extraction
- **lxml** (6.0.0): XML/HTML parser backend for BeautifulSoup
- **mysql-connector-python** (9.4.0): MySQL database connector
- **SQLAlchemy** (2.0.41): Database ORM
- **python-crontab** (3.3.0): Cron job management
- **mutagen** (1.47.0): Audio metadata manipulation
- **eyeD3** (0.9.8): MP3 tag editing
- **python-dateutil** (2.9.0.post0): Date/time parsing utilities
- **icalendar** (6.3.1): iCal/ICS calendar parsing
- **pytz** (2025.2): Timezone handling
- **feedgen** (1.0.0): RSS feed generation
- **PyYAML** (6.0.2): YAML configuration parsing
- **python-dotenv** (1.1.1): Environment variable loading
- **requests** (2.32.4): HTTP client library
- **urllib3** (2.5.0): HTTP library foundation
- **chardet** (5.2.0): Character encoding detection
- **Pillow** (10.4.0): Image processing for logo optimization and resizing
- **selenium** (4.x): JavaScript-aware web scraping (manually installed)

**Auto-installed Dependencies:**
- **certifi** (2025.7.14): SSL certificate bundle
- **charset-normalizer** (3.4.2): Unicode text normalization
- **deprecation** (2.1.0): Deprecation warnings
- **filetype** (1.2.0): File type detection
- **greenlet** (3.2.3): Coroutine support for SQLAlchemy
- **idna** (3.10): Internationalized domain names
- **packaging** (25.0): Version parsing and comparison
- **six** (1.17.0): Python 2/3 compatibility
- **soupsieve** (2.7): CSS selector library for BeautifulSoup
- **typing_extensions** (4.14.1): Extended type hints
- **tzdata** (2025.2): Timezone database
- **tzlocal** (5.3.1): Local timezone detection

### Manually Installed Packages (Post-Container Build)
These packages were installed after container deployment and should be added to the Dockerfile:

```bash
# Installed on 2025-07-23 for multi-tool recording support
docker exec radiograb-recorder-1 apt update && docker exec radiograb-recorder-1 apt install -y ffmpeg

# Installed on 2025-07-29 for image processing and logo storage
docker exec radiograb-web-1 /opt/radiograb/venv/bin/pip install Pillow
```

**⚠️ TODO for Next Container Rebuild**: 
- Add `ffmpeg` installation to Dockerfile to ensure it's included in future builds
- Add `Pillow` to requirements.txt for logo image processing

### Package Installation Status
**✅ Python packages**: Most properly defined in requirements.txt
**⚠️ Missing from requirements.txt**: Pillow (image processing) - manually installed
**⚠️ System packages**: ffmpeg was manually installed and needs to be added to Dockerfile

## File Deployment Process

1. **Copy to server**: `scp file.py radiograb@167.71.84.143:/tmp/`
2. **Copy to container**: `docker cp /tmp/file.py radiograb-recorder-1:/opt/radiograb/path/file.py`
3. **Restart if needed**: `docker compose restart service-name`

## Container Management Commands

All commands run as `radiograb` user in `/opt/radiograb/`:

```bash
# Start all services
docker compose up -d

# Stop all services  
docker compose down

# Restart specific service
docker compose restart radiograb-recorder-1

# View logs
docker logs radiograb-recorder-1 --tail 50

# Execute Python commands in container (CRITICAL: Use virtual environment)
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/recording_service.py --test

# Execute with proper environment
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/recording_service.py --test"

# RSS Management Commands
docker exec radiograb-web-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/rss_manager.py --action update-all"
docker exec radiograb-web-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/rss_manager.py --action update-master"

# Check container status
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Install new Python packages (if needed)
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/pip install package_name

# Logo and Social Media Services (new 2025-07-29)
docker exec radiograb-web-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/logo_storage_service.py --help"
docker exec radiograb-web-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/facebook_logo_extractor.py --help"
docker exec radiograb-web-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/social_media_detector.py --help"
```

### ⚠️ CRITICAL Python Execution Rules
- **NEVER use**: `python3` or `python` directly
- **ALWAYS use**: `/opt/radiograb/venv/bin/python`
- **Set PYTHONPATH**: `PYTHONPATH=/opt/radiograb`
- **Working directory**: `cd /opt/radiograb`

## Database Connection

Environment variables in containers:
- **DB_HOST**: `mysql`
- **DB_PORT**: `3306`  
- **DB_USER**: `radiograb`
- **DB_PASSWORD**: `radiograb_pass_2024`
- **DB_NAME**: `radiograb`
- **DATABASE_URL**: `mysql+pymysql://radiograb:radiograb_pass_2024@mysql:3306/radiograb`

## Security Configuration

- **SSH Access**: Key-based authentication for `radiograb` user
- **Docker Isolation**: Containers run in isolated network  
- **File Permissions**: `/opt/radiograb/` owned by `radiograb:radiograb`
- **Non-root Execution**: All containers run under `radiograb` user context

## API Endpoints

### Core APIs
- **CSRF Token**: `/api/get-csrf-token.php` - Security token generation
- **Test Recording**: `/api/test-recording.php` - Manual recording tests and on-demand recordings
- **Show Management**: `/api/shows.php` - Show creation and management

### Logo and Social Media APIs (New 2025-07-29)
- **Station Logo Update**: `/api/station-logo-update.php` - Bulk and individual station logo/social media updates
  - Actions: `update_station_logos`, `update_single_station`, `get_logo_update_status`
  - Features: Facebook logo extraction, local logo storage, social media link detection

### Data Directories
- **Logos**: `/var/radiograb/logos/` - Local station logos and social media images
- **Recordings**: `/var/radiograb/recordings/` - Audio recordings
- **Feeds**: `/var/radiograb/feeds/` - RSS feeds
- **Temp**: `/var/radiograb/temp/` - Test recordings and temporary files

### New Python Services (2025-07-29)
- **logo_storage_service.py**: Downloads, optimizes, and stores station logos locally
- **facebook_logo_extractor.py**: Extracts profile pictures from Facebook pages
- **social_media_detector.py**: Detects and categorizes social media links
- **mp3_metadata_service.py**: Writes comprehensive MP3 metadata for recordings

### Database Schema Extensions (2025-07-29)
```sql
-- Logo and social media storage
ALTER TABLE stations 
ADD COLUMN facebook_url VARCHAR(500) NULL,
ADD COLUMN local_logo_path VARCHAR(255) NULL,
ADD COLUMN logo_source VARCHAR(50) NULL,
ADD COLUMN logo_updated_at TIMESTAMP NULL,
ADD COLUMN social_media_links JSON NULL,
ADD COLUMN social_media_updated_at TIMESTAMP NULL;

-- Show type and playlist support
ALTER TABLE shows
ADD COLUMN show_type ENUM('scheduled', 'playlist') DEFAULT 'scheduled',
ADD COLUMN allow_uploads BOOLEAN DEFAULT FALSE,
ADD COLUMN max_file_size_mb INT DEFAULT 50,
ADD COLUMN description TEXT NULL;

-- Recording source tracking
ALTER TABLE recordings
ADD COLUMN source_type ENUM('scheduled', 'test', 'on_demand', 'upload') DEFAULT 'scheduled',
ADD COLUMN track_number INT NULL;
```