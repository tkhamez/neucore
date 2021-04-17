#!/usr/bin/env node

'use strict';

const https = require('https');
const fs = require('fs');

https.get('https://esi.evetech.net/latest/swagger.json', response => {
    let data = '';
    response.on('data', chunk => {
        data += chunk;
    })
    response.on('end', () => {
        fetchDone(JSON.parse(data));
    });
});

function fetchDone(def) {
    const get = [];
    const post = [];
    for (const path in def.paths) {
        if (! def.paths.hasOwnProperty(path)) {
            continue;
        }
        if (def.paths[path].get) {
            get.push('/latest' + path);
        } else if (def.paths[path].post) {
            post.push('/latest' + path);
        }
    }
    writeFiles(get, post);
}

function writeFiles(get, post) {
    fs.writeFile(__dirname + "/../../frontend/public/esi-paths-http-get.json", JSON.stringify(get), function(err) {
        result(err, 'frontend/public/esi-paths-http-get.json');
    });
    fs.writeFile(__dirname + "/../../frontend/public/esi-paths-http-post.json", JSON.stringify(post), function(err) {
        result(err, 'frontend/public/esi-paths-http-post.json');
    });
    function result(err, file) {
        if (! err) {
            console.log(`wrote ${file}`);
        } else {
            console.log(err);
        }
    }
}
