#!/bin/bash
# RadioGrab Container SSL Setup with Let's Encrypt
# Sets up SSL certificates within the Docker container environment

set -e

echo "🔒 RadioGrab Container SSL Setup"
echo "================================"

if [ -z "$1" ]; then
    echo "Usage: $0 <domain_name> [email]"
    echo "Example: $0 radiograb.yourdomain.com admin@yourdomain.com"
    echo ""
    echo "⚠️  Important: Your domain must point to 167.71.84.143 before running this script"
    echo ""
    echo "To set up DNS:"
    echo "  1. Add an A record: radiograb.yourdomain.com → 167.71.84.143"
    echo "  2. Wait for DNS propagation (can take up to 24 hours)"
    echo "  3. Verify with: dig +short radiograb.yourdomain.com"
    exit 1
fi

DOMAIN="$1"
EMAIL="${2:-admin@${DOMAIN}}"
SERVER="radiograb@167.71.84.143"

echo "🌐 Domain: $DOMAIN"
echo "📧 Email: $EMAIL"
echo "🖥️  Server: $SERVER"
echo ""

# Verify domain resolution
echo "🔍 Verifying domain resolution..."
RESOLVED_IP=$(dig +short "$DOMAIN" 2>/dev/null | tail -n1)
if [ -z "$RESOLVED_IP" ]; then
    echo "❌ Domain $DOMAIN does not resolve. Please set up DNS first."
    exit 1
elif [ "$RESOLVED_IP" != "167.71.84.143" ]; then
    echo "⚠️  Warning: Domain $DOMAIN resolves to $RESOLVED_IP, not 167.71.84.143"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "✅ Domain resolution verified"
echo ""

# Create enhanced nginx configuration with SSL
cat > nginx-ssl.conf << 'EOF'
server {
    listen 80;
    server_name DOMAIN_PLACEHOLDER;
    
    # Let's Encrypt challenge - allow HTTP for certificate verification
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
    
    # Health check endpoint
    location /health {
        access_log off;
        return 200 "OK\\n";
        add_header Content-Type text/plain;
    }
    
    # Redirect all other traffic to HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name DOMAIN_PLACEHOLDER;
    
    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem;
    
    # Modern SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    
    # OCSP stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: ws: wss: data: blob: 'unsafe-inline'; frame-ancestors 'self';" always;
    
    # Document root
    root /opt/radiograb/frontend/public;
    index index.php index.html;
    
    # Max upload size for recordings
    client_max_body_size 200M;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # PHP handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_param HTTPS on;
        fastcgi_param SERVER_PORT 443;
        
        # Timeout settings
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }
    
    # Static assets with long cache
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
    }
    
    # Audio files with shorter cache
    location ~* \.(mp3|wav|ogg|m4a|aac|flac)$ {
        expires 7d;
        add_header Cache-Control "public";
        add_header Accept-Ranges bytes;
        
        # Enable range requests for audio streaming
        location ~ ^/recordings/ {
            add_header Access-Control-Allow-Origin *;
        }
    }
    
    # RSS feeds
    location ~* \.(xml|rss)$ {
        expires 1h;
        add_header Content-Type application/rss+xml;
    }
    
    # Health check endpoint
    location /health {
        access_log off;
        return 200 "OK - SSL Enabled\\n";
        add_header Content-Type text/plain;
    }
    
    # Deny access to sensitive files and directories
    location ~ /\.(ht|git|env) {
        deny all;
        return 404;
    }
    
    location ~ /(database|backend|includes|\.git)/ {
        deny all;
        return 404;
    }
    
    # API endpoints
    location /api/ {
        try_files $uri $uri/ =404;
    }
    
    # Default try_files
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
EOF

# Replace placeholder with actual domain
sed -i "s/DOMAIN_PLACEHOLDER/$DOMAIN/g" nginx-ssl.conf

echo "📤 Uploading SSL configuration..."
scp nginx-ssl.conf "$SERVER:/tmp/"

echo "🔧 Setting up SSL in container..."
ssh "$SERVER" << REMOTE_COMMANDS
    echo "📦 Installing certbot in container..."
    docker exec radiograb-web-1 bash -c "
        # Update package list
        apt-get update -qq
        
        # Install certbot
        apt-get install -y certbot python3-certbot-nginx curl
        
        # Create webroot directory for challenges
        mkdir -p /var/www/html/.well-known/acme-challenge
        chown -R www-data:www-data /var/www/html
        
        # Create initial challenge directory
        mkdir -p /var/www/html
        echo 'Challenge directory ready' > /var/www/html/.well-known/acme-challenge/test
    "
    
    echo "🔒 Generating SSL certificate..."
    docker exec radiograb-web-1 bash -c "
        # Generate certificate using webroot method
        certbot certonly \\
            --webroot \\
            --webroot-path=/var/www/html \\
            --non-interactive \\
            --agree-tos \\
            --email '$EMAIL' \\
            --domains '$DOMAIN' \\
            --expand \\
            --verbose
    "
    
    echo "🔧 Installing nginx SSL configuration..."
    docker cp /tmp/nginx-ssl.conf radiograb-web-1:/etc/nginx/sites-available/radiograb-ssl
    
    docker exec radiograb-web-1 bash -c "
        # Remove default site
        rm -f /etc/nginx/sites-enabled/default
        
        # Enable SSL site
        ln -sf /etc/nginx/sites-available/radiograb-ssl /etc/nginx/sites-enabled/
        
        # Test nginx configuration
        nginx -t
        
        # Reload nginx
        nginx -s reload
    "
    
    echo "⏰ Setting up certificate renewal..."
    docker exec radiograb-web-1 bash -c "
        # Create renewal script
        cat > /usr/local/bin/renew-certs.sh << 'RENEW_SCRIPT'
#!/bin/bash
/usr/bin/certbot renew --quiet --webroot --webroot-path=/var/www/html
if [ \\\$? -eq 0 ]; then
    nginx -s reload
fi
RENEW_SCRIPT
        
        chmod +x /usr/local/bin/renew-certs.sh
        
        # Add to crontab (runs twice daily)
        echo '0 2,14 * * * /usr/local/bin/renew-certs.sh' | crontab -
        
        # Start cron
        service cron start
    "
    
    echo "✅ SSL setup completed!"
REMOTE_COMMANDS

# Clean up
rm -f nginx-ssl.conf

echo ""
echo "🎉 SSL Certificate Setup Complete!"
echo "=================================="
echo ""
echo "🔗 Your secure RadioGrab instance:"
echo "   https://$DOMAIN"
echo ""
echo "🔒 SSL Certificate Details:"
ssh "$SERVER" "docker exec radiograb-web-1 certbot certificates 2>/dev/null | grep -A5 '$DOMAIN' || echo 'Certificate details not available'"
echo ""
echo "🔄 Certificate Auto-Renewal:"
echo "   • Certificates will auto-renew twice daily"
echo "   • Renewal script: /usr/local/bin/renew-certs.sh"
echo "   • Check renewal status: docker exec radiograb-web-1 certbot renew --dry-run"
echo ""
echo "🔧 Configuration Files:"
echo "   • nginx config: /etc/nginx/sites-available/radiograb-ssl"
echo "   • SSL certificates: /etc/letsencrypt/live/$DOMAIN/"
echo ""
echo "✅ Test your SSL setup:"
echo "   https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
echo ""
echo "📋 Troubleshooting:"
echo "   • Container logs: docker logs radiograb-web-1"
echo "   • nginx status: docker exec radiograb-web-1 nginx -t"
echo "   • Certificate status: docker exec radiograb-web-1 certbot certificates"