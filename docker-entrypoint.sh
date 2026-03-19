#!/bin/bash
set -e

CONF_DIR=/var/www/html/lib/confs
CONF_FILE="${CONF_DIR}/Conf.php"

if [ -n "${ORANGEHRM_DATABASE_HOST}" ]; then
    # Check whether OrangeHRM tables already exist in the DB.
    # Only generate Conf.php when the DB is initialised — otherwise
    # the app crashes on an empty schema.  The web installer will
    # create Conf.php itself the first time around.
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
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
            \$stmt = \$pdo->query(\"SHOW TABLES LIKE 'hs_hr_config'\");
            echo \$stmt->rowCount() > 0 ? 'yes' : 'no';
        } catch (Exception \$e) {
            echo 'no';
        }
    ")

    if [ "${DB_READY}" = "yes" ]; then
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
            echo 'Generated Conf.php for ' . getenv('ORANGEHRM_DATABASE_HOST') . PHP_EOL;
        "
        chown www-data:www-data "${CONF_FILE}"
    else
        echo "DB not yet initialised — skipping Conf.php generation (run the web installer)"
    fi
else
    echo "WARNING: ORANGEHRM_DATABASE_HOST not set — skipping Conf.php generation"
fi

exec apache2-foreground
