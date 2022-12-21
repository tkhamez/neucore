#!/usr/bin/env bash

if [[ ! -f dist.sh ]]; then
    echo "The script must be called from the same directory where it is located."
    exit 1
fi

mkdir -p dist
rm -Rf dist/*

git checkout-index -a -f --prefix=./dist/build/

# A minimum configuration is required to generate the doctrine proxy classes
echo "NEUCORE_APP_ENV=prod"                                                          > dist/build/backend/.env
echo "NEUCORE_DATABASE_URL=mysql://user:@127.0.0.1/db?serverVersion=mariadb-10.2.7" >> dist/build/backend/.env

# Backend
cd dist/build/backend || exit
composer install --no-dev --optimize-autoloader --no-interaction
bin/doctrine orm:generate-proxies
composer openapi

# OpenAPI JS client
cd ../frontend && ./openapi.sh
cd neucore-js-client || exit
npm install
npm run build

# Frontend
cd .. || exit;
npm install
npm run build

# Collect files and create archive
cd .. || exit
./dist-collect-files.sh
if [[ "$1" ]]; then
    NAME=$1
else
    NAME=$(git rev-parse --short HEAD)
fi
cd .. || exit
tar -czf neucore-"${NAME}".tar.gz neucore
sha256sum neucore-"${NAME}".tar.gz > neucore-"${NAME}".sha256

rm -Rf build
rm -Rf neucore
