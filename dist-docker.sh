#!/usr/bin/env bash

if [[ ! -f dist-docker.sh ]]; then
    echo "The script must be called from the same directory where it is located."
    exit 1
fi

mkdir -p dist
rm -Rf dist/*

git checkout-index -a -f --prefix=./dist/build/
if [[ -f backend/.env ]]; then
    # database connection parameters are required to generate the doctrine proxy classes
    cp backend/.env dist/build/backend/.env
fi

docker-compose exec php-fpm sh -c "cd ../dist/build/backend && composer install --no-dev --optimize-autoloader --no-interaction"
docker-compose exec php-fpm sh -c "cd ../dist/build/backend && vendor/bin/doctrine orm:generate-proxies"
docker-compose exec php-fpm sh -c "cd ../dist/build/backend && composer openapi"

docker-compose run java /app/dist/build/frontend/openapi.sh
docker-compose run node sh -c "cd ../dist/build/frontend/neucore-js-client && npm install"
docker-compose run node sh -c "cd ../dist/build/frontend/neucore-js-client && npm run build"
docker-compose run node sh -c "cd ../dist/build/frontend && npm install"
docker-compose run node sh -c "cd ../dist/build/frontend && npm run build"

cd dist || exit
mkdir neucore
mv build/backend neucore/backend
rm neucore/backend/.env
rm -r neucore/backend/.phan
rm -r neucore/backend/tests
mv build/doc neucore/doc
rm -r neucore/doc/screenshots
mv build/web neucore/web
mv build/LICENSE neucore/LICENSE
mv build/CHANGELOG.md neucore/CHANGELOG.md
mv build/README.md neucore/README.md

if [[ "$1" ]]; then
    NAME=$1
else
    NAME=$(git rev-parse --short HEAD)
fi
tar -czf neucore-"${NAME}".tar.gz neucore
sha256sum neucore-"${NAME}".tar.gz > neucore-"${NAME}".sha256

rm -Rf build
rm -Rf neucore
