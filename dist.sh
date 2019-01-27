#!/usr/bin/env bash

cd $(dirname "$(realpath "$0")")

mkdir -p dist
rm -Rf dist/*

git checkout-index -a -f --prefix=./dist/build/

cd dist/build/backend
composer install --no-dev --optimize-autoloader --no-interaction
cp ../../../backend/.env .env
vendor/bin/doctrine orm:generate-proxies
composer openapi

cd ../frontend
./swagger.sh
npm install
npm run build:prod

cd ../web
npm install

cd ../..
mkdir brvneucore
cp -R build/backend brvneucore/backend
cp -R build/doc brvneucore/doc
cp -R build/web brvneucore/web
cp build/LICENSE brvneucore/LICENSE
cp build/CHANGELOG.md brvneucore/CHANGELOG.md
cp build/README.md brvneucore/README.md

COMMIT=`git rev-parse --short HEAD`
DATE=`date '+%Y%m%d'`
tar -czf brvneucore-${COMMIT}-${DATE}.tar.gz brvneucore

rm -Rf build
rm -Rf brvneucore
