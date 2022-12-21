#!/usr/bin/env bash

# Install backend, run database migrations and generate OpenAPI files.
if [[ $1 = prod ]]; then
    docker-compose exec neucore_php composer install --no-dev --optimize-autoloader --no-interaction
    docker-compose exec neucore_php composer compile:prod --no-dev --no-interaction
else
    docker-compose exec neucore_php composer install
    docker-compose exec neucore_php composer compile
fi


# Generate and build OpenAPI JavaScript client
docker-compose run neucore_java /app/frontend/openapi.sh
docker-compose run neucore_node npm install --prefix /app/frontend/neucore-js-client
docker-compose run neucore_node npm run build --prefix /app/frontend/neucore-js-client


# Build frontend
docker-compose run neucore_node npm install
if [[ $1 = prod ]]; then
    docker-compose run neucore_node npm run build
fi

# Create database for unit tests
docker exec neucore_dev_db sh -c 'mysql -e "CREATE DATABASE IF NOT EXISTS neucore_test" -uroot -pneucore'
docker exec neucore_dev_db sh -c 'mysql -e "GRANT ALL PRIVILEGES ON neucore_test.* TO neucore@\"%\" IDENTIFIED BY \"neucore\"" -uroot -pneucore'
