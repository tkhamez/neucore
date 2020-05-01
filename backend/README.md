# Backend

The backend is based on the [Slim Framework](https://www.slimframework.com)
with the [PHP-DI container](http://php-di.org/).

## Table of Contents

<!-- toc -->

- [Style Guide](#style-guide)
- [API](#api)
  * [operationId](#operationid)
  * [Documentation](#documentation)
  * [ESI routes](#esi-routes)
- [Install](#install)
- [Console Commands](#console-commands)
  * [Console application](#console-application)
    + [Making yourself an admin](#making-yourself-an-admin)
  * [Commands via Composer](#commands-via-composer)
  * [Doctrine](#doctrine)

<!-- tocstop -->

## Style Guide

[PSR-2: Coding Style Guide](https://www.php-fig.org/psr/psr-2/)

You can partially check and fix the code by executing the following:
```
composer style:check
composer style:fix
```

## API

### operationId

The `operationId` must be a unique string among all operations described in the API.

This must be followed for the `Neucore\Api\App` namespace.

For the `Neucore\Api\User` namespace it works (for now), if the `operationId` is
unique only within each tag.

### Documentation

The file [doc/API.md](../doc/API.md) is automatically generated from the template file `doc/API.md.tpl`, 
the route and security configuration from the `config` directory and `web/swagger.json`:
```
bin/doc-api-gen.php
```

### ESI routes

The `/api/app/v1/esi` endpoint uses a blacklist to block requests to publicly accessible ESI routes. 
This list can be regenerated with:

```
bin/esi-paths-public.php
```

## Install

See also main [**README**](../README.md#server-requirements) for prerequisites.

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
`BRAVECORE_APP_ENV`) are:
- Doctrine proxy classes are auto generated in dev mode, the APCu cache is used in prod mode
- PHP-DI uses compilation and the APCu cache in prod mode

## Console Commands

### Console application

Run the console app to see all available commands:

```
bin/console
```

#### Making yourself an admin

Login with your EVE character to create an account. Then execute this command,
replace the ID 1 with your Neucore player ID.

```
bin/console make-admin 1
```

This will add all available roles to your player account.

### Commands via Composer

Run unit tests, with or without coverage:
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

Security check of packages from composer.lock:
```
composer security-checker
```

Check code with PHPStan:
```
composer phpstan:src
composer phpstan:test
```

Check code with Phan:
```
composer phan
```

Run the built-in web server:
```
composer start
```

### Doctrine

Generate proxy classes:
```
vendor/bin/doctrine orm:generate-proxies
```

Generate constructor, getters and setters:
```
vendor/bin/doctrine orm:generate-entities src
```

Validate the mapping files:
```
vendor/bin/doctrine orm:validate-schema
```

Generate a migration by comparing the current database to the mapping information:
```
vendor/bin/doctrine-migrations migrations:diff
```

Check reserved words:
```
vendor/bin/doctrine dbal:reserved-words
```
