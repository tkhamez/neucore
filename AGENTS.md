# AGENTS.md — Neucore

Compact guide for OpenCode sessions working in this repo.

## Project shape

- **Backend**: PHP 8.1–8.5, Slim 4 + PHP-DI + Doctrine ORM/Migrations. Lives in `backend/`.
- **Frontend**: Vue 3 + Bootstrap/Bootswatch. Lives in `frontend/`.
- **Document root**: `web/` (`web/app.php` is the web entrypoint).
- **Other dirs**: `setup/` (install scripts, Dockerfiles), `doc/` (docs), `dist/` (release build output).

## Entrypoints

- Web: `web/app.php` → `Neucore\Application::runWebApp()`.
- CLI: `backend/bin/console` → `Neucore\Application::runConsoleApp()`.
- Wiring: `backend/src/Application.php`, `backend/src/Container.php` (PHP-DI definitions).
- Routes/security config: `backend/config/routes.php`, `backend/config/security.php`.

## Local setup

### Docker dev environment

- The root `compose.yaml` is a **gitignored local file** (contains user-specific plugin mounts); adapt it as needed.
- `export UID` first, in every shell (containers run as `${UID}`).
- `docker compose build` then `docker compose up`; then run `setup/install-docker.sh`.
- URLs: app http://localhost:8080, frontend dev server http://localhost:3000, DB at 127.0.0.1:30306.
- Copy `backend/.env.dist` → `backend/.env`; inside containers the DB host is `neucore_db`.
- Make first user admin: `docker compose exec neucore_php bin/console make-admin 1`.

### Manual install

```sh
cd backend
composer install
composer compile          # clear-cache + db:migrate + db:seed + openapi

cd ../frontend
npm ci
npm run postinstall       # installs swagger-ui-dist
npm run build
```

Or run `setup/install.sh` (dev) / `setup/install.sh prod` (prod), which does backend + JS client + frontend.

## Environment / configuration

- Config is env-var based. Copy `backend/.env.dist` → `backend/.env` (gitignored).
- Required: `NEUCORE_APP_ENV=dev|prod`, `NEUCORE_DATABASE_URL`, EVE app credentials.
- Full env reference: `backend/.env.dist`.

## Running tests

```sh
cd backend
composer test        # phpunit --colors=always
composer test:cov    # HTML coverage in var/phpunit
```

Tests need env vars set. Minimum for MySQL (as in CI):

```sh
export NEUCORE_APP_ENV=dev
export NEUCORE_TEST_DATABASE_URL='mysql://root:@127.0.0.1/test'
export NEUCORE_EVE_CLIENT_ID=123
export NEUCORE_EVE_SECRET_KEY=abc
export NEUCORE_EVE_CALLBACK_URL='http://localhost'
export NEUCORE_MEMCACHED_SERVER='127.0.0.1:11211'
```

- `NEUCORE_TEST_DATABASE_URL=sqlite:///:memory:` is supported (see `.env.dist`).
- Test bootstrap creates the DB schema automatically: `backend/tests/bootstrap.php`.
- Functional tests use the DB and Memcached.
- Single test: `vendor/bin/phpunit tests/Unit/SomeTest.php` (or `--filter TestName::method`).

## Code style & static analysis

- PHP style: PER CS, config `backend/config/php-cs-fixer.dist.php`.
  - `composer style:check`
  - `composer style:fix`
- PHPStan level 8: `composer phpstan` (config `backend/phpstan.neon`).
- Frontend style: 4-space indent, 120 char line max (from `frontend/README.md`).
- Verification order: `composer style:check && composer phpstan && composer test`.

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

Frontend production build writes into the document root: `web/dist/` and `web/index.html`.
Dev server (`npm run serve`) hot-reloads on port 3000 and proxies to `VUE_APP_BACKEND_HOST` (set in `frontend/.env.development`).

## Important generated / ignored artifacts

Do not hand-edit these; regenerate via the scripts above:

- `web/openapi-3.yaml`, `web/frontend-api-3.yml`, `web/application-api-3.yml` (gitignored)
- `frontend/neucore-js-client/` (generated OpenAPI JS client, gitignored)
- `web/dist/`, `web/index.html` (frontend production build, gitignored)
- `backend/var/cache/`, `backend/var/logs/`
- Doctrine proxy classes (`bin/doctrine orm:generate-proxies`) in prod

Tracked static ESI data files (regenerate with `bin/console generate-eve-api-files` after ESI changes):
`web/esi-paths-http-get.json`, `web/esi-paths-http-post.json`, `backend/config/esi-rate-limits.php`.

## Database migrations

- Migration namespace: `Neucore\Migrations` → `backend/src/Migrations/` (config `backend/config/migrations.yml`).
- Generate diff: `vendor/bin/doctrine-migrations migrations:diff` (set `serverVersion` in the URL for compatible syntax).
- Run: `composer db:migrate`; seed fixtures: `composer db:seed`.

## Common CLI commands

```sh
bin/console make-admin 1
bin/console update-member-tracking
bin/console generate-eve-api-files      # regenerate ESI static data files
bin/console check-tokens                # cronjob to keep refresh tokens alive
bin/run-jobs.sh                         # runs all background jobs in order
```

## Plugins

- Plugins live under the directory set by `NEUCORE_PLUGINS_INSTALL_DIR` (e.g. `/plugins`); each has a `plugin.yml` in its own subdirectory.
- Plugin frontends are served from `web/plugin/{name}/` (symlink or mount).
- Plugin code must **only** use classes from `tkhamez/neucore-plugin` and the `FactoryInterface`; never import Neucore app classes or the Neucore DB directly.
- If you update a library also included in `tkhamez/neucore-plugin`, update it there and release it together with Neucore.

## CI / release

- `.github/workflows/test.yml` runs on every push. Matrix: PHP 8.1–8.5 × MariaDB 10.5.1/10.11/11.4/11.8, MySQL 8.0.22/8.4. Only the PHP 8.4 job uploads coverage to SonarCloud.
- `.github/workflows/release.yml` runs on tag pushes: builds the distribution tarball (via `setup/dist-collect-files.sh`) and the multi-arch Docker image.
- PHP platform in `composer.json` is pinned to `8.1.0`; app supports up to 8.5.

## Toolchain versions

- PHP: 8.1–8.5 (platform 8.1.0 in composer).
- Node: 24.14, npm 11.11 (from `frontend/package.json` engines).
- Java: Temurin 17 for the OpenAPI generator.
- DB: MariaDB 10.5.1/10.11/11.4/11.8 or MySQL 8.0.22/8.4.

## References

- Backend guide: `backend/README.md`
- Frontend guide: `frontend/README.md`
- Install/deployment: `doc/Install.md`
- Features/API concepts: `doc/Documentation.md`
- Plugin API contract: `doc/Plugins.md`
- Env vars: `backend/.env.dist`