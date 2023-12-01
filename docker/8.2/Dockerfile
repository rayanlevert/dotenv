FROM composer:2.6.5 AS composer
FROM php:8.2-fpm

LABEL maintainer="Rayan Levert <rayanlevert@msn.com>"

# Enabling xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Créé le dossier /app
RUN mkdir /app

# Volumes
VOLUME ["/app"]

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

CMD ["php-fpm"]