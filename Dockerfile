FROM php:8.2-apache

# Remove policy-rc.d so background services can run inside container
RUN rm -f /usr/sbin/policy-rc.d

# Install MariaDB server and client + utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Setup MariaDB data directory, socket directory, and pre-initialize system tables
RUN mkdir -p /var/run/mysqld /var/lib/mysql \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql \
    && mariadb-install-db --user=mysql --datadir=/var/lib/mysql

# Configure low-memory MariaDB settings (optimized for 512MB RAM free cloud tiers)
RUN printf "[mysqld]\nbind-address = 0.0.0.0\nport = 3306\nsocket = /var/run/mysqld/mysqld.sock\nperformance_schema = OFF\ninnodb_buffer_pool_size = 32M\ninnodb_log_buffer_size = 1M\nmax_connections = 50\nkey_buffer_size = 8M\n" > /etc/mysql/mariadb.conf.d/99-low-memory.cnf

# Configure PHP settings (upload limits, execution time, explicit socket path)
RUN printf "file_uploads = On\nmemory_limit = 256M\nupload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 300\nmysqli.default_socket = /var/run/mysqld/mysqld.sock\n" > /usr/local/etc/php/conf.d/custom-uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Ensure uploads directory exists and is writable
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 10000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
