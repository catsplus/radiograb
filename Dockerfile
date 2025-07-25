FROM ubuntu:22.04

# Prevent interactive prompts during installation
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
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
    && rm -rf /var/lib/apt/lists/*

# Create application directory
WORKDIR /opt/radiograb

# Copy application files
COPY . /opt/radiograb/

# Create Python virtual environment and install dependencies
RUN python3 -m venv venv && \
    . venv/bin/activate && \
    pip install --no-cache-dir -r requirements.txt

# Create necessary directories
RUN mkdir -p /var/radiograb/{recordings,feeds,logs,temp} && \
    mkdir -p /var/log/supervisor && \
    mkdir -p /run/php

# Set permissions
RUN chown -R www-data:www-data /opt/radiograb /var/radiograb && \
    chmod +x /opt/radiograb/venv/bin/python

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Configure PHP-FPM
RUN sed -i 's/listen = \/run\/php\/php8.1-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/8.1/fpm/pool.d/www.conf && \
    sed -i 's/;clear_env = no/clear_env = no/' /etc/php/8.1/fpm/pool.d/www.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

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
EOF

# Expose ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Use supervisor to manage services
CMD ["/start.sh"]