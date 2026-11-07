FROM php:8.4-apache-bookworm

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PORT=10000 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl mbstring opcache pcntl pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Cache dependency installation separately from application source changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction \
        --no-progress --no-scripts --no-autoloader

COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --chmod=755 docker/start.sh /usr/local/bin/start-watchwise

RUN printf 'Listen ${PORT}\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/server-name.conf \
    && a2enconf server-name \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && mkdir -p bootstrap/cache storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/start-watchwise"]
