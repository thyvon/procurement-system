# Procurement Management System — Foundation (Laravel + MariaDB/MySQL)

**Version:** 2.0 (Proposal)
**Status:** Draft for review. Revision 2.0 (August 2026) pins every component to its current stable release with verified pairwise compatibility (§16), resolves former open questions Q9–Q11, and adds cross-cutting guarantees (§3.3) covering entity-scoping enforcement, audit immutability, timezone policy, N+1 prevention, and CORS/CSRF configuration.
**Relationship to prior documents:** This document is now the single source of truth for both product design and stack. It supersedes the original NestJS/Postgres baseline entirely.

---

## 0. What changes and what doesn't

| Area | Original (NestJS baseline) | This proposal |
|---|---|---|
| Backend framework | NestJS 11 (TypeScript) | **Laravel 13** (PHP 8.4, minimum 8.3) — current major, released March 2026; bug fixes until Q3 2027, security fixes until Q1 2028 |
| ORM | Prisma 7 | Eloquent (ships with the framework — no separate version to track) |
| Database | PostgreSQL 17 | **MariaDB 12.3 LTS** (recommended, latest LTS) or MariaDB 11.8/11.4 LTS; MySQL 8.4 LTS as the alternative |
| Frontend | Next.js 16 / React 19 | **Unchanged in kind** — Next.js 16.3 (Active LTS, ≥ 16.3.3 for the August 2026 security release), React 19.2.x pinned ≥ 19.2.4 (React2Shell CVE-2025-55182 fix), Node.js 22 LTS |
| Approval engine logic, data model shape, roadmap phases, business risks, glossary | — | **Unchanged in substance** — only the implementation vocabulary changes (Eloquent instead of Prisma, migrations instead of `.prisma` files) |

Everything below either replaces or extends the corresponding section number in the original NestJS/Postgres baseline.

---

## 1. Product Overview

No change. See the original NestJS baseline §1. The design principles in §1.1 (configuration over code, immutable history, one engine many documents, modular boundaries, contract-first, localization from day one) all hold under Laravel/MySQL — none of them are Postgres- or Nest-specific.

---

## 2. Tech Stack

### 2.1 Backend

| Layer | Original | Proposed | Notes |
|---|---|---|---|
| Runtime | Node.js 22 | **PHP 8.4** (minimum 8.3) | Laravel 13 supports PHP 8.3–8.5. Target **8.4** — mature, security-supported, and every package below declares `^8.3` compatibility. PHP 8.5 works but offers no package-level advantage here; don't chase it in year one |
| Framework | NestJS 11 | **Laravel 13** (`^13.0`) | Released 2026-03-17. Note: Laravel no longer ships LTS releases (Laravel 6 was the last). Each major gets ~18 months of bug fixes + 2 years of security fixes; plan an annual upgrade cadence, see §2.4 |
| ORM | Prisma 7 | **Eloquent** | Ships with Laravel. Workflow shifts from a shared `.prisma` schema file to migration files as the source of truth (§4) |
| Database | PostgreSQL 17 | **MariaDB 12.3 LTS** (recommended) or MySQL 8.4 LTS | See §2.5 for the reasoning and the Mroonga caveat that drives this choice |
| Cache / queue store | Redis 7.4 | **Redis 7.4+** (Redis 8.x compatible) | Laravel's `predis`/phpredis clients work with both. Pin whichever the ops team standardizes on; nothing in this design is version-sensitive |
| Job queue | BullMQ | **Laravel Queue (Redis driver) + Horizon ^5** | Horizon is Laravel's queue dashboard — direct equivalent of Bull Board |
| Auth | Passport + JWT | **Laravel Sanctum** (bundled) + custom refresh-token rotation table | Decision made (was Q10): Sanctum personal access tokens for API clients, plus a first-party `refresh_tokens` table implementing rotating refresh tokens — avoids the third-party `tymon/jwt-auth` dependency whose maintenance cadence lags framework majors. See §12 |
| Authorization | CASL | **Laravel Policies/Gates + `spatie/laravel-permission` ^7** | v6 is EOL-track; v7 is current and explicitly supported by Scramble's test matrix. Policies answer "what may this user do to this record"; the permission package answers "what role does this user hold" |
| Validation / DTOs | class-validator + class-transformer | **Form Requests** (native) + **`spatie/laravel-data` ^4** for typed DTOs | Form Requests replace per-endpoint DTO validation; laravel-data gives typed, validated data objects where a plain array isn't enough. Scramble has first-class laravel-data support |
| API contract | `@nestjs/swagger` | **`dedoc/scramble` ^0.13.42** | Infers OpenAPI 3.1 from PHP types and Form Requests. Supports `illuminate ^13`. **Pin ≥ 0.13.22**: CVE-2026-44262 (code injection via spec generation) affects 0.13.2–0.13.21. `knuckles/scribe` remains the fallback if inference gaps require manual annotation |
| Events | `@nestjs/event-emitter` | **Native Laravel Events & Listeners** | No package needed |
| Scheduling | `@nestjs/schedule` | **Laravel Task Scheduling** (native) | `routes/console.php`, driven by a single cron entry or `schedule:work`; add a scheduler-heartbeat health probe, §10 |
| Config | `@nestjs/config` + Zod | **`.env` + config files** + a boot-time validation check | Laravel doesn't validate `.env` by default; add an `AppServiceProvider::boot()` check (or `spatie/laravel-settings` for admin-editable runtime settings) so misconfiguration fails fast, matching the original intent |
| Health checks | `@nestjs/terminus` | **`spatie/laravel-health` ^1.40** (latest 1.40.2 — no 2.x exists; verified on Packagist) | Direct equivalent — DB, Redis, storage, queue-worker heartbeat, scheduler heartbeat, `/health` endpoint |
| Rate limiting | `@nestjs/throttler` | **Laravel's built-in `throttle` middleware** | No package needed |
| Decimal math | `decimal.js` | **`brick/math`** | Eloquent's `decimal` cast already returns strings (never floats) by default, which avoids the JS-float trap this row exists to prevent; `brick/math` handles arithmetic on those strings |

### 2.1.1 Backend dev-quality toolchain (pinned)

| Tool | Version | Role |
|---|---|---|
| Pest | `^4.7` | Test runner (PHPUnit 12 underneath). Pest v5 just released; adopt only after the full plugin matrix (`pest-plugin-laravel`, Scramble's dev deps, orchestra/testbench) declares support — today they cap at Pest 4.x |
| Larastan | `^3.10` | Static analysis (PHPStan 2.x for Laravel); level 6 target per setup doc |
| Laravel Pint | `^1` | Code style (official PHP-CS-Fixer wrapper) |
| `nwidart/laravel-modules` | `^13` | Module boundaries — v13 is the Laravel-13-compatible line (v12 targets L12, v11 targets L11 — match majors exactly) |
| deptrac | `deptrac/deptrac` | Boundary linting in CI — note the canonical package is now `deptrac/deptrac` (the old `qossmic/deptrac-shim` name is abandoned) |
| spatie/laravel-model-states | `^2` | Document state machines |
| spatie/laravel-health | `^2` | `/health` probes |

### 2.2 Frontend

**Same architecture as the original, versions pinned (August 2026):** Next.js **16.3.x Active LTS — pin ≥ 16.3.3**, which carries the August 2026 security release fixing two critical RCE vulnerabilities; React **19.2.x pinned ≥ 19.2.4** (`react` and `react-dom` exact-pinned, no caret — required for the React2Shell CVE-2025-55182 fix); Node.js 22 LTS; Tailwind 4; shadcn/ui; TanStack Query v5; React Hook Form + Zod v4; Orval; next-intl v4; TanStack Table v8; **`@xyflow/react` v12** (the `reactflow` npm package is deprecated/renamed — do not install `reactflow`); lucide-react. Orval simply points at whatever OpenAPI document Scramble generates instead of what Nest's Swagger module generated; the frontend does not know or care which backend produced the spec.

Two frontend rules added by this revision:
- **Pin `next`, `react`, `react-dom` exactly** (no `^`) — patch releases of this trio have carried critical security fixes in 2026; let Renovate open the PRs instead of floating.
- **Turbopack is the default build/dev engine** on Next.js 16.3 — no webpack fallback config; if a library breaks under Turbopack, replace the library rather than re-enabling webpack.

### 2.3 Infrastructure & tooling

| Concern | Original | Proposed |
|---|---|---|
| Containers | Docker + Docker Compose | Unchanged |
| Reverse proxy | NGINX / NGINX Proxy Manager | Unchanged |
| Object storage | MinIO | Unchanged — Laravel's `Storage` facade has a native S3-compatible driver; point it at MinIO. This is *simpler* than the original's hand-rolled `IStorageService`, since presigned URLs are a built-in method on the disk (`Storage::disk('minio')->temporaryUrl(...)`) |
| CI/CD | GitHub Actions | Unchanged |
| Monorepo tool | pnpm workspaces | **pnpm workspaces for `apps/web` only**; Laravel is not a Node project and doesn't join a pnpm workspace. See §4 for the resulting repo layout |
| Lint / format | ESLint + Prettier + Husky + lint-staged | **Laravel Pint** (PHP-CS-Fixer wrapper, official) + **Larastan** (PHPStan for Laravel) on the backend; ESLint/Prettier unchanged on the frontend; Husky stays at the repo root and simply also runs `composer pint --test` and `composer stan` |
| API tests | Jest + Supertest | **Pest** (or PHPUnit) using Laravel's built-in `Illuminate\Foundation\Testing` HTTP helpers (`postJson`, `getJson`, …) | No Supertest equivalent needed — this tooling ships with the framework |
| E2E tests | Playwright | Unchanged — the frontend is still a separate app, so Playwright still drives it end to end |
| Dependency updates | Renovate/Dependabot | Unchanged — both understand `composer.json` as well as `package.json` |
| Error tracking | Sentry | Unchanged — official first-party Laravel SDK |

### 2.4 Version policy

Laravel no longer has LTS releases — every major gets ~18 months of bug fixes and 2 years of security fixes, on an annual cadence (Laravel 13: security until March 2028). The policy therefore becomes **annual upgrade discipline** instead of "sit on an LTS":

- Pin `laravel/framework` and all first-party packages (`sanctum`, `horizon`) to exact majors in `composer.json`; commit both lockfiles.
- CI/production installs with `composer install --no-dev --prefer-dist`, gated by a frozen-lockfile check; frontend mirrors this with `pnpm install --frozen-lockfile`.
- Each Q1 when a new Laravel major lands: run the upgrade on a branch within the quarter, while the previous major still receives security fixes — never accumulate skipped majors ("skipped-version upgrades are where the pain hides").
- Same discipline for Next.js: its Active LTS line moves continuously (16.x today); track patch releases immediately via Renovate since critical fixes ship as patches.

### 2.5 MariaDB vs MySQL — the decision, revised

The original database choice (Postgres 17) was driven substantially by one requirement: search across scripts that don't delimit words with spaces (Khmer, Thai, Lao, Japanese, Chinese), solved via `pg_trgm` with **PGroonga** as the named fallback. Revision 2.0 changes the recommendation:

**Database: MariaDB 12.3 LTS** (released May 2026, latest LTS) — fully open-source, community-governed, actively maintained alongside the 11.8/11.4 LTS lines. MySQL 8.4 LTS remains valid if the team has existing MySQL operational expertise or wants Oracle support contracts.

**Search is decoupled from the database engine entirely (decision D3″, closing former Q9): Laravel Scout + Meilisearch is now the primary search path**, for one decisive reason:

- **Mroonga is not included in the official `mariadb` Docker images or standard distro packages.** Running it means custom-built images or MariaDB's special repositories on every environment — a permanent operational tax that contradicts the "clone to running in 3 commands" Phase 0 goal, for a benefit Meilisearch delivers anyway.
- Meilisearch tokenizes at the character/n-gram level regardless of script, ships typo tolerance out of the box, integrates with Eloquent through Scout's native `meilisearch` driver (the separate `meilisearch-laravel-scout` package is deprecated — Scout supports Meilisearch first-party since v10), and runs as a single static binary in Compose with zero database-engine gymnastics.
- It keeps the door open: switching engines later costs one `SCOUT_DRIVER` env change plus a re-index command.

Mroonga remains documented as the fallback if the team later decides external search infrastructure is unacceptable — but it then requires a custom MariaDB image, and §7.3's real-data validation spike applies doubly.

---

## 3. Design Patterns

### 3.1 Architecture-level

#### Modular Monolith
Still one deployable artifact, still strict internal boundaries — enforced differently. Laravel doesn't have Nest's compile-time module system, so use **`nwidart/laravel-modules` ^13** (v13 is the Laravel-13 line; match package major to framework major exactly): each business module gets its own service provider, routes file, config, migrations, and namespace. A module may only be consumed through the classes it explicitly binds in its service provider (its "public API"); reaching into another module's `Models` or `Repositories` namespace directly is the equivalent violation of Nest's "only import the public barrel" rule. Enforce it with a Larastan custom rule or **`deptrac/deptrac`** in CI — note the canonical package name is now `deptrac/deptrac` (the old `qossmic/*` names are abandoned). The ruleset direction: *every other layer is forbidden from depending on a module's internal layer; only the module itself may see its own internals.*

#### Domain-Driven Design (light)
Unchanged. Same ubiquitous language (`PurchaseRequisition`, `GoodsReceiptNote`, `ApprovalStage`), same glossary (§14 of the original NestJS baseline), now expressed as Eloquent model class names instead of Prisma model names.

#### Event-Driven Architecture
- In-process: native Laravel **Events + Listeners** (`Event::listen`, or attribute-based listener discovery) for immediate side effects like audit logging.
- Deferred: **Laravel Queue** (Redis driver) for anything slow or retryable — notifications, PDF rendering, escalation sweeps — with **Horizon** for visibility, retries, and metrics, replacing BullMQ + Bull Board one-for-one.
- Event naming: unchanged — `<aggregate>.<past-tense-verb>`, e.g. `requisition.submitted`. Implement as Laravel event class names (`RequisitionSubmitted`) with that string exposed as a constant for anything that needs the wire-format name (webhooks, audit rows).

#### Contract-First API
The intent survives, the mechanism inverts slightly. Nest generates the OpenAPI spec *from* decorators the developer writes deliberately. Laravel + Scramble instead **infers** the spec from Form Request rules and PHP return types the developer would write anyway — arguably less ceremony, same outcome: **the frontend client is always generated, never hand-written.** Wire the Orval codegen step into CI in Phase 0, exactly as the original warns — "if it's a manual step, it will be abandoned by week six" applies regardless of backend language.

#### Layered Architecture (within each module)
```
Controller  →  Service  →  Repository  →  Eloquent
   HTTP        business       data          DB
  concerns      logic        access
```
Structurally identical to the original. Controllers hold no business logic, services hold no raw Eloquent query chains beyond what the repository exposes, repositories hold no business rules. The one Laravel-specific temptation to resist: Eloquent makes it easy to call `Model::where(...)` directly from a controller or service. Don't — route all reads and writes for a module through that module's repository, same discipline as the original, for the same reason (one place to optimize a query, natural home for scoping).

### 3.2 Code-level

| Pattern | Where it is used | Laravel implementation |
|---|---|---|
| **Repository** | Every module | Plain PHP class wrapping Eloquent queries, bound to an interface in the module's service provider |
| **Dependency Injection** | Framework-wide | Laravel's service container; interface bindings in `register()` for anything with a plausible second implementation (`StorageServiceInterface`, `NotificationChannelInterface`) |
| **DTO** | Every endpoint | Form Requests for input; **API Resources** (`JsonResource`) for output — Resources are Laravel's direct answer to "never expose a model directly," shaping the response and preventing column leakage the same way the original's response DTOs did |
| **Strategy** | Approval condition evaluators, assignee resolvers, quorum evaluators, notification channels | Plain classes implementing a small interface, resolved from the container by a key stored in configuration data — identical in spirit to the original |
| **Specification** | Approval condition trees | Same JSON structure (§5.5), same recursive evaluator — the pattern doesn't touch the database, so nothing changes here |
| **Chain of Responsibility** | Approval stage progression | Unchanged in design |
| **State Machine** | Document lifecycles | **`spatie/laravel-model-states`** — a maintained, Laravel-idiomatic finite-state-machine package — or a hand-rolled `TransitionGuard` base class if the team prefers no dependency here, matching the original's own base class |
| **Polymorphic Association** | Approvals, attachments, audit, comments | **Native Eloquent polymorphic relations** (`morphTo` / `morphMany`) — this is arguably a strict improvement over the original, since Prisma has no first-class polymorphic-association support and the original design already had to work around that; Eloquent supports it out of the box |
| **Snapshot / Memento** | Approval workflow versioning | JSON column, same as original |
| **Observer / Pub-Sub** | Audit log, notifications | **Eloquent Model Observers** for model-lifecycle events, plus the Events/Listeners above for domain events — subscribers still know about events, domain modules still don't know about subscribers |
| **Unit of Work** | Multi-table writes | `DB::transaction()` wraps number assignment + state change + audit write, replacing `prisma.$transaction()` |
| **Guard + Policy** | Route authorization | Roles via `spatie/laravel-permission` say who you are; **Laravel Policies** say what you may do to which record — this is a closer native fit than the original's CASL-on-Nest-Guards combination, since Policies are a first-class Laravel concept |
| **Scoping (was "Prisma Client Extension")** | Entity scoping | **Eloquent Global Scopes applied automatically on every entity-scoped model** via a shared `BelongsToEntity` trait (never hand-applied per model), reading the current entity from a request-scoped container binding set by middleware. Middleware alone is not enough — one forgotten `where(entity_id)` in any repository leaks data across entities, so the global scope is the backstop and every scoped model gets a negative Pest test proving cross-entity reads return nothing |
| **Interceptor** | Cross-cutting concerns | **Laravel Middleware** — response shaping, correlation IDs, timing all move here; Laravel doesn't distinguish interceptors from middleware the way Nest does, so this collapses into one concept |

#### Document state machines

Unchanged — see §3.2 of the original NestJS baseline. The state lists for Purchase Requisition, RFQ, Purchase Order, and Goods Receipt Note don't reference the framework or database at all.

### 3.3 Cross-cutting guarantees (added in revision 2.0)

These are framework-level rules enforced once in the shared kernel so no module has to remember them:

1. **N+1 prevention:** `Model::preventLazyLoading(! app()->isProduction())` is called in `AppServiceProvider::boot()`. Lazy-loading in dev/test throws immediately instead of shipping silent per-row queries to production. Larastan and Pint will not catch this class of bug — only this runtime rule does.
2. **Audit immutability:** the `audits` table is append-only by construction — the Audit module exposes *no* update/delete repository methods, the model overrides are removed, and a Pest test asserts that attempting `update()`/`delete()` on an audit row throws. Database-level defense-in-depth: grant only `SELECT`/`INSERT` on `audits` to the application DB user.
3. **Timezone policy (new decision D11):** all timestamps stored in **UTC** (`created_at`, `updated_at`, approval deadlines, SLA clocks); each organization carries an IANA timezone setting used only for *display* and for computing local-date boundaries in escalation sweeps. Escalation jobs always evaluate against UTC instants. This prevents the classic bug where a scheduler in server-local time fires escalation sweeps twice or never.
4. **CORS + CSRF (completes the security baseline):** Sanctum's SPA mode requires explicit configuration from day one — `config/cors.php` allows only the web app's origins with `supports_credentials: true`; `SANCTUM_STATEFUL_DOMAINS` lists the web origin; cookie-based auth for the first-party SPA, bearer tokens for mobile. Both configs live in `.env.example` from Phase 0, not discovered during Phase 1 auth work.
5. **Scheduler heartbeat:** escalation sweeps and SLA dashboards depend on the scheduler being alive; `spatie/laravel-health` gets a scheduled-task heartbeat check alongside the queue-worker probe so a dead `schedule:work` fails `/health` instead of failing silently.

---

## 4. Repository Structure

```
procurement-system/
├── apps/
│   ├── api/                        # Laravel 13 — standalone PHP project, own composer.json
│   │   ├── app/
│   │   │   ├── Console/
│   │   │   ├── Exceptions/          # Handler.php — global exception formatting
│   │   │   ├── Http/
│   │   │   │   ├── Kernel.php
│   │   │   │   ├── Middleware/      # Scoping, correlation ID, throttling
│   │   │   │   └── Controllers/     # Thin — only truly cross-module controllers live here
│   │   │   ├── Models/              # SHARED KERNEL models only (User, Setting)
│   │   │   ├── Providers/
│   │   │   └── Support/             # BaseRepository, PaginatedResource, TransitionGuard base
│   │   ├── Modules/                 # nwidart/laravel-modules — MIRRORS the original's modules/
│   │   │   ├── Auth/
│   │   │   ├── Users/
│   │   │   ├── Organization/
│   │   │   ├── Audit/
│   │   │   ├── Notifications/
│   │   │   ├── Catalog/
│   │   │   ├── Suppliers/
│   │   │   ├── Approval/            # THE ENGINE — see §5
│   │   │   ├── Requisitions/
│   │   │   ├── Rfq/
│   │   │   ├── PurchaseOrders/
│   │   │   ├── Receiving/
│   │   │   ├── Budget/
│   │   │   └── Reporting/
│   │   ├── database/
│   │   │   ├── migrations/          # Framework-level migrations (users, cache, jobs tables)
│   │   │   └── factories/
│   │   ├── config/
│   │   ├── routes/
│   │   │   ├── api.php              # Versioned: Route::prefix('v1')
│   │   │   └── console.php          # Scheduled tasks
│   │   ├── tests/
│   │   │   ├── Feature/             # HTTP-level, mirrors original's e2e/integration split
│   │   │   └── Unit/
│   │   ├── composer.json
│   │   └── artisan
│   └── web/                        # Next.js 16.3 — UNCHANGED from the original baseline §4
│       └── (identical to original)
├── packages/
│   └── shared-types/               # TS enums + constants shared across the web app only
│                                    # (no PHP↔TS package sharing; PHP enums live in Modules/*/Enums)
├── docker/
├── docs/
│   ├── foundation.md                  # THIS FILE — merged foundation & stack proposal (v2.0)
│   ├── project-setup-and-architecture.md
│   ├── modules/
│   └── adr/
├── .github/workflows/ci.yml
├── pnpm-workspace.yaml              # covers apps/web only now
└── README.md
```

The main structural difference from the original: **`apps/api` is its own PHP project root**, not a member of the pnpm workspace. It gets its own CI job (PHP toolchain, `composer install`, Pest, Pint, Larastan) running alongside — not inside — the Node-based job for `apps/web`.

### 4.1 Anatomy of a module

```
Modules/Catalog/
├── Http/
│   ├── Controllers/
│   │   ├── ItemController.php
│   │   └── CategoryController.php
│   ├── Requests/                    # was DTO — input validation
│   │   ├── CreateItemRequest.php
│   │   └── UpdateItemRequest.php
│   └── Resources/                   # was response DTO — output shaping
│       ├── ItemResource.php
│       └── ItemCollection.php
├── Models/
│   ├── Item.php
│   ├── Category.php
│   └── ItemPrice.php
├── Repositories/
│   ├── ItemRepositoryInterface.php
│   └── ItemRepository.php
├── Services/
│   └── ItemService.php
├── Policies/
│   └── ItemPolicy.php
├── Events/
│   └── ItemPriceChanged.php
├── Listeners/
├── Jobs/                            # queued work — was a BullMQ processor
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── Providers/
│   └── CatalogServiceProvider.php   # binds interfaces, registers routes — the module's "index.ts"
├── routes/
│   └── api.php
└── module.json
```

Every module looks identical. No exceptions — same rule as the original, same reasoning.

---

## 5. Approval Engine

The engine's **design is entirely unchanged** — §5.1 through §5.5 and §5.9–§5.12 of the original NestJS baseline describe action types, stage configuration, quorum rules, assignee resolvers, the specification-pattern condition tree, guardrails, delegation/escalation, the API surface, and the UI requirements, and none of that depends on Nest or Postgres. Read those sections as-is. What follows covers only the parts that touch storage or transaction mechanics.

### 5.6 Data model — storage deltas

Same tables, same columns, same relationships as the original NestJS baseline §5.6, with these substitutions:

| Original (Postgres/Prisma) | This proposal (MySQL/MariaDB/Eloquent) |
|---|---|
| `jsonb` columns (`name`, `verb_past`, `definition`, `context`, `payload`, `workflow_snapshot`, `config`) | `JSON` columns. MySQL 8 and MariaDB 10.2+ both have a native `JSON` type with indexable generated columns and `JSON_TABLE`/`JSON_EXTRACT` functions. Functionally equivalent for this use case; MySQL's JSON implementation is marginally faster on deep queries, MariaDB's is a `LONGTEXT` with a `CHECK` constraint under the hood pre-11.x — irrelevant for the read/write patterns here (store-and-retrieve, rarely queried *into*) |
| UUID v7 primary keys | **ULID** (recommended) via Laravel's native `HasUlids` trait, or UUID v7 via `Str::uuid7()` (Laravel 11+). Both are time-sortable and index-friendly on a `BINARY(16)`/`CHAR(26)` column; ULID is the more idiomatic Laravel default and ships with zero extra packages |
| `prisma.$transaction()` | `DB::transaction(function () { ... })` |
| `SELECT … FOR UPDATE` | `->lockForUpdate()` on the Eloquent/query builder — identical row-locking semantics on InnoDB (MySQL/MariaDB's default and required engine here; **do not** use MyISAM, it doesn't support transactions or row locks at all) |

Every table listed in the original NestJS baseline §5.6 (`approval_action_types` through `approval_delegations`) carries over unchanged in name, columns, and purpose — only expressed as Laravel migrations instead of `.prisma` model blocks, and as Eloquent models instead of Prisma Client types.

### 5.7 Runtime algorithm

Unchanged step-for-step from the original NestJS baseline §5.7, with the transaction and locking primitives swapped per the table above. Idempotency-key handling and concurrent-actor handling are storage-agnostic and need no changes.

### 5.8 Versioning and snapshots

Unchanged. "A workflow definition is never edited once it has been used" and the full-JSON-snapshot-per-instance rule are business rules, not database features.

---

## 6. Core Data Model

Table shapes are unchanged from the original NestJS baseline §6.1–§6.7 — same tables, same key columns, same relationships. Apply these substitutions uniformly wherever the original says `jsonb`, `Decimal`, or `UUID`:

| Original type | MySQL/MariaDB type |
|---|---|
| `jsonb` (localized text, specs, addresses) | `JSON` |
| `Decimal(18, 4)` (money) | `DECIMAL(18,4)` — identical; Eloquent's `decimal` cast returns a string, never a float, preserving the original's "never use JS floats for money" rule in its PHP equivalent |
| UUID primary key | ULID (`CHAR(26)`) or UUID (`CHAR(36)`/`BINARY(16)`) — pick one convention repo-wide |
| Prisma enum (closed sets, e.g. document status) | PHP 8.1+ **native enum** backing a `string`/`tinyint` column via an Eloquent cast — same "enum for closed sets, lookup table for admin-editable sets" rule from §6.8 applies unchanged |

### 6.8 Modelling rules — deltas only

- **Money, localized text, soft delete, line numbers:** unchanged from the original — none of those rules are database-specific.
- **Indexes:** unchanged in intent (one on every FK, every filtered `status` column, `document_no`, and the search columns; composite index on `approval_tasks (assignee_id, status)` for the inbox query). MySQL/MariaDB and Postgres index the same columns for the same reasons here; only the `CREATE INDEX` syntax in the migration file differs, and Laravel's schema builder hides that difference entirely (`$table->index(['assignee_id', 'status'])` works on both).
- **Search columns:** this is the one place the storage engine choice materially changes the column design. See §7.3 below — it replaces the original's `search_vector tsvector` + `search_raw text` pair.

---

## 7. Conventions

### 7.1 General

| Original | Proposed |
|---|---|
| Database: `snake_case`, plural table names | **Unchanged** — this happens to be both Prisma's convention (via `@@map`) *and* Eloquent's native default, so nothing to configure |
| Prisma models: `PascalCase` singular, mapped via `@@map` | Eloquent models: `PascalCase` singular — same convention, no mapping annotation needed, it's the framework default |
| API routes: `kebab-case` plural | Unchanged — `/api/v1/purchase-requisitions` |
| TypeScript: `PascalCase` classes, `camelCase` variables | PHP: `PascalCase` classes, `camelCase` methods and variables (PSR-12) |
| Files: `kebab-case.role.ts` | PHP: `StudlyCase.php` matching the class name (PSR-4 / Laravel convention) — `ItemService.php`, `CreateItemRequest.php` |
| Events: `<aggregate>.<past-tense-verb>` | Unchanged as the wire-format string; the PHP class itself is `StudlyCase` (`RequisitionSubmitted`) |

**Every table still gets:** `id`, `created_at`, `updated_at`, `created_by`, `updated_by`, `deleted_at` where soft-deletable — Laravel's `$table->timestamps()` and `SoftDeletes` trait cover most of this with less boilerplate than the original required.

**API:** versioned under `/api/v1`, cursor pagination for large lists (Laravel's `cursorPaginate()` is native), offset pagination for small admin lists, consistent error envelope (`{ statusCode, message, error, correlationId }` — implement once in `App\Exceptions\Handler`), every endpoint typed so Scramble can infer it, mutating endpoints accept an idempotency key — all unchanged from the original.

**Git / commit conventions:** unchanged — `main` ← `develop` ← `feature/*`, PR + CI green required, Conventional Commits.

**Comments:** unchanged — explain *why*, never *what*.

### 7.2 Document numbering

Unchanged in design from the original NestJS baseline §7.2 — same `document_number_formats` table, same tokens, same rules (generated inside the transaction that changes document state, assigned on submit not on draft creation, immutable once assigned, indexed separately from the primary key). The concurrency test the original calls out — **generate 100 numbers in parallel, assert zero duplicates** — matters just as much here; write it against `DB::transaction()` + `lockForUpdate()` on the sequence row exactly as the original wrote it against Prisma's equivalents.

### 7.3 Search — Scout + Meilisearch (decided, D3″)

This is the one area where swapping Postgres for MariaDB is a genuine engineering trade-off, because the original's search strategy leaned on Postgres extensions (`pg_trgm`, `tsvector`, PGroonga) for exactly the multi-script requirement this system has (Khmer, Thai, Lao, Japanese, Chinese — none of them space-delimited).

**Decision: Laravel Scout + Meilisearch.** Closed as of revision 2.0 (was Q9).

| Content | Method | Notes |
|---|---|---|
| All catalog/supplier free text, every locale | Meilisearch via Scout's native driver (`Searchable` trait) | Character-level tokenization regardless of script + typo tolerance out of the box; replaces both the original's `tsvector` and `pg_trgm` paths with one engine |
| Codes and identifiers (SKU, document numbers) | Plain indexed column + `LIKE 'prefix%'` / exact match | Typo tolerance matters less here; no reason to push identifiers through the search service |

Implementation rules:
- **Use Laravel Scout's built-in `meilisearch` driver** (`composer require laravel/scout meilisearch/meilisearch-php http-interop/http-factory-guzzle`). The standalone `meilisearch/meilisearch-laravel-scout` package is **deprecated** — do not install it.
- Run indexing through the queue (`'queue' => true` in `config/scout.php`) so catalog writes never block on HTTP calls to Meilisearch.
- Pin the Meilisearch binary version per environment and track its breaking-change releases alongside `meilisearch-php` compatibility, per Laravel's own warning.
- **Fallback path:** Mroonga full-text indexes on MariaDB remain documented as the alternative if external search infrastructure is ever rejected — but it requires a custom MariaDB Docker image (not in official images) and re-runs the validation spike below.

**Either way:**
- **Normalize input before indexing and querying:** Unicode NFC, strip zero-width characters (`U+200B`), collapse whitespace.
- **Validate with real Khmer/Thai/Lao data in Phase 1**, not strings typed by the team — build the throwaway spike from §13 with real sample data before committing the schema.
- **Accent/case-insensitive matching** for Latin-script content comes free in Meilisearch; set MariaDB's `utf8mb4_unicode_ci` (or MySQL's `utf8mb4_0900_ai_ci`) as the default collation for ordering/dedup behaviour at the SQL level.

### 7.4 Definition of Done

Unchanged from the original NestJS baseline §7.4, with the tooling nouns swapped:

- [ ] Endpoint appears correctly in the generated OpenAPI spec (Scramble) with a typed response Resource
- [ ] Frontend client regenerated (Orval) and committed
- [ ] Input validated via a Form Request; invalid input returns 422 with a useful message (Laravel's default validation error shape — align the error envelope here with the rest of the API)
- [ ] Authorization enforced via a Policy — verified by attempting it as a lower-privileged role
- [ ] Errors handled centrally in `App\Exceptions\Handler`; no stack trace reaches the client outside local/debug mode
- [ ] Unit tests (Pest) on the service's business logic
- [ ] All user-facing strings present in every configured locale
- [ ] Loading, empty, and error states exist in the UI
- [ ] Works at phone width — approvers act from phones
- [ ] Audit event emitted if the action changes business data
- [ ] Manually tested against seed data that resembles reality

---

## 8. Feature Roadmap

Phase intent, ordering, and exit criteria are **unchanged** from the original NestJS baseline §8 — same four phases, same week estimates, same "one department completes a full month without falling back" exit criterion for Phase 2. Only the checklist items that name a specific tool change:

### 8.1 Phase 0 — Skeleton (weeks 1–2)

**Repository & tooling**
- [ ] Two-toolchain repo per §4 — pnpm workspace for `apps/web`, standalone Composer project for `apps/api`
- [ ] Laravel Pint + Larastan on the backend; ESLint + Prettier unchanged on the frontend; Husky at the repo root runs both
- [ ] GitHub Actions: two parallel jobs (PHP: lint → stan → Pest; Node: lint → typecheck → build) on every PR
- [ ] `README.md` with a working "clone to running in 3 commands" section covering both toolchains

**Environment**
- [ ] Docker Compose: MariaDB 12.3, Redis 7.4, MinIO, Meilisearch, api, web
- [ ] `.env.example` with a boot-time config validation check — including CORS origins and `SANCTUM_STATEFUL_DOMAINS` from day one
- [ ] Meilisearch service added to Compose with a pinned image tag; `SCOUT_DRIVER=meilisearch` wired
- [ ] Seed: roles, units of measure, action types, one admin user (Laravel seeders)
- [ ] `php artisan migrate:fresh --seed` as the one-command reset, mirroring the original's `pnpm db:reset`

**Shared kernel**
- [ ] Laravel bootstrap with global Form Request validation conventions
- [ ] Base `Repository` interface/class, request-scoped entity context set by middleware **plus the shared `BelongsToEntity` global-scope trait (§3.3)** — the scoping seam
- [ ] `Model::preventLazyLoading()` enabled outside production (§3.3)
- [ ] Global exception handler producing the standard error envelope
- [ ] `spatie/laravel-health` `/health` endpoint with db, redis, storage, queue-worker **and scheduler-heartbeat** probes
- [ ] Scramble wired at `/docs`, explicitly configured to scan each module's `routes/api.php` (Scramble does not pick up nwidart module routes by default)
- [ ] `PaginatedResource`, `TransitionGuard` base
- [ ] `DocumentNumberService` (§7.2)
- [ ] MinIO disk configured via Laravel's native S3-compatible filesystem driver, presigned URLs via `temporaryUrl()`
- [ ] Horizon wired with one demo job

**Web skeleton**
- [ ] Unchanged from the original NestJS baseline §8.1 — Next.js 16, Tailwind 4 + shadcn/ui, app shell, next-intl, TanStack Query, Orval codegen against the Scramble-generated spec, reusable `DataTable`

**Exit criteria:** unchanged — `GET /health` callable from the web app through the generated client, running in Docker, reachable at a staging URL, all configured locales rendering correctly.

### 8.2–8.4

Unchanged in scope from the original NestJS baseline §8.2–§8.4 — swap only the named tools where they appear (e.g. "CASL ability factory" → "Policies + `spatie/laravel-permission` role setup"; "BullMQ escalation job" → "Laravel queued job + scheduler entry"). Every checkbox item's *business content* — auth, org structure, catalog, suppliers, the full approval engine build-out in weeks 9–13, requisitions, RFQ/PO/GRN/three-way-match in Phase 3 — is identical.

---

## 9. Testing Strategy

| Layer | Original | Proposed |
|---|---|---|
| Unit | Jest | **Pest ^4.7** — quorum evaluation, condition trees, resolver strategies, state transitions, UoM conversion, number generation, price resolution — same coverage targets |
| Integration | Jest + Supertest + real Postgres | **Pest + Laravel's built-in HTTP test helpers + real MariaDB/MySQL in Docker** — no Supertest equivalent needed, it's built into the framework. Repository queries, transaction correctness, scoping, search behaviour, concurrent number generation |
| E2E | Playwright | Unchanged — still Playwright against the Next.js app (Pest v4's browser plugin is noted as a future consolidation option once its ecosystem support is proven) |
| Manual | — | Unchanged — everything visual, plus non-Latin script rendering |

**Rules — unchanged from the original §9:** no coverage target, every bug gets a test before a fix, the approval engine gets the most tests in the system (same specific list: quorum boundaries, unreachable quorum, skipped stages, empty resolution, self-approval removal, concurrent actions on the same stage, amendment mid-approval, delegation chains). Additions specific to this stack:
- **Integration tests must run against real MariaDB/MySQL in Docker, never SQLite** — full-text and JSON behaviour cannot be exercised on SQLite.
- **Every entity-scoped model gets a negative scoping test** proving a user from entity B reads zero rows from entity A (§3.3).
- **Audit immutability test:** update/delete attempts on audit rows must throw (§3.3).

---

## 10. Environments, Deployment & Backup

Structurally unchanged from the original NestJS baseline §10 — same three environments, same `develop`→staging / tag→production flow, same "migrations run as a separate step before the new API container starts" rule, same backwards-compatible-migration discipline (add columns before writing to them, drop a release after they stop being read).

Deltas:

- **Migrations:** `php artisan migrate` as the separate pre-boot step, replacing Prisma's migration step. Laravel migrations are PHP files, not a DSL compiled into SQL — review them in PR the same way the original expects `.prisma` diffs to be reviewed.
- **Backup:** `mysqldump` (or `mariabackup` for large databases, since it does hot physical backups without locking) replaces `pg_dump`, same retention policy — nightly retained 30 days, weekly retained 6 months, both copied off the server, restore tested before go-live and quarterly thereafter.
- **Monitoring:** `spatie/laravel-health`'s `/health` endpoint replaces Terminus — now including a **scheduler heartbeat probe** so escalation sweeps failing to run surfaces in monitoring rather than silently; Horizon's dashboard gives the queue-depth and failed-job visibility BullMQ's board gave; the "dashboard for stuck approvals — instances pending beyond SLA" requirement is unchanged and is a query against `approval_instance_stages`, not a framework feature either way.

---

## 11. Decisions & Open Questions

### 11.1 Decisions carried over, unchanged

D2 (modular monolith, not microservices), D4 (approval rules stored as data), D5 (workflow snapshot per instance), D6 (action types are a table, not an enum), D7 (in-app notifications first), D8 (documents cancelled, never deleted), D9 (prices append-only) — all framework- and database-independent, all still stand as written in the original NestJS baseline §11.1.

### 11.1 Decisions superseded by this proposal

| # | Original decision | Proposed replacement | Rationale |
|---|---|---|---|
| D1 | PostgreSQL 17 | **D1′ — MariaDB 12.3 LTS** (MySQL 8.4 LTS as the alternative) | See §2.5 — fully supported LTS line, no Mroonga packaging tax since search is external now |
| D3 | Search inside Postgres via `tsvector` + `pg_trgm` | **D3″ — Laravel Scout + Meilisearch (native driver)**; Mroonga documented as fallback only | See §7.3 — closes former Q9; Mroonga's absence from official MariaDB images makes external search the lower-risk path |
| D10 | Contract-first API via `@nestjs/swagger` | **D10′ — API-spec-inferred via `dedoc/scramble` ^0.13.42**, frontend client still generated via Orval | See §3.1 |

*A superseded decision is still closed — this table exists so the reasoning is traceable, not so D1/D3/D10 get re-litigated line by line.*

### 11.2 Decisions closed in revision 2.0 (formerly open questions)

| # | Was open | Decision | Rationale |
|---|---|---|---|
| Q9 → closed | MariaDB+Mroonga vs. Scout/Meilisearch | **Scout + Meilisearch** | §2.5 / §7.3 — Mroonga not in official images; Meilisearch gives CJK tokenization + typo tolerance with a single Compose service |
| Q10 → closed | Sanctum vs. `tymon/jwt-auth` | **Sanctum personal access tokens + first-party rotating refresh-token table** | Keeps auth on a first-party-maintained package; refresh rotation is ~50 lines of migration + service, versus depending on a third-party JWT package that historically lags framework majors. Cookie-based Sanctum SPA auth for the web app, bearer tokens for mobile |
| Q11 → closed | ULID vs. UUID v7 | **ULID via Laravel's native `HasUlids` trait** (`CHAR(26)`) | Zero extra packages, time-sortable, index-friendly, idiomatic Laravel default. Applied repo-wide as the single PK convention |

New decisions added with this revision:

| # | Decision | Rationale |
|---|---|---|
| D11 | All timestamps stored UTC; org-level IANA timezone for display/local-date boundaries; escalation jobs evaluate UTC instants | §3.3 — prevents double-fire/never-fire escalation bugs across server timezones |
| D12 | Audit rows append-only at application *and* database level | §3.3 — "immutable history" must be enforced, not aspirational |
| D13 | Entity scoping enforced by a shared global-scope trait, mandatory negative tests per scoped model | §3.3 — middleware alone leaks data on any forgotten `where` clause |
| D14 | `next`, `react`, `react-dom` exact-pinned; Renovate-managed patch bumps | §2.2 — 2026 shipped multiple critical fixes as patch releases for this trio |

### 11.3 Open questions — carried over unchanged

Q1 (document number templates), Q2 (initial locale set), Q3 (org entity levels), Q4 (initial approval policy), Q5 (multi-currency), Q6 (tax handling), Q7 (backup destination/retention), Q8 (who administers users/workflows) — all unchanged from the original baseline, all still open, all still needed by the same phases.

---

## 12. Security Baseline

Unchanged in substance from the original NestJS baseline §12 — every bullet point applies regardless of framework:

- Secrets never in Git; `.env` gitignored, `.env.example` holds dummies.
- HTTPS only, no plain HTTP path to the API.
- Passwords: bcrypt cost 12 (Laravel's default hasher, already bcrypt — just confirm the cost factor), never logged, never in a Resource.
- Tokens: **Sanctum personal access tokens + first-party rotating refresh tokens (Q10 closed)** — short-lived access, one-time-use refresh with reuse detection, Redis-backed revocation. Cookie-based Sanctum SPA auth for the web client; bearer tokens for mobile.
- CORS/CSRF: explicit allowlist of the web app's origins only (`supports_credentials: true`), `SANCTUM_STATEFUL_DOMAINS` pinned in `.env` — configured in Phase 0, per §3.3.
- Rate limiting on login and password reset at minimum — Laravel's `throttle` middleware.
- Input: every mutating endpoint goes through a Form Request; unexpected fields rejected, not silently ignored (`$request->validated()` only returns declared fields — the Laravel-native equivalent of the original's `whitelist: true, forbidNonWhitelisted: true`).
- SQL: Eloquent and the query builder parameterize by default; any raw query uses parameter binding (`DB::select($sql, $bindings)`), never string interpolation.
- File uploads: validate MIME type and size, generate storage keys server-side, never trust the client filename, serve via short-expiry presigned URLs — all native to Laravel's `Storage` facade and validation rules.
- Authorization tested negatively: for each role, confirm what it *cannot* do — write this as a Pest test per Policy; same discipline per entity-scoped model (§3.3).
- Audit rows append-only: no update/delete paths in code, restricted DB grants, immutability test in CI (D12).
- Approval integrity, audit trail coverage: unchanged — these are business rules on the approval engine and audit module, not framework features.
- Dependency scanning enabled on the repository — Renovate/Dependabot cover `composer.json` too; additionally run `composer audit` in CI (it would have caught e.g. CVE-2026-44262 in Scramble < 0.13.22).

---

## 13. Risks

All business risks from the original NestJS baseline §13 carry over unchanged (approval engine over/under-built, configuration complexity, scope creep, user adoption, data loss before backups exist, concurrency bugs). One risk is added, specific to this proposal:

| Risk | Impact | Mitigation |
|---|---|---|
| **Multi-script search is less battle-tested on Scout/Meilisearch than on Postgres/PGroonga for this specific requirement** | Poor catalog usability for Khmer/Thai/Lao content, discovered late | Meilisearch is chosen precisely because it tokenizes script-agnostically — but still build a throwaway spike with real Khmer/Thai/Lao/Japanese/Chinese sample data in Phase 1 *before* committing the schema, and validate recall/precision manually |
| **Annual Laravel major upgrades are now mandatory (no LTS exists)** | Falling two majors behind turns routine upgrades into migrations | §2.4 policy: upgrade branch opens within Q1 of each major release while security coverage on the previous major overlaps; Renovate keeps minors/patches flowing continuously |
| **Pest v5 is brand-new; ecosystem packages currently cap at Pest 4.x** | Premature adoption breaks CI resolution | Pin Pest `^4.7` until Scramble's dev deps and orchestra/testbench declare v5 support; revisit quarterly |

---

## 14. Glossary

Unchanged — see the original NestJS baseline §14 in full. The business vocabulary (Purchase Requisition, RFQ, Quotation, PO, GRN, Supplier Invoice, three-way match, Item, Category, UoM, Cost Center, Budget Line, Commitment, Workflow, Stage, Task, Action type, Quorum, Resolver, Delegation, Reassignment, Escalation) describes the product, not the implementation — it doesn't change with the stack.

---

## 16. Verified Stack & Compatibility Matrix (as of August 2026)

Every pairing below was verified against current release notes and package requirement ranges. This table is the checklist to re-run at each annual Laravel major.

| Component | Version | Verified compatible with | Source of truth |
|---|---|---|---|
| PHP | 8.4 (min 8.3, supports up to 8.5) | Laravel 13 declares `8.3–8.5` | laravel.com/docs/releases |
| Laravel | `^13.0` (13.x, released 2026-03-17) | bug fixes Q3 2027, security Q1 2028 | laravelversions.com |
| MariaDB | 12.3 LTS (alt: 11.8/11.4 LTS; alt DB: MySQL 8.4 LTS) | Laravel `mysql` driver covers both identically | mariadb.org |
| Redis | 7.4+ (Redis 8.x works) | Laravel cache/session/queue drivers | — |
| Sanctum | bundled with framework | SPA cookie auth + PATs | laravel.com/docs |
| spatie/laravel-permission | `^7` (v6 EOL-track) | in Scramble's supported test matrix | Packagist |
| spatie/laravel-data | `^4` | first-class Scramble schema support | scramble.dedoc.co |
| dedoc/scramble | `^0.13.42` (pin ≥ 0.13.22 — CVE-2026-44262) | requires `illuminate ^10|^11|^12|^13`, PHP `^8.1` | Packagist |
| nwidart/laravel-modules | `^13` (match framework major exactly) | v13 = Laravel 13 line | GitHub releases |
| deptrac | `deptrac/deptrac` (new canonical name) | CI boundary linting | Packagist |
| Pest | `^4.7` (PHPUnit 12 underneath) — **not v5 yet** | Scramble dev deps + orchestra/testbench cap at Pest 4.x today | pestphp.com, Packagist |
| Larastan | `^3.10` (PHPStan 2.x) | Laravel 13 | GitHub releases |
| Horizon / model-states / health | `^5` / `^2` / `^1.40` | first-party + Spatie majors current for L13; laravel-health has no 2.x line | Packagist |
| Node.js | 22 LTS | Next.js 16 minimum is 20.9 | nextjs.org |
| Next.js | 16.3.x Active LTS, **exact-pin ≥ 16.3.3** (Aug 2026 critical RCE fixes) | React 19.2 required | nextjs.org/blog |
| React / ReactDOM | 19.2.x, **exact-pin ≥ 19.2.4** (React2Shell CVE-2025-55182) | Next.js 16 pairs with React 19.2 canary-stable APIs | react.dev advisories |
| Tailwind CSS | 4.x | shadcn/ui current templates target v4 | tailwindcss.com |
| TanStack Query / Table | v5 / v8 | React 19 compatible | tanstack.com |
| Zod | 4.x (+ `@hookform/resolvers` current) | React Hook Form integration | github.com |
| Flow diagrams | **`@xyflow/react` v12** — never install deprecated `reactflow` | React 19 compatible | xyflow.com |
| next-intl | 4.x | Next.js 16 App Router | next-intl.dev |
| Orval | latest | consumes Scramble's OpenAPI 3.1 output | orval.dev |
| Scout + Meilisearch | Scout 11+ native `meilisearch` driver (`meilisearch-laravel-scout` pkg is deprecated) | binary↔PHP-SDK version matrix per Meilisearch docs | laravel.com/docs/scout |

**Known non-pairings to avoid:** `reactflow` (deprecated) on React 19; Pest 5 before ecosystem support; `qossmic/deptrac-shim`; Mroonga via official `mariadb` Docker images (not included); `meilisearch/meilisearch-laravel-scout` (deprecated); Scramble < 0.13.22 (CVE).

---

## 17. Change Log

| Version | Date | Change |
|---|---|---|
| 1.0 | — | Initial proposal: Laravel/MySQL-MariaDB stack, patterns, repository structure, approval-engine storage deltas, data-model deltas, conventions, search strategy, roadmap deltas, testing, deployment, security, risks. Frontend stack and all business-level design carried over unchanged from the NestJS baseline |
| 2.0 | 2026-08-26 | Stack revision: pinned every component to current stable versions with verified compatibility (§16); Laravel 13 + PHP 8.4 + MariaDB 12.3 LTS; Next.js 16.3 LTS ≥16.3.3 and React ≥19.2.4 exact-pinned; closed Q9 (Scout+Meilisearch as search engine), Q10 (Sanctum + rotating refresh tokens), Q11 (ULID); added §3.3 cross-cutting guarantees (entity-scoping global scopes, audit immutability, UTC/timezone policy, N+1 prevention, CORS/CSRF from Phase 0, scheduler heartbeat); version policy rewritten around Laravel's post-LTS annual cadence; new risks for mandatory annual upgrades and Pest v5 adoption timing |
