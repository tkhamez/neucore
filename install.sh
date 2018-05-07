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
cd ../frontend
if [ ! -f swagger-codegen-cli.jar ]; then
	wget http://central.maven.org/maven2/io/swagger/swagger-codegen-cli/2.3.1/swagger-codegen-cli-2.3.1.jar \
		-O swagger-codegen-cli.jar
fi
rm -Rf bravecore-swagger-js-client/*
java -jar swagger-codegen-cli.jar generate \
	-c bravecore-swagger-js-client.json \
	-i ../web/swagger.json \
	-l javascript \
	-o bravecore-swagger-js-client
cd bravecore-swagger-js-client
npm install

# build front-end
cd ..
npm install
if [ "$1" = "prod" ]; then
    npm run build:prod
else
    npm run build
fi

# install Swagger UI and the temporary front-end
cd ../web
npm install
node_modules/.bin/browserify index.src.js > index.js
