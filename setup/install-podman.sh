#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

# Install backend, run database migrations and generate OpenAPI files.
cd "${DIR}"/.. || exit
if [[ $1 = prod ]]; then
    podman compose exec neucore_php composer install --no-dev --optimize-autoloader --no-interaction
    podman compose exec neucore_php composer compile:prod --no-dev --no-interaction
else
    podman compose exec neucore_php composer install
    podman compose exec neucore_php composer compile
fi

# Generate and build OpenAPI JavaScript client
podman compose run neucore_java /app/frontend/openapi.sh
podman compose exec neucore_node npm install --prefix /app/frontend/neucore-js-client
podman compose exec neucore_node npm run build --prefix /app/frontend/neucore-js-client

# Build frontend
podman compose exec neucore_node npm i file:neucore-js-client
podman compose exec neucore_node npm ci
if [[ $1 = prod ]]; then
    podman compose exec neucore_node npm run build
fi

# Create database for unit tests
podman exec neucore_dev_db sh -c 'ln -s /usr/bin/mariadb /usr/local/bin/mysql' # MariaDB 11 does not have "mysql"
podman exec neucore_dev_db sh -c 'mysql -e "CREATE DATABASE IF NOT EXISTS neucore_test" -uroot -pneucore'
podman exec neucore_dev_db sh -c 'mysql -e "GRANT ALL PRIVILEGES ON neucore_test.* TO neucore@\"%\";" -uroot -pneucore'
