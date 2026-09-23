# Project rules

## Principles

### API-first: the API is the core, the frontend is a shell UI

All business logic, validation, authorization, defaults/code generation, and state transitions live in `apps/api`. `apps/web` only: renders UI, calls the API, and displays what the API returns.

- No business logic or authoritative validation in the client. Client-side checks are UX-only hints; the API's response (422/403/… with `message`) is the source of truth.
- Always surface API messages: wrap every orval call with `unwrap()` **inside** `mutationFn`/`queryFn` (throws when `status >= 400` → TanStack Query routes to `onError`). Never unwrap in `onSuccess`.
- orval-generated clients under `apps/web/src/lib/api/**` are the contract — regenerate when the API changes; never hand-edit.
- After mutations, invalidate queries and re-read server state; don't derive/mutate state locally.
- Success/error toast text comes from the API message where available, not hardcoded guesses.

### Consistency rule (enterprise): mimic the sibling

- Before creating any endpoint, file, component, or test, open the **nearest existing sibling** that does the same kind of work (lookups → Brand/Group/Uom; full CRUD → Product; tabs/dialogs → existing feature files) and copy its structure, naming, and patterns **exactly**.
- Never introduce a second way of doing something the codebase already does one way. If two existing conventions conflict, follow the **majority**, apply it, and mention the conflict — do not invent a hybrid.
- The authority for "how we do X" is the existing code plus these rules; files under `docs/` may be stale — verify against code before following them.

## API conventions (`apps/api`)

- **Structure**: one nwidart module per bounded context: `Modules/<Name>/{Http/{Controllers,Requests,Resources}, Models, Policies, Repositories, routes/api.php, Tests/Feature}`. No new top-level folders.
- **Endpoint recipe**: versioned `Route::apiResource` inside `prefix('v1/...')` + `auth:sanctum` group → `Store*`/`Update*` FormRequest (validation only; `authorize()` returns `true`) → `$this->authorize()` with a policy → Repository for persistence → `*Resource` for output. Multi-model writes go in a Service. Business logic duplicated across controllers (e.g. code generation) belongs in **one** shared Service, not copies.
- **Response contract**: success is always `{data: ...}` (201 for creates) via `ApiResponse::success` or implicit Resource wrapping; errors are always `{statusCode, message, error, correlationId[, errors]}` from the central renderer in `bootstrap/app.php` — never hand-built 422s that omit `errors`.
- **Casing**: responses camelCase **only through Resources** (never serialize raw Eloquent models); request payloads snake_case only. Documented exceptions: OAuth token payload, health probe.
- **Codes/IDs/defaults** (`PRD-yy-NNN`, `CAT-yy-NNN`, `short_code`, `short_name`) are generated server-side only.
- **Models**: `HasUlids` + `SoftDeletes` on domain entities, `#[Fillable]` attribute, `BelongsToEntity` for tenant data (`Entity` itself exempt), audit columns `created_by`/`updated_by` stamped on writes.

## Frontend conventions (`apps/web`)

- **Structure**: `src/app` = thin route shells (5–7 lines) delegating to `src/features/<name>/`. Shared `unwrap`/`withAuth` live in `src/lib/api-client.ts`. Feature anatomy: kebab-case `*-page|tab|dialog.tsx` + `components/` for large pieces. Kebab-case filenames; no barrel files.
- **Data fetching**: only orval-generated functions. `unwrap()` inside `queryFn`/`mutationFn` + `withAuth()` headers — never hand-roll envelope/status checks. One key → one `queryFn` → one type shape.
- **Query keys**: plural string literals — `["products"]`, `["categories"]`, `["brands"]`. Never template-build keys (the `${entity}s` bug), never reuse a key with a different shape.
- **Errors**: no per-mutation `onError` toasts — the global `MutationCache` in `providers.tsx` toasts the API `message`; opt out with `meta: { silent: true }` only when the form shows the error inline (e.g. login). All mutations use `useMutation` — no manual `saving` + try/catch state.
- **Mutations**: invalidate the exact literal keys in `onSuccess`.
- **UI**: shadcn `components/ui` + Tailwind tokens only; one `cn` source; one Toaster (sonner); no raw palette classes or inline gradient styles. `"use client"` only where hooks/browser APIs are needed.
- **Forms**: UX-only hints are allowed; the API response is the source of truth.

## Verification (run before finishing)

- **API**: PHP ≥ 8.4 (XAMPP's default `php` is 8.2 — use the php84 terminal). After PHP changes: `vendor/bin/pint --dirty --format agent`, then the narrowest tests: `php artisan test --compact Modules/<X>/Tests/Feature/<File>.php`. If Meilisearch/Docker is down, set `SCOUT_DRIVER=null` (search tests will then fail — infra only, not your change).
- **Web**: in `apps/web`: `npm run typecheck` && `npm run lint`. If typecheck errors point at `.next/types/validator.ts`, delete `.next/types` (gitignored, stale build output) and rerun.
- **Contract change**: start the API on `:8000`, run `generate-client` in `apps/web`, then diff `src/lib/api/**` — expect only the changes your API edit implies.
- Husky pre-commit runs web lint + typecheck.

## Testing

- Pest style `it('...', fn () => ...)` feature tests in `Modules/<X>/Tests/Feature`; assert the envelope (`data.*`, `statusCode`).
- **Never delete a failing test** — update it to the new contract when behavior intentionally changes.
- New entities get a factory + seeder; prefer factories over raw `Model::create` in tests once they exist.

## Known inconsistencies (do not copy — fix opportunistically when already in the area)

- `product-form.tsx`: `payload as never` instead of the generated request type.
- `products-tab.tsx`: status filter UI is wired to state but never sent to the API (`productsItemsIndex({})`).
- `entity-quick-add-modal.tsx`: manual `saving`/try/catch instead of `useMutation`; requires a category for groups client-side, but the API's group create has no `category_id` field at all — a client-only rule with nowhere to send the value.
- `ProductRefController` returns raw models (snake_case); `ProductImportController` builds its own 422 without `errors`; `VariationController::merge` takes a plain `Request` (no FormRequest).
- Products feature UI strings hardcoded English; `messages/km.json` has orphan `nav.catalog` key; two `cn` import sources; no Prettier despite docs mentioning it.
