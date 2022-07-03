#!/usr/bin/env bash

if [[ ! -d ../../dist || ! -d ../build ]]; then
    echo "This script must be called from the dist/build directory."
    exit 1
fi

mkdir ../neucore
mv backend ../neucore/backend
rm -f ../neucore/backend/.env
rm -r ../neucore/backend/.phan
rm -r ../neucore/backend/tests
mv doc ../neucore/doc
rm -r ../neucore/doc/screenshots
mv web ../neucore/web
mv LICENSE ../neucore/LICENSE
mv CHANGELOG.md ../neucore/CHANGELOG.md
mv README.md ../neucore/README.md
