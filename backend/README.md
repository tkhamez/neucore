# Backend

The backend is based on the [Slim Framework](https://www.slimframework.com)
with the [PHP-DI container](http://php-di.org/).

## API

### operationId

The `operationId` must be a unique string among all operations described in the API.

This must be followed for the `Brave\Core\Api\App` namespace.

For the `Brave\Core\Api\User` namespace it works (for now), if the `operationId` is
unique only within each tag.

### Documentation

The file `doc/API.md` is automatically generated from the template file `doc/API.md.tpl`, 
the route and security configuration and `web/swagger.json` with `backend/bin/doc-api-gen.php`.

## Style Guide

[PSR-2: Coding Style Guide](https://www.php-fig.org/psr/psr-2/)

## Install

See also main [**README**](../README.md) for prerequisites.

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

## Console Commands

### Console application

Run the console app to see all available commands:

```
bin/console
```

#### Making yourself an admin

Login with your EVE character to create an account. Then execute this command,
replace the ID 1234 with your EVE character ID.

```
bin/console make-admin 1234
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

Clear cache:
```
composer cache:clear
```

Security check of packages from composer.lock:
```
composer security:check
```

Run the built-in web server:
```
composer run
```

### Doctrine

Generate constructor, getters and setters:
```
vendor/bin/doctrine orm:generate-entities src/classes
```

Validate the mapping files:
```
vendor/bin/doctrine orm:validate-schema
```

Generate migration by comparing the current database to the mapping information:
```
vendor/bin/doctrine-migrations migrations:diff
```

Check reserved words:
```
vendor/bin/doctrine dbal:reserved-words
```
