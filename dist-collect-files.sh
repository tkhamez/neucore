#!/usr/bin/env bash

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
