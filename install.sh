#!/bin/sh

# build back-end
cd backend
if [ "$1" = "prod" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile:prod --no-dev --no-interaction
else
    composer install
    composer compile
fi

# generate the Swagger client
cd ../frontend2
if [ ! -f swagger-codegen-cli.jar ]; then
	wget http://central.maven.org/maven2/io/swagger/swagger-codegen-cli/2.3.1/swagger-codegen-cli-2.3.1.jar \
		-O swagger-codegen-cli.jar
fi
rm -Rf brvneucore-js-client/*
java -jar swagger-codegen-cli.jar generate \
	-c brvneucore-js-client.json \
	-i ../web/swagger.json \
	-l javascript \
	-o brvneucore-js-client
cd brvneucore-js-client
npm install

# build front-end
cd ../../frontend
npm install
if [ "$1" = "prod" ]; then
    npm run build:prod
else
    npm run build
fi

# build frontend2
cd ../frontend2
npm install
npm run build

# install Swagger UI
cd ../web
npm install
