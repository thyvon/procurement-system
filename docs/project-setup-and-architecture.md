# Procurement System — Project Setup, Scaffold & Architecture

**Version:** 1.0
**Status:** Living document — scaffold phase (Phase 0)
**Scope:** How the project is initialized, structured, and wired together. **Deliberately excludes** which procurement features get built and in what order — that lives in `foundation-laravel-proposal.md` §8 (roadmap) and in per-module docs added as each module is actually built. This document only answers: *what do we run, and what does the skeleton look like once it's running.*
**Stack assumed:** Laravel 13 (PHP 8.4, min 8.3) + MariaDB 12.3 LTS + Meilisearch + Next.js 16.3 LTS, per `foundation.md` v2.0 (§16 compatibility matrix).

---

## Contents

1. [Prerequisites](#1-prerequisites)
2. [Repository Layout](#2-repository-layout)
3. [Step 1 — Repo Skeleton](#3-step-1--repo-skeleton)
4. [Step 2 — Scaffold the API App](#4-step-2--scaffold-the-api-app)
5. [Step 3 — Scaffold the Web App](#5-step-3--scaffold-the-web-app)
6. [Step 4 — Docker Compose](#6-step-4--docker-compose)
7. [Step 5 — Shared Kernel (API)](#7-step-5--shared-kernel-api)
8. [Step 6 — Module Convention](#8-step-6--module-convention)
9. [Step 7 — Frontend Architecture Skeleton](#9-step-7--frontend-architecture-skeleton)
10. [Step 8 — Tooling & Quality Gates](#10-step-8--tooling--quality-gates)
11. [Step 9 — Environment & Config](#11-step-9--environment--config)
12. [Step 10 — Verify the Skeleton](#12-step-10--verify-the-skeleton)
13. [Naming Conventions Reference](#13-naming-conventions-reference)
14. [Out of Scope](#14-out-of-scope)
15. [Change Log](#15-change-log)

---

## 1. Prerequisites

| Tool | Version | Purpose |
|---|---|---|
| PHP | 8.3+ | API runtime |
| Composer | 2.x | PHP dependency management |
| Node.js | 22 LTS | Web app runtime + tooling |
| pnpm | latest | Web app package manager |
| Docker + Docker Compose | latest | Local services (DB, cache, storage) |
| Git | 2.4x+ | Version control |

Install PHP locally for tooling/IDE support even though it also runs in Docker — `php artisan` and Pest run faster on the host during development. `pnpm` and `composer` should both be on `PATH` before starting.

---

## 2. Repository Layout

Target end state after this document is followed:

```
procurement-system/
├── apps/
│   ├── api/                        # Laravel — standalone Composer project
│   └── web/                        # Next.js — standalone pnpm project
├── packages/
│   └── shared-types/               # TS enums/constants shared across the web app only
├── docker/
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   └── mariadb/
│       └── init/                   # first-boot SQL (extensions, if any)
├── docs/
│   ├── foundation.md                       # foundation & stack proposal (v2.0) — single source of truth
│   ├── project-setup-and-architecture.md     # this file
│   ├── modules/                            # one doc per module, added as built
│   └── adr/                                # architecture decision records
├── .github/
│   └── workflows/
│       └── ci.yml
├── docker-compose.yml
├── docker-compose.override.yml.example
├── .editorconfig
├── .gitignore
├── pnpm-workspace.yaml              # covers apps/web only
└── README.md
```

Two independent toolchains live side by side in one Git repository. `apps/api` is never added to the pnpm workspace; `apps/web` never touches Composer. CI treats them as two parallel, unrelated jobs (§10).

---

## 3. Step 1 — Repo Skeleton

```bash
mkdir procurement-system && cd procurement-system
git init

mkdir -p apps packages docker/php docker/nginx docker/mariadb/init docs/modules docs/adr .github/workflows

cat > .editorconfig <<'EOF'
root = true

[*]
indent_style = space
indent_size = 4
end_of_line = lf
charset = utf-8
trim_trailing_whitespace = true
insert_final_newline = true

[*.{ts,tsx,js,jsx,json,yml,yaml}]
indent_size = 2
EOF

cat > .gitignore <<'EOF'
# API
apps/api/vendor/
apps/api/.env
apps/api/storage/*.key
apps/api/bootstrap/cache/*.php

# Web
apps/web/node_modules/
apps/web/.next/
apps/web/.env.local

# General
.DS_Store
*.log
EOF

git add -A && git commit -m "chore: repository skeleton"
```

`foundation.md` (v2.0) is the single source of truth in `docs/` — product design and stack together, predating the code it governs.

---

## 4. Step 2 — Scaffold the API App

```bash
composer create-project laravel/laravel:^13.0 apps/api
cd apps/api
```

### 4.1 Core packages

Versions follow `foundation.md` v2.0 §16 (compatibility matrix, August 2026). Pin majors explicitly — do not let Composer resolve them implicitly.

```bash
# Auth & authorization
composer require laravel/sanctum
composer require spatie/laravel-permission:^7.0

# Data & validation
composer require spatie/laravel-data:^4.0

# Search (decision D3″ — Scout's native Meilisearch driver)
composer require laravel/scout
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
# NOTE: never install meilisearch/meilisearch-laravel-scout — deprecated

# Module boundaries (major must match the framework major)
composer require nwidart/laravel-modules:^13.0

# State machines
composer require spatie/laravel-model-states:^2.0

# API contract generation (pin >= 0.13.22: CVE-2026-44262 fixed there)
composer require dedoc/scramble:^0.13.42

# Health checks
composer require spatie/laravel-health:^1.40

# Queue dashboard
composer require laravel/horizon:^5.0

# Precise decimal math
composer require brick/math

# --dev: quality gates
composer require --dev laravel/pint:^1.0
composer require --dev larastan/larastan:^3.10
composer require --dev pestphp/pest:"^4.7" --with-all-dependencies
composer require --dev pestphp/pest-plugin-laravel:^4.0

# --dev: boundary linting (canonical package name; qossmic/* names are abandoned)
composer require --dev deptrac/deptrac
```

Pest stays on `^4.7` deliberately — Pest v5 exists but the plugin/Scramble/testbench matrix still caps at 4.x (see `foundation.md` §13 risks). Revisit quarterly.

### 4.2 Publish and register

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
php artisan vendor:publish --tag=health-config
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"   # then set SCOUT_DRIVER=meilisearch
php artisan module:install   # nwidart/laravel-modules stub, if not auto-registered

./vendor/bin/pest --init
```

### 4.3 Database connection

Set the connection to MySQL/MariaDB in `config/database.php` — Laravel's `mysql` driver connection type covers both MariaDB and MySQL identically; only the host/port/socket differ per environment:

```php
'default' => env('DB_CONNECTION', 'mysql'),

'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'url' => env('DB_URL'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'procurement'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict' => true,
        'engine' => 'InnoDB',   // required — never MyISAM (no transactions/row locks)
    ],
],
```

---

## 5. Step 3 — Scaffold the Web App

```bash
cd ../..   # repo root
pnpm create next-app apps/web \
  --typescript --tailwind --app --src-dir --import-alias "@/*"

cd apps/web
```

### 5.1 Core packages

```bash
pnpm add @tanstack/react-query @tanstack/react-table
pnpm add react-hook-form zod @hookform/resolvers
pnpm add next-intl
pnpm add reactflow
pnpm add lucide-react
pnpm add orval -D
```

### 5.2 shadcn/ui

```bash
pnpm dlx shadcn@latest init
```

### 5.3 pnpm workspace root

Back at the repo root:

```bash
cat > pnpm-workspace.yaml <<'EOF'
packages:
  - "apps/web"
  - "packages/*"
EOF
```

`apps/api` is intentionally absent from this file.

---

## 6. Step 4 — Docker Compose

```yaml
# docker-compose.yml
services:
  mariadb:
    image: mariadb:12.3
    environment:
      MARIADB_DATABASE: procurement
      MARIADB_USER: procurement
      MARIADB_PASSWORD: procurement
      MARIADB_ROOT_PASSWORD: root
    ports:
      - "3306:3306"
    volumes:
      - mariadb_data:/var/lib/mysql
      - ./docker/mariadb/init:/docker-entrypoint-initdb.d
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10

  meilisearch:
    image: getmeili/meilisearch:v1.13
    environment:
      MEILI_MASTER_KEY: meilisearch-dev-key
    ports:
      - "7700:7700"
    volumes:
      - meilisearch_data:/meili_data

  redis:
    image: redis:7.4-alpine
    ports:
      - "6379:6379"

  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    ports:
      - "9000:9000"
      - "9001:9001"
    volumes:
      - minio_data:/data

  api:
    build:
      context: ./docker/php
    working_dir: /var/www/html
    volumes:
      - ./apps/api:/var/www/html
    ports:
      - "8000:8000"
    environment:
      DB_HOST: mariadb
      REDIS_HOST: redis
    depends_on:
      mariadb:
        condition: service_healthy
      redis:
        condition: service_started
    command: php artisan serve --host=0.0.0.0 --port=8000

  web:
    image: node:22-alpine
    working_dir: /app
    volumes:
      - ./apps/web:/app
    ports:
      - "3000:3000"
    command: sh -c "corepack enable && pnpm install && pnpm dev"
    environment:
      NEXT_PUBLIC_API_URL: http://localhost:8000

volumes:
  mariadb_data:
  meilisearch_data:
  minio_data:
```

```dockerfile
# docker/php/Dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev \
    && docker-php-ext-install pdo_mysql zip gd \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html
```

Bring it up:

```bash
docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

`docker-compose.override.yml.example` — commit an example, gitignore the real one — is the place for anything a specific developer's machine needs (Xdebug ports, bind-mount tweaks) without polluting the shared file.

---

## 7. Step 5 — Shared Kernel (API)

This is infrastructure every module depends on. Build it before any module exists.

```
apps/api/app/
├── Console/
├── Exceptions/
│   └── Handler.php              # standard error envelope, see below
├── Http/
│   ├── Middleware/
│   │   ├── EntityScope.php      # reads current entity from the authenticated user, binds it into the container
│   │   └── CorrelationId.php    # generates/propagates X-Correlation-ID
│   └── Controllers/
│       └── HealthController.php
├── Models/                      # SHARED KERNEL models only — User, Setting. Business models live in Modules/*
├── Providers/
│   └── AppServiceProvider.php   # config validation on boot, interface bindings
└── Support/
    ├── Repository/
    │   ├── RepositoryInterface.php
    │   └── BaseRepository.php
    ├── Http/
    │   ├── PaginatedResource.php
    │   └── ApiResponse.php      # error envelope helper
    └── StateMachine/
        └── TransitionGuard.php  # base class, if not using spatie/laravel-model-states directly
```

### 7.1 Error envelope

`Handler.php` renders every exception as:

```json
{
  "statusCode": 422,
  "message": "The given data was invalid.",
  "error": "ValidationException",
  "correlationId": "01J..."
}
```

Wire `CorrelationId` middleware first in the global middleware stack so it's available before the exception handler runs.

### 7.2 Base repository

```php
<?php

namespace App\Support\Repository;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected readonly \Illuminate\Database\Eloquent\Model $model) {}

    public function find(string $id): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->model->newQuery()->find($id);
    }

    public function paginate(int $perPage = 20): \Illuminate\Contracts\Pagination\CursorPaginator
    {
        return $this->model->newQuery()->cursorPaginate($perPage);
    }

    // create(), update(), delete() follow the same shape —
    // every module's repository extends this rather than calling
    // Eloquent statics directly from a service or controller.
}
```

### 7.3 Config validation on boot

```php
// AppServiceProvider::boot()
$rules = [
    'app.key' => 'required|string',
    'database.connections.mysql.host' => 'required|string',
    'services.minio.key' => 'required|string',
];

$validator = Validator::make(config()->all(), $rules);
if ($validator->fails()) {
    throw new \RuntimeException('Invalid configuration: ' . $validator->errors());
}
```

This is the fail-fast-on-boot behavior the original NestJS+Zod setup gave for free — Laravel doesn't validate `.env`/config by default, so this check is what closes that gap.

### 7.4 Health endpoint

```php
// routes/api.php (unversioned, deliberately — health checks aren't a business contract)
Route::get('/health', HealthController::class);
```

Backed by `spatie/laravel-health` checks for: database connection, Redis connection, MinIO/S3 disk write, queue worker heartbeat.

---

## 8. Step 6 — Module Convention

No procurement modules exist yet. What exists is the **template** every future module follows, and the mechanism that enforces its boundaries.

### 8.1 Boundary enforcement

`nwidart/laravel-modules` gives every module its own service provider, config, routes, and namespace. The rule: **a module may only be consumed through classes explicitly bound in its own service provider.** Reaching into `Modules\Catalog\Models\Item` from `Modules\Requisitions` directly is the violation to prevent — route it through an interface `Modules\Catalog` exposes deliberately.

Enforce it in CI, not just by convention. Add `deptrac` (dependency-boundary linter) with a ruleset that forbids cross-module imports outside each module's declared public surface:

```bash
composer require --dev qossmic/deptrac-shim
```

```yaml
# deptrac.yaml
deptrac:
  paths:
    - ./Modules
  layers:
    - name: CatalogPublic
      collectors:
        - type: className
          regex: ^Modules\\Catalog\\(Contracts|Facades)\\.*
    - name: CatalogInternal
      collectors:
        - type: className
          regex: ^Modules\\Catalog\\(?!Contracts|Facades).*
  ruleset:
    CatalogInternal:
      - CatalogPublic   # internal classes may see their own public surface
    # every other layer: forbidden to depend on CatalogInternal directly
```

(Extend one layer pair per module as modules are added — this file grows with the codebase, it isn't written once and frozen.)

### 8.2 The template

Every module, whenever one gets created, is scaffolded to this shape (this is the anatomy from `foundation-laravel-proposal.md` §4.1, repeated here as the literal thing `php artisan module:make` should produce or be configured to produce):

```
Modules/<ModuleName>/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Repositories/
│   ├── <X>RepositoryInterface.php
│   └── <X>Repository.php
├── Services/
├── Policies/
├── Events/
├── Listeners/
├── Jobs/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── Providers/
│   └── <ModuleName>ServiceProvider.php
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── module.json
```

```bash
# Generates the above shape for a module named "Example" —
# used to sanity-check the template works, then removed.
# Real modules (Catalog, Suppliers, Approval, …) are scaffolded
# the same way when their own build phase starts.
php artisan module:make Example
php artisan module:make-controller ExampleController Example
php artisan module:make-request CreateExampleRequest Example
php artisan module:make-resource ExampleResource Example
php artisan module:make-policy ExamplePolicy Example
php artisan module:make-migration create_examples_table Example
```

Run the generator, confirm the tree matches, commit the module registration in `modules_statuses.json`, then delete the `Example` module. Its only purpose is proving the scaffold works before real modules depend on it.

### 8.3 Layering inside a module

Every module's own code still follows Controller → Service → Repository → Eloquent (`foundation-laravel-proposal.md` §3.1). Controllers hold no business logic; services hold no direct Eloquent query chains beyond what the repository exposes; repositories hold no business rules. This is a code-review checklist item, not something a linter enforces on its own — call it out explicitly in the module's own README stub if one exists.

---

## 9. Step 7 — Frontend Architecture Skeleton

```
apps/web/src/
├── app/
│   ├── (auth)/
│   │   └── login/
│   │       └── page.tsx
│   ├── (app)/
│   │   ├── layout.tsx           # sidebar, top bar, breadcrumbs, notification bell
│   │   ├── dashboard/
│   │   │   └── page.tsx         # placeholder — no procurement widgets yet
│   │   └── admin/
│   └── proxy.ts                 # Next 16's middleware.ts replacement
├── components/
│   ├── ui/                      # shadcn primitives
│   ├── layout/
│   └── shared/                  # DataTable, StatusBadge — generic, feature-agnostic
├── features/                    # empty — one folder per API module, added when that module lands
├── lib/
│   ├── api/                     # GENERATED by Orval — never hand-edited
│   ├── auth/
│   └── utils/
├── messages/
│   ├── en.json
│   └── (other locales, per Q2 in the proposal doc)
└── styles/
```

`features/` starts empty on purpose — its structure mirrors `apps/api/Modules/*` one-for-one, but nothing goes in it until the corresponding API module exists to consume.

### 9.1 Orval config

```typescript
// orval.config.ts
export default {
  api: {
    input: 'http://localhost:8000/docs/api.json',   // Scramble's generated spec
    output: {
      target: './src/lib/api/generated.ts',
      client: 'react-query',
    },
  },
};
```

Wire `pnpm orval` into the same CI step that runs the frontend build, so a spec change that isn't reflected in the generated client fails the build rather than drifting silently — this is the concrete enforcement of the contract-first rule from the proposal doc.

---

## 10. Step 8 — Tooling & Quality Gates

### 10.1 Backend

```json
// apps/api/pint.json
{
  "preset": "laravel"
}
```

```neon
# apps/api/phpstan.neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - Modules
    level: 6
```

### 10.2 Husky at the repo root

```bash
cd procurement-system
pnpm add -D husky -w
pnpm exec husky init
```

```bash
# .husky/pre-commit
cd apps/api && composer pint --test && composer stan
cd ../web && pnpm lint && pnpm typecheck
```

### 10.3 CI skeleton

```yaml
# .github/workflows/ci.yml
name: CI

on:
  pull_request:
  push:
    branches: [develop, main]

jobs:
  api:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: apps/api
    services:
      mariadb:
        image: mariadb:12.3
        env:
          MARIADB_DATABASE: procurement_test
          MARIADB_ROOT_PASSWORD: root
        ports: ["3306:3306"]
        options: >-
          --health-cmd="healthcheck.sh --connect --innodb_initialized"
          --health-interval=5s --health-retries=10
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
      - run: composer install --prefer-dist --no-progress
      - run: composer pint --test
      - run: composer stan
      - run: php artisan test --parallel

  web:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: apps/web
    steps:
      - uses: actions/checkout@v4
      - uses: pnpm/action-setup@v4
      - uses: actions/setup-node@v4
        with:
          node-version: "22"
          cache: "pnpm"
      - run: pnpm install --frozen-lockfile
      - run: pnpm lint
      - run: pnpm typecheck
      - run: pnpm build
```

Two independent jobs, matching the two independent toolchains — neither blocks on the other, both block merge.

---

## 11. Step 9 — Environment & Config

`.env.example` (API) — structural keys only, no business/procurement values:

```dotenv
APP_NAME=Procurement
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=procurement
DB_USERNAME=procurement
DB_PASSWORD=procurement

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=meilisearch-dev-key

FILESYSTEM_DISK=minio
MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_BUCKET=procurement
MINIO_ENDPOINT=http://minio:9000

SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

`.env.local.example` (Web):

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000
```

The boot-time validator in §7.3 checks that every key it lists is present and non-empty — extend that rule list as new required config is introduced, rather than discovering a missing key at request time in production.

---

## 12. Step 10 — Verify the Skeleton

Exit criteria for "the scaffold is done" — deliberately identical to the exit criteria in `foundation-laravel-proposal.md` §8.1, repeated here because this document is what actually gets you there:

- [ ] `docker compose up -d` brings up mariadb, redis, minio, api, web with no errors
- [ ] `GET /health` returns 200 from the API directly
- [ ] `GET /health` is callable from the web app through the **Orval-generated** client (not a hand-written `fetch` call) — proves the contract-first pipeline works end to end
- [ ] Both CI jobs (`api`, `web`) pass on an empty PR that changes nothing but a comment
- [ ] `php artisan module:make Example` scaffolds the module template correctly, and the `deptrac` check fails when a deliberately-introduced cross-module violation is added, then passes once removed
- [ ] `pnpm lint`, `composer pint --test`, and `composer stan` all pass with zero suppressions
- [ ] A developer following only this document, on a clean machine, reaches all of the above in under 30 minutes

---

## 13. Naming Conventions Reference

| Layer | Convention | Example |
|---|---|---|
| DB tables | `snake_case`, plural | `purchase_requisitions` |
| Eloquent models | `PascalCase`, singular | `PurchaseRequisition` |
| API routes | `kebab-case`, plural, versioned | `/api/v1/purchase-requisitions` |
| PHP classes/files | `StudlyCase.php` matching class name | `CreateItemRequest.php` |
| PHP methods/variables | `camelCase` | `resolveAssignees()` |
| Module folders | `StudlyCase`, singular business domain | `Modules/Approval` |
| Frontend routes | Next.js App Router segment conventions | `app/(app)/dashboard/page.tsx` |
| Frontend components | `PascalCase.tsx` | `DataTable.tsx` |
| Frontend hooks/utils | `camelCase.ts` | `useCorrelationId.ts` |
| Git branches | `feature/*`, `fix/*` off `develop` | `feature/approval-engine-skeleton` |
| Commits | Conventional Commits | `feat(catalog): add item repository` |

---

## 14. Out of Scope

This document does not cover, and should not be extended to cover:

- Which procurement modules exist or their build order — that's `foundation-laravel-proposal.md` §8.
- The approval engine's data model or algorithm — that's `foundation-laravel-proposal.md` §5.
- Any table beyond the shared-kernel ones needed to prove the skeleton works (`users`, `health` probes).
- Business validation rules, workflow logic, or anything a domain expert rather than an engineer would need to weigh in on.

When the first real module (per the roadmap) starts, its own doc goes in `docs/modules/`, following the template in §8.2 — this document doesn't grow to include it.

---

## 15. Change Log

| Version | Date | Change |
|---|---|---|
| 1.0 | — | Initial scaffold guide: prerequisites, repo layout, API and web app initialization, Docker Compose, shared kernel, module convention and boundary enforcement, frontend skeleton, tooling/CI, environment config, verification checklist, naming reference |
