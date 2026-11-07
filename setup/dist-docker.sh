#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

dist_clean() {
    docker compose exec neucore_php sh -c "rm -Rf ../dist/build/backend"
    docker compose exec neucore_node sh -c "rm -Rf ../dist/build/frontend"
    docker compose exec neucore_node sh -c "rm -Rf ../dist/build/web"
    rm -Rf "${DIR}"/../dist/build
    rm -Rf "${DIR}"/../dist/neucore
}

mkdir -p "${DIR}"/../dist
dist_clean
rm -f "${DIR}"/../dist/neucore-*.sha256
rm -f "${DIR}"/../dist/neucore-*.tar.gz

git checkout-index -a -f --prefix="${DIR}"/../dist/build/

# A minimum configuration is required to generate the doctrine proxy classes
echo "NEUCORE_APP_ENV=prod" > "${DIR}"/../dist/build/backend/.env
echo "NEUCORE_DATABASE_URL=mysql://user:@127.0.0.1/db?serverVersion=8.0.22" >> "${DIR}"/../dist/build/backend/.env

# Backend
cd "${DIR}"/.. || exit
docker compose exec neucore_php sh -c "cd ../dist/build/backend && composer install --no-dev --optimize-autoloader --no-interaction"
docker compose exec neucore_php sh -c "cd ../dist/build/backend && bin/doctrine orm:generate-proxies"
docker compose exec neucore_php sh -c "cd ../dist/build/backend && composer openapi"

# OpenAPI JS client
cd "${DIR}"/.. || exit
docker compose exec neucore_node /app/dist/build/frontend/openapi.sh
docker compose exec neucore_node sh -c "cd ../dist/build/frontend/neucore-js-client && npm install --ignore-scripts"
docker compose exec neucore_node sh -c "cd ../dist/build/frontend/neucore-js-client && npm run build"

# Frontend
cd "${DIR}"/.. || exit
docker compose exec neucore_node sh -c "cd ../dist/build/frontend && npm ci"
docker compose exec neucore_node sh -c "cd ../dist/build/frontend && npm run postinstall"
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

dist_clean
