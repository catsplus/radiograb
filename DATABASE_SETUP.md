# RadioGrab Database Setup Guide

This guide covers database initialization for fresh RadioGrab deployments.

## 🏗️ Database Architecture

RadioGrab uses MySQL 8.0 with a comprehensive schema including:

### Core Tables
- **stations** - Radio station information and stream URLs
- **shows** - Show metadata, schedules, and settings  
- **recordings** - Recorded audio files and metadata
- **cron_jobs** - Scheduled recording jobs

### Enhanced Features  
- **custom_feeds** / **custom_feed_shows** - Custom RSS feed system
- **station_feeds** - Station-specific RSS feeds
- **show_schedules** - Multiple airings per show support

### Admin & Branding
- **users** - Admin authentication system
- **site_settings** - Customizable branding settings

### System Tables
- **stream_tests** - Stream testing history and results
- **system_info** - System metadata and configuration
- **schema_migrations** - Migration tracking
- **feed_generation_log** - RSS generation monitoring

## 🚀 Fresh Deployment Setup

### Option 1: Docker Compose (Recommended)

The Docker setup automatically initializes the database on first run:

```bash
# Clone and deploy
git clone https://github.com/mattbaya/radiograb.git
cd radiograb
cp .env.example .env
# Edit .env with your settings

# Deploy with fresh database
docker compose up -d
```

### Option 2: Manual Database Setup

If you need to set up the database manually:

```bash
# Create database and user
mysql -u root -p
CREATE DATABASE radiograb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radiograb'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON radiograb.* TO 'radiograb'@'localhost';
FLUSH PRIVILEGES;

# Initialize schema
mysql -u radiograb -p radiograb < database/init-database.sql
```

### Option 3: Existing Database Migration

If you have an existing RadioGrab database, run migrations:

```bash
# Run all pending migrations
./scripts/apply-migrations.sh

# Or manually apply specific migrations
mysql -u radiograb -p radiograb < database/migrations/add_users_table.sql
```

## 🔧 Schema Management

### Migration System

RadioGrab uses a dual approach:
1. **Fresh installs**: Complete schema via `init-database.sql`
2. **Existing systems**: Incremental migrations via `scripts/apply-migrations.sh`

### Available Migrations

- `add_call_sign_field.sql` - Station call letters (WEHC, WERU, etc.)
- `add_enhanced_feed_system.sql` - Custom RSS feeds and playlists
- `add_multiple_show_schedules.sql` - Multiple airings per show
- `add_site_settings.sql` - Branding customization system
- `add_users_table.sql` - Admin authentication
- `add_ttl_support.sql` - Recording retention policies
- And more...

### Schema Verification

Check your database schema:

```bash
# List all tables
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb -e "SHOW TABLES;"

# Check specific table structure
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb -e "DESCRIBE stations;"

# Verify migration status
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb -e "SELECT * FROM schema_migrations;"
```

## 🔐 Default Credentials

### Database
- **Host**: mysql (in Docker) or localhost
- **Database**: radiograb  
- **Username**: radiograb
- **Password**: Set via `DB_PASSWORD` environment variable

### Admin Panel
- **Username**: admin
- **Password**: password
- **URL**: https://your-domain.com/login.php

⚠️ **Security**: Change the default admin password immediately after deployment!

## 🐛 Troubleshooting

### Common Issues

**"Table doesn't exist" errors**
```bash
# Run migrations manually
./scripts/apply-migrations.sh

# Or check if migrations table exists
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb -e "SHOW TABLES LIKE 'schema_migrations';"
```

**Database connection failed**
```bash
# Check MySQL container status
docker compose ps mysql

# Check logs
docker logs radiograb-mysql-1

# Test connection
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb -e "SELECT 1;"
```

**Missing tables after deployment**
```bash
# Verify Docker initialization
docker exec radiograb-mysql-1 ls -la /docker-entrypoint-initdb.d/

# Manually run initialization
docker exec radiograb-mysql-1 mysql -u radiograb -p radiograb < /opt/radiograb/database/init-database.sql
```

## 📊 Schema Version

Current schema version: **3.9.0**  
Last updated: 2025-07-31  
Total tables: 13  
Migration files: 14  

## 🔄 Backup & Recovery

### Backup
```bash
# Full database backup
docker exec radiograb-mysql-1 mysqldump -u radiograb -p radiograb > radiograb-backup.sql

# Schema only
docker exec radiograb-mysql-1 mysqldump -u radiograb -p --no-data radiograb > schema-backup.sql
```

### Recovery
```bash
# Restore full backup
docker exec -i radiograb-mysql-1 mysql -u radiograb -p radiograb < radiograb-backup.sql
```