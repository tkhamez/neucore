#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

mkdir -p "${DIR}"/../dist
rm -Rf "${DIR}"/../dist/*

git checkout-index -a -f --prefix="${DIR}"/../dist/build/

# A minimum configuration is required to generate the doctrine proxy classes
echo "NEUCORE_APP_ENV=prod" > "${DIR}"/../dist/build/backend/.env
echo "NEUCORE_DATABASE_URL=mysql://user:@127.0.0.1/db?serverVersion=10.5.22-MariaDB-1:10.5.22+maria~ubu2004" >> "${DIR}"/../dist/build/backend/.env

# Backend
cd "${DIR}"/.. || exit
docker compose exec neucore_php sh -c "cd ../dist/build/backend && composer install --no-dev --optimize-autoloader --no-interaction"
docker compose exec neucore_php sh -c "cd ../dist/build/backend && bin/doctrine orm:generate-proxies"
docker compose exec neucore_php sh -c "cd ../dist/build/backend && composer openapi"

# OpenAPI JS client
cd "${DIR}"/.. || exit
docker compose run neucore_java /app/dist/build/frontend/openapi.sh
docker compose exec neucore_node sh -c "cd ../dist/build/frontend/neucore-js-client && npm install"
docker compose exec neucore_node sh -c "cd ../dist/build/frontend/neucore-js-client && npm run build"

# Frontend
cd "${DIR}"/.. || exit
docker compose exec neucore_node sh -c "cd ../dist/build/frontend && npm install"
docker compose exec neucore_node sh -c "cd ../dist/build/frontend && npm run build"

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
