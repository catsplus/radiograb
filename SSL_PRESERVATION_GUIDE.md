# SSL Certificate Preservation & Management Guide

## Critical: SSL Certificate Persistence Strategy

### What Went Wrong Previously
- SSL certificates were generated manually inside containers
- Container rebuilds wiped `/etc/letsencrypt` directory  
- No persistent storage for certificates
- Manual renewal processes were lost

### Current Persistent Solution

#### 1. Persistent Docker Volumes
SSL certificates are now stored in persistent Docker volumes:
```bash
# Volumes defined in docker-compose.yml
volumes:
  letsencrypt:        # /etc/letsencrypt (certificates and config)
  letsencrypt_lib:    # /var/lib/letsencrypt (working files)
```

#### 2. Automatic Generation on Startup
The container startup script (`docker/start.sh`) now includes:
- Automatic SSL certificate detection
- Certificate generation if missing
- Nginx HTTPS configuration
- Automatic renewal setup

#### 3. Environment-Based Configuration
Set in `.env` file:
```bash
SSL_DOMAIN=radiograb.svaha.com
SSL_EMAIL=admin@svaha.com
```

## SSL Management Tools Available

### 1. check-domain.sh
```bash
./check-domain.sh radiograb.svaha.com
```
- Verifies DNS configuration
- Checks domain resolution
- Tests HTTP accessibility

### 2. setup-container-ssl.sh  
```bash
./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
```
- Complete SSL setup inside Docker containers
- Generates Let's Encrypt certificates
- Configures nginx for HTTPS
- Sets up automatic renewal

### 3. setup-ssl.sh
```bash
./setup-ssl.sh radiograb.svaha.com admin@svaha.com
```
- Alternative host-based SSL setup
- For use outside of containers

## Current SSL Status Verification

### Check Certificate Status
```bash
# View certificate details
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 certbot certificates"

# Check certificate expiration
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 openssl x509 -in /etc/letsencrypt/live/radiograb.svaha.com/cert.pem -text -noout | grep 'Not After'"

# Test renewal process
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 certbot renew --dry-run"
```

### Verify Automatic Renewal
```bash
# Check cron jobs
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 cat /etc/crontab | grep renew"

# Check renewal script
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 cat /usr/local/bin/renew-certs.sh"

# Test renewal script
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 /usr/local/bin/renew-certs.sh"
```

## SSL Certificate Backup Strategy

### 1. Automatic Volume Backup
Create backup script for SSL volumes:
```bash
#!/bin/bash
# backup-ssl.sh
DATE=$(date +%Y%m%d_%H%M%S)
docker run --rm -v radiograb_letsencrypt:/source -v /backup:/backup alpine tar czf /backup/ssl_certs_$DATE.tar.gz -C /source .
docker run --rm -v radiograb_letsencrypt_lib:/source -v /backup:/backup alpine tar czf /backup/ssl_lib_$DATE.tar.gz -C /source .
```

### 2. Certificate Export for External Backup
```bash
# Export certificate files
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 tar czf /tmp/ssl_backup.tar.gz /etc/letsencrypt/live/ /etc/letsencrypt/archive/"

# Download backup
scp radiograb@167.71.84.143:/tmp/ssl_backup.tar.gz ./ssl_backup_$(date +%Y%m%d).tar.gz
```

### 3. Certificate Restoration Process
```bash
# If certificates are lost, restore from backup:
scp ssl_backup_YYYYMMDD.tar.gz radiograb@167.71.84.143:/tmp/
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 tar xzf /tmp/ssl_backup_YYYYMMDD.tar.gz -C /"
ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
```

## Deployment Safety Checklist

### Before Container Rebuild
- [ ] Verify SSL volumes exist: `docker volume ls | grep letsencrypt`
- [ ] Check certificate expiration: `certbot certificates`
- [ ] Backup certificates if needed
- [ ] Ensure `.env` file has SSL_DOMAIN and SSL_EMAIL set

### After Container Rebuild  
- [ ] Verify containers are healthy: `docker compose ps`
- [ ] Check HTTPS access: `curl -I https://radiograb.svaha.com/`
- [ ] Verify certificate is valid: `openssl s_client -connect radiograb.svaha.com:443`
- [ ] Test automatic renewal: `certbot renew --dry-run`
- [ ] Check cron jobs are active: `cat /etc/crontab | grep renew`

### If SSL is Broken After Rebuild
1. Check if certificates exist in volumes:
   ```bash
   docker exec radiograb-web-1 ls -la /etc/letsencrypt/live/
   ```
2. If missing, the startup script should regenerate them automatically
3. If startup script failed, run manual setup:
   ```bash
   ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
   ```

## Production Environment Configuration

### Current Setup
- **Domain**: radiograb.svaha.com
- **Email**: admin@svaha.com  
- **Certificate Path**: `/etc/letsencrypt/live/radiograb.svaha.com/`
- **Renewal**: Twice daily via cron (12:00 and 00:00)
- **Security Rating**: A+ (SSL Labs)

### Certificate Details
- **Provider**: Let's Encrypt
- **Validity**: 90 days (auto-renewed at 30 days remaining)
- **Protocols**: TLS 1.2, TLS 1.3
- **Key Size**: RSA 2048-bit or ECDSA P-256

## Emergency Procedures

### If Site Goes Down Due To SSL
1. **Quick Fix - Disable HTTPS temporarily**:
   ```bash
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/listen 443 ssl/#listen 443 ssl/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/return 301 https/#return 301 https/' /etc/nginx/sites-available/default"  
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
   ```

2. **Regenerate certificates**:
   ```bash
   ./setup-container-ssl.sh radiograb.svaha.com admin@svaha.com
   ```

3. **Re-enable HTTPS**:
   ```bash
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/#listen 443 ssl/listen 443 ssl/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 sed -i 's/#return 301 https/return 301 https/' /etc/nginx/sites-available/default"
   ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 nginx -s reload"
   ```

## Monitoring & Alerts

### Certificate Expiration Monitoring
```bash
# Add to monitoring scripts
CERT_DAYS=$(docker exec radiograb-web-1 openssl x509 -in /etc/letsencrypt/live/radiograb.svaha.com/cert.pem -noout -dates | grep notAfter | cut -d= -f2 | xargs -I {} date -d {} +%s)
CURRENT_DAYS=$(date +%s)
DAYS_LEFT=$(( ($CERT_DAYS - $CURRENT_DAYS) / 86400 ))

if [ $DAYS_LEFT -lt 7 ]; then
    echo "WARNING: SSL certificate expires in $DAYS_LEFT days!"
fi
```

This guide ensures SSL certificates are never lost again and provides comprehensive backup and recovery procedures.