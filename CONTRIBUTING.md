# Contributing to Neucore

Thank you for your interest in contributing to Neucore! This document provides guidelines for 
contributing to the project.

## Project Language

The project language is **English (British spelling)**.

## Principles

- **AI assistance** is acceptable, but you must fully understand and be able to justify all code
  you submit.
- **Inclusive language** — use it throughout code, documentation, and communication.

Backend: 

- **Dependency Injection** — use it whenever possible.
- **Program against interfaces**, not concrete classes.
- **PSRs (PHP Standards Recommendations)** — follow them where applicable.
- **All code must have unit tests**.

## How to Contribute

### Reporting Issues

- Check existing issues first to avoid duplicates
- Provide clear steps to reproduce the problem
- Include relevant environment details (PHP version, database, etc.)
- AI assistance is acceptable, but keep issues **concise and precise** — no unnecessary verbosity.

### Suggesting Enhancements

- Open an issue describing the enhancement
- Explain the use case and expected behaviour
- Discuss the design before implementing large changes

### Pull Requests

1. Fork the repository and create a feature branch
2. Make your changes following the guidelines below
3. Ensure all tests pass and code style checks succeed
4. Submit a pull request with a clear description

## Code Style and Quality

### PHP

- **Standard**: PER Coding Style (configured in `backend/config/php-cs-fixer.dist.php`)
- **Static Analysis**: PHPStan Level 8 (configured in `backend/phpstan.neon`)

Run checks:
```bash
cd backend
composer style:check    # Check style
composer style:fix      # Auto-fix style issues
composer phpstan        # Static analysis
composer test           # Run tests
```

### Frontend

- **Indent**: 4 spaces
- **Line length**: Maximum 120 characters

### Generated Files

Do not edit these files directly; they are generated with the `generate-eve-api-files` 
command or build scripts:

- `web/esi-paths-http-get.json`, `web/esi-paths-http-post.json`
- `backend/config/esi-paths-public.php`, `backend/config/esi-rate-limits.php`
- Doctrine proxy classes (in prod)

When changing backend routes or OpenAPI annotations, regenerate in order:
```bash
cd backend && composer openapi
cd ../frontend && ./openapi.sh
cd neucore-js-client && npm install --ignore-scripts && npm run build
cd .. && npm run build
```

## Database Migrations

- Migration namespace: `Neucore\Migrations` → `backend/src/Migrations/`
- Generate a migration: `vendor/bin/doctrine-migrations migrations:diff`
- Run migrations: `composer db:migrate`
- Seed fixtures: `composer db:seed`

Set `serverVersion` in the database URL for compatible syntax generation.

## Documentation

- Project documentation lives in `doc/`
- API documentation is generated from OpenAPI annotations
- Update relevant documentation when changing features

Key documentation files:
- `doc/Install.md` — Installation guide
- `doc/Documentation.md` — Features and API concepts
- `doc/Plugins.md` — Plugin API contract
- `backend/README.md` — Backend guide
- `frontend/README.md` — Frontend guide

## Getting Help

- Join the [Neucore Discord Server](https://discord.gg/memUh56u8z)
- Check existing documentation in `doc/`

## Licensing

By contributing, you agree that your contributions will be licensed under the MIT License 
(see LICENSE file).
