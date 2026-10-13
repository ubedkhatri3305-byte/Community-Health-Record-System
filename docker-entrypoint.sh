#!/bin/bash

echo "=> Initializing CHR container..."

# 1. Start local MariaDB if no external DB_HOST is provided
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "=> Ensuring MariaDB directories..."
    mkdir -p /var/run/mysqld /var/lib/mysql
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql 2>/dev/null || true

    # Pre-initialize data directory if needed
    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "=> Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql 2>/dev/null || true
    fi

    echo "=> Starting MariaDB daemon..."
    if command -v mariadbd-safe >/dev/null 2>&1; then
        mariadbd-safe --user=mysql --datadir=/var/lib/mysql --skip-syslog &
    elif command -v mysqld_safe >/dev/null 2>&1; then
        mysqld_safe --user=mysql --datadir=/var/lib/mysql --skip-syslog &
    else
        service mariadb start 2>/dev/null || true
    fi

    # Wait up to 35 seconds for MariaDB to become ready
    echo "=> Waiting for MariaDB socket..."
    for i in $(seq 1 35); do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "=> MariaDB is ready (attempt $i)!"
            break
        fi
        sleep 1
    done

    # Initialize chr_db database and permissions if server is alive
    if mysqladmin ping --silent 2>/dev/null; then
        if ! mysql -e "USE chr_db;" 2>/dev/null; then
            echo "=> Creating chr_db and importing database.sql..."
            mysql -e "CREATE DATABASE IF NOT EXISTS chr_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>/dev/null || true
            mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
            mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null || true
            mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
            if [ -f /var/www/html/database.sql ]; then
                mysql chr_db < /var/www/html/database.sql 2>/dev/null || mysql < /var/www/html/database.sql 2>/dev/null || true
            fi
            echo "=> Database initialization completed successfully!"
        else
            echo "=> Database chr_db already present."
        fi
    else
        echo "=> Note: MariaDB local daemon still booting in background. Apache will start and connect."
    fi
fi

# 2. Configure Apache port dynamically for Render ($PORT)
PORT="${PORT:-10000}"
echo "=> Configuring Apache to listen on port ${PORT}..."

# Set ServerName to avoid FQDN warnings
echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
a2enconf servername 2>/dev/null || true

# Cleanly rewrite ports.conf and default VirtualHost
if [ "$PORT" != "80" ]; then
    cat <<EOF > /etc/apache2/ports.conf
Listen ${PORT}
Listen 80
EOF
    cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:${PORT} *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
else
    cat <<EOF > /etc/apache2/ports.conf
Listen 80
EOF
    cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
fi

# 3. Start Apache in foreground
echo "=> Launching Apache on port ${PORT}..."
exec "$@"
