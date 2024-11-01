#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

# Install backend, run database migrations and generate OpenAPI files.
cd "${DIR}"/../backend || exit
if [[ $1 = prod ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction
else
    composer install
    composer compile
fi

# Generate and build OpenAPI JavaScript client
cd "${DIR}"/../frontend && ./openapi.sh
cd "${DIR}"/../frontend/neucore-js-client || exit
npm install
npm run build

# Build frontend
cd "${DIR}"/../frontend || exit
npm i file:neucore-js-client
npm ci
if [[ $1 = prod ]]; then
    npm run build
fi
