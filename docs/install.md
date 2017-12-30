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
```

## Install prod

Set the required environment variables, see in file `.env.dist`

Make sure that the webserver can write in var/logs and var/cache.

Execute:
```
composer install --no-dev --optimize-autoloader --no-interaction
composer compile --no-dev --no-interaction
```

## Heroku

To deploy to Heroku, add buildpacks first:
```
heroku buildpacks:add heroku/php
heroku buildpacks:add --index 1 heroku/nodejs
```
