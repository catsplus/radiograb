FROM ubuntu:22.04

# Prevent interactive prompts during installation
ENV DEBIAN_FRONTEND=noninteractive

# Set timezone to Eastern
ENV TZ=America/New_York
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Set WebDriver Manager cache directory to writable location
ENV WDM_LOCAL=/var/radiograb/temp/.wdm

# Install system dependencies including Chromium for WebDriver
RUN apt-get update && apt-get install -y \
    gettext-base \
    python3 \
    python3-pip \
    python3-venv \
    nginx \
    php8.1-fpm \
    php8.1-mysql \
    php8.1-curl \
    php8.1-xml \
    php8.1-mbstring \
    php8.1-zip \
    streamripper \
    ffmpeg \
    mysql-client \
    curl \
    wget \
    git \
    supervisor \
    cron \
    certbot \
    python3-certbot-nginx \
    wget \
    gnupg \
    unzip \
    msmtp \
    msmtp-mta \
    mailutils \
    && rm -rf /var/lib/apt/lists/*

# Install Google Chrome for reliable Selenium WebDriver support
# Ubuntu 22.04 chromium-browser package is broken (requires Snap)
RUN wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome-keyring.gpg && \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome-keyring.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
    apt-get update && \
    apt-get install -y google-chrome-stable && \
    rm -rf /var/lib/apt/lists/*

# Install rclone for remote storage support (Issue #42)
RUN curl https://rclone.org/install.sh | bash

# Create application directory
WORKDIR /opt/radiograb

# Copy application files
COPY . /opt/radiograb/

# Create Python virtual environment and install dependencies
RUN python3 -m venv venv && \
    . venv/bin/activate && \
    pip install --no-cache-dir -r requirements.txt

# Create necessary directories
RUN mkdir -p /var/radiograb/{recordings,feeds,logs,temp,rclone} && \
    mkdir -p /var/log/supervisor && \
    mkdir -p /run/php

# Set permissions
RUN chown -R www-data:www-data /opt/radiograb /var/radiograb && \
    chmod +x /opt/radiograb/venv/bin/python

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/radiograb-locations.conf /etc/nginx/radiograb-locations.conf
RUN rm -f /etc/nginx/sites-enabled/default && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Configure PHP-FPM
RUN sed -i 's/listen = \/run\/php\/php8.1-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/8.1/fpm/pool.d/www.conf && \
    sed -i 's/;clear_env = no/clear_env = no/' /etc/php/8.1/fpm/pool.d/www.conf

# Copy custom PHP configuration for upload limits
COPY docker/php-custom.ini /etc/php/8.1/fpm/conf.d/99-radiograb.ini
COPY docker/php-custom.ini /etc/php/8.1/cli/conf.d/99-radiograb.ini

# Configure msmtp for outgoing email
COPY docker/msmtprc /etc/msmtprc.template
RUN chmod 600 /etc/msmtprc.template && \
    touch /var/log/msmtp.log && \
    chown www-data:www-data /var/log/msmtp.log && \
    chmod 600 /var/log/msmtp.log

# Configure Supervisor  
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure Station Health Monitoring Cron
COPY docker/station-health-cron /etc/cron.d/station-health
RUN chmod 0644 /etc/cron.d/station-health && \
    crontab /etc/cron.d/station-health

# Configure Database Backup Cron
COPY docker/cron/database-backup /etc/cron.d/database-backup
RUN chmod 0644 /etc/cron.d/database-backup && \
    crontab /etc/cron.d/database-backup

# Configure Schedule Verification Cron  
COPY docker/cron/schedule-verification /etc/cron.d/schedule-verification
RUN chmod 0644 /etc/cron.d/schedule-verification && \
    crontab /etc/cron.d/schedule-verification

# Configure TTL Cleanup Cron
COPY docker/cron/ttl-cleanup /etc/cron.d/ttl-cleanup
RUN chmod 0644 /etc/cron.d/ttl-cleanup && \
    crontab /etc/cron.d/ttl-cleanup

# Make scripts executable
RUN chmod +x /opt/radiograb/scripts/backup-database.sh

# Create startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Create environment file template
RUN cat > /opt/radiograb/.env.template << 'EOF'
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
RECORDINGS_DIR=${RECORDINGS_DIR}
FEEDS_DIR=${FEEDS_DIR}
LOGS_DIR=${LOGS_DIR}
TEMP_DIR=${TEMP_DIR}
RADIOGRAB_BASE_URL=${RADIOGRAB_BASE_URL}
STREAMRIPPER_PATH=${STREAMRIPPER_PATH}
API_ENCRYPTION_KEY=${API_ENCRYPTION_KEY}
EOF

# Expose ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Use supervisor to manage services
CMD ["/start.sh"]