#!/bin/bash
set -e

CONF_DIR=/var/www/html/lib/confs
CONF_FILE="${CONF_DIR}/Conf.php"

if [ -n "${ORANGEHRM_DATABASE_HOST}" ]; then
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
    echo "WARNING: ORANGEHRM_DATABASE_HOST not set — skipping Conf.php generation (installer will handle it)"
fi

exec apache2-foreground
