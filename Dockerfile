FROM php:8.2-apache

# Install required PHP extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure PHP settings (upload limits, execution time)
RUN printf "file_uploads = On\nmemory_limit = 256M\nupload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 300\n" > /usr/local/etc/php/conf.d/custom-uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Ensure uploads directory exists and is writable by www-data
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Setup entrypoint script to dynamically bind Apache to Render's $PORT
RUN printf '#!/bin/bash\nset -e\nPORT="${PORT:-80}"\nsed -i "s/Listen [0-9]*/Listen $PORT/" /etc/apache2/ports.conf\nsed -i "s/:[0-9]*>/:$PORT>/" /etc/apache2/sites-available/000-default.conf\nexec "$@"\n' > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 10000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
