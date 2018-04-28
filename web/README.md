# Front-ends

Install dependencies:

```
npm i
```

## Swagger UI

api.html is slightly modified version of swagger-ui-dist/index.html.

## Quick 'n' Dirty Frontend

The index.html and index.src.js files contain a minimal frontend that users can use to
add their alts to their account.

Build:
```
node_modules/.bin/browserify index.src.js > index.js
```
