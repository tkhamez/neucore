#!/bin/sh

if [ "$1" = "prod" ]
then
    cd backend
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction

    cd ../web
    npm install
    node_modules/.bin/browserify index.src.js > index.js

    cd ../frontend
    npm install
    npm run build:prod

else
    cd backend
    composer install
    composer compile

    cd ../web
    npm install
    node_modules/.bin/browserify index.src.js > index.js

    cd ../frontend
    npm install
    npm run build
fi
