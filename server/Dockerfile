# Use the official PHP image with FPM (nginx will be installed separately)
FROM php:8.1-fpm

# Set the working directory
WORKDIR /var/www/html

# Copy the current directory contents into the container
COPY . /var/www/html

# Install system dependencies and nginx
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    nginx \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/www/html/var/cache/*

# Install PHP extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configure PHP for large file uploads (20GB)
RUN echo "upload_max_filesize = 20G" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 21G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 1G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "file_uploads = On" >> /usr/local/etc/php/conf.d/uploads.ini

# Copy performance configuration
COPY config/performance.ini /usr/local/etc/php/conf.d/performance.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies (used when no volume overrides /var/www/html)
RUN composer install --no-dev

# Entrypoint: ensure vendor exists at runtime when ./server is bind-mounted (no vendor on host)
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# Expose port 80
EXPOSE 80

# nginx config: document root /var/www/html/public, front-controller, 20GB body, long timeouts
# Map for Content-Disposition filename (http context; load first so variable exists)
COPY nginx-map-disposition.conf /etc/nginx/conf.d/00-map-disposition.conf
COPY nginx-default.conf /etc/nginx/conf.d/default.conf
# Internal WP locations in app dir so conf.d/*.conf does not load them at http level; overwritten at runtime by generate-nginx-internal-wp.php
RUN rm -f /etc/nginx/sites-enabled/default

# Start nginx in foreground (PHP-FPM started by entrypoint)
CMD ["nginx", "-g", "daemon off;"]
