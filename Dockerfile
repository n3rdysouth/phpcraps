# PHP Craps Game - Docker Image
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    supervisor \
    linux-headers \
    && docker-php-ext-install pdo pdo_sqlite sockets

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create database directory and set permissions
RUN mkdir -p database server && \
    touch database/craps_game.db && \
    chown -R www-data:www-data database server && \
    chmod -R 775 database server

# Initialize database if needed
RUN php database/setup.php && \
    php database/migrate.php

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose PHP-FPM and WebSocket ports
EXPOSE 9000 8080

# Start supervisor (manages PHP-FPM + WebSocket server)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
