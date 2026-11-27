# syntax=docker/dockerfile:1.7

############################
# 1) PHP dependencies
############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

############################
# 2) Frontend assets (Vite + Tailwind; Flux CSS comes from vendor)
############################
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor/livewire/flux ./vendor/livewire/flux
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

############################
# 3) Application runtime (PHP-FPM)
############################
FROM php:8.4-fpm-alpine AS app
WORKDIR /var/www/html

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis intl zip bcmath pcntl opcache gd exif \
    && apk add --no-cache postgresql-client fcgi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-mtc.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-mtc.conf
COPY docker/php/entrypoint.sh /usr/local/bin/mtc-entrypoint
RUN chmod +x /usr/local/bin/mtc-entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN rm -rf node_modules tests/.phpunit.cache storage/logs/*.log \
    && mkdir -p storage/framework/{cache,sessions,views} storage/app/private storage/app/public bootstrap/cache && ln -sfn ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
ENTRYPOINT ["mtc-entrypoint"]
CMD ["php-fpm"]

############################
# 4) Web server (static files + FastCGI to app)
############################
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/maintenance /usr/share/nginx/maintenance
COPY --from=app /var/www/html/public /var/www/html/public
