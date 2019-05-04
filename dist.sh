#!/usr/bin/env bash

if [[ ! -f dist.sh ]]; then
    echo "The script must be called from the same directory where it is located."
    exit 1
fi

mkdir -p dist
rm -Rf dist/*

git checkout-index -a -f --prefix=./dist/build/
if [[ -f backend/.env ]]; then
    cp backend/.env dist/build/backend/.env
fi

cd dist/build/backend
composer install --no-dev --optimize-autoloader --no-interaction
vendor/bin/doctrine orm:generate-proxies
composer openapi

cd ../frontend
./openapi.sh
npm install
npm run build:prod

cd ../web
npm install

cd ../..
mkdir neucore
cp -R build/backend neucore/backend
rm -r neucore/backend/src/tests
cp -R build/doc neucore/doc
cp -R build/web neucore/web
cp build/LICENSE neucore/LICENSE
cp build/CHANGELOG.md neucore/CHANGELOG.md
cp build/README.md neucore/README.md

if [[ "$1" ]]; then
    NAME=$1
else
    NAME=$(git rev-parse --short HEAD)
fi
tar -czf neucore-${NAME}.tar.gz neucore
sha256sum neucore-${NAME}.tar.gz > neucore-${NAME}.sha256

rm -Rf build
rm -Rf neucore
