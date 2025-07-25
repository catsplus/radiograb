#!/bin/bash
# RadioGrab Security Cleanup Script
# Removes sensitive data for public repository

echo "🧹 RadioGrab Security Cleanup for Public Repository"
echo "=================================================="

# Create backup
echo "📋 Creating backup..."
cp -r . ../radiograb-backup-$(date +%Y%m%d_%H%M%S)

# Define replacements
declare -A replacements=(
    ["167.71.84.143"]="YOUR_SERVER_IP"
    ["radiograb.svaha.com"]="your-domain.com"
    ["admin@svaha.com"]="admin@your-domain.com"
    ["radiograb@167.71.84.143"]="your-user@YOUR_SERVER_IP"
    ["root@radiograb.svaha.com"]="your-user@your-domain.com"
    ["ssh radiograb@167.71.84.143"]="ssh your-user@YOUR_SERVER_IP"
    ["scp.*radiograb@167.71.84.143"]="scp file.ext your-user@YOUR_SERVER_IP"
    ["radiograb_pass_2024"]="your_db_password"
    ["radiograb_root_2024"]="your_root_password"
)

echo "🔄 Processing files..."

# Files to process (exclude backup directory)
find . -type f \( -name "*.md" -o -name "*.sh" -o -name "*.yml" -o -name "*.yaml" -o -name "*.php" -o -name "*.py" \) \
    ! -path "./radiograb-backup*" \
    ! -path "./.git/*" \
    ! -name "cleanup-for-public.sh" | while read file; do
    
    echo "  Processing: $file"
    
    # Apply all replacements
    for search in "${!replacements[@]}"; do
        replacement="${replacements[$search]}"
        sed -i.bak "s|$search|$replacement|g" "$file"
    done
    
    # Remove backup files
    rm -f "$file.bak"
done

echo "✅ File processing complete"

# Update docker-compose.yml for environment variables
echo "🐳 Updating docker-compose.yml..."
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-your_root_password}
      MYSQL_DATABASE: radiograb
      MYSQL_USER: radiograb
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-your_db_password}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/migrations:/docker-entrypoint-initdb.d/migrations
    restart: unless-stopped
    ports:
      - "3306:3306"
    networks:
      - radiograb_network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 10s
      retries: 5
      interval: 30s

  web:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_USER=radiograb
      - DB_PASSWORD=${DB_PASSWORD:-your_db_password}
      - DB_NAME=radiograb
      - SSL_DOMAIN=${SSL_DOMAIN:-your-domain.com}
      - SSL_EMAIL=${SSL_EMAIL:-admin@your-domain.com}
    volumes:
      - recordings:/var/radiograb/recordings
      - feeds:/var/radiograb/feeds
      - logs:/var/radiograb/logs
      - temp:/var/radiograb/temp
      - letsencrypt:/etc/letsencrypt
      - letsencrypt_lib:/var/lib/letsencrypt
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped
    networks:
      - radiograb_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  recorder:
    build: .
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_USER=radiograb
      - DB_PASSWORD=${DB_PASSWORD:-your_db_password}
      - DB_NAME=radiograb
    volumes:
      - recordings:/var/radiograb/recordings
      - logs:/var/radiograb/logs
      - temp:/var/radiograb/temp
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped
    networks:
      - radiograb_network
    healthcheck:
      test: ["CMD", "python3", "-c", "import sys; sys.exit(0)"]
      interval: 60s
      timeout: 10s
      retries: 3

  rss-updater:
    build: .
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_USER=radiograb
      - DB_PASSWORD=${DB_PASSWORD:-your_db_password}
      - DB_NAME=radiograb
    volumes:
      - recordings:/var/radiograb/recordings
      - feeds:/var/radiograb/feeds
      - logs:/var/radiograb/logs
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped
    networks:
      - radiograb_network

  housekeeping:
    build: .
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_USER=radiograb
      - DB_PASSWORD=${DB_PASSWORD:-your_db_password}
      - DB_NAME=radiograb
    volumes:
      - recordings:/var/radiograb/recordings
      - logs:/var/radiograb/logs
      - temp:/var/radiograb/temp
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped
    networks:
      - radiograb_network

volumes:
  mysql_data:
  recordings:
  feeds:
  logs:
  temp:
  letsencrypt:
  letsencrypt_lib:

networks:
  radiograb_network:
    driver: bridge
EOF

# Create production .env template
echo "📝 Creating .env.template..."
cat > .env.template << 'EOF'
# RadioGrab Production Configuration
# Copy this file to .env and update with your values

# Database Configuration
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_PASSWORD=your_secure_db_password
DB_PASSWORD=your_secure_db_password

# SSL Configuration
SSL_DOMAIN=your-domain.com
SSL_EMAIL=admin@your-domain.com

# Optional: Application Settings
# APP_DEBUG=false
# DEFAULT_RETENTION_DAYS=30
EOF

# Update .env.example to be more generic
echo "📝 Updating .env.example..."
cat > .env.example << 'EOF'
# RadioGrab Configuration Example
# Copy this file to .env and update the values for your environment

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_USER=radiograb
DB_PASSWORD=your_secure_password
DB_NAME=radiograb

# Docker Database Configuration (for docker-compose)
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_PASSWORD=your_secure_password

# SSL Configuration (for production with Let's Encrypt)
SSL_DOMAIN=your-domain.com
SSL_EMAIL=admin@your-domain.com

# Application Settings
APP_DEBUG=false
APP_LOG_LEVEL=info

# File Storage Paths
RECORDINGS_PATH=./recordings
LOGS_PATH=./logs

# Audio Settings
DEFAULT_AUDIO_FORMAT=mp3
DEFAULT_RETENTION_DAYS=30
STREAMRIPPER_PATH=/usr/bin/streamripper

# Web Interface
WEB_BASE_URL=http://localhost/radiograb

# RSS Feed Settings
RSS_TITLE=RadioGrab Recordings
RSS_DESCRIPTION=Personal radio show recordings
RSS_AUTHOR=RadioGrab
RSS_CATEGORY=Technology
EOF

# Update .gitignore
echo "🚫 Updating .gitignore..."
cat > .gitignore << 'EOF'
# Environment files
.env
.env.local
.env.production

# Logs
*.log
logs/
/var/radiograb/logs/

# Runtime data
temp/
/var/radiograb/temp/

# Recordings
recordings/
/var/radiograb/recordings/

# Database
*.db
*.sqlite

# SSL certificates and keys
*.pem
*.key
*.crt
ssl/
certs/

# Backups
backups/
*.backup
*.bak

# Docker
.dockerignore

# IDE
.vscode/
.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Python
__pycache__/
*.pyc
*.pyo
*.pyd
.Python
build/
develop-eggs/
dist/
downloads/
eggs/
.eggs/
lib/
lib64/
parts/
sdist/
var/
wheels/
*.egg-info/
.installed.cfg
*.egg

# Node.js (if any)
node_modules/
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# PHP
vendor/
composer.lock
EOF

# Remove sensitive .env if it exists
if [ -f ".env" ]; then
    echo "🗑️  Removing production .env file..."
    rm .env
fi

echo ""
echo "✅ Security cleanup complete!"
echo ""
echo "📋 Summary of changes:"
echo "  • Replaced server IPs and domains with placeholders"
echo "  • Updated docker-compose.yml to use environment variables"
echo "  • Created .env.template for production setup"
echo "  • Updated .env.example with secure defaults"
echo "  • Enhanced .gitignore to exclude sensitive files"
echo "  • Removed production .env file"
echo ""
echo "🎯 Next steps:"
echo "  1. Review changes: git status"
echo "  2. Test docker-compose with: docker-compose config"
echo "  3. Commit changes: git add . && git commit -m 'Security cleanup for public repository'"
echo "  4. Make repository public on GitHub"
echo ""
EOF