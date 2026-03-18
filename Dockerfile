FROM php:8.3-apache-bookworm

ENV OHRM_VERSION=5.8
ENV OHRM_MD5=32c08e6733430414a5774f9fefb71902

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    libldap2-dev \
    libicu-dev \
    unzip \
    curl \
    default-mysql-server \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap \
       --with-libdir=lib/$(uname -m)-linux-gnu/ \
    && docker-php-ext-install -j "$(nproc)" \
       gd opcache intl pdo_mysql zip ldap \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/cache/apt /var/lib/apt/lists/*

RUN curl -fSL -o /tmp/orangehrm.zip \
    "https://sourceforge.net/projects/orangehrm/files/stable/${OHRM_VERSION}/orangehrm-${OHRM_VERSION}.zip" \
    && echo "${OHRM_MD5} /tmp/orangehrm.zip" | md5sum -c - \
    && unzip -q /tmp/orangehrm.zip -d /tmp/ \
    && rm -rf /var/www/html \
    && mv /tmp/orangehrm-${OHRM_VERSION} /var/www/html \
    && rm -f /tmp/orangehrm.zip

COPY src/ /var/www/html/src/

RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN a2enmod rewrite

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 \
       /var/www/html/lib/confs \
       /var/www/html/src/cache \
       /var/www/html/src/log \
       /var/www/html/src/config

COPY docker-entrypoint-custom.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint-custom.sh

EXPOSE 80

CMD ["/usr/local/bin/docker-entrypoint-custom.sh"]