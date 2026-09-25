#!/bin/bash
set -e

# 1. Start local MariaDB if no external DB_HOST is provided
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "=> Starting internal MariaDB server..."
    service mariadb start || /etc/init.d/mariadb start

    # Wait for MariaDB to become ready
    for i in {1..30}; do
        if mysqladmin ping --silent 2>/dev/null; then
            break
        fi
        sleep 1
    done

    # Initialize chr_db database and permissions if not already present
    if ! mysql -e "USE chr_db;" 2>/dev/null; then
        echo "=> Creating chr_db and importing database.sql..."
        mysql -e "CREATE DATABASE IF NOT EXISTS chr_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');" 2>/dev/null || true
        mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
        mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('') WITH GRANT OPTION;" 2>/dev/null || true
        mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
        mysql chr_db < /var/www/html/database.sql
        echo "=> Database initialized with default admin and all tables!"
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
