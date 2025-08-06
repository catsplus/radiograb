#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! mysqladmin ping -h"$DB_HOST" --silent; do
    echo "MySQL is unavailable - sleeping"
    sleep 2
done
echo "MySQL is ready!"

# Generate .env file from template
echo "Creating environment configuration..."
envsubst < /opt/radiograb/.env.template > /opt/radiograb/.env

# Set proper permissions
chown www-data:www-data /opt/radiograb/.env
chmod 600 /opt/radiograb/.env

# Configure msmtp for outgoing email
echo "Configuring email service (msmtp)..."
envsubst < /etc/msmtprc.template > /etc/msmtprc
chmod 600 /etc/msmtprc
chown root:root /etc/msmtprc

# Configure PHP to use msmtp for mail()
echo "sendmail_path = /usr/bin/msmtp -t" >> /etc/php/8.1/fpm/conf.d/99-radiograb.ini
echo "sendmail_path = /usr/bin/msmtp -t" >> /etc/php/8.1/cli/conf.d/99-radiograb.ini

# Log email configuration status
if [ -n "${SMTP_USERNAME}" ]; then
    echo "Email configured with SMTP server: ${SMTP_HOST}:${SMTP_PORT}"
    echo "From address: ${SMTP_FROM}"
    echo "Authentication: ${SMTP_USERNAME}"
else
    echo "Email configured with local delivery (no SMTP authentication)"
    echo "From address: ${SMTP_FROM}"
fi

# Ensure all directories exist with proper permissions
mkdir -p /var/radiograb/{recordings,feeds,logs,temp}
chown -R www-data:www-data /var/radiograb
chmod -R 755 /var/radiograb

# Test database connection and run migrations if needed
echo "Testing database connection..."
cd /opt/radiograb
source venv/bin/activate

# Simple database connectivity test
python3 -c "
import os
import mysql.connector
try:
    conn = mysql.connector.connect(
        host=os.environ['DB_HOST'],
        port=int(os.environ['DB_PORT']),
        user=os.environ['DB_USER'],
        password=os.environ['DB_PASSWORD'],
        database=os.environ['DB_NAME']
    )
    print('Database connection successful!')
    conn.close()
except Exception as e:
    print(f'Database connection failed: {e}')
    exit(1)
"

# SSL Certificate Setup - Check if certificates exist, if not generate them
echo "Checking SSL certificate status..."
if [ ! -f "/etc/letsencrypt/live/${SSL_DOMAIN}/cert.pem" ] && [ -n "${SSL_DOMAIN}" ] && [ -n "${SSL_EMAIL}" ]; then
    echo "SSL certificates not found for ${SSL_DOMAIN}. Attempting to generate..."
    
    # Install certbot if not present
    if ! command -v certbot &> /dev/null; then
        echo "Installing certbot..."
        apt-get update -qq
        apt-get install -y -qq certbot python3-certbot-nginx
    fi
    
    # Start nginx temporarily for webroot verification
    nginx -t && nginx
    
    # Generate certificate using webroot method
    echo "Generating Let's Encrypt SSL certificate..."
    certbot certonly \
        --webroot \
        --webroot-path=/opt/radiograb/frontend/public \
        --non-interactive \
        --agree-tos \
        --email "${SSL_EMAIL}" \
        --domains "${SSL_DOMAIN}" \
        --expand \
        --keep-until-expiring || true
    
    # Stop nginx so supervisor can start it
    nginx -s quit || true
    sleep 2
    
    if [ -f "/etc/letsencrypt/live/${SSL_DOMAIN}/cert.pem" ]; then
        echo "SSL certificate generated successfully for ${SSL_DOMAIN}"
        
        # Update nginx configuration for SSL
        sed -i "s/server_name localhost;/server_name ${SSL_DOMAIN};/" /etc/nginx/sites-available/default
        
        # Add SSL configuration to nginx
        cat >> /etc/nginx/sites-available/default << EOF

# SSL Configuration
server {
    listen 443 ssl http2;
    server_name ${SSL_DOMAIN};
    
    ssl_certificate /etc/letsencrypt/live/${SSL_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${SSL_DOMAIN}/privkey.pem;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Same location blocks as HTTP version
    root /opt/radiograb/frontend/public;
    index index.php index.html;
    
    # Recording files
    location ^~ /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
        add_header Accept-Ranges bytes;
    }
    
    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Default PHP handler
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}

# HTTP to HTTPS redirect
server {
    listen 80;
    server_name ${SSL_DOMAIN};
    return 301 https://\$server_name\$request_uri;
}
EOF
        
        # Setup certificate renewal cron job
        echo "Setting up certificate auto-renewal..."
        cat > /usr/local/bin/renew-certs.sh << 'EOF'
#!/bin/bash
certbot renew --quiet --deploy-hook "nginx -s reload"
EOF
        chmod +x /usr/local/bin/renew-certs.sh
        
        # Add to crontab
        echo "0 12 * * * /usr/local/bin/renew-certs.sh" >> /etc/crontab
        echo "0 0 * * * /usr/local/bin/renew-certs.sh" >> /etc/crontab
        
    else
        echo "SSL certificate generation failed. Continuing with HTTP only."
    fi
elif [ -f "/etc/letsencrypt/live/${SSL_DOMAIN}/cert.pem" ]; then
    echo "SSL certificates found for ${SSL_DOMAIN}. Setting up HTTPS configuration..."
    
    # Update nginx configuration for SSL if not already done
    if ! grep -q "ssl_certificate" /etc/nginx/sites-available/default; then
        sed -i "s/server_name localhost;/server_name ${SSL_DOMAIN};/" /etc/nginx/sites-available/default
        
        cat >> /etc/nginx/sites-available/default << EOF

# SSL Configuration  
server {
    listen 443 ssl http2;
    server_name ${SSL_DOMAIN};
    
    ssl_certificate /etc/letsencrypt/live/${SSL_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${SSL_DOMAIN}/privkey.pem;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Same location blocks as HTTP version
    root /opt/radiograb/frontend/public;
    index index.php index.html;
    
    # Recording files
    location ^~ /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
        add_header Accept-Ranges bytes;
    }
    
    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Default PHP handler
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}

# HTTP to HTTPS redirect
server {
    listen 80;
    server_name ${SSL_DOMAIN};
    return 301 https://\$server_name\$request_uri;
}
EOF
    fi
    
    # Ensure renewal script exists
    if [ ! -f "/usr/local/bin/renew-certs.sh" ]; then
        cat > /usr/local/bin/renew-certs.sh << 'EOF'
#!/bin/bash
certbot renew --quiet --deploy-hook "nginx -s reload"
EOF
        chmod +x /usr/local/bin/renew-certs.sh
        
        # Add to crontab if not already there
        if ! crontab -l 2>/dev/null | grep -q "renew-certs.sh"; then
            echo "0 12 * * * /usr/local/bin/renew-certs.sh" >> /etc/crontab
            echo "0 0 * * * /usr/local/bin/renew-certs.sh" >> /etc/crontab
        fi
    fi
else
    echo "No SSL configuration found. SSL_DOMAIN and SSL_EMAIL environment variables required for HTTPS."
    echo "Continuing with HTTP only configuration."
fi

# Start supervisor to manage all services
echo "Starting RadioGrab services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf