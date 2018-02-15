# Installation

## Requirements

- PHP with Composer
- Node.js + npm
- MySQL/MariaDB
- Apache (dev should also works with PHP's build-in server)

## Install dev

Copy `.env.dist` file to `.env` and adjust values.

Execute:
```
chmod 0777 var/cache
chmod 0777 var/logs
composer install
vendor/bin/doctrine-migrations migrations:migrate
vendor/bin/swagger --exclude bin,config,docs,var,vendor,web --output web
```

Frontend:
```
cd frontend
npm i
npm run build
```

## Install prod

Set the required environment variables, see in file `.env.dist`

Make sure that the webserver can write in var/logs and var/cache.

Execute:
```
composer install --no-dev --optimize-autoloader --no-interaction
composer compile --no-dev --no-interaction
```

Frontend:
```
cd frontend
npm i
npm run build:prod
```

## Heroku

To deploy to Heroku, add buildpacks first:
```
heroku buildpacks:add heroku/php
heroku buildpacks:add --index 1 heroku/nodejs
```
