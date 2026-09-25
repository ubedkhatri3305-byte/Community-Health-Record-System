#!/bin/bash
set -e

# 1. Start local MariaDB if no external DB_HOST is provided
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "=> Ensuring MariaDB directories..."
    mkdir -p /var/run/mysqld /var/lib/mysql
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

    echo "=> Starting MariaDB server..."
    if command -v mariadbd-safe >/dev/null 2>&1; then
        mariadbd-safe --datadir=/var/lib/mysql --skip-syslog &
    else
        mysqld_safe --datadir=/var/lib/mysql --skip-syslog &
    fi

    # Wait up to 30 seconds for MariaDB to become ready
    echo "=> Waiting for MariaDB to be ready..."
    for i in $(seq 1 30); do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "=> MariaDB is ready!"
            break
        fi
        sleep 1
    done

    # Initialize chr_db database and permissions if not already present
    if ! mysql -e "USE chr_db;" 2>/dev/null; then
        echo "=> Creating chr_db and importing real database.sql from XAMPP..."
        mysql -e "CREATE DATABASE IF NOT EXISTS chr_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');" 2>/dev/null || true
        mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
        mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('') WITH GRANT OPTION;" 2>/dev/null || true
        mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
        mysql < /var/www/html/database.sql
        echo "=> Real XAMPP database initialized with all tables and data!"
    else
        echo "=> Database chr_db already initialized."
    fi
fi

# 2. Configure Apache port dynamically for Render ($PORT)
PORT="${PORT:-80}"
echo "=> Binding Apache to port ${PORT}..."
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:[0-9]*>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# 3. Start Apache in foreground
echo "=> Starting Apache..."
exec "$@"
