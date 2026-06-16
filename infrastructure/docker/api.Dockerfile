FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip \
    postgresql-dev \
    zlib-dev \
    curl

RUN docker-php-ext-install pdo pdo_pgsql intl zip opcache pcntl

RUN curl -sS https://getcomposer.org/installer -o /tmp/composer-installer.php \
    && curl -sS https://composer.github.io/installer.sig -o /tmp/composer-installer.sig \
    && php -r "if (hash_file('sha384', '/tmp/composer-installer.php') !== trim(file_get_contents('/tmp/composer-installer.sig'))) { echo 'Composer installer checksum failed' . PHP_EOL; unlink('/tmp/composer-installer.php'); unlink('/tmp/composer-installer.sig'); exit(1); }" \
    && php /tmp/composer-installer.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-installer.php /tmp/composer-installer.sig

RUN addgroup -g 1000 appuser && adduser -u 1000 -G appuser -s /bin/sh -D appuser

WORKDIR /var/www/html

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

COPY apps/api ./

RUN cp .env.example .env
RUN composer run-script post-autoload-dump
RUN php artisan key:generate --ansi || true

RUN chown -R appuser:appuser /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

USER appuser

EXPOSE 8890

CMD ["sh", "-c", "php artisan migrate:fresh --seed && cd public && php -S 0.0.0.0:8890 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
