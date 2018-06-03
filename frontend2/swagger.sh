#!/bin/sh

# generate the Swagger client

if [ ! -f swagger-codegen-cli.jar ]; then
	wget http://central.maven.org/maven2/io/swagger/swagger-codegen-cli/2.3.1/swagger-codegen-cli-2.3.1.jar \
		-O swagger-codegen-cli.jar
fi

rm -Rf brvneucore-js-client/*

java -jar swagger-codegen-cli.jar generate \
	-c brvneucore-js-client-config.json \
	-i ../web/swagger.json \
	-l javascript \
	-o brvneucore-js-client
