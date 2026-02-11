# Use the official PHP image with Apache
FROM php:8.1-apache

# Set the working directory
WORKDIR /var/www/html

# Update Apache configuration to point to public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy the current directory contents into the container
COPY . /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
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

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set LimitRequestBody for 20GB uploads in Apache
RUN echo "LimitRequestBody 21474836480" >> /etc/apache2/apache2.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies and optimize autoloader
RUN composer install --no-dev --classmap-authoritative

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]