#!/bin/bash
set -e

CONF_DIR=/var/www/html/lib/confs
CONF_FILE="${CONF_DIR}/Conf.php"

if [ -n "${DB_HOST}" ]; then
    php -r "
        \$template = file_get_contents('/var/www/html/installer/config/Conf.tpl.php');
        \$search  = ['{{dbHost}}', '{{dbPort}}', '{{dbName}}', '{{dbUser}}', '{{dbPass}}'];
        \$replace = [
            getenv('DB_HOST'),
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASS'),
        ];
        file_put_contents('${CONF_FILE}', str_replace(\$search, \$replace, \$template));
        echo 'Generated Conf.php for ' . getenv('DB_HOST') . PHP_EOL;
    "
    chown www-data:www-data "${CONF_FILE}"
else
    echo "WARNING: DB_HOST not set — skipping Conf.php generation (installer will handle it)"
fi

exec apache2-foreground
