#!/bin/bash
set -e

# Създайте нужните директории
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld
chown -R mysql:mysql /var/lib/mysql

if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "Initializing MySQL data directory..."
    mysqld --initialize-insecure --user=mysql
fi

echo "Starting MySQL..."
mysqld --user=mysql &
MYSQL_PID=$!

echo "Waiting for MySQL..."
until mysqladmin ping -h localhost --silent 2>/dev/null; do
    sleep 1
done
echo "MySQL is ready."

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS orangehrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'orangehrm'@'localhost' IDENTIFIED BY 'orangehrm123';
GRANT ALL PRIVILEGES ON orangehrm.* TO 'orangehrm'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "Database configured."

apache2-foreground