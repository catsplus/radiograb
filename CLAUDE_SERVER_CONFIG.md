# Claude Server Configuration Reference

## Critical Server Information
**NEVER FORGET**: All changes must be deployed to the REMOTE SERVER, not just local files!

### Server Details
- **Domain**: radiograb.svaha.com  
- **IP Address**: 167.71.84.143
- **SSH Access**: `ssh radiograb@167.71.84.143` (using SSH keys)
- **Server Path**: `/opt/radiograb/`

### Architecture
- **Platform**: Ubuntu on DigitalOcean droplet
- **Deployment**: Docker containers via docker-compose
- **Services**: 
  - `radiograb-web-1` - Main web service (Nginx + PHP-FPM)
  - `radiograb-recorder-1` - Recording service
  - `radiograb-rss-updater-1` - RSS feed updater
  - `radiograb-mysql-1` - Database

### ⚠️ CRITICAL: DOCKER CONTAINER ARCHITECTURE ⚠️ 
**🚨 THE APPLICATION RUNS ENTIRELY IN DOCKER CONTAINERS 🚨**
**🚨 ALL APPLICATION FILES ARE INSIDE CONTAINERS, NOT ON HOST FILESYSTEM 🚨**

#### Container Details:
- **Web Container**: `radiograb-web-1` (Nginx + PHP-FPM + Python services) - THIS IS WHERE THE APPLICATION RUNS
- **Database Container**: `radiograb-mysql-1` (MySQL 8.0) - THIS IS WHERE THE DATABASE RUNS
- **Recorder Container**: `radiograb-recorder-1` (Recording service) - THIS IS WHERE RECORDINGS HAPPEN
- **RSS Container**: `radiograb-rss-updater-1` (RSS feed updater) - THIS IS WHERE RSS FEEDS ARE GENERATED

#### File Architecture - READ THIS CAREFULLY:
**Dockerfile uses: `COPY . /opt/radiograb/` - Files are COPIED into container during build**
- **Host filesystem**: `/opt/radiograb/` on server (source files for building containers)
- **Container filesystem**: `/opt/radiograb/` inside containers (WHERE THE APPLICATION ACTUALLY RUNS)
- **🚨 CRITICAL**: Files are COPIED during container build, NOT mounted as live volumes
- **🚨 CRITICAL**: Editing host files does NOT change running application
- **🚨 CRITICAL**: Must rebuild containers to pick up any file changes

#### Deployment Process (CORRECTED):
1. **Make changes locally** in `/Users/mjb9/scripts/radiograb/`
2. **Copy to server host** using scp:
   ```bash
   scp file.php radiograb@167.71.84.143:/opt/radiograb/path/to/file.php
   ```
3. **Rebuild and restart containers** to pick up changes:
   ```bash
   ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker-compose down && docker-compose up -d --build"
   ```
   
#### Why Restart Alone Doesn't Work:
- Files are COPIED into container during build (not mounted)
- Updating host files doesn't affect running container
- Must rebuild container to pick up file changes

### Key Directories on Server
- **Frontend**: `/opt/radiograb/frontend/public/`
- **Backend**: `/opt/radiograb/backend/services/`
- **Database**: `/opt/radiograb/database/`
- **Configuration**: `/opt/radiograb/.env`

### Quick Commands
```bash
# SSH to server
ssh radiograb@167.71.84.143

# Check container status
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker-compose ps"

# View logs
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker-compose logs web"

# PROPER deployment (rebuild containers):
# 1. Update VERSION file first
# 2. Deploy files to server
# 3. Rebuild containers
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker-compose down && docker-compose up -d --build"

# Copy single file to host (then rebuild)
scp local_file.php root@radiograb.svaha.com:/opt/radiograb/path/

# Copy multiple files to host (then rebuild)  
rsync -av --progress local_dir/ root@radiograb.svaha.com:/opt/radiograb/path/

# Deploy with version update (recommended):
scp /Users/mjb9/scripts/radiograb/VERSION root@radiograb.svaha.com:/opt/radiograb/

# Execute commands inside container:
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 command"

# Interactive shell in container:
ssh radiograb@167.71.84.143 "docker exec -it radiograb-web-1 /bin/bash"
```

### Database Access
```bash
# Connect to MySQL container
ssh radiograb@167.71.84.143 "docker exec -it radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb"
```

### URLs
- **Main Site**: https://radiograb.svaha.com/
- **Settings**: https://radiograb.svaha.com/settings.php
- **Stations**: https://radiograb.svaha.com/stations.php

### Enhanced Calendar Parsing System
**ENHANCED FEATURE**: Both calendar URLs AND parsing methods are now cached in the database.

#### How It Works:
- When schedule import succeeds, both URL and parsing method are saved to database
- Future imports use the exact same parsing strategy that worked before
- Supports different parsing methods: `html`, `json`, `xml`, `ical`, `custom`
- Dramatically reduces import time and prevents parsing failures
- Manual override: Update both `calendar_url` and `calendar_parsing_method` fields

#### Database Schema:
```sql
-- Enhanced calendar fields in stations table
ALTER TABLE stations ADD COLUMN calendar_url VARCHAR(500) DEFAULT NULL;
ALTER TABLE stations ADD COLUMN calendar_parsing_method TEXT DEFAULT NULL;
```

#### Parsing Methods:
- **`html`**: Standard HTML table/div parsing (most common)
- **`json`**: JSON API endpoints with schedule data
- **`xml`**: XML/RSS feeds with programming information  
- **`ical`**: Calendar feeds (.ics files)
- **`custom`**: Station-specific parsing logic (for complex sites like WYSO)

#### Manual Configuration Example:
```sql
-- Set WYSO to use custom parsing method
UPDATE stations SET 
  calendar_url = 'https://www.wyso.org/wyso-schedule',
  calendar_parsing_method = 'custom_wyso'
WHERE name = 'WYSO';
```

### Version Tracking System  
**NEW FEATURE**: Version timestamps displayed on website footer for deployment tracking.

#### How It Works:
- Version info stored in `/VERSION` file in project root
- Website footer displays current version from this file
- Update VERSION file with each deployment to track changes
- Format: `YYYY-MM-DD HH:MM:SS - Description of changes`

#### Current Version:
```
2025-07-20 21:55:00 - Fixed Docker deployment process, added calendar URL caching, added version tracking
```

### Important Notes ⚠️ CRITICAL REMINDERS
- ⚠️ **ALWAYS deploy changes to remote server**
- ⚠️ **Local files are NOT the running server**
- ⚠️ **Files are COPIED into containers, not mounted**
- ⚠️ **Must REBUILD containers after file changes (not just restart)**
- ⚠️ **`docker-compose restart` does NOT pick up file changes**
- ⚠️ **Use `docker-compose down && docker-compose up -d --build`**
- ⚠️ **UPDATE VERSION file with each deployment**
- ⚠️ **Calendar URLs are cached - check database for saved URLs**

### Emergency Recovery
If something breaks:
```bash
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker-compose down && docker-compose up -d"
```