#!/usr/bin/env sh

DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

# generate the OpenAPI client

VERSION=7.25.0
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

CLIENT_DIR="${DIR}"/neucore-js-client

# Keep a copy of the pristine generated file so the changes applied below can
# be compared easily (it is removed on the next regeneration).
cp "${CLIENT_DIR}"/package.json "${CLIENT_DIR}"/package.json.orig

# The generator emits a Babel build targeting legacy browsers; the frontend is
# bundled by Vite, so the client is kept ESM-native instead: point the entry
# point at src/, drop the Babel build and its dependencies, and remove .babelrc.
NEUCORE_CLIENT_DIR="${CLIENT_DIR}" node - <<'EOF'
const fs = require('fs');
const path = require('path');

const packageJsonPath = path.join(process.env.NEUCORE_CLIENT_DIR, 'package.json');
const pkg = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));

pkg.type = 'module';
pkg.main = 'src/index.js';
pkg.files = ['src'];

delete pkg.scripts.build;
delete pkg.scripts.prepare;
// The generated test specs are CJS and rely on the removed Babel runner.
delete pkg.scripts.test;
if (Object.keys(pkg.scripts).length === 0) {
    delete pkg.scripts;
}

for (const key of ['dependencies', 'devDependencies']) {
    for (const name of Object.keys(pkg[key] || {})) {
        if (name.startsWith('@babel/')) {
            delete pkg[key][name];
        }
    }
    if (Object.keys(pkg[key] || {}).length === 0) {
        delete pkg[key];
    }
}

fs.writeFileSync(packageJsonPath, JSON.stringify(pkg, null, 2) + '\n');
EOF

rm -f "${CLIENT_DIR}"/.babelrc
