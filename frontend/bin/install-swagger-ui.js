#!/usr/bin/env node

const fs = require('fs');

const sourcePath = `${__dirname}/../node_modules/swagger-ui-dist`;
const targetPath = `${__dirname}/../../web/swagger-ui-dist`;

if (fs.existsSync(targetPath)) {
    fs.rmSync(targetPath, { recursive: true });
}
fs.mkdirSync(targetPath);

const files = [
    '/favicon-32x32.png',
    '/LICENSE',
    '/swagger-ui.css',
    '/swagger-ui-bundle.js',
    '/swagger-ui-standalone-preset.js',
];

for (const file of files) {
    fs
        .createReadStream(sourcePath + file)
        .pipe(fs.createWriteStream(targetPath + file));
}
