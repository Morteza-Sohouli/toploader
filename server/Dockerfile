# Use the official PHP image with FPM (nginx will be installed separately)
FROM php:8.1-fpm

WORKDIR /var/www/html

# System deps and nginx (cached unless base image or this block changes)
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions for MySQL (cached)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# PHP upload limits (20GB)
RUN echo "upload_max_filesize = 30G" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 30G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 20G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "file_uploads = On" >> /usr/local/etc/php/conf.d/uploads.ini

# Composer binary (cached)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP deps first so this layer is cached when only app code changes
COPY composer.json composer.lock ./
RUN composer install --no-dev

# App code (vendor excluded via .dockerignore so previous layer's vendor is kept)
COPY . /var/www/html

# Clear cache if present in copied tree
RUN rm -rf /var/www/html/var/cache/* 2>/dev/null || true

# PHP and nginx config (system paths)
COPY config/performance.ini /usr/local/etc/php/conf.d/performance.ini
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
COPY nginx-map-disposition.conf /etc/nginx/conf.d/00-map-disposition.conf
COPY nginx-default.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

ENTRYPOINT ["docker-entrypoint.sh"]
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
