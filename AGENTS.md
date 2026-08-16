# AGENTS.md — Neucore

Compact guide for OpenCode sessions working in this repo.

## Project shape

- **Backend**: PHP 8.1–8.5, Slim 4 + PHP-DI + Doctrine ORM/Migrations. Lives in `backend/`.
- **Frontend**: Vue 3 + Bootstrap/Bootswatch, lives in `frontend/`.
- **Document root**: `web/` (`web/app.php` is the web entrypoint).
- **Monorepo packages**: `backend/`, `frontend/`, `web/`, `setup/`, `doc/`.
- **No existing agent config** (no `opencode.json`, `.cursorrules`, or prior `AGENTS.md`).

## Entrypoints

- Web: `web/app.php` → `Neucore\Application::runWebApp()`.
- CLI: `backend/bin/console` → `Neucore\Application::runConsoleApp()`.
- Container/services wiring: `backend/src/Application.php`, `backend/src/Container.php`.

## Local setup

### Docker dev environment (preferred)

- `export UID` first, in every shell.
- `docker compose build` then `docker compose up`.
- Run install: `setup/install-docker.sh`.
- URLs: app http://localhost:8080, frontend dev server http://localhost:3000, DB at 127.0.0.1:30306.
- Copy `backend/.env.dist` → `backend/.env`; inside containers the DB host is `neucore_db`.
- Make first user admin: `docker compose exec neucore_php bin/console make-admin 1`.

### Manual install

```sh
# Backend
cd backend
composer install
composer compile

# Frontend
cd ../frontend
npm ci
npm run postinstall
npm run build
```

Or run `setup/install.sh` (dev) / `setup/install.sh prod` (prod).

## Environment / configuration

- Config is env-var based. Copy `backend/.env.dist` → `backend/.env`.
- Required: `NEUCORE_APP_ENV=dev|prod`, `NEUCORE_DATABASE_URL`, EVE app credentials.
- Full env reference: `backend/.env.dist`.

## Running tests

```sh
cd backend
composer test        # phpunit --colors=always
composer test:cov    # HTML coverage in var/phpunit
```

Tests need env vars set. Minimum for CI/local MySQL:

```sh
export NEUCORE_APP_ENV=dev
export NEUCORE_TEST_DATABASE_URL='mysql://root:@127.0.0.1/test'
export NEUCORE_EVE_CLIENT_ID=123
export NEUCORE_EVE_SECRET_KEY=abc
export NEUCORE_EVE_CALLBACK_URL='http://localhost'
export NEUCORE_MEMCACHED_SERVER='127.0.0.1:11211'
```

- Functional tests use the DB and Memcached.
- `NEUCORE_TEST_DATABASE_URL=sqlite:///:memory:` is supported for unit tests (see `.env.dist`).
- Test bootstrap creates the DB schema automatically: `backend/tests/bootstrap.php`.

## Code style & static analysis

- PHP style: PER CS, config `backend/config/php-cs-fixer.dist.php`.
  - `composer style:check`
  - `composer style:fix`
- PHPStan level 8: `composer phpstan` (config `backend/phpstan.neon`).
- Frontend style: 4-space indent, 120 char line max (from `frontend/README.md`).

## Build / codegen order

When you change backend routes or OpenAPI annotations, regenerate:

```sh
cd backend
composer openapi     # writes web/openapi-3.yaml, web/frontend-api-3.yml, web/application-api-3.yml
```

Then regenerate the JS API client:

```sh
cd frontend
./openapi.sh                              # needs Java; downloads openapi-generator-cli 7.18.0
cd neucore-js-client && npm install --ignore-scripts && npm run build
```

Then rebuild the frontend:

```sh
cd frontend
npm run build
```

`composer compile` / `composer compile:prod` runs migrations, seeds and `composer openapi` in one go.

## Important generated / ignored artifacts

- `web/openapi-3.yaml`, `web/frontend-api-3.yml`, `web/application-api-3.yml`
- `frontend/neucore-js-client/` (generated OpenAPI JS client)
- `web/dist/` (frontend production build)
- `backend/var/cache/`, `backend/var/logs/`
- Doctrine proxy classes (`bin/doctrine orm:generate-proxies`) in prod

Do not hand-edit generated files; regenerate them via the scripts above.

## Database migrations

- Migration namespace: `Neucore\Migrations`
- Migration files: `backend/src/Migrations/`
- Config: `backend/config/migrations.yml`
- Generate diff: `vendor/bin/doctrine-migrations migrations:diff`
- Run: `composer db:migrate`
- Seed fixtures: `composer db:seed`

## Common CLI commands

```sh
bin/console make-admin 1
bin/console update-member-tracking
bin/console generate-eve-api-files      # regenerate ESI static data files
bin/console check-tokens                # cronjob to keep refresh tokens alive
bin/run-jobs.sh                         # runs all background jobs in order
```

## Plugins

- Plugins live under the directory set by `NEUCORE_PLUGINS_INSTALL_DIR` (e.g. `/plugins`).
- Each plugin has a `plugin.yml` at `plugins/<name>/plugin.yml`.
- Plugin frontends are served from `web/plugin/<name>/` (symlink or mount).
- Plugin code must **only** use classes from `tkhamez/neucore-plugin` and the `FactoryInterface`; never import Neucore app classes or DB directly.

## CI / release

- `.github/workflows/test.yml` runs on every push. Matrix: PHP 8.1–8.5 × MariaDB/MySQL. Only the PHP 8.4 job uploads coverage to SonarCloud.
- `.github/workflows/release.yml` runs on tags, builds the distribution tarball and Docker image.
- PHP platform in `composer.json` is pinned to `8.1.0`; app supports up to 8.5.

## Toolchain versions

- PHP: 8.1–8.5 (platform 8.1.0 in composer).
- Node: 24.14, npm 11.11 (from `frontend/package.json` engines).
- Java: >= 11, release/CI uses Temurin 17 (for OpenAPI generator).
- DB: MariaDB 10.5.1/10.11/11.4/11.8 or MySQL 8.0.22/8.4.

## Quick verification

```sh
cd backend
composer style:check && composer phpstan && composer test
```

For frontend:

```sh
cd frontend
npm run build
```

## References

- Backend guide: `backend/README.md`
- Frontend guide: `frontend/README.md`
- Install/deployment: `doc/Install.md`
- Features/API concepts: `doc/Documentation.md`
- Plugin API contract: `doc/Plugins.md`
- Env vars: `backend/.env.dist`
