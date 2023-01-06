#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

mkdir "${DIR}"/../../neucore
mv "${DIR}"/../backend "${DIR}"/../../neucore/backend
rm -r "${DIR}"/../../neucore/backend/.phan
rm -r "${DIR}"/../../neucore/backend/var/cache/.gitkeep
rm -r "${DIR}"/../../neucore/backend/var/logs/.gitkeep
rm -f "${DIR}"/../../neucore/backend/.env
rm -f "${DIR}"/../../neucore/backend/.gitignore
rm -f "${DIR}"/../../neucore/backend/composer.json
rm -f "${DIR}"/../../neucore/backend/composer.lock
rm -f "${DIR}"/../../neucore/backend/phpstan.neon
rm -f "${DIR}"/../../neucore/backend/phpunit.xml
rm -f "${DIR}"/../../neucore/backend/psalm.xml
rm -f "${DIR}"/../../neucore/backend/README.md
rm -r "${DIR}"/../../neucore/backend/tests
rm -r "${DIR}"/../../neucore/backend/var/xdebug
mv "${DIR}"/../doc "${DIR}"/../../neucore/doc
rm -r "${DIR}"/../../neucore/doc/api-examples/.gitignore
rm -r "${DIR}"/../../neucore/doc/screenshots
rm -r "${DIR}"/../../neucore/doc/API.md.tpl
rm -r "${DIR}"/../../neucore/doc/er-model.mwb
mv "${DIR}"/../web "${DIR}"/../../neucore/web
rm -f "${DIR}"/../../neucore/web/.gitignore
rm -f "${DIR}"/../../neucore/web/plugin/.gitignore
mv "${DIR}"/../CHANGELOG.md "${DIR}"/../../neucore/CHANGELOG.md
mv "${DIR}"/../LICENSE "${DIR}"/../../neucore/LICENSE
mv "${DIR}"/../README.md "${DIR}"/../../neucore/README.md
