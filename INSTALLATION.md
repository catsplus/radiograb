# RadioGrab Quick Installation Guide

This guide provides step-by-step instructions for installing RadioGrab on common platforms.

## Quick Start (Ubuntu/Debian)

### 1. Prerequisites
```bash
sudo apt update
sudo apt install -y python3 python3-pip python3-venv mysql-server nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-xml streamripper git curl wget
```

### 2. Download and Setup
```bash
# Create application directory
sudo mkdir -p /opt/radiograb
sudo chown $(whoami):$(whoami) /opt/radiograb

# Clone repository
cd /opt/radiograb
git clone https://github.com/yourusername/radiograb.git .

# Create Python virtual environment
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Create directories
sudo mkdir -p /var/radiograb/{recordings,feeds,logs,temp}
sudo chown -R www-data:www-data /var/radiograb /opt/radiograb
```

### 3. Database Setup
```bash
# Start MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Create database (replace 'your_password' with a secure password)
sudo mysql << EOF
CREATE DATABASE radiograb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radiograb'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON radiograb.* TO 'radiograb'@'localhost';
FLUSH PRIVILEGES;
EXIT;
EOF

# Import database schema
mysql -u radiograb -p radiograb < database/schema.sql
```

### 4. Configuration
```bash
# Create environment file
cat > .env << EOF
DB_HOST=localhost
DB_PORT=3306
DB_NAME=radiograb
DB_USER=radiograb
DB_PASSWORD=your_password
RECORDINGS_DIR=/var/radiograb/recordings
FEEDS_DIR=/var/radiograb/feeds
LOGS_DIR=/var/radiograb/logs
TEMP_DIR=/var/radiograb/temp
RADIOGRAB_BASE_URL=http://localhost
STREAMRIPPER_PATH=/usr/bin/streamripper
EOF

# Secure the environment file
sudo chmod 600 .env
```

### 5. Web Server Setup
```bash
# Create Nginx configuration
sudo tee /etc/nginx/sites-available/radiograb << 'EOF'
server {
    listen 80;
    server_name localhost;
    
    root /opt/radiograb/frontend/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
    }

    location /feeds/ {
        alias /var/radiograb/feeds/;
        add_header Content-Type application/rss+xml;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

# Enable site
sudo ln -s /etc/nginx/sites-available/radiograb /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### 6. Services Setup
```bash
# Create recording service
sudo tee /etc/systemd/system/radiograb-recorder.service << 'EOF'
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
EOF

# Enable and start services
sudo systemctl daemon-reload
sudo systemctl enable radiograb-recorder
sudo systemctl start radiograb-recorder
```

### 7. Verify Installation
```bash
# Check services
sudo systemctl status nginx
sudo systemctl status php8.1-fpm
sudo systemctl status mysql
sudo systemctl status radiograb-recorder

# Test web interface
curl -I http://localhost
```

### 8. First Use
1. Open web browser to `http://localhost`
2. Click "Add Station"
3. Enter a radio station website URL
4. Click "Discover" to auto-detect streaming info
5. Save the station
6. Go to "Stations" and click "Import" to import show schedules
7. Go to "Shows" and toggle shows to "Active"

## Docker Installation

### Using Docker Compose
```bash
# Clone repository
git clone https://github.com/yourusername/radiograb.git
cd radiograb

# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: radiograb
      MYSQL_USER: radiograb
      MYSQL_PASSWORD: radiograb
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    ports:
      - "3306:3306"

  web:
    build: .
    ports:
      - "80:80"
    volumes:
      - recordings:/var/radiograb/recordings
      - feeds:/var/radiograb/feeds
      - logs:/var/radiograb/logs
    environment:
      - DB_HOST=mysql
      - DB_USER=radiograb
      - DB_PASSWORD=radiograb
      - DB_NAME=radiograb
    depends_on:
      - mysql

volumes:
  mysql_data:
  recordings:
  feeds:
  logs:
EOF

# Create Dockerfile
cat > Dockerfile << 'EOF'
FROM ubuntu:22.04

# Install dependencies
RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-venv \
    nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-xml \
    streamripper mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Copy application
COPY . /opt/radiograb
WORKDIR /opt/radiograb

# Setup Python environment
RUN python3 -m venv venv && \
    . venv/bin/activate && \
    pip install -r requirements.txt

# Setup directories
RUN mkdir -p /var/radiograb/{recordings,feeds,logs,temp} && \
    chown -R www-data:www-data /var/radiograb /opt/radiograb

# Copy configurations
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
EOF

# Start containers
docker-compose up -d
```

## Raspberry Pi Installation

### Optimizations for Pi
```bash
# Install on Raspberry Pi OS
sudo apt update
sudo apt install -y python3 python3-pip python3-venv mysql-server nginx php8.1-fpm php8.1-mysql streamripper

# Use lighter Python packages
pip install --no-cache-dir -r requirements.txt

# Optimize MySQL for low memory
sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf << 'EOF'
[mysqld]
innodb_buffer_pool_size = 64M
innodb_log_file_size = 16M
innodb_log_buffer_size = 8M
query_cache_size = 8M
query_cache_limit = 1M
EOF

sudo systemctl restart mysql
```

## VPS Installation (DigitalOcean/Linode)

### Domain Setup
```bash
# Replace localhost with your domain
sudo sed -i 's/localhost/yourdomain.com/g' /etc/nginx/sites-available/radiograb
sudo nginx -t
sudo systemctl reload nginx

# Install SSL certificate
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

### Firewall Setup
```bash
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## Troubleshooting

### Common Issues

**Error: "streamripper: command not found"**
```bash
# Install streamripper
sudo apt install streamripper
# or compile from source
wget http://streamripper.sourceforge.net/files/streamripper-1.64.6.tar.gz
tar -xzf streamripper-1.64.6.tar.gz
cd streamripper-1.64.6
./configure && make && sudo make install
```

**Error: "Access denied for user 'radiograb'@'localhost'"**
```bash
# Reset database password
sudo mysql -u root << EOF
ALTER USER 'radiograb'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
EOF
# Update .env file with new password
```

**Error: "502 Bad Gateway"**
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm
sudo systemctl restart php8.1-fpm
# Check Nginx error logs
sudo tail -f /var/log/nginx/error.log
```

**Web interface shows blank page**
```bash
# Check permissions
sudo chown -R www-data:www-data /opt/radiograb/frontend
sudo chmod -R 755 /opt/radiograb/frontend
# Check PHP error logs
sudo tail -f /var/log/php8.1-fpm.log
```

### Log Locations
- **Application**: `/var/radiograb/logs/`
- **Nginx**: `/var/log/nginx/`
- **PHP**: `/var/log/php8.1-fpm.log`
- **MySQL**: `/var/log/mysql/`
- **System**: `journalctl -u radiograb-recorder`

### Support
If you encounter issues:
1. Check the logs for error messages
2. Verify all services are running
3. Ensure file permissions are correct
4. Test network connectivity to radio stations
5. Create an issue on GitHub with logs and system info

---

**Next Steps**: See [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment and [README.md](README.md) for usage instructions.