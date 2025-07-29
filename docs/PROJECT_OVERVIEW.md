# RadioGrab - Complete Project Overview

## 🎯 Project Mission

RadioGrab transforms any radio station into a personal podcast library. It's a "Radio Recording System" that automatically discovers streaming URLs, parses show schedules, records audio content, and generates podcast feeds - all while handling the complexities of timezone management, stream compatibility, and system maintenance.

## ✨ Key Innovations

### 1. **Complete Playlist Upload System**
- User audio file uploads with multi-format support (MP3, WAV, M4A, AAC, OGG, FLAC)
- Drag & drop track ordering with automatic sequential numbering
- Real-time playlist management with track reordering interface
- Automatic format conversion to MP3 with quality validation
- Upload progress tracking and comprehensive error handling

### 2. **Comprehensive MP3 Metadata Implementation**
- Automatic metadata writing for all recordings using FFmpeg integration
- Standardized tagging: artist=show name, album=station name, recording date, description
- Upload metadata enhancement preserves existing tags while adding show/station information
- Genre tagging support with metadata source tracking
- Backend service architecture for consistent metadata management

### 3. **JavaScript-Aware Schedule Parsing**
- Handles dynamic web calendars (WordPress plugins, FullCalendar, etc.)
- Selenium WebDriver for JavaScript rendering
- Google Sheets iframe parsing for embedded schedules
- Eliminates "No shows found" issues with modern station websites

### 4. **Intelligent Recording Tool Management**
- Tests and stores optimal recording method per station (streamripper/wget/ffmpeg)
- Eliminates repeated compatibility discovery
- Database storage of stream testing results
- Automatic fallback to alternative tools when needed

### 5. **Comprehensive Test & On-Demand System**
- **Test Recording**: 30-second stream validation with one-click button
- **On-Demand Recording**: 1-hour manual recordings from station cards
- Automatic show creation for on-demand recordings
- Test files isolated in temporary directory

### 6. **Automatic Housekeeping Service**
- Runs every 6 hours to clean empty recording files
- Prevents accumulation of 33,000+ zero-byte files
- Removes orphaned database records
- Logs cleanup statistics and performance metrics

### 7. **Advanced Timezone Management**
- Per-station timezone storage and handling
- Container-wide EST/EDT timezone configuration
- Prevents recordings at wrong times due to timezone confusion
- Database fields for station and show timezone preferences

### 8. **Call Letters File Organization**
- Human-readable naming: `{CALL_LETTERS}_{show_name}_YYYYMMDD_HHMM.mp3`
- Easy identification with 4-letter call signs (WEHC, WERU, WTBR, WYSO)
- Improved file management and organization
- Supports multi-station deployments with intuitive file names

## 🏗️ System Architecture

### Enhanced Recording Service v2.5.0
Complete rewrite with database-driven architecture, unified recording strategies, and full integration with test recording proven methods.

### Container Architecture (5 Services)
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   radiograb-    │ │   radiograb-    │ │   radiograb-    │
│     web-1       │ │   recorder-1    │ │    mysql-1      │
│                 │ │                 │ │                 │
│ • Web Interface │ │ • Recording     │ │ • Database      │
│ • PHP Frontend  │ │   Daemon        │ │ • Timezone      │
│ • API Endpoints │ │ • APScheduler   │ │   Support       │
│ • Test Record   │ │ • Multi-tool    │ │ • Show/Station  │
│   Service       │ │   Recording     │ │   Metadata      │
└─────────────────┘ └─────────────────┘ └─────────────────┘

┌─────────────────┐ ┌─────────────────┐
│   radiograb-    │ │   radiograb-    │
│ rss-updater-1   │ │ housekeeping-1  │
│                 │ │                 │
│ • RSS Feed Gen  │ │ • Empty File    │
│ • Master Feed   │ │   Cleanup       │
│ • Every 15min   │ │ • Every 6hrs    │
│ • iTunes Compat │ │ • Orphan Record │
│                 │ │   Removal       │
└─────────────────┘ └─────────────────┘
```

### Recording Flow Architecture
```
Web Interface → API → Background Service → Recording Tools → File Storage
     ↓              ↓           ↓               ↓             ↓
  Station       Test/On-    Recording      Tool Selection   Database
   Cards       Demand API   Service       (streamripper/    Recording
                                         wget/ffmpeg)       Entries
```

## 🔧 Technical Innovations

### 1. **Multi-Strategy Schedule Discovery**
- **HTML Parsing**: Traditional scrapers for static content
- **JavaScript Rendering**: Selenium Chrome WebDriver for dynamic content
- **API Discovery**: Direct calendar feeds and JSON endpoints
- **iframe Parsing**: Google Sheets and embedded calendar extraction
- **Plugin Detection**: WordPress calendar plugin identification

### 2. **MP3 Metadata Service Architecture**
```python
# Comprehensive metadata writing with FFmpeg
def write_metadata_for_recording(self, recording_id: int) -> bool:
    """Write comprehensive MP3 metadata for recording"""
    recording = db.query(Recording).filter(Recording.id == recording_id).first()
    show = recording.show
    station = show.station
    
    metadata = {
        'artist': show.name,
        'album': station.name,
        'title': f"{show.name} - {recording.recorded_at.strftime('%Y-%m-%d')}",
        'comment': show.description or f"Recorded from {station.name}",
        'date': recording.recorded_at.strftime('%Y-%m-%d'),
        'genre': show.genre or 'Radio Show'
    }
    
    return self._write_metadata_with_ffmpeg(recording.filename, metadata)
```

### 3. **Upload Service with Format Conversion**
```python
# Multi-format upload handling with automatic conversion
def handle_audio_upload(self, file_data: bytes, filename: str, show_id: int) -> Dict:
    """Process uploaded audio file with format validation and conversion"""
    # Validate audio format
    if not self._is_valid_audio_file(file_data, filename):
        raise ValueError("Invalid audio file format")
    
    # Convert to MP3 if needed
    if not filename.lower().endswith('.mp3'):
        file_data = self._convert_to_mp3(file_data, filename)
        filename = filename.rsplit('.', 1)[0] + '.mp3'
    
    # Extract and enhance metadata
    metadata = self._extract_metadata(file_data)
    enhanced_metadata = self._enhance_upload_metadata(metadata, show_id)
    
    # Save file and write metadata
    saved_path = self._save_upload_file(file_data, filename, show_id)
    self._write_metadata(saved_path, enhanced_metadata)
    
    return {'success': True, 'filename': filename, 'metadata': enhanced_metadata}
```

### 4. **Recording Tool Intelligence**
```python
# Intelligent tool selection with database storage
def _get_station_recommended_tool(self, show_id: int) -> Optional[str]:
    """Get stored optimal recording tool for station"""
    show = db.query(Show).filter(Show.id == show_id).first()
    if show and show.station:
        recommended_tool = show.station.recommended_recording_tool
        if recommended_tool and self.available_tools.get(recommended_tool):
            return recommended_tool
    return None
```

### 5. **Automatic Cleanup System**
```python
# Prevents empty file accumulation
def cleanup_empty_recordings(self) -> Dict:
    """Remove zero-byte MP3 files and orphaned records"""
    empty_files = []
    for file_path in self.recordings_dir.glob("*.mp3"):
        if file_path.stat().st_size == 0:
            empty_files.append(file_path)
            file_path.unlink()  # Delete empty file
    
    # Remove orphaned database records
    orphaned_records = self.remove_orphaned_recordings()
    return {'files_removed': len(empty_files), 'records_cleaned': orphaned_records}
```

### 6. **Deployment Automation**
```bash
# Comprehensive deployment with verification
./deploy.sh                    # Full system deployment
./quick-deploy.sh stations.php # Single file deployment
```

## 📊 System Capabilities

### Recording Management
- **Scheduled Recordings**: APScheduler-based (not system cron)
- **Test Recordings**: 30-second stream validation
- **On-Demand Recordings**: 1-hour manual captures
- **Multi-Tool Support**: streamripper, wget, ffmpeg with intelligent selection
- **Automatic Cleanup**: Empty file prevention and removal
- **MP3 Metadata**: Comprehensive metadata writing for all recordings

### Playlist Management
- **User Uploads**: Multi-format audio file uploads (MP3, WAV, M4A, AAC, OGG, FLAC)
- **Track Ordering**: Drag & drop reordering with sequential numbering
- **Format Conversion**: Automatic conversion to MP3 with quality validation
- **Metadata Enhancement**: Upload metadata preserved and enhanced with show/station info
- **Playlist Interface**: Dedicated management interface with real-time updates

### Station Management  
- **Discovery Engine**: Automatic stream URL detection
- **Schedule Import**: JavaScript-aware calendar parsing
- **Timezone Handling**: Per-station timezone storage
- **Stream Testing**: Compatibility validation and storage
- **Tool Preference**: Optimal recording method per station

### File Organization
- **Consistent Naming**: Station-prefixed filenames with call letters
- **Directory Structure**: Separated test, on-demand, scheduled recordings, and uploaded files
- **RSS Generation**: iTunes-compatible feeds with master feed and playlist support
- **Web Interface**: Built-in audio player and management with upload functionality
- **Metadata Management**: Automatic MP3 tagging with FFmpeg integration

## 🎯 Problem Solutions

### Original Challenge: "No shows found in the station schedule"
**Root Cause**: Modern station websites use JavaScript calendars that traditional scrapers can't parse

**Solution**: 
- Selenium WebDriver with Chrome headless browser
- 5 parsing strategies with automatic fallback
- Google Sheets iframe detection and parsing
- WordPress calendar plugin support

### Original Challenge: 33,000+ empty recording files
**Root Cause**: Recording tools create files immediately, even on stream failure or timezone issues

**Solution**:
- Housekeeping service runs every 6 hours
- Automatic empty file detection and removal
- Orphaned database record cleanup
- Container-wide timezone configuration (EST/EDT)

### Original Challenge: Rediscovering stream compatibility repeatedly
**Root Cause**: No storage of which recording tool works best for each station

**Solution**:
- Database fields for recording method storage
- Automatic stream testing with tool preference storage
- Intelligent tool selection based on historical success
- Elimination of repeated compatibility discovery

### Original Challenge: Manual deployment and missed file updates
**Root Cause**: No systematic deployment process

**Solution**:
- Comprehensive deployment script (`deploy.sh`)
- Quick single-file deployment (`quick-deploy.sh`)
- Container-aware file distribution
- Automatic service restart and verification

## 🚀 Deployment Architecture

### File Distribution Strategy
```
Local Development → Server Temp → Docker Containers
      ↓                ↓              ↓
  Git Commits     SCP Transfer   Docker CP
  Documentation   Database       Service
  Code Changes    Migrations     Restart
```

### Container Service Management
```yaml
# docker-compose.yml - 5-container architecture
services:
  web:          # Frontend, APIs, Python services
  recorder:     # Recording daemon, APScheduler  
  mysql:        # Database with timezone support
  rss-updater:  # Feed generation every 15 minutes
  housekeeping: # Cleanup service every 6 hours
```

## 📈 System Metrics & Monitoring

### Recording Performance
- Zero-byte file prevention and tracking
- Recording success rates by tool and station
- Stream compatibility database with historical data
- Automatic cleanup statistics and performance metrics

### Service Health
- Container status monitoring via Docker health checks
- APScheduler job status and execution tracking
- Database connection health and query performance
- RSS feed generation success and timing metrics

### Storage Management
- Automatic retention policy enforcement
- Empty file cleanup with detailed logging
- Orphaned record detection and removal
- Disk usage monitoring and alerting capabilities

## 🎯 Future Development Roadmap

### Short-term Enhancements
- Stream quality monitoring and automatic adjustment
- Enhanced mobile interface for on-demand recording
- Email notifications for recording failures
- Advanced search and filtering in web interface

### Long-term Vision
- Multi-server deployment with load balancing
- Machine learning for optimal recording timing
- Integration with popular podcast platforms
- Community-driven station database and sharing

---

**RadioGrab represents a complete solution for automated radio recording**, combining modern web scraping techniques, intelligent system management, and robust deployment automation to create a reliable, maintainable, and user-friendly platform for transforming live radio into personal podcast libraries.