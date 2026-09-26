# Creates an image for production


FROM php:8.5-apache-trixie AS build

COPY neucore-*.tar.gz /var/www/neucore.tar.gz
RUN tar -xf /var/www/neucore.tar.gz -C /var/www


FROM php:8.5-apache-trixie

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
            ssl-cert libzip5 libzip-dev libgmp-dev libicu76 libicu-dev \
            libmemcached11t64 libmemcached-dev && \
    docker-php-ext-install pdo_mysql bcmath gmp zip mysqli intl && \
    pecl channel-update pecl.php.net && \
    printf "\n" | pecl install apcu && \
    printf "\n" | pecl install memcached && \
    docker-php-ext-enable apcu && \
    docker-php-ext-enable memcached && \
    apt-get remove --purge -y libgmp-dev libzip-dev libicu-dev libmemcached-dev && \
    apt-get autoremove --purge -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" # /usr/local/etc/php
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/x-neucore.ini"

RUN a2enmod rewrite headers ssl socache_shmcb && \
    cp /etc/ssl/certs/ssl-cert-snakeoil.pem /etc/ssl/certs/neucore.pem && \
    cp /etc/ssl/private/ssl-cert-snakeoil.key /etc/ssl/private/neucore.key && \
    sed -i '/SSLCertificateFile.*snakeoil\.pem/c\SSLCertificateFile \/etc\/ssl\/certs\/neucore.pem' /etc/apache2/sites-available/default-ssl.conf &&  \
    sed -i '/SSLCertificateKeyFile.*snakeoil\.key/cSSLCertificateKeyFile /etc/ssl/private/neucore.key\' /etc/apache2/sites-available/default-ssl.conf && \
    a2ensite default-ssl && \
    echo 'Header always set Strict-Transport-Security "max-age=31536000"' > /etc/apache2/conf-enabled/x-neucore.conf && \
    echo "Header always set Content-Security-Policy \"default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self' data:; img-src 'self' data: https://images.evetech.net; connect-src 'self' https://esi.evetech.net; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; sandbox allow-downloads allow-forms allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts allow-top-navigation;\"" >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set X-Frame-Options "sameorigin"'                >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set X-Content-Type-Options "nosniff"'            >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set Referrer-Policy "same-origin"'               >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set Cross-Origin-Embedder-Policy "credentialless"'    >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set Cross-Origin-Opener-Policy "same-origin"'    >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set Cross-Origin-Resource-Policy "same-origin"'  >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Header always set Permissions-Policy: "accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), cross-origin-isolated=(), display-capture=(), document-domain=(), encrypted-media=(), execution-while-not-rendered=(), execution-while-out-of-viewport=(), fullscreen=(), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), navigation-override=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), web-share=(), xr-spatial-tracking=(), clipboard-read=(), clipboard-write=(self), gamepad=(), speaker-selection=(), conversion-measurement=(), focus-without-user-activation=(), hid=(), idle-detection=(), interest-cohort=(), serial=(), sync-script=(), trust-token-redemption=(), unload=(), window-placement=(), vertical-scroll=()"' >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'ServerSignature Off'                                           >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'ServerTokens Prod'                                             >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'TraceEnable off'                                               >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'Protocols h2 http/1.1'                                         >> /etc/apache2/conf-enabled/x-neucore.conf && \
    echo 'AddOutputFilterByType DEFLATE text/plain text/html application/json font/ttf image/svg+xml image/x-icon' >> /etc/apache2/conf-enabled/x-neucore.conf

RUN ln -s /var/www/html /var/www/web
COPY --from=build /var/www/neucore/web /var/www/web
COPY --from=build /var/www/neucore/backend /var/www/backend
COPY --from=build /var/www/neucore/setup /var/www/setup
RUN chown www-data /var/www/backend/var/cache && \
    chown www-data /var/www/backend/var/logs
