FROM composer:2 AS vendor

WORKDIR /app

RUN docker-php-ext-install ftp

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan ./

RUN composer dump-autoload --optimize

FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.ts tsconfig.json eslint.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm ci && npm run build

FROM php:8.3-fpm-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        curl \
        icu-dev \
        libzip-dev \
        nginx \
        oniguruma-dev \
    && docker-php-ext-install \
        ftp \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/cache/apk/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache /run/nginx

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["web"]
