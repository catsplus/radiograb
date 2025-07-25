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

### 🚨 CRITICAL LIMITATION: Server Cannot Pull from GitHub  
**THE PRODUCTION SERVER CANNOT EXECUTE `git pull` DUE TO AUTHENTICATION RESTRICTIONS.**

This is the **#1 source of deployment failures** - understanding this is crucial:

#### The Problem:
- Error: `fatal: could not read Username for 'https://github.com': No such device or address`
- The `./deploy-from-git.sh` script does NOT actually pull from GitHub - it only uses local files on server
- Server git repository is often behind the latest commits by hours/days
- **CRITICAL**: When deploy script shows "Using local git repository (GitHub pull requires authentication setup)" it means your latest changes are NOT deployed

#### Why This Causes Failures:
- You push changes to GitHub ✅
- You run `./deploy-from-git.sh` ❌ (uses old local files)
- Container rebuilds with old code ❌
- Your changes don't work because they weren't actually deployed ❌
- Functions/files you added locally don't exist on server ❌

### ✅ PREFERRED: Git-Based Deployment
```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Deploy using the automated script
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"
```

### ✅ CORRECT DEPLOYMENT WORKFLOW (Required 99% of the time)

**THIS IS THE WORKFLOW YOU MUST FOLLOW FOR RELIABLE DEPLOYMENTS:**

```bash
# 1. Local changes and push to GitHub
git add . && git commit -m "Update files" && git push origin main

# 2. Try deploy script first (but expect it to fail to pull)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"

# 3. WATCH THE OUTPUT - if you see "Using local git repository" you MUST manually copy files:

# 3a. Copy ALL changed files to server (replace with your actual files):
scp frontend/public/api/test-recording.php radiograb@167.71.84.143:/opt/radiograb/frontend/public/api/
scp frontend/includes/functions.php radiograb@167.71.84.143:/opt/radiograb/frontend/includes/
scp VERSION radiograb@167.71.84.143:/opt/radiograb/

# 3b. Or copy entire directories:
scp -r frontend/public/ radiograb@167.71.84.143:/opt/radiograb/frontend/public/

# 4. Rebuild containers (ABSOLUTELY REQUIRED!)
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"

# 5. Test that your changes actually work
```

### 🚨 CRITICAL DEPLOYMENT CHECKLIST

**BEFORE assuming your deployment worked:**
- [ ] Did you see "Using local git repository" in deploy output? If YES → manually copy files
- [ ] Did you run `docker compose down && docker compose up -d --build`? If NO → your changes aren't live
- [ ] Did you test your changes work on the live site? If NO → they might not be deployed
- [ ] Are you getting "function not found" errors? → You didn't copy the file with the function

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

## 🚨 CRITICAL SUCCESS FACTORS

### Deployment Requirements
- ✅ **TRY `./deploy-from-git.sh` script first, but often requires manual file copying**
- ✅ Commit and push to GitHub before deploying: `git add . && git commit -m "..." && git push origin main`
- ✅ Deploy with: `ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./deploy-from-git.sh"`
- ⚠️ **If deploy shows "Using local git repository" - manually copy changed files with `scp`**
- ✅ Always rebuild containers after file changes: `docker compose down && docker compose up -d --build`
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

---

**🔄 Remember: Docker containers = isolated filesystem. Host file copies ≠ live site updates!**
**🚨 DEPLOYMENT REALITY: `./deploy-from-git.sh` will show "Using local git repository" - you MUST manually copy files!**
**📋 DEPLOYMENT CHECKLIST: 1) git push 2) deploy script 3) manually copy files 4) docker rebuild 5) test**
**🐍 Always: `/opt/radiograb/venv/bin/python` for Python execution**
**🔒 SSL: Persistent volumes ensure certificates survive container rebuilds**
**⚠️ "Function not found" errors = you forgot to copy the file containing the function!**