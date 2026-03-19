#!/bin/bash
set -e

CONF_DIR=/var/www/html/lib/confs
CONF_FILE="${CONF_DIR}/Conf.php"

if [ -n "${ORANGEHRM_DATABASE_HOST}" ]; then

    # Check whether OrangeHRM tables already exist in the DB.
    DB_READY=$(php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=' . getenv('ORANGEHRM_DATABASE_HOST')
                     . ';port=' . (getenv('ORANGEHRM_DATABASE_PORT') ?: '3306')
                     . ';dbname=' . getenv('ORANGEHRM_DATABASE_NAME'),
                getenv('ORANGEHRM_DATABASE_USER'),
                getenv('ORANGEHRM_DATABASE_PASSWORD'),
                [
                    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                    PDO::ATTR_TIMEOUT => 10,
                ]
            );
            \$stmt = \$pdo->query(\"SHOW TABLES LIKE 'hs_hr_config'\");
            echo \$stmt->rowCount() > 0 ? 'yes' : 'no';
        } catch (Exception \$e) {
            echo 'no';
        }
    ")

    if [ "${DB_READY}" = "yes" ]; then
        # DB already initialised — regenerate Conf.php from env vars and start
        echo "DB already initialised — regenerating Conf.php"
        php -r "
            \$template = file_get_contents('/var/www/html/installer/config/Conf.tpl.php');
            \$search  = ['{{dbHost}}', '{{dbPort}}', '{{dbName}}', '{{dbUser}}', '{{dbPass}}'];
            \$replace = [
                getenv('ORANGEHRM_DATABASE_HOST'),
                getenv('ORANGEHRM_DATABASE_PORT') ?: '3306',
                getenv('ORANGEHRM_DATABASE_NAME'),
                getenv('ORANGEHRM_DATABASE_USER'),
                getenv('ORANGEHRM_DATABASE_PASSWORD'),
            ];
            file_put_contents('${CONF_FILE}', str_replace(\$search, \$replace, \$template));
        "
        chown www-data:www-data "${CONF_FILE}"
    else
        # First boot — run the CLI installer (no HTTP timeout risk)
        echo "DB not initialised — running CLI installer..."
        php /var/www/html/installer/docker_install.php
        chown www-data:www-data "${CONF_FILE}"
        echo "CLI installer finished."
    fi

else
    echo "WARNING: ORANGEHRM_DATABASE_HOST not set — skipping setup"
fi

exec apache2-foreground
