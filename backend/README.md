# Backend

The backend is based on the [Slim Framework](https://www.slimframework.com)
with the [PHP-DI container](http://php-di.org/).

## Table of Contents

<!-- toc -->

- [Install](#install)
- [Style Guide](#style-guide)
- [Guidelines](#guidelines)
  * [Clear Entity Manager](#clear-entity-manager)
  * [Tests](#tests)
- [API](#api)
  * [operationId](#operationid)
  * [Documentation](#documentation)
  * [ESI routes](#esi-routes)
- [Console Commands](#console-commands)
  * [Console application](#console-application)
    + [Making yourself an admin](#making-yourself-an-admin)
  * [Commands via Composer](#commands-via-composer)
  * [Doctrine](#doctrine)

<!-- tocstop -->

## Install

See also [doc/Install.md](../doc/Install.md#server-requirements) for prerequisites.

dev:
```
composer install
composer compile
```

prod:
```
composer install --no-dev --optimize-autoloader --no-interaction
composer compile:prod
```

The differences between `dev` and `prod` mode (which is determined by the environment variable 
`NEUCORE_APP_ENV`) are:
- Doctrine proxy classes are auto generated in dev mode, the APCu cache is used in prod mode
- PHP-DI uses compilation and the APCu cache in prod mode

## Style Guide

[PSR-12: Coding Style Guide](https://www.php-fig.org/psr/psr-12/)

You can partially check and fix the code by executing the following:
```
composer style:check
composer style:fix
```

## Guidelines

### Clear Entity Manager

Some commands clear the entity manager to save memory. Never do this anywhere else, only
in "Command" classes.

### Tests

Any new code should be covered by unit tests.

## API

### operationId

The `operationId` must be a unique string among all operations described in the API.

### Documentation

The file [doc/API.md](../doc/API.md) is automatically generated from the template file `doc/API.tpl.md`, 
the route and security configuration from the `config` directory and `web/swagger.json`:
```
bin/doc-api-gen.php
```

### ESI routes

The `/api/app/v1/esi` endpoint uses a denylist to block requests to publicly accessible ESI routes. 
This list can be regenerated with:

```
bin/esi-paths-public.php
```

## Console Commands

### Console application

Run the console app to see all available commands:

```
bin/console
```

To debug, start like this:
```sh
php -dxdebug.start_with_request=yes bin/console
```

#### Making yourself an admin

Login with your EVE character to create an account. Then execute this command,
replace the ID 1 with your Neucore player ID.

```
bin/console make-admin 1
```

This will add all available roles to your player account.

### Commands via Composer

Run unit tests, with or without HTML coverage:
```
composer test:cov
composer test
```

Execute database migrations:
```
composer db:migrate
```

Load data fixtures:
```
composer db:seed
```

Generate OpenAPI interface description files:
```
composer openapi
```

Check code with PHPStan, Phan and Psalm:
```
composer phpstan
composer phan
composer psalm
```

Run the built-in web server:
```
composer start
```

### Doctrine

Generate proxy classes:
```
bin/doctrine orm:generate-proxies
```

Generate constructor, getters and setters (deprecated):
```
bin/doctrine orm:generate-entities src
```

Validate the mapping files:
```
bin/doctrine orm:validate-schema
```

Generate a migration by comparing the current database to the mapping information.  
Set the server version to something below 5.7 to generate MariaDB 10.2 compatible syntax, e.g. 
`mysql://neucore:password@127.0.0.1/core?mariadb-10.2.7`:
```
vendor/bin/doctrine-migrations migrations:diff
```

Execute a single migration, e.g.:
```
vendor/bin/doctrine-migrations migrations:execute Neucore\\Migrations\\Version20210123141218 --up
vendor/bin/doctrine-migrations migrations:execute Neucore\\Migrations\\Version20210123141218 --down
```

Create/sync the database schema - **This can delete data - backup your database first!** (For example, there are
changes to the database schema where this command deletes a column and creates a new one instead of renaming it.):
```
bin/doctrine orm:schema-tool:update
```

Check reserved words:
```
bin/doctrine dbal:reserved-words
```
