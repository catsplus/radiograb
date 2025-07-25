#!/bin/bash
#
# SSL Certificate Backup Script for RadioGrab
# Creates backups of SSL certificates from Docker volumes
#

set -e

BACKUP_DIR="/opt/radiograb/backups/ssl"
DATE=$(date +%Y%m%d_%H%M%S)
SERVER="radiograb@167.71.84.143"

echo "🔒 RadioGrab SSL Certificate Backup"
echo "=================================="
echo "📅 Date: $(date)"
echo "📁 Backup Directory: $BACKUP_DIR"

# Create backup directory
ssh $SERVER "mkdir -p $BACKUP_DIR"

echo "📦 Creating SSL certificate backups..."

# Backup letsencrypt volume
echo "  - Backing up letsencrypt certificates..."
ssh $SERVER "docker run --rm -v radiograb_letsencrypt:/source -v $BACKUP_DIR:/backup alpine tar czf /backup/ssl_certs_$DATE.tar.gz -C /source ."

# Backup letsencrypt lib volume  
echo "  - Backing up letsencrypt library..."
ssh $SERVER "docker run --rm -v radiograb_letsencrypt_lib:/source -v $BACKUP_DIR:/backup alpine tar czf /backup/ssl_lib_$DATE.tar.gz -C /source ."

# Export certificate files directly
echo "  - Exporting certificate files..."
ssh $SERVER "docker exec radiograb-web-1 tar czf /tmp/ssl_export_$DATE.tar.gz /etc/letsencrypt/live/ /etc/letsencrypt/archive/ /etc/letsencrypt/renewal/ 2>/dev/null || true"
ssh $SERVER "mv /tmp/ssl_export_$DATE.tar.gz $BACKUP_DIR/"

# Get certificate info
echo "  - Saving certificate information..."
ssh $SERVER "docker exec radiograb-web-1 certbot certificates > $BACKUP_DIR/cert_info_$DATE.txt 2>/dev/null || echo 'Certbot not available' > $BACKUP_DIR/cert_info_$DATE.txt"

# List backup files
echo "✅ Backup completed successfully!"
echo ""
echo "📋 Backup files created:"
ssh $SERVER "ls -lah $BACKUP_DIR/ssl_*_$DATE.* $BACKUP_DIR/cert_info_$DATE.txt"

echo ""
echo "💾 Total backup size:"
ssh $SERVER "du -sh $BACKUP_DIR/"

echo ""
echo "🔄 To restore certificates if needed:"
echo "  1. scp $SERVER:$BACKUP_DIR/ssl_export_$DATE.tar.gz ."
echo "  2. scp ssl_export_$DATE.tar.gz $SERVER:/tmp/"
echo "  3. ssh $SERVER \"docker exec radiograb-web-1 tar xzf /tmp/ssl_export_$DATE.tar.gz -C /\""
echo "  4. ssh $SERVER \"docker exec radiograb-web-1 nginx -s reload\""

echo ""
echo "📧 Certificate expiration check:"
CERT_EXPIRES=$(ssh $SERVER "docker exec radiograb-web-1 openssl x509 -in /etc/letsencrypt/live/radiograb.svaha.com/cert.pem -noout -enddate 2>/dev/null | cut -d= -f2" 2>/dev/null || echo "Unable to check expiration")
echo "  Certificate expires: $CERT_EXPIRES"

echo ""
echo "✨ Backup complete! Files saved to $BACKUP_DIR on server."