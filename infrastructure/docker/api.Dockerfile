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

RUN docker-php-ext-install pdo pdo_pgsql intl zip opcache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

COPY apps/api ./

RUN cp .env.example .env
RUN php artisan key:generate --ansi || true

EXPOSE 8890

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8890"]
