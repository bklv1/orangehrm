#!/bin/bash
set -e

CONF_DIR=/var/www/html/lib/confs
CONF_FILE="${CONF_DIR}/Conf.php"

if [ -n "${ORANGEHRM_DATABASE_HOST}" ]; then

    # A COMPLETE install has ohrm_user with at least one row (admin created).
    # hs_hr_config alone is not enough — it appears early in migrations and
    # can exist in a broken partial state.
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
            \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'ohrm_user'\");
            if ((int)\$stmt->fetchColumn() === 0) { echo 'no'; exit; }
            \$count = (int)\$pdo->query('SELECT COUNT(*) FROM ohrm_user')->fetchColumn();
            echo \$count > 0 ? 'yes' : 'no';
        } catch (Exception \$e) {
            echo 'no';
        }
    ")

    if [ "${DB_READY}" = "yes" ]; then
        echo "DB fully initialised — regenerating Conf.php"
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
        echo "DB not fully initialised — running CLI installer..."
        php /var/www/html/installer/docker_install.php
        chown www-data:www-data "${CONF_FILE}"
        echo "CLI installer finished."
    fi

else
    echo "WARNING: ORANGEHRM_DATABASE_HOST not set — skipping setup"
fi

exec apache2-foreground
