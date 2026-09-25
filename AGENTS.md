# AGENTS.md — Neucore

Compact guide for AI coding agent sessions working in this repo.

**Project language**: English (British spelling).

## Development Principles

See `CONTRIBUTING.md` for full guidelines.

- **Dependency Injection** — use it whenever possible.
- **Program against interfaces**, not concrete classes.
- **PSRs** — follow PHP Standards Recommendations where applicable.
- **All backend code must have unit tests**.
- **Inclusive language** — use it throughout code, documentation, and communication.
- **Never drop or destroy data on the development database**. There are two databases (dev + test). Ask before any operation that could destroy dev data.

## Project shape

- **Backend**: PHP 8.1–8.5, Slim 4 + PHP-DI + Doctrine ORM/Migrations. Lives in `backend/`.
- **Frontend**: Vue 3 + Bootstrap/Bootswatch. Lives in `frontend/`.
- **Document root**: `web/` (`web/app.php` is the web entrypoint).
- **Other dirs**: `setup/` (install scripts, Dockerfiles), `doc/` (docs), `dist/` (release build output).

## Entrypoints

- Web: `web/app.php` → `Neucore\Application::runWebApp()`.
- CLI: `backend/bin/console` → `Neucore\Application::runConsoleApp()`.
- Wiring: `backend/src/Application.php`, `backend/src/Container.php` (PHP-DI definitions).
  - PHP-DI **autowiring** is enabled — concrete class entries like `RateLimitState::class => fn() => new RateLimitState()` are unnecessary. Only add definitions when autowiring cannot work (e.g. interfaces, conditional logic, or third-party classes).
- Routes/security config: `backend/config/routes.php`, `backend/config/security.php`.

## Docker development environment

`setup/compose.yaml` defines services: `neucore_db` (MariaDB/MySQL), `neucore_php`, `neucore_node`, `neucore_http`, `neucore_memcached`, `neucore_java` (for OpenAPI codegen).

- The root `compose.yaml` is a **gitignored local copy** with user-specific mounts (plugin dirs, PHP Dockerfile selection).
- Setup: `docker compose build` → `docker compose up` → `setup/install-docker.sh` → `docker compose exec neucore_node npm run build`.
- URLs: app `http://localhost:8080`, frontend dev server `http://localhost:3000`, DB `127.0.0.1:30306`.
- Inside containers: DB host is `neucore_db`; working dirs are `/app/backend` (PHP) and `/app/frontend` (Node).
- Copy `backend/.env.dist` → `backend/.env` (gitignored).

### Rules

- **NEVER** run `composer`, `npm`, `node`, `php`, `phpunit`, etc. on the host. Always use `docker compose exec` or `docker compose run`:
  - PHP/Composer/PHPUnit: `docker compose exec neucore_php <command>`
  - npm/node: `docker compose exec neucore_node <command>`
  - Java (OpenAPI codegen): `docker compose run --rm neucore_java <command>`
- Do not install software on the host system.
- Do not make changes to files outside this project.
- Only work on files under version control.

## Environment / configuration

- Env-var based. Full reference: `backend/.env.dist`.
- Required: `NEUCORE_APP_ENV`, `NEUCORE_DATABASE_URL`, EVE app credentials.
- For tests: `NEUCORE_TEST_DATABASE_URL` (MySQL or `sqlite:///:memory:`) and `NEUCORE_MEMCACHED_SERVER`.
- Functional tests use the DB and Memcached. Test bootstrap (`backend/tests/bootstrap.php`) creates the schema automatically.
- Single test: `docker compose exec neucore_php vendor/bin/phpunit tests/Unit/SomeTest.php` (or `--filter TestName::method`).
- Tests with coverage: `docker compose exec neucore_php vendor/bin/phpunit --coverage-clover=var/logs/clover.xml`.

## Code style & static analysis

- PHP style: PER CS, config `backend/config/php-cs-fixer.dist.php`.
  - `composer style:check` / `composer style:fix`
- PHPStan level 8: `composer phpstan` (config `backend/phpstan.neon`).
- Max line length: **120 characters** (strictly enforced for both backend and frontend).
- Frontend: 4-space indent, 120 char line max. No linter/formatter — manual compliance.
- Verification order: `composer style:check && composer phpstan && composer test`.

## Build / codegen order

When you change backend routes or OpenAPI annotations, regenerate **in this order**:

```sh
# 1. OpenAPI spec files
cd backend && docker compose exec neucore_php composer openapi
# 2. JS API client (needs Java)
cd ../frontend && docker compose run --rm neucore_java /app/frontend/openapi.sh
cd neucore-js-client && docker compose exec neucore_node npm install --ignore-scripts && npm run build
# 3. Frontend production build
cd .. && docker compose exec neucore_node npm run build
```

Frontend production build writes to `web/dist/` and `web/index.html`.
Dev server (`npm run serve`) hot-reloads on port 3000, proxies to `VUE_APP_BACKEND_HOST` (set in `frontend/.env.development`).

**`composer.json` has `"sort-packages": true`** — keep `require`/`require-dev` alphabetically sorted.

## Generated / ignored artefacts

Do not hand-edit these; regenerate via the scripts above:

- `web/openapi-3.yaml`, `web/frontend-api-3.yml`, `web/application-api-3.yml`
- `frontend/neucore-js-client/`
- `web/dist/`, `web/index.html`
- `backend/var/cache/`, `backend/var/logs/`
- Doctrine proxy classes (`bin/doctrine orm:generate-proxies`) in prod

Tracked static ESI data files (regenerate with `bin/console generate-eve-api-files` after ESI changes):
`web/esi-paths-http-get.json`, `web/esi-paths-http-post.json`, `backend/config/esi-paths-public.php`, `backend/config/esi-rate-limits.php`.

## Database migrations

- Migration namespace: `Neucore\Migrations` → `backend/src/Migrations/` (config `backend/config/migrations.yml`).
- Generate diff: `vendor/bin/doctrine-migrations migrations:diff`.
- Run: `composer db:migrate`; seed fixtures: `composer db:seed`.
- **Important**: Generate migrations while using the **oldest** supported database version, then test against all supported MariaDB/MySQL versions (see CI matrix in `.github/workflows/test.yml`).

## Plugins

- Plugins live under `NEUCORE_PLUGINS_INSTALL_DIR` (e.g. `/plugins`); each has a `plugin.yml`.
- Plugin frontends served from `web/plugin/{name}/` (symlink or mount).
- Plugin code must **only** use classes from `tkhamez/neucore-plugin` and the `FactoryInterface`; never import Neucore app classes or the Neucore DB.
- If you update a library also shipped in `tkhamez/neucore-plugin`, update it there and release together with Neucore.

## CI / release

- Test: `.github/workflows/test.yml` runs on every push. Matrix: PHP 8.1–8.5 × MariaDB/MySQL (7 combinations). Only the PHP 8.4 job uploads coverage to SonarCloud.
- Release: `.github/workflows/release.yml` runs on tag pushes — builds distribution tarball (`setup/dist-collect-files.sh`) and multi-arch Docker image.
- SonarCloud project: `tkhamez_neucore`.

## Toolchain versions

- PHP: 8.1–8.5 (platform 8.1.0 in composer).
- Node: 24.14, npm 11.11 (`frontend/package.json` engines).
- Java: Temurin 17 (OpenAPI generator, used via `neucore_java` Docker service).
- DB: MariaDB 10.11/11.4/11.8/12.3 or MySQL 8.0.22/8.4/9.7.

## References

- `backend/README.md` — Backend guide
- `frontend/README.md` — Frontend guide
- `doc/Install.md` — Installation
- `doc/Documentation.md` — Features and API concepts
- `doc/Plugins.md` — Plugin API contract
- `doc/API.md` — Auto-generated API docs (from `doc/API.tpl.md`)
