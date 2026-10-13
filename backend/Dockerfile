FROM composer:2 AS composer
FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev libssl-dev libcurl4-openssl-dev libbrotli-dev zlib1g-dev $PHPIZE_DEPS && docker-php-ext-install pdo_pgsql mbstring zip pcntl && pecl install swoole redis && docker-php-ext-enable swoole redis && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/www
COPY composer.* ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts
COPY . .
RUN mkdir -p runtime && composer dump-autoload --optimize && php -r "foreach (['swoole', 'pdo_pgsql', 'redis', 'fileinfo'] as \$e) { if (!extension_loaded(\$e)) exit(1); }"
EXPOSE 9501
ENTRYPOINT ["sh", "bin/entrypoint.sh"]
