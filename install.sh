#!/usr/bin/env bash

# build backend
cd backend
if [[ $1 = prod ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction
else
    composer install
    composer compile
fi

# build frontend
cd ../frontend
./swagger.sh
npm install
if [[ $1 = prod ]]; then
    npm run build:prod
else
    npm run build
fi

# install Swagger UI
cd ../web
npm install
