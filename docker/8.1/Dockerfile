FROM composer:latest as composer
FROM php:8.1-fpm

LABEL maintainer="Rayan Levert <rayanlevert@msn.com>"

RUN apt update -y && \
    apt install -y \
    apt-utils \
    gettext \
    libpq-dev \
    libzip-dev \
    nano \
    sudo \
    wget

# Enabling xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Créé le dossier /app
RUN mkdir /app

# Volumes
VOLUME ["/app"]

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

CMD ["php-fpm"]