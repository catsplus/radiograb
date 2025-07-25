# CLAUDE.md - Complete RadioGrab System Reference

**⚠️ CRITICAL: READ THIS FIRST - COMPLETE SYSTEM ARCHITECTURE ⚠️**

## 🚨 PRODUCTION SERVER DETAILS 🚨

- **Domain**: https://radiograb.svaha.com
- **Server IP**: 167.71.84.143
- **SSH User**: `radiograb` (SSH key authentication)
- **Project Directory**: `/opt/radiograb/` (owned by `radiograb:radiograb`)
- **Platform**: Ubuntu 22.04 on DigitalOcean droplet

## 🚨 CRITICAL DEPLOYMENT ARCHITECTURE 🚨

### Docker Container Architecture
**THE ENTIRE APPLICATION RUNS IN DOCKER CONTAINERS - NOT ON HOST FILES!**

```yaml
# 5-Container Architecture
radiograb-web-1:        # Web interface + API (nginx + PHP-FPM + Python)
radiograb-recorder-1:   # Recording daemon service
radiograb-mysql-1:      # Database (MySQL 8.0)
radiograb-rss-updater-1: # RSS feed generation (every 15 min)
radiograb-housekeeping-1: # Cleanup service (every 6 hours)
```

### File Architecture - CRITICAL UNDERSTANDING
```bash
# Files are BAKED INTO containers at build time via:
COPY . /opt/radiograb/

# Host filesystem: /opt/radiograb/ (used for BUILDING containers)
# Container filesystem: /opt/radiograb/ (WHERE THE APPLICATION ACTUALLY RUNS)
# 🚨 CRITICAL: Host file changes do NOT affect running containers
# 🚨 CRITICAL: Must REBUILD containers to deploy file changes
```

## ✅ STREAMLINED DEPLOYMENT WORKFLOW

### ✅ PUBLIC REPOSITORY: Direct GitHub Deployment
**THE PRODUCTION SERVER CAN NOW PULL FROM THE PUBLIC RADIOGRAB REPOSITORY.**

The RadioGrab repository is now public, which simplifies deployment:

#### Benefits of Public Repository:
- No authentication required for `git pull`
- The `./deploy-from-git.sh` script can pull latest changes directly from GitHub
- Server repository stays synchronized with latest commits
- **CRITICAL**: Deployment workflow is now streamlined and reliable

#### Simple Deployment Process:
- Push changes to GitHub ✅
- Run `./deploy-from-git.sh` ✅ (pulls from GitHub automatically)
- Container rebuilds with latest code ✅
- Changes are deployed and working ✅

### ✅ PREFERRED: Git-Based Deployment
```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Deploy using the automated script
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"
```

### ✅ SIMPLE DEPLOYMENT WORKFLOW

**STREAMLINED PROCESS FOR PUBLIC REPOSITORY:**

```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Deploy using the automated script (pulls from GitHub automatically)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"

# 3. Script automatically:
#    - Pulls latest changes from GitHub
#    - Rebuilds containers with new code
#    - Restarts all services

# 4. Verify deployment worked
curl -I https://radiograb.svaha.com/
```

### ✅ DEPLOYMENT VERIFICATION CHECKLIST

**VERIFY your deployment worked:**
- [ ] Did you push changes to GitHub first?
- [ ] Did the deploy script show "Pulling from GitHub" (not "Using local repository")?
- [ ] Did containers rebuild successfully?
- [ ] Did you test your changes work on the live site?
- [ ] Check logs if something seems wrong: `docker logs radiograb-web-1 --tail 20`

### Alternative: Direct Server File Management
```bash
# 1. Edit files directly on server in /opt/radiograb/
ssh radiograb@167.71.84.143 "nano /opt/radiograb/path/to/file.php"

# 2. Rebuild containers (REQUIRED!)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"
```

### Emergency Hotfix Process
```bash
# 1. Edit files directly on server in /opt/radiograb/
# 2. Test changes work
# 3. Rebuild containers: docker compose down && docker compose up -d --build
# 4. Copy changes back to local and commit to avoid losing them
```

### 🔧 Future Fix: Git Authentication Setup
To enable proper git-based deployment, the server needs:
- SSH key setup for GitHub authentication, OR
- Personal Access Token configuration, OR
- Deploy key configuration for the repository

## 🎯 SYSTEM CAPABILITIES & FEATURES

### Core Functionality
- **TiVo for Radio**: Automatically record radio shows and generate podcast feeds
- **Station Discovery**: Extract streaming URLs, logos, schedules from website URLs
- **JavaScript-Aware Parsing**: Selenium WebDriver for dynamic calendars
- **Multi-Tool Recording**: streamripper/wget/ffmpeg with automatic tool selection
- **Test & On-Demand Recording**: 30-second tests + 1-hour manual recordings
- **RSS Feeds**: Individual show feeds + master feed (all shows combined)
- **Automatic Housekeeping**: Cleans empty files every 6 hours

### Recording Tools (All in radiograb-recorder-1 container)
- **streamripper** (`/usr/bin/streamripper`): Direct HTTP/MP3 streams
- **ffmpeg** (`/usr/bin/ffmpeg`): Authentication, modern protocols  
- **wget** (`/usr/bin/wget`): Redirect URLs (StreamTheWorld)

### Key Directories
```bash
# Inside Containers (where application runs):
/opt/radiograb/                    # Application root
/opt/radiograb/frontend/public/    # Web interface
/opt/radiograb/backend/services/   # Python services
/opt/radiograb/venv/               # Python virtual environment (CRITICAL!)
/var/radiograb/recordings/         # Recorded audio files
/var/radiograb/feeds/             # RSS feeds
/var/radiograb/temp/              # Test recordings
/var/radiograb/logs/              # Application logs
```

## 🔧 TECHNICAL REQUIREMENTS

### Python Execution (CRITICAL!)
```bash
# ❌ WRONG - will fail with module errors:
python3 script.py
docker exec radiograb-recorder-1 python3 script.py

# ✅ CORRECT - uses virtual environment:
docker exec radiograb-recorder-1 /opt/radiograb/venv/bin/python /opt/radiograb/backend/services/script.py

# ✅ CORRECT - with environment:
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/script.py"
```

### Database Connection
```bash
# Environment Variables in Containers:
DB_HOST=mysql
DB_PORT=3306
DB_USER=radiograb
DB_PASSWORD=radiograb_pass_2024
DB_NAME=radiograb

# Direct MySQL access:
ssh radiograb@167.71.84.143 "docker exec -it radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb"
```

### Timezone Configuration
```bash
# All containers use Eastern Time (America/New_York)
TZ=America/New_York

# Configured in:
# - Dockerfile: ENV TZ=America/New_York
# - docker-compose.yml: TZ=America/New_York for all services
# - Ensures recording timestamps match local time zone

# Verify timezone in containers:
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 date"
ssh radiograb@167.71.84.143 "docker exec radiograb-recorder-1 date"
```

### Python Dependencies (Virtual Environment Required)
```bash
# Critical packages (from requirements.txt):
APScheduler==3.11.0         # Job scheduling
beautifulsoup4==4.13.4      # HTML parsing
mysql-connector-python==9.4.0  # MySQL connector
SQLAlchemy==2.0.41          # Database ORM
requests==2.32.4            # HTTP client
selenium==4.x               # JavaScript parsing (manually installed)
```

## 🔐 SSL/SECURITY CONFIGURATION

### Automatic SSL Management
```bash
# Environment configuration in .env:
SSL_DOMAIN=radiograb.svaha.com
SSL_EMAIL=admin@svaha.com

# Persistent SSL storage in Docker volumes:
letsencrypt:/etc/letsencrypt        # Certificates and config
letsencrypt_lib:/var/lib/letsencrypt # Working files

# SSL Management Scripts:
./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
./check-domain.sh radiograb.svaha.com
./backup-ssl.sh
```

### Security Features
- Let's Encrypt certificates with automatic renewal (twice daily)
- CSRF protection on all forms with session management
- HTTP to HTTPS redirect
- Security headers (HSTS, CSP, X-Frame-Options)
- A+ SSL Labs rating

## 📋 COMMON OPERATIONS

### Container Management
```bash
# Container status
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose ps"

# View logs
ssh radiograb@167.71.84.143 "docker logs radiograb-web-1 --tail 50"

# Restart specific service
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose restart radiograb-recorder-1"

# Execute commands in container
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 command"
```

### Testing & Verification
```bash
# Website accessibility
curl -I https://radiograb.svaha.com/

# CSRF token API
curl -s https://radiograb.svaha.com/api/get-csrf-token.php | jq .

# Test recording workflow
TOKEN=$(curl -s -c /tmp/cookies.txt "https://radiograb.svaha.com/api/get-csrf-token.php" | jq -r '.csrf_token')
curl -s -b /tmp/cookies.txt -X POST "https://radiograb.svaha.com/api/test-recording.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=test_recording&station_id=1&csrf_token=$TOKEN"

# CSRF debugging tools
# Browser debug page: https://radiograb.svaha.com/csrf-debug.php
# API debug endpoint: https://radiograb.svaha.com/api/debug-csrf.php
```

### Version Management
```bash
# VERSION file: /opt/radiograb/VERSION
# Format: YYYY-MM-DD HH:MM:SS - Description
# Displayed in website footer for tracking deployments

# Current version:
2025-07-24 02:30:00 - SECURITY & SSL FIXES - Fixed CSRF token validation, implemented production SSL certificates with Let's Encrypt, resolved recording file access issues, and established proper Docker container deployment workflow with comprehensive debugging.
```

## 🗂️ DOCUMENTATION REFERENCE

### Complete Documentation Files
- **CLAUDE.md** (this file) - Complete system reference (auto-read by Claude Code)
- **README.md** - Project overview and quick start
- **SYSTEM_ARCHITECTURE.md** - Detailed technical architecture
- **DEPLOYMENT.md** - Installation and deployment guide
- **CLAUDE_SERVER_CONFIG.md** - Server configuration details
- **CLAUDE_PROJECT_SUMMARY.md** - Project goals and features
- **CONTAINER_SETUP.md** - Docker container configuration
- **SSL_PRESERVATION_GUIDE.md** - SSL certificate management
- **CONFIGURATION_PRESERVATION_CHECKLIST.md** - Deployment safety checklist
- **TROUBLESHOOTING.md** - Common issues and solutions
- **STREAM_TESTING_INTEGRATION.md** - Automatic stream testing system
- **RECORDING_TOOLS_GUIDE.md** - Multi-tool recording compatibility
- **PROJECT_OVERVIEW.md** - High-level project overview
- **CHANGELOG.md** - Version history and changes

### Key Technical Insights from Documentation
1. **JavaScript-Aware Schedule Parsing**: Selenium WebDriver handles dynamic calendars
2. **Multi-Tool Recording Strategy**: Automatic tool selection for 100% stream compatibility
3. **Test & On-Demand System**: 30-second tests + 1-hour manual recordings
4. **Housekeeping Service**: Automatic cleanup prevents empty file accumulation
5. **Master RSS Feed**: Combined feed of all shows in chronological order
6. **Enhanced Station Discovery**: Deep stream URL detection from JavaScript players
7. **Database-Cached Parsing Methods**: Stores successful parsing strategies per station
8. **Container-Based SSL**: Persistent certificates with automatic renewal
9. **Call Sign File Naming**: Human-readable 4-letter call signs replace numeric station IDs
10. **Timezone Synchronization**: All containers use America/New_York for consistent timestamps
11. **Enhanced Download Security**: Proper MP3 headers with filename validation and security checks

## 🚨 CRITICAL SUCCESS FACTORS

### Deployment Requirements
- ✅ **Use `./deploy-from-git.sh` script for all deployments (public repo = no auth required)**
- ✅ Commit and push to GitHub before deploying: `git add . && git commit -m "..." && git push origin main`
- ✅ Deploy with: `ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"`
- ✅ **Script automatically pulls from GitHub and rebuilds containers**
- ✅ Verify deployment worked by testing the live site
- ✅ Use `/opt/radiograb/venv/bin/python` for all Python execution
- ✅ Set `PYTHONPATH=/opt/radiograb` for proper module imports
- ✅ Update VERSION file with each deployment
- ✅ Verify SSL certificates are in persistent Docker volumes

### System Monitoring
- ✅ Check container health: `docker compose ps`
- ✅ Monitor SSL certificate expiration
- ✅ Verify CSRF token API functionality
- ✅ Test recording system periodically
- ✅ Check disk space in `/var/radiograb/recordings/`

### Emergency Recovery
```bash
# If containers fail to start:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d"

# If SSL certificates are lost:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com"

# If git repository is corrupted:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && git status && git stash && git pull origin main"
```

## 🆕 RECENT UPDATES (July 2025)

### ✅ Call Sign Implementation (Completed)
- **Filename Format Change**: Recording files now use 4-letter call signs instead of numeric station IDs
  - New format: `WEHC_test_2025-07-25-070014.mp3` (instead of `1_test_2025-07-25-070014.mp3`)
  - Backward compatibility maintained for existing files
  - All stations configured with proper call signs: WEHC, WERU, WTBR, WYSO

### ✅ Timezone Fixes (Completed) 
- **Container Timezone**: All Docker containers now use `America/New_York` (EDT)
- **Timestamp Accuracy**: Recording timestamps now match local time instead of being 4 hours ahead
- **Configuration**: Added `TZ=America/New_York` environment variable to all services

### ✅ Download Security Enhancements (Completed)
- **Proper MP3 Downloads**: Test recordings now download as MP3 files instead of HTML
- **Security Validation**: Added filename format validation to prevent directory traversal
- **Headers Fixed**: Proper `audio/mpeg` content-type and download headers
- **API Enhancement**: Added dedicated download action to test-recordings.php API

### ✅ Database Environment Variables (Completed)
- **Critical Fix**: Changed from `$_ENV` to `$_SERVER` for PHP-FPM environment variable access
- **Container Configuration**: Enabled `clear_env = no` in PHP-FPM for proper variable passing
- **Connection Stability**: All MySQL connections now use environment variables correctly

### 🔄 Known Outstanding Issues
- **Frontend Auto-refresh**: Test recordings may not immediately appear in webpage interface (API works correctly)
- **JavaScript Integration**: Frontend-backend integration needs refinement for real-time updates

---

**🔄 Remember: Docker containers = isolated filesystem. Host file copies ≠ live site updates!**
**✅ PUBLIC REPOSITORY: `./deploy-from-git.sh` pulls from GitHub automatically - no manual file copying needed!**
**📋 DEPLOYMENT CHECKLIST: 1) git push 2) deploy script 3) verify site works**
**🐍 Always: `/opt/radiograb/venv/bin/python` for Python execution**
**🔒 SSL: Persistent volumes ensure certificates survive container rebuilds**
**🚨 Database: All MySQL connections must use environment variables (DB_HOST=mysql, DB_PORT=3306)**
**📞 Call Signs: All new recordings use 4-letter call signs (WEHC, WERU, WTBR, WYSO) for easy identification**