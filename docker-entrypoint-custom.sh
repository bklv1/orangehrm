#!/bin/bash
set -e

service mysql start

echo "Waiting for MySQL..."
until mysqladmin ping --silent; do
    sleep 1
done

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS orangehrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'orangehrm'@'localhost' IDENTIFIED BY 'orangehrm123';
GRANT ALL PRIVILEGES ON orangehrm.* TO 'orangehrm'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "MySQL ready."

apache2-foreground