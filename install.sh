#!/bin/sh

# build backend
cd backend
if [ "$1" = "prod" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction
else
    composer install
    composer compile
fi

# build frontend2
cd ../frontend2
./swagger.sh
npm install
if [ "$1" = "prod" ]; then
    npm run build:prod
else
    npm run build
fi

# install Swagger UI
cd ../web
npm install
