#!/usr/bin/env bash

# build backend (PHP)
cd backend || exit
if hash composer 2>/dev/null; then
    COMPOSER_CMD=composer
else
    # for AWS Beanstalk
    COMPOSER_CMD=composer.phar
fi
if [[ $1 = prod ]]; then
    $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction
    $COMPOSER_CMD compile:prod --no-dev --no-interaction
else
    $COMPOSER_CMD install
    $COMPOSER_CMD compile
fi

# generate OpenAPI JS client (Java)
cd ../frontend || exit
./openapi.sh

# build frontend (Node.js)
cd neucore-js-client || exit
npm install
npm run build
cd .. || exit
npm install
if [[ $1 = prod ]]; then
    npm run build
fi
