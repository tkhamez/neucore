#!/usr/bin/env bash

# generate the Swagger client

VERSION=3.3.4
FILENAME=openapi-generator-cli-${VERSION}.jar

if [[ ! -f ${FILENAME} ]]; then
    wget http://central.maven.org/maven2/org/openapitools/openapi-generator-cli/${VERSION}/${FILENAME} \
        -O ${FILENAME}
fi

rm -Rf neucore-js-client/*

java -jar ${FILENAME} generate \
    -c neucore-js-client-config.json \
    -i ../web/frontend-api.json \
    -g javascript \
    -o neucore-js-client
