FROM composer:2 AS composer-bin

FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql opcache \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --no-interaction --optimize
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/artsci-entrypoint
RUN chmod +x /usr/local/bin/artsci-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_NAME="ARTSCI Survey Platform" APP_ENV=production APP_DEBUG=false
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/artsci-entrypoint"]
