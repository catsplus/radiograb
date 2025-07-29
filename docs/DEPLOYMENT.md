# RadioGrab Deployment Guide

**⚠️ CRITICAL: PRODUCTION DEPLOYMENT ARCHITECTURE ⚠️**

RadioGrab is a "Radio Recording System" application that runs in Docker containers on production server 167.71.84.143.

## Production Server Details
- **Server**: 167.71.84.143 (user: `radiograb`)
- **Application Directory**: `/opt/radiograb/` (host filesystem - used for building)
- **Live Site**: **Runs from Docker containers - NOT host files!**
- **Site URL**: https://radiograb.svaha.com

## Docker Container Architecture
**CRITICAL**: Files are BAKED INTO Docker images at build time via `COPY . /opt/radiograb/` in Dockerfile.

```bash
# ❌ WRONG - This does NOT update the live site:
scp file.php radiograb@167.71.84.143:/opt/radiograb/frontend/public/

# ✅ CORRECT - This updates the live site:
ssh radiograb@167.71.84.143 "cd /opt/radiograb && docker compose down && docker compose up -d --build"
```

## System Requirements

### Minimum Hardware
- **RAM**: 2GB (4GB recommended)
- **Storage**: 10GB free space (more for recordings)
- **CPU**: 1 core (2+ cores recommended)
- **Network**: Stable internet connection

### Supported Platforms
- **Ubuntu/Debian**: 20.04+ / 11+
- **CentOS/RHEL**: 8+
- **Raspberry Pi**: 4B with Raspberry Pi OS
- **VPS Providers**: DigitalOcean, Linode, AWS, etc.

## Prerequisites

### System Packages
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y python3 python3-pip python3-venv mysql-server nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-xml streamripper wget curl git

# CentOS/RHEL
sudo dnf install -y python3 python3-pip mysql-server nginx php-fpm php-mysql php-curl php-xml streamripper wget curl git
```

### Python Dependencies
```bash
# Create virtual environment
python3 -m venv /opt/radiograb/venv
source /opt/radiograb/venv/bin/activate
pip install -r requirements.txt

# Additional dependencies for logo and social media features (2025-07-29)
pip install Pillow  # Image processing for logo optimization
# selenium is automatically installed via pip if needed for JavaScript-aware parsing
```

## Installation

### 1. Clone Repository
```bash
sudo mkdir -p /opt/radiograb
sudo chown $(whoami):$(whoami) /opt/radiograb
cd /opt/radiograb
git clone https://github.com/yourusername/radiograb.git .
```

### 2. Set Permissions
```bash
sudo chown -R www-data:www-data /opt/radiograb/frontend
sudo chmod -R 755 /opt/radiograb/frontend

# Create directories
sudo mkdir -p /var/radiograb/{recordings,feeds,logs,temp,logos}
sudo chown -R www-data:www-data /var/radiograb
```

### 3. Database Setup
```bash
# Start MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Secure installation
sudo mysql_secure_installation

# Create database
sudo mysql -u root -p << EOF
CREATE DATABASE radiograb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radiograb'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON radiograb.* TO 'radiograb'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import schema
mysql -u radiograb -p radiograb < database/schema.sql

# Apply logo and social media migrations (2025-07-29)
mysql -u radiograb -p radiograb < database/migrations/add_logo_storage_fields.sql
mysql -u radiograb -p radiograb < database/migrations/add_show_playlist_support.sql
```

### 4. Configuration

#### Environment Variables
Create `/opt/radiograb/.env`:
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=radiograb
DB_USER=radiograb
DB_PASSWORD=your_secure_password

# Directories
RECORDINGS_DIR=/var/radiograb/recordings
FEEDS_DIR=/var/radiograb/feeds
LOGS_DIR=/var/radiograb/logs
TEMP_DIR=/var/radiograb/temp

# Application
RADIOGRAB_BASE_URL=https://yourdomain.com
SECRET_KEY=your_32_character_secret_key_here

# Streamripper
STREAMRIPPER_PATH=/usr/bin/streamripper
```

#### PHP Configuration
Create `/opt/radiograb/frontend/config.php`:
```php
<?php
// Load environment variables
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'radiograb');
define('DB_USER', $_ENV['DB_USER'] ?? 'radiograb');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
```

### 5. Web Server Setup

#### Nginx Configuration
Create `/etc/nginx/sites-available/radiograb`:
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
    root /opt/radiograb/frontend/public;
    index index.php index.html;

    # Logging
    access_log /var/log/nginx/radiograb-access.log;
    error_log /var/log/nginx/radiograb-error.log;

    # Main application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Recordings access
    location /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
        add_header Cache-Control "public, max-age=3600";
    }

    # RSS feeds
    location /feeds/ {
        alias /var/radiograb/feeds/;
        add_header Content-Type application/rss+xml;
        add_header Cache-Control "public, max-age=300";
    }

    # Station logos (2025-07-29)
    location /logos/ {
        alias /var/radiograb/logos/;
        add_header Cache-Control "public, max-age=604800";
        add_header Access-Control-Allow-Origin "*";
        expires 7d;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(env|ini|conf|log)$ {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/radiograb /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### SSL Certificate (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### 6. Systemd Services

#### Recording Service
Create `/etc/systemd/system/radiograb-recorder.service`:
```ini
[Unit]
Description=RadioGrab Recording Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/opt/radiograb
Environment=PATH=/opt/radiograb/venv/bin
ExecStart=/opt/radiograb/venv/bin/python backend/services/recording_service.py --daemon
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### RSS Update Service
Create `/etc/systemd/system/radiograb-rss.service`:
```ini
[Unit]
Description=RadioGrab RSS Update Service
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/opt/radiograb
Environment=PATH=/opt/radiograb/venv/bin
ExecStart=/opt/radiograb/venv/bin/python backend/services/rss_manager.py --update-all
```

#### RSS Update Timer
Create `/etc/systemd/system/radiograb-rss.timer`:
```ini
[Unit]
Description=RadioGrab RSS Update Timer
Requires=radiograb-rss.service

[Timer]
OnCalendar=*:0/15
Persistent=true

[Install]
WantedBy=timers.target
```

Enable services:
```bash
sudo systemctl daemon-reload
sudo systemctl enable radiograb-recorder
sudo systemctl enable radiograb-rss.timer
sudo systemctl start radiograb-recorder
sudo systemctl start radiograb-rss.timer
```

### 7. Logo and Social Media Services (New 2025-07-29)

#### Initial Logo Population
After deployment, populate station logos and social media links:

```bash
# Method 1: Via web interface
# Visit https://yourdomain.com/admin (if admin interface exists)
# Click "Update All Station Logos"

# Method 2: Via API call
curl -X POST "https://yourdomain.com/api/station-logo-update.php" \
  -H "Content-Type: application/json" \
  -d '{"action": "update_station_logos", "csrf_token": "your_token"}'

# Method 3: Via command line
cd /opt/radiograb
source venv/bin/activate
python backend/services/logo_storage_service.py --update-all-stations
python backend/services/facebook_logo_extractor.py --extract-all
python backend/services/social_media_detector.py --scan-all-stations
```

#### Verify Logo Storage
```bash
# Check logo directory
ls -la /var/radiograb/logos/

# Test logo serving
curl -I "https://yourdomain.com/logos/station_1_logo.png"
```

## Configuration

### 1. Web Interface Access
Visit `https://yourdomain.com` to access the RadioGrab web interface.

### 2. Add Your First Station
1. Click "Add Station"
2. Enter station website URL
3. Click "Discover" to auto-detect streaming info
4. Save the station

### 3. Import Show Schedules
1. Go to "Stations"
2. Click "Import" on a station
3. Preview the discovered shows
4. Import the shows you want to record

### 4. Activate Recording
1. Go to "Shows"
2. Toggle shows to "Active"
3. Recordings will start according to schedule

## Monitoring

### Service Status
```bash
# Check service status
sudo systemctl status radiograb-recorder
sudo systemctl status radiograb-rss.timer

# View logs
sudo journalctl -u radiograb-recorder -f
sudo journalctl -u radiograb-rss -f

# Check recordings
ls -la /var/radiograb/recordings/
```

### Log Files
```bash
# Application logs
tail -f /var/radiograb/logs/radiograb.log

# Nginx access logs
tail -f /var/log/nginx/radiograb-access.log

# PHP error logs
tail -f /var/log/php8.1-fpm.log
```

### Disk Usage
```bash
# Monitor recording storage
du -sh /var/radiograb/recordings/
df -h /var/radiograb
```

## Maintenance

### Database Backup
```bash
#!/bin/bash
# /opt/radiograb/scripts/backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u radiograb -p radiograb > /var/radiograb/backups/radiograb_${DATE}.sql
gzip /var/radiograb/backups/radiograb_${DATE}.sql

# Keep only last 7 days
find /var/radiograb/backups/ -name "*.sql.gz" -mtime +7 -delete
```

### Log Rotation
Create `/etc/logrotate.d/radiograb`:
```
/var/radiograb/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    copytruncate
    notifempty
}
```

### Cleanup Old Recordings
```bash
#!/bin/bash
# /opt/radiograb/scripts/cleanup.sh
# Delete recordings older than 30 days
find /var/radiograb/recordings/ -name "*.mp3" -mtime +30 -delete

# Update database to mark as deleted
mysql -u radiograb -p radiograb << EOF
UPDATE recordings 
SET filename = NULL 
WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND filename IS NOT NULL;
EOF
```

### Updates
```bash
cd /opt/radiograb
git pull origin main
source venv/bin/activate
pip install -r requirements.txt --upgrade
sudo systemctl restart radiograb-recorder
```

## Troubleshooting

### Common Issues

#### Recording Not Starting
1. Check streamripper installation: `which streamripper`
2. Verify service status: `systemctl status radiograb-recorder`
3. Check logs: `journalctl -u radiograb-recorder -f`
4. Test stream URL manually: `streamripper http://stream-url -l 10`

#### Web Interface Not Loading
1. Check Nginx: `systemctl status nginx`
2. Check PHP-FPM: `systemctl status php8.1-fpm`
3. Verify permissions: `ls -la /opt/radiograb/frontend/`
4. Check error logs: `tail -f /var/log/nginx/radiograb-error.log`

#### Database Connection Errors
1. Check MySQL: `systemctl status mysql`
2. Test connection: `mysql -u radiograb -p radiograb`
3. Verify credentials in `.env` file
4. Check PHP MySQL extension: `php -m | grep mysql`

#### Schedule Import Not Working
1. Check Python dependencies: `pip list | grep beautifulsoup4`
2. Test parser manually: `python3 backend/services/calendar_parser.py https://station-url.com`
3. Verify website accessibility: `curl -I https://station-url.com`

### Performance Optimization

#### For High-Volume Recording
```bash
# Increase file limits
echo "www-data soft nofile 65536" >> /etc/security/limits.conf
echo "www-data hard nofile 65536" >> /etc/security/limits.conf

# Optimize MySQL
echo "innodb_buffer_pool_size = 512M" >> /etc/mysql/mysql.conf.d/mysqld.cnf
echo "max_connections = 200" >> /etc/mysql/mysql.conf.d/mysqld.cnf
```

#### Storage Optimization
```bash
# Use separate partition for recordings
sudo mkdir /mnt/recordings
sudo mount /dev/sdb1 /mnt/recordings
sudo ln -s /mnt/recordings /var/radiograb/recordings
```

## Security

### Firewall Configuration
```bash
# UFW setup
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### File Permissions
```bash
# Secure sensitive files
sudo chmod 600 /opt/radiograb/.env
sudo chown www-data:www-data /opt/radiograb/.env

# Recordings directory
sudo chmod 755 /var/radiograb/recordings
sudo chown www-data:www-data /var/radiograb/recordings
```

### Database Security
- Use strong passwords
- Limit MySQL access to localhost
- Regular security updates
- Monitor access logs

## Backup Strategy

### Complete System Backup
```bash
#!/bin/bash
# /opt/radiograb/scripts/full_backup.sh
DATE=$(date +%Y%m%d)
BACKUP_DIR="/backup/radiograb_${DATE}"

mkdir -p $BACKUP_DIR

# Database
mysqldump -u radiograb -p radiograb | gzip > $BACKUP_DIR/database.sql.gz

# Configuration
cp -r /opt/radiograb/.env $BACKUP_DIR/
cp -r /etc/nginx/sites-available/radiograb $BACKUP_DIR/
cp -r /etc/systemd/system/radiograb-* $BACKUP_DIR/

# Recent recordings (last 7 days)
find /var/radiograb/recordings/ -name "*.mp3" -mtime -7 -exec cp {} $BACKUP_DIR/recordings/ \;

echo "Backup completed: $BACKUP_DIR"
```

## Support

### Resources
- **Documentation**: [GitHub Wiki](https://github.com/yourusername/radiograb/wiki)
- **Issues**: [GitHub Issues](https://github.com/yourusername/radiograb/issues)
- **Community**: [Discord Server](https://discord.gg/radiograb)

### Getting Help
1. Check logs for error messages
2. Search existing GitHub issues
3. Provide system information and logs when reporting issues
4. Include steps to reproduce problems

---

**RadioGrab** - Your personal radio recording system
Version 1.0 | Last updated: 2025