# Backend

The backend is based on the [Slim Framework](https://www.slimframework.com)
with the [PHP-DI container](http://php-di.org/).

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
composer compile:prod --no-dev --no-interaction
```

## Console commands

The console application is available at

```
bin/console
```

### Making yourself an admin

Login with your EVE character to create an account. Then execute this command,
replace the ID 1234 with your EVE character ID.

```
bin/console make-admin 1234
```

This will add all available roles to your player account.

### Unit Tests

Run tests:
```
vendor/bin/phpunit
```

Or use composer, with or without coverage report:
```
composer test:cov
composer test
```

### Doctrine

Generate constructor, getters and setters:
```
vendor/bin/doctrine orm:generate-entities src/classes
```

Validate the mapping files
```
vendor/bin/doctrine orm:validate-schema
```

Generate migration by comparing the current database to the mapping information:
```
vendor/bin/doctrine-migrations migrations:diff
```

Apply migrations:
```
vendor/bin/doctrine-migrations migrations:migrate
```

### Swagger

Generate swagger.json:
```
vendor/bin/swagger --exclude bin,config,var,vendor --output ../web
```
