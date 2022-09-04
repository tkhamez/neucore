# Creates an image for production

FROM php:8.1-apache-bullseye

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install apt-utils && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends libgmp-dev libzip4 libzip-dev libicu-dev && \
    docker-php-ext-install pdo_mysql bcmath gmp zip intl opcache mysqli && \
    apt-get remove --purge -y libgmp-dev libzip-dev libicu-dev && \
    apt-get autoremove --purge -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
RUN pecl channel-update pecl.php.net &&  \
    printf "\n" | pecl install apcu &&  \
    docker-php-ext-enable apcu

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN a2enmod rewrite

COPY dist/neucore/web/ /var/www/html/
COPY dist/neucore/backend/ /var/www/backend
RUN chown www-data /var/www/backend/var/cache
RUN chown www-data /var/www/backend/var/logs
