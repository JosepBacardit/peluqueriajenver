FROM php:8.3-fpm-alpine

# No GD/Imagick extension: nothing under app/ or resources/ touches image
# processing at runtime (WebP generation happens outside PHP, in
# optimize-images.sh and the Vite build). Add it back only if that changes.
RUN apk add --no-cache \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        sqlite-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql pdo_sqlite bcmath intl zip \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Local-only OPcache tuning; see the file for why.
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-local-opcache.ini

WORKDIR /var/www/html
