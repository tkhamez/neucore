# Installation

## Vagrant Requirements

- `vagrant up`
- browse to https://localhost
- If the vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

## Local dev Requirements

- PHP with Composer
- Node.js + npm
- MySQL/MariaDB
- Apache (dev should also works with PHP's build-in server)

## EVE API setup

- visit https://developers.eveonline.com/applications
- create a new application (eg: brvneucore-dev)
- TODO document list of required permissions here for authentication & api access
- set the callback to https://localhost/api/user/auth/callback

## Install dev

Copy `.env.dist` file to `.env` and adjust values.

Execute:
```
chmod 0777 var/cache
chmod 0777 var/logs
composer install
composer compile-dev
```

Note
`composer install` also executes:
- `cd web && npm install`
- `cd frontend && npm install`

`composer compile-dev` also executes:
- `cd frontend && npm run build`

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
