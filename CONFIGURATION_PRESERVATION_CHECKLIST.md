# Configuration Preservation Checklist

## Critical: Never Lose Configuration Again

### What This Checklist Prevents
- ❌ SSL certificates lost during container rebuilds
- ❌ Manual configurations that don't survive deployments  
- ❌ Missing backup and recovery procedures
- ❌ Inconsistent deployment processes

## Pre-Deployment Checklist

### 1. SSL Certificate Verification ✅
- [ ] SSL certificates are in persistent Docker volumes
  ```bash
  ssh radiograb@167.71.84.143 "docker volume ls | grep letsencrypt"
  ```
- [ ] Environment variables are set in `.env` file
  ```bash
  cat .env | grep SSL_
  ```
- [ ] Backup script is available and tested
  ```bash
  ./backup-ssl.sh
  ```

### 2. Essential Files on Server ✅
- [ ] All SSL management scripts are deployed
  ```bash
  ssh radiograb@167.71.84.143 "ls -la /opt/radiograb/setup*ssl* check-domain.sh backup-ssl.sh"
  ```
- [ ] Docker compose configuration includes persistent volumes
  ```bash
  grep -A5 -B5 letsencrypt docker-compose.yml
  ```
- [ ] Startup script includes SSL auto-generation
  ```bash
  grep -A10 -B10 "SSL certificate" docker/start.sh
  ```

### 3. Configuration Backup ✅
- [ ] Current SSL certificates backed up
- [ ] Docker volumes backed up
- [ ] Configuration files committed to git
- [ ] Documentation is up to date

## During Deployment Safety Checks

### 1. Before Container Rebuild
```bash
# Check current SSL status
curl -I https://radiograb.svaha.com/

# Backup certificates
./backup-ssl.sh

# Verify volumes exist
ssh radiograb@167.71.84.143 "docker volume ls | grep radiograb"
```

### 2. During Rebuild Process
```bash
# Use correct rebuild command that preserves volumes
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"

# NOT: docker compose down -v (this would delete volumes!)
```

### 3. After Rebuild Verification
```bash
# Check containers are healthy
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose ps"

# Verify HTTPS is working
curl -I https://radiograb.svaha.com/

# Check SSL certificate is valid
openssl s_client -connect radiograb.svaha.com:443 -servername radiograb.svaha.com

# Verify automatic renewal is set up
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 crontab -l | grep renew"
```

## Post-Deployment Validation

### 1. Functional Tests ✅
- [ ] Site loads properly at https://radiograb.svaha.com
- [ ] CSS/JS assets are loading (no 404s)
- [ ] CSRF token functionality works
- [ ] Test recording feature works
- [ ] SSL certificate is valid and not expired

### 2. Security Validation ✅
- [ ] HTTPS redirect is working (HTTP → HTTPS)
- [ ] Security headers are present
- [ ] SSL Labs rating is A+ 
- [ ] Certificate auto-renewal is configured

### 3. Backup Validation ✅
- [ ] SSL backup script works
- [ ] Configuration is committed to git
- [ ] Documentation reflects current setup

## Emergency Recovery Procedures

### If SSL is Broken After Deployment

1. **Quick Diagnosis**:
   ```bash
   # Check if certificates exist
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 ls -la /etc/letsencrypt/live/"
   
   # Check nginx configuration
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -t"
   
   # Check container logs
   ssh radiograb@167.71.84.143 "docker logs radiograb-web-1 --tail 50"
   ```

2. **Quick Fix Options**:
   
   **Option A: Automatic Regeneration** (preferred)
   ```bash
   # Restart container to trigger SSL auto-generation
   ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose restart web"
   ```
   
   **Option B: Manual SSL Setup**
   ```bash
   ssh radiograb@167.71.84.143 "cd /opt/radiograb && ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com"
   ```
   
   **Option C: Restore from Backup**
   ```bash
   # Find latest backup
   ssh radiograb@167.71.84.143 "ls -la /opt/radiograb/backups/ssl/"
   
   # Restore certificates
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 tar xzf /opt/radiograb/backups/ssl/ssl_export_YYYYMMDD_HHMMSS.tar.gz -C /"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
   ```

### If Site is Completely Down

1. **Temporary HTTP-only mode**:
   ```bash
   # Disable HTTPS temporarily to restore service
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/listen 443 ssl/#listen 443 ssl/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/return 301 https/#return 301 https/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
   ```

2. **Fix SSL and re-enable HTTPS**:
   ```bash
   # Regenerate certificates
   ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
   
   # Re-enable HTTPS
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/#listen 443 ssl/listen 443 ssl/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/#return 301 https/return 301 https/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
   ```

## Configuration Files to Always Preserve

### 1. Docker Configuration ✅
- `docker-compose.yml` (with persistent volumes)
- `Dockerfile` (with certbot packages)
- `.env` (with SSL_DOMAIN and SSL_EMAIL)

### 2. SSL Management Scripts ✅
- `setup-container-ssl.sh` (primary SSL setup)
- `setup-ssl.sh` (alternative setup)
- `check-domain.sh` (domain verification)
- `backup-ssl.sh` (certificate backup)

### 3. Container Configuration ✅
- `docker/start.sh` (with SSL auto-generation)
- `docker/nginx.conf` (if customized)
- `docker/supervisord.conf` (service management)

### 4. Documentation ✅
- `SSL_PRESERVATION_GUIDE.md` (this guide)
- `SYSTEM_ARCHITECTURE.md` (system overview)
- `CLAUDE_SERVER_CONFIG.md` (server access info)

## Regular Maintenance Schedule

### Weekly (Automated)
- [ ] SSL certificate expiration check
- [ ] SSL certificate backup
- [ ] Site functionality test

### Monthly (Manual)
- [ ] Review SSL configuration
- [ ] Test recovery procedures  
- [ ] Update documentation if needed
- [ ] Review backup retention

### Before Major Changes
- [ ] Run this entire checklist
- [ ] Create full configuration backup
- [ ] Test rollback procedures

## Success Indicators

✅ **Configuration is Properly Preserved When**:
- SSL certificates survive container rebuilds
- All management scripts are available on server
- Automatic renewal is working
- Backups are being created regularly
- Recovery procedures are tested and documented
- Site maintains 99.9% uptime during deployments

This checklist ensures that configuration loss like we experienced with SSL certificates never happens again.