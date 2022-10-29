# Creates an image for production


FROM php:8.1-apache-bullseye AS build

COPY dist/neucore-*.tar.gz /var/www/neucore.tar.gz
RUN tar -xf /var/www/neucore.tar.gz -C /var/www


FROM php:8.1-apache-bullseye

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
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

RUN a2enmod rewrite headers
RUN echo 'Header always set Strict-Transport-Security "max-age=31536000"' > /etc/apache2/conf-enabled/neucore.conf && \
    echo "Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' data:; font-src 'self' data:; img-src 'self' data: https://images.evetech.net; connect-src 'self' https://esi.evetech.net;\"" >> /etc/apache2/conf-enabled/neucore.conf && \
    echo 'Header always set X-Frame-Options "sameorigin"'                >> /etc/apache2/conf-enabled/neucore.conf && \
    echo 'Header always set X-Content-Type-Options "nosniff"'            >> /etc/apache2/conf-enabled/neucore.conf

COPY --from=build /var/www/neucore/web /var/www/html
COPY --from=build /var/www/neucore/backend /var/www/backend
RUN chown www-data /var/www/backend/var/cache && \
    chown www-data /var/www/backend/var/logs
