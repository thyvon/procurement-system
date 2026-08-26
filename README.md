# Procurement Management System

Modular-monolith procurement platform (requisitions → RFQ → PO → GRN with a configurable approval engine), built per `docs/foundation.md` v2.0.

| App | Stack | Location |
|---|---|---|
| API | Laravel 13, PHP 8.4, MariaDB | `apps/api` |
| Web | Next.js 16.3 LTS, React 19.2, Tailwind 4 | `apps/web` |

## Local development (no Docker required)

Prerequisites: PHP **8.4** (`C:\php84\php.exe` on this machine), Composer 2, Node.js 22, pnpm 11, MariaDB/MySQL on `127.0.0.1:3306`.

### One-time setup

```powershell
# 1. Databases (XAMPP MariaDB: root, no password)
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS procurement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS procurement_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. API
cd apps/api
& "C:\php84\php.exe" C:\composer\composer.phar install
Copy-Item .env.example .env          # adjust if needed
& "C:\php84\php.exe" artisan key:generate
& "C:\php84\php.exe" artisan migrate

# 3. Web (from repo root)
pnpm install
```

### Daily workflow

```powershell
# Terminal 1 — API on http://localhost:8000
cd apps/api; & "C:\php84\php.exe" artisan serve

# Terminal 2 — Web on http://localhost:3000
pnpm web
```

Local dev intentionally runs without Redis/Meilisearch/MinIO (file/sync/local-disk/null drivers). Production parity is provided by Docker — see below.

## Contract-first client generation

The frontend API client is **generated, never hand-written**:

```powershell
# requires the API running on :8000
pnpm generate-client
```

`src/lib/api/**` is committed; CI fails if it drifts from the OpenAPI spec.

## Quality gates

```powershell
# API (in apps/api)
composer pint:test   # code style
composer stan        # PHPStan level 6
composer deptrac     # module boundaries
composer test        # Pest against real MariaDB (procurement_test)

# Web (from root)
pnpm --filter web lint
pnpm --filter web typecheck
```

All gates run in CI (`.github/workflows/ci.yml`) and on pre-commit (Husky).

## Production (Docker)

```bash
docker compose up -d --build
docker compose exec api php artisan migrate --force
```

Brings up MariaDB 12.3, Redis, Meilisearch, MinIO, the API, and the web app. Compose values are development-grade defaults — set real secrets via `docker-compose.override.yml` / environment before any external exposure.

## Documentation

- `docs/foundation.md` — product design, stack decisions (§11), compatibility matrix (§16)
- `docs/project-setup-and-architecture.md` — scaffold conventions, module template
- `docs/modules/` — one doc per module as they are built
