#!/usr/bin/env bash

# generate the Swagger client

VERSION=2.4.0

if [[ ! -f swagger-codegen-cli-${VERSION}.jar ]]; then
    wget http://central.maven.org/maven2/io/swagger/swagger-codegen-cli/${VERSION}/swagger-codegen-cli-${VERSION}.jar \
        -O swagger-codegen-cli-${VERSION}.jar
fi

rm -Rf brvneucore-js-client/*

java -jar swagger-codegen-cli-${VERSION}.jar generate \
    -c brvneucore-js-client-config.json \
    -i ../web/frontend-api.json \
    -l javascript \
    -o brvneucore-js-client
