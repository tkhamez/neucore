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
