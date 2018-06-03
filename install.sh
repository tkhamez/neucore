#!/bin/sh

# build back-end
cd backend
if [ "$1" = "prod" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction
else
    composer install
    composer compile
fi

# build front-end
cd ../frontend
npm install
if [ "$1" = "prod" ]; then
    npm run build:prod
else
    npm run build
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
