# Creates an image for production

FROM php:8.1-apache-bullseye

RUN apt update && apt install -y libgmp-dev libzip-dev libicu-dev
RUN docker-php-ext-install pdo_mysql opcache gmp zip intl
RUN printf "\n" | pecl install apcu && docker-php-ext-enable apcu

RUN a2enmod rewrite

COPY dist/neucore/web/ /var/www/html/
COPY dist/neucore/backend/ /var/www/backend
RUN chown www-data /var/www/backend/var/cache
RUN chown www-data /var/www/backend/var/logs
