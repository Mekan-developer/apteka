#!/bin/sh
# Устанавливает phpredis, перебирая источники по очереди:
#   1) зеркало Packagist (Nexus), если задан PACKAGIST_MIRROR
#   2) GitHub
#   3) PECL (последний шанс)
# Использование: install-redis.sh 6.3.0
set -eu

VERSION="${1:-6.3.0}"
TMP=/tmp/phpredis
ZIP="$TMP/src.zip"
mkdir -p "$TMP"

try_download() {
    echo ">>> Пробую: $1"
    curl -fsSL --connect-timeout 10 --retry 2 -o "$ZIP" "$1"
}

downloaded=0

if [ -n "${PACKAGIST_MIRROR:-}" ]; then
    MIRROR="${PACKAGIST_MIRROR%/}"
    if try_download "$MIRROR/phpredis/phpredis/$VERSION/phpredis-phpredis-$VERSION.zip"; then
        downloaded=1
    fi
fi

if [ "$downloaded" -eq 0 ]; then
    if try_download "https://github.com/phpredis/phpredis/archive/refs/tags/$VERSION.zip"; then
        downloaded=1
    fi
fi

if [ "$downloaded" -eq 1 ]; then
    docker-php-source extract
    mkdir -p "$TMP/src" /usr/src/php/ext/redis
    unzip -q "$ZIP" -d "$TMP/src"
    mv "$TMP"/src/*/* /usr/src/php/ext/redis/
    docker-php-ext-install redis
    docker-php-source delete
else
    echo ">>> Зеркало и GitHub недоступны, пробую PECL"
    pecl install "redis-$VERSION"
    docker-php-ext-enable redis
fi

rm -rf "$TMP"
php -m | grep -q '^redis$' && echo ">>> phpredis $VERSION установлен"