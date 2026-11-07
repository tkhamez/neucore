#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

mkdir "${DIR}"/../../neucore

mkdir "${DIR}"/../../neucore/backend
cp -r "${DIR}"/../backend/bin           "${DIR}"/../../neucore/backend/bin
cp -r "${DIR}"/../backend/config        "${DIR}"/../../neucore/backend/config
cp -r "${DIR}"/../backend/src           "${DIR}"/../../neucore/backend/src
cp -r "${DIR}"/../backend/var           "${DIR}"/../../neucore/backend/var
cp -r "${DIR}"/../backend/vendor        "${DIR}"/../../neucore/backend/vendor
cp    "${DIR}"/../backend/.env.dist     "${DIR}"/../../neucore/backend/.env.dist
cp    "${DIR}"/../backend/composer.json "${DIR}"/../../neucore/backend/composer.json
cp    "${DIR}"/../backend/composer.lock "${DIR}"/../../neucore/backend/composer.lock
rm -r "${DIR}"/../../neucore/backend/var/xdebug
rm    "${DIR}"/../../neucore/backend/var/cache/.gitkeep
rm    "${DIR}"/../../neucore/backend/var/logs/.gitkeep

cp -r "${DIR}"/../doc "${DIR}"/../../neucore/doc
rm -r "${DIR}"/../../neucore/doc/screenshots
rm    "${DIR}"/../../neucore/doc/api-examples/php/.gitignore
rm    "${DIR}"/../../neucore/doc/API.tpl.md
rm    "${DIR}"/../../neucore/doc/er-model.mwb

mkdir "${DIR}"/../../neucore/setup
cp    "${DIR}"/../setup/docker-nginx.conf "${DIR}"/../../neucore/setup/docker-nginx.conf
cp    "${DIR}"/../setup/logo.svg          "${DIR}"/../../neucore/setup/logo.svg
cp    "${DIR}"/../setup/logo-small.svg    "${DIR}"/../../neucore/setup/logo-small.svg

cp -r "${DIR}"/../web "${DIR}"/../../neucore/web
rm    "${DIR}"/../../neucore/web/.gitignore

cp "${DIR}"/../CHANGELOG.md "${DIR}"/../../neucore/CHANGELOG.md
cp "${DIR}"/../LICENSE      "${DIR}"/../../neucore/LICENSE
cp "${DIR}"/../README.md    "${DIR}"/../../neucore/README.md
