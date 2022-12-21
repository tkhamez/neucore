#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

mkdir -p "${DIR}"/../dist
rm -Rf "${DIR}"/../dist/*

git checkout-index -a -f --prefix="${DIR}"/../dist/build/

# A minimum configuration is required to generate the doctrine proxy classes
echo "NEUCORE_APP_ENV=prod"                                                          > "${DIR}"/../dist/build/backend/.env
echo "NEUCORE_DATABASE_URL=mysql://user:@127.0.0.1/db?serverVersion=mariadb-10.2.7" >> "${DIR}"/../dist/build/backend/.env

# Backend
cd "${DIR}"/../dist/build/backend || exit
composer install --no-dev --optimize-autoloader --no-interaction
bin/doctrine orm:generate-proxies
composer openapi

# OpenAPI JS client
"${DIR}"/../dist/build/frontend/openapi.sh
cd "${DIR}"/../dist/build/frontend/neucore-js-client || exit
npm install
npm run build

# Frontend
cd "${DIR}"/../dist/build/frontend || exit
npm install
npm run build

# Collect files and create archive
"${DIR}"/../dist/build/setup/dist-collect-files.sh
if [[ "$1" ]]; then
    NAME=$1
else
    NAME=$(git rev-parse --short HEAD)
fi
cd "${DIR}"/../dist || exit
tar -czf neucore-"${NAME}".tar.gz neucore
sha256sum neucore-"${NAME}".tar.gz > neucore-"${NAME}".sha256

rm -Rf "${DIR}"/../dist/build
rm -Rf "${DIR}"/../dist/neucore
