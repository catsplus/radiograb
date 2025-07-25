#!/bin/bash
# RadioGrab SSL Setup with Let's Encrypt
# This script sets up SSL certificates using Certbot and configures nginx

set -e

echo "🔒 RadioGrab SSL Setup with Let's Encrypt"
echo "=========================================="

# Check if domain is provided
if [ -z "$1" ]; then
    echo "Usage: $0 <domain_name> [email]"
    echo "Example: $0 radiograb.example.com admin@example.com"
    echo ""
    echo "Note: Make sure your domain points to this server's IP (167.71.84.143)"
    exit 1
fi

DOMAIN="$1"
EMAIL="${2:-admin@${DOMAIN}}"
SERVER="radiograb@167.71.84.143"

echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Server: $SERVER"
echo ""

# Verify domain resolves to this server
echo "🔍 Verifying domain resolution..."
RESOLVED_IP=$(dig +short "$DOMAIN" | tail -n1)
if [ "$RESOLVED_IP" != "167.71.84.143" ]; then
    echo "⚠️  Warning: Domain $DOMAIN resolves to $RESOLVED_IP, not 167.71.84.143"
    echo "   Please update your DNS records before continuing."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "📋 Setting up SSL certificates..."

# Create nginx configuration with SSL
cat > nginx-ssl.conf << EOF
server {
    listen 80;
    server_name $DOMAIN;
    
    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
    
    # Redirect all other traffic to HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN;
    
    # SSL certificates (will be created by Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    
    # Modern SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    root /opt/radiograb/frontend/public;
    index index.php index.html;
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_param HTTPS on;
        fastcgi_param SERVER_PORT 443;
    }
    
    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Audio files
    location ~* \.(mp3|wav|ogg|m4a)$ {
        expires 1d;
        add_header Cache-Control "public";
        add_header Accept-Ranges bytes;
    }
    
    # Deny access to sensitive files
    location ~ /\.(ht|git|env) {
        deny all;
    }
    
    location ~ /(database|backend|includes)/ {
        deny all;
    }
}
EOF

echo "📤 Uploading nginx configuration..."
scp nginx-ssl.conf "$SERVER:/tmp/"

echo "🛠️  Installing SSL certificates on server..."
ssh "$SERVER" << REMOTE_COMMANDS
    # Install certbot if not present
    if ! command -v certbot &> /dev/null; then
        echo "Installing certbot..."
        sudo dnf install -y certbot python3-certbot-nginx
    fi
    
    # Stop nginx temporarily for certificate generation
    echo "Stopping nginx..."
    sudo systemctl stop nginx || echo "nginx not running"
    
    # Generate certificate
    echo "Generating SSL certificate for $DOMAIN..."
    sudo certbot certonly --standalone \
        --non-interactive \
        --agree-tos \
        --email "$EMAIL" \
        -d "$DOMAIN"
    
    # Copy nginx configuration
    echo "Installing nginx configuration..."
    sudo cp /tmp/nginx-ssl.conf /etc/nginx/conf.d/radiograb-ssl.conf
    
    # Remove default nginx config if it exists
    sudo rm -f /etc/nginx/conf.d/default.conf
    
    # Test nginx configuration
    echo "Testing nginx configuration..."
    sudo nginx -t
    
    # Start nginx
    echo "Starting nginx..."
    sudo systemctl start nginx
    sudo systemctl enable nginx
    
    # Setup automatic renewal
    echo "Setting up automatic certificate renewal..."
    (sudo crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet --post-hook 'systemctl reload nginx'") | sudo crontab -
    
    echo "✅ SSL setup completed!"
    echo "Certificate expires: \$(sudo certbot certificates 2>/dev/null | grep -A2 '$DOMAIN' | grep 'Expiry Date' || echo 'Unknown')"
REMOTE_COMMANDS

echo ""
echo "🎉 SSL setup completed!"
echo ""
echo "🔗 Your RadioGrab instance is now available at:"
echo "   https://$DOMAIN"
echo ""
echo "📋 SSL Certificate Information:"
ssh "$SERVER" "sudo certbot certificates 2>/dev/null | grep -A5 '$DOMAIN' || echo 'Certificate info not available'"
echo ""
echo "🔄 Certificate will auto-renew via cron job"
echo "🔧 nginx configuration: /etc/nginx/conf.d/radiograb-ssl.conf"

# Clean up local temp file
rm -f nginx-ssl.conf

echo ""
echo "✅ Setup complete! Test your SSL certificate at:"
echo "   https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"