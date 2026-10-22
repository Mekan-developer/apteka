# Стадии:
#   base   — PHP, расширения, composer (общая для всех)
#   dev    — для разработки, код монтируется с хоста (docker-compose.override.yml)
#   assets — сборка фронта (нужны PHP + Node: Wayfinder вызывает artisan при vite build)
#   prod   — код, vendor без dev-пакетов и собранный фронт внутри образа
#   nginx  — nginx с public/ из prod-образа (docker-compose.prod.yml)

ARG NODE_IMAGE=node:22-bookworm-slim
ARG NGINX_IMAGE=nginx:1.27-alpine

FROM ${NODE_IMAGE} AS node

FROM php:8.4-fpm AS base

# Зеркало пакетов задаётся снаружи (docker-compose.yml / .env).
# Пусто = качать напрямую из интернета.
ARG PACKAGIST_MIRROR=""
ARG DEBIAN_MIRROR=""
ARG DEBIAN_SECURITY_MIRROR=""
ARG REDIS_VERSION=6.3.0

ENV COMPOSER_ALLOW_SUPERUSER=1

# Подменяем deb.debian.org на зеркало, если оно задано.
RUN if [ -n "$DEBIAN_MIRROR" ]; then \
        sed -i "s#^URIs: http://deb.debian.org/debian\$#URIs: ${DEBIAN_MIRROR%/}#" /etc/apt/sources.list.d/debian.sources; \
    fi \
    && if [ -n "$DEBIAN_SECURITY_MIRROR" ]; then \
        sed -i "s#^URIs: http://deb.debian.org/debian-security\$#URIs: ${DEBIAN_SECURITY_MIRROR%/}#" /etc/apt/sources.list.d/debian.sources; \
    fi

# Все расширения встроены в исходники PHP: собираем штатным docker-php-ext-install,
# без обращения к pecl.php.net (он недоступен из нашей сети).
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        zip \
        gd \
        exif \
        pcntl \
        pdo_pgsql \
        pgsql \
    && rm -rf /var/lib/apt/lists/*

# phpredis: зеркало → GitHub → PECL
COPY docker/php/install-redis.sh /usr/local/bin/install-redis
RUN sed -i 's/\r$//' /usr/local/bin/install-redis \
    && chmod +x /usr/local/bin/install-redis \
    && install-redis ${REDIS_VERSION}

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN if [ -n "$PACKAGIST_MIRROR" ]; then \
        composer config -g repos.packagist composer "$PACKAGIST_MIRROR"; \
    fi

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www/app

# ---------- dev ----------
FROM base AS dev
CMD ["php-fpm"]

# ---------- assets ----------
FROM base AS assets

ARG NPM_REGISTRY=""

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi

# Сначала только манифесты зависимостей, чтобы слои кэшировались.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
RUN composer dump-autoload --no-interaction \
    && npm run build

# ---------- prod ----------
FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /var/www/app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && mkdir -p storage/app/public storage/app/private \
        storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

USER www-data
CMD ["php-fpm"]

# ---------- nginx ----------
FROM ${NGINX_IMAGE} AS nginx

COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/app/public /var/www/app/public
