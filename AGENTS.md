# AGENTS.md — Neucore

Compact guide for AI coding agent sessions working in this repo.

**Project language**: English (British spelling).

## Project shape

- **Backend**: PHP 8.1–8.5, Slim 4 + PHP-DI + Doctrine ORM/Migrations. Lives in `backend/`.
- **MCP server** (end-user feature, see `doc/MCP-Server.md`): `POST /api/app/v1/mcp`, implemented with the
  `mcp/sdk` library in `backend/src/Mcp/` (tools/resources are auto-discovered from that directory).
  Character/player data and access restrictions apply to both the REST API and the MCP tools — keep them in sync.
- **Frontend**: Node.js 24 with npm 11, Vue 3 + Bootstrap. Lives in `frontend/`.
- **Document root**: `web/` (`web/app.php` is the web entrypoint).
- **Other dirs**: `setup/` (install scripts, Dockerfiles), `doc/` (docs), `dist/` (release build output).
- **Supported Databases and versions**: MariaDB 10.11/11.4/11.8/12.3, MySQL 8.0.22/8.4/9.7

## Development Principles

- **Dependency Injection** — use it whenever possible.
- **Program against interfaces**, not concrete classes.
- **PSRs** — follow PHP Standards Recommendations where applicable.
- **All backend code must have unit tests**.
- **Inclusive language** — use it throughout code, documentation, and communication.

## Docker development environment

`setup/compose.yaml` defines services: `neucore_db` (MariaDB/MySQL), `neucore_php`, `neucore_node`, 
`neucore_http`, `neucore_memcached`.
Java is available in the `neucore_node` container for the OpenAPI Generator.

- The root `compose.yaml` is a **gitignored local copy** with user-specific mounts 
  (plugin dirs, PHP Dockerfile selection).
- Initial setup: `docker compose build` → `docker compose up` → `setup/install-docker.sh` (production variant:
  `setup/install-docker.sh prod`, which also builds the frontend). In dev, the frontend is served by the dev
  server on port 3000 — no separate `npm run build` needed.
- URLs: app `http://localhost:8080`, frontend dev server `http://localhost:3000`, DB `127.0.0.1:30306`.
- Inside containers: DB host is `neucore_db`; working dirs are `/app/backend` (PHP) and `/app/frontend` (Node).
- Copy `backend/.env.dist` → `backend/.env` (gitignored).

### Rules

- **Never drop or destroy data on the development database**. There are two databases (dev + test). 
  Ask before any operation that could destroy dev data.
- **NEVER** run `composer`, `npm`, `node`, `php`, etc. on the host. Always use `docker compose exec`:
  - PHP/Composer/PHPUnit: `docker compose exec neucore_php <command>`
  - npm/node: `docker compose exec neucore_node <command>`
- Only work on files under version control.
- Use Git read-only, the user commits and pushes.

## Entrypoints

- Web: `web/app.php` → `Neucore\Application::runWebApp()`.
- CLI: `backend/bin/console` → `Neucore\Application::runConsoleApp()`.
- Wiring: `backend/src/Application.php`, `backend/src/Container.php` (PHP-DI definitions).
  - PHP-DI **autowiring** is enabled — concrete class entries like 
    `RateLimitState::class => fn() => new RateLimitState()` are unnecessary. Only add definitions
    when autowiring cannot work (e.g. interfaces, conditional logic, or third-party classes).
- Routes/security config: `backend/config/routes.php`, `backend/config/security.php`.

## Environment / configuration

- Env-var based. Full reference: `backend/.env.dist`.
- Required: `NEUCORE_APP_ENV`, `NEUCORE_DATABASE_URL`, EVE app credentials.
- For tests: `NEUCORE_TEST_DATABASE_URL` (MySQL or `sqlite:///:memory:`) and `NEUCORE_MEMCACHED_SERVER`.
- Functional tests use the DB and Memcached. Test bootstrap (`backend/tests/bootstrap.php`) 
  creates the schema automatically.
- Single test: `docker compose exec neucore_php vendor/bin/phpunit tests/Unit/SomeTest.php` 
  (or `--filter TestName::method`).
- Tests with coverage: `docker compose exec neucore_php vendor/bin/phpunit --coverage-clover=var/logs/clover.xml`.

## Code style & static analysis

- PHP style: PER CS, config `backend/config/php-cs-fixer.dist.php`.
  - `composer style:check` / `composer style:fix`
- PHPStan level 8: `composer phpstan` (config `backend/phpstan.neon`).
- Max line length: **120 characters**. Not enforced by any tool, but it is a hard requirement in all new or
  modified code (backend and frontend).
- Frontend: 4-space indent, 120 char line max. No linter/formatter — manual compliance.
- Verification order: `composer style:check && composer phpstan && composer test`.

## Build / codegen order

When you change backend routes or OpenAPI annotations, regenerate **in this order**:

```sh
# 1. OpenAPI spec files and documentation
docker compose exec neucore_php composer openapi
docker compose exec neucore_php bin/doc-api-gen.php
docker compose exec neucore_node markdown-toc ../doc/API.md -i
# 2. JS API client
docker compose exec neucore_node /app/frontend/openapi.sh
docker compose exec neucore_node npm install --ignore-scripts --prefix /app/frontend/neucore-js-client
docker compose exec neucore_node npm run build --prefix /app/frontend/neucore-js-client
# 3. Frontend production build
docker compose exec neucore_node npm run build
```

Frontend production build writes to `web/dist/` and `web/index.html`.
Dev server (`npm run serve`) hot-reloads on port 3000, proxies to `VUE_APP_BACKEND_HOST` 
(set in `frontend/.env.development`).

**`composer.json` has `"sort-packages": true`** — keep `require`/`require-dev` alphabetically sorted.

## Generated / ignored artefacts

Do not hand-edit these; regenerate via the scripts above:

- `web/openapi-3.yaml`, `web/frontend-api-3.yml`, `web/application-api-3.yml`
- `frontend/neucore-js-client/`
- `web/dist/`, `web/index.html`
- `backend/var/cache/`, `backend/var/logs/`
- Doctrine proxy classes (`bin/doctrine orm:generate-proxies`) in prod

Tracked static ESI data files (regenerate with `bin/console generate-eve-api-files` after ESI changes):
`web/esi-paths-http-get.json`, `web/esi-paths-http-post.json`, `backend/config/esi-paths-public.php`, 
`backend/config/esi-rate-limits.php`.

## Database migrations

- Migration namespace: `Neucore\Migrations` → `backend/src/Migrations/` (config `backend/config/migrations.yml`).
- Create a new migration after making changes to entities: `vendor/bin/doctrine-migrations migrations:diff`.
- Run: `composer db:migrate`; seed fixtures: `composer db:seed`.
- **Important**: Generate migrations while using the **oldest** supported database version, then test 
  against all supported MariaDB/MySQL versions (see CI matrix in `.github/workflows/test.yml`).

## Plugins

- Plugins live under `NEUCORE_PLUGINS_INSTALL_DIR` (e.g. `/plugins`); each has a `plugin.yml`.
- Service pages (`#Service/{id}`) map to the `plugins` table (id, name, configuration JSON).
- Plugin frontends served from `web/plugin/{name}/` (symlink or mount).
- Plugin code must **only** use classes and interfaces from `tkhamez/neucore-plugin`; never import Neucore 
  app classes or the Neucore DB.
- If you update a library also shipped in `tkhamez/neucore-plugin`, update it there and release together with Neucore.

## References

- `backend/README.md` — Backend guide
- `frontend/README.md` — Frontend guide
- `doc/Install.md` — Installation
- `doc/Documentation.md` — Features and API concepts
- `doc/Plugins.md` — Plugin API contract
- `doc/API.md` — Auto-generated API docs (from `doc/API.tpl.md`)
