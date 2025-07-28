#!/bin/bash
#
# RadioGrab Database Backup Script
# Automated weekly database backups with 3-week retention
#

# Configuration
BACKUP_DIR="/var/radiograb/backups"
BACKUP_PREFIX="radiograb_backup"
RETENTION_WEEKS=3
DB_CONTAINER="radiograb-mysql-1"
DB_NAME="radiograb"
DB_ROOT_USER="root"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${BACKUP_PREFIX}_${TIMESTAMP}.sql.gz"

# Log file
LOG_FILE="${BACKUP_DIR}/backup.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_message "Starting database backup"

# Get MySQL root password from environment
if [ -f "/opt/radiograb/.env" ]; then
    MYSQL_ROOT_PASSWORD=$(grep "MYSQL_ROOT_PASSWORD=" /opt/radiograb/.env | cut -d'=' -f2)
    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        log_message "ERROR: Could not find MYSQL_ROOT_PASSWORD in .env file"
        exit 1
    fi
else
    log_message "ERROR: .env file not found"
    exit 1
fi

# Create database backup
log_message "Creating backup: $BACKUP_FILE"

if docker exec "$DB_CONTAINER" mysqldump \
    -u "$DB_ROOT_USER" \
    -p"$MYSQL_ROOT_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"; then
    
    # Check if backup file was created and has content
    if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
        BACKUP_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || stat -f%z "$BACKUP_FILE" 2>/dev/null)
        log_message "Backup created successfully: $BACKUP_FILE (${BACKUP_SIZE} bytes)"
    else
        log_message "ERROR: Backup file is empty or was not created"
        rm -f "$BACKUP_FILE"
        exit 1
    fi
else
    log_message "ERROR: mysqldump command failed"
    rm -f "$BACKUP_FILE"
    exit 1
fi

# Clean up old backups (older than retention period)
log_message "Cleaning up backups older than $RETENTION_WEEKS weeks"

# Calculate cutoff date (retention_weeks * 7 days ago)
CUTOFF_DATE=$(date -d "$RETENTION_WEEKS weeks ago" +%Y%m%d 2>/dev/null || \
              date -v-"$((RETENTION_WEEKS * 7))d" +%Y%m%d 2>/dev/null)

if [ -n "$CUTOFF_DATE" ]; then
    DELETED_COUNT=0
    DELETED_SIZE=0
    
    # Find and delete old backup files
    for backup_file in "${BACKUP_DIR}/${BACKUP_PREFIX}"_*.sql.gz; do
        if [ -f "$backup_file" ]; then
            # Extract date from filename (format: prefix_YYYYMMDD_HHMMSS.sql.gz)
            backup_date=$(basename "$backup_file" | sed -n "s/^${BACKUP_PREFIX}_\([0-9]\{8\}\)_.*$/\1/p")
            
            if [ -n "$backup_date" ] && [ "$backup_date" -lt "$CUTOFF_DATE" ]; then
                file_size=$(stat -c%s "$backup_file" 2>/dev/null || stat -f%z "$backup_file" 2>/dev/null)
                rm "$backup_file"
                DELETED_COUNT=$((DELETED_COUNT + 1))
                DELETED_SIZE=$((DELETED_SIZE + file_size))
                log_message "Deleted old backup: $(basename "$backup_file") (${file_size} bytes)"
            fi
        fi
    done
    
    if [ "$DELETED_COUNT" -gt 0 ]; then
        log_message "Cleanup completed: deleted $DELETED_COUNT old backups (${DELETED_SIZE} bytes total)"
    else
        log_message "No old backups to clean up"
    fi
else
    log_message "WARNING: Could not calculate cutoff date for cleanup"
fi

# Show current backup statistics
TOTAL_BACKUPS=$(ls -1 "${BACKUP_DIR}/${BACKUP_PREFIX}"_*.sql.gz 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sb "${BACKUP_DIR}/${BACKUP_PREFIX}"_*.sql.gz 2>/dev/null | awk '{sum+=$1} END {print sum+0}')

log_message "Backup statistics: $TOTAL_BACKUPS backups, ${TOTAL_SIZE} bytes total"
log_message "Database backup completed successfully"

# Optional: Keep only the last 10 log entries to prevent log growth
tail -n 50 "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"

exit 0