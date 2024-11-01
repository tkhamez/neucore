#!/usr/bin/env sh

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

# generate the OpenAPI client

VERSION=7.9.0
FILENAME=openapi-generator-cli-${VERSION}.jar

if [ ! -f "${DIR}"/${FILENAME} ]; then
    wget https://repo1.maven.org/maven2/org/openapitools/openapi-generator-cli/${VERSION}/${FILENAME} \
        -O "${DIR}"/${FILENAME}
fi

rm -Rf "${DIR}"/neucore-js-client/*

java -jar "${DIR}"/${FILENAME} generate \
    -c "${DIR}"/neucore-js-client-config.json \
    -i "${DIR}"/../web/frontend-api-3.yml \
    -g javascript \
    -o "${DIR}"/neucore-js-client
