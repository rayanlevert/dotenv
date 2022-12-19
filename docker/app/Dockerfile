FROM buildpack-deps:bullseye
LABEL maintainer="Rayan Levert <rlevert@group-dis.com>"

# ENV
ENV DEBIAN_FRONTEND noninteractive
ENV INITRD No
ENV TERM xterm

ENV PHP_UPLOAD_MAX_FILESIZE 10M
ENV PHP_POST_MAX_SIZE 10M

ENV TIMEZONE Europe/Paris

COPY apt_sources.list /etc/apt/sources.list

RUN echo "Acquire::Check-Valid-Until \"false\";" > /etc/apt/apt.conf.d/100disablechecks

RUN apt-get -y update && \
    apt-get -y install curl apt-transport-https lsb-release ca-certificates debian-keyring debian-archive-keyring && \
    curl -sLf https://packages.sury.org/php/apt.gpg | gpg --dearmor > /etc/apt/trusted.gpg.d/php.gpg && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list && \
    apt-get -y update && \
    apt-get -y install locales && \
    echo 'fr_FR.UTF-8 UTF-8' >> /etc/locale.gen && locale-gen

ENV LC_ALL fr_FR.UTF-8
ENV LANG fr_FR.UTF-8
ENV LANGUAGE fr_FR.UTF-8

# Timezone
RUN echo $TIMEZONE > /etc/timezone && dpkg-reconfigure tzdata

# Install, update et nettoie les packets needed pour minimiser au mieux la taille de l'image
RUN apt-get install -f --no-install-recommends -y --allow-downgrades --allow-remove-essential --allow-change-held-packages \
    vim sudo less pwgen zip unzip bzip2 nano \
    debconf-utils \
    apache2 libapache2-mod-php8.1 \
    php8.1-intl \
    php8.1-mbstring \
    php8.1-psr \
    php8.1-xdebug \
    php8.1-xml \
    php8.1-curl \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && a2enmod php8.1 \
    && a2enmod rewrite \
    && a2enmod headers \
    && a2enmod ssl

# Install Composer et créé le dossier /app
RUN cd /tmp \
    && curl -sS https://getcomposer.org/installer | php \
    && mv /tmp/composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    && mkdir /app

# Volumes
EXPOSE 80
VOLUME  ["/app"]

COPY entrypoint.sh /sbin/entrypoint.sh
RUN chmod 755 /sbin/entrypoint.sh

# Start Apache par défaut
CMD ["/sbin/entrypoint.sh"]