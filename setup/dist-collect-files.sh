#!/usr/bin/env bash

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

mkdir "${DIR}"/../../neucore

mkdir "${DIR}"/../../neucore/backend
mv    "${DIR}"/../backend/bin           "${DIR}"/../../neucore/backend/bin
mv    "${DIR}"/../backend/config        "${DIR}"/../../neucore/backend/config
mv    "${DIR}"/../backend/src           "${DIR}"/../../neucore/backend/src
mv    "${DIR}"/../backend/var           "${DIR}"/../../neucore/backend/var
mv    "${DIR}"/../backend/vendor        "${DIR}"/../../neucore/backend/vendor
mv    "${DIR}"/../backend/.env.dist     "${DIR}"/../../neucore/backend/.env.dist
mv    "${DIR}"/../backend/composer.json "${DIR}"/../../neucore/backend/composer.json
mv    "${DIR}"/../backend/composer.lock "${DIR}"/../../neucore/backend/composer.lock
rm -r "${DIR}"/../../neucore/backend/var/xdebug
rm    "${DIR}"/../../neucore/backend/var/cache/.gitkeep
rm    "${DIR}"/../../neucore/backend/var/logs/.gitkeep

mv    "${DIR}"/../doc "${DIR}"/../../neucore/doc
rm -r "${DIR}"/../../neucore/doc/screenshots
rm    "${DIR}"/../../neucore/doc/api-examples/php/.gitignore
rm    "${DIR}"/../../neucore/doc/api-examples/python/.gitignore
rm    "${DIR}"/../../neucore/doc/API.tpl.md
rm    "${DIR}"/../../neucore/doc/er-model.mwb

mkdir "${DIR}"/../../neucore/setup
mv    "${DIR}"/../setup/docker-nginx.conf "${DIR}"/../../neucore/setup/docker-nginx.conf
mv    "${DIR}"/../setup/logo.svg          "${DIR}"/../../neucore/setup/logo.svg
mv    "${DIR}"/../setup/logo-small.svg    "${DIR}"/../../neucore/setup/logo-small.svg

mv "${DIR}"/../web "${DIR}"/../../neucore/web
rm "${DIR}"/../../neucore/web/.gitignore

mv "${DIR}"/../CHANGELOG.md "${DIR}"/../../neucore/CHANGELOG.md
mv "${DIR}"/../LICENSE      "${DIR}"/../../neucore/LICENSE
mv "${DIR}"/../README.md    "${DIR}"/../../neucore/README.md
