# Payments module plan

Working spec for the Cash Request / Cash Clearance / Payment Request work.
Phased so each phase is independently verifiable and committable.

**Sequencing:** Phase 0 → Phase 1 → Phase 2.

---

## Locked decisions

| # | Decision |
|---|---|
| 1 | Signature boxes on the sample forms are **sample data only** — no step is hardcoded. Seed a baseline; admins reconfigure at runtime via the Approvals settings UI. Print renders signature columns from each step's `show_on_print`. |
| 2 | **One `cash_requests` model** with `type = petty_cash \| advance \| cash` (PC / AD / CR are one table). |
| 3 | **VAT is included in Amount**: `amount_usd = qty × unit_price − discount + vat`. |
| 4 | **Extend the shared `CodeGenerationService`** (never fork it; `InvoiceService`/`EvaluationService` already inject it cross-module). |
| 5 | A clearance/liquidation requires its source CashRequest to be **`approved`**. |
| 6 | **`paid_at` derived from `transaction_type`**: `invoice_date` for `petty_cash`/`advance`, `null` for `credit`. |
| 7 | **`cash_reference`**: `required_unless:transaction_type,credit`; stays a free string (no cross-module existence check). |
| 8 | Cash-reference dropdown: **only for non-credit invoices**, listing **`approved` CashRequests only**. |
| 9 | `short_code`: **unique per entity**; **no backfill** (dev environment). |
| 10 | Print labels are **hardcoded literal text, not translated** — bake the forms' Khmer + English labels in. |
| 11 | Form **"ID:"** field rendered **blank** for now (no `users` schema change). |
| 12 | Approval baseline: **one band `0 → null` per subject**; admins add amount bands later. |
| 13 | Dev environment — backfill/production-safety concerns out of scope. |

---

## Phase 0 — `short_code` on campuses, divisions, departments

Convention copied from `product_categories.short_code`: nullable `string(16)`,
`['nullable','string','max:16']` / `['sometimes','string','max:16']`,
server default `Str::upper(Str::substr($name,0,10))`, `shortCode` on the Resource.

1. Migration `Modules/Organization/Database/Migrations/2026_10_19_100000_add_short_code_to_lookup_tables.php`
   — add column after `name` on all three tables + `unique([entity_id, short_code])`. No backfill.
2. `Modules/Products/Services/CodeGenerationService::nextShortCode($base, $modelClass, $entityId)`
   — collision-safe `-2`, `-3`… suffix loop (a unique index makes the sibling's bare `??=` a 500).
3. Models `Campus`/`Division`/`Department` — `'short_code'` in `#[Fillable]` after `'name'`.
4. Requests — `Store*` adds `Rule::unique(...)->where('entity_id',…)->whereNull('deleted_at')`;
   `Update*` adds `Rule::unique(...)->ignore($routeParam)->whereNull('deleted_at')`.
5. Controllers — default `short_code` before persist:
   - `CampusController::store` (uses `doStore`)
   - `DivisionController::store` / `DepartmentController::store` (build `create()` themselves, because of the many-to-many sync)
6. `InteractsWithOrgLookups::doDestroy()` — null `short_code` before the soft delete so a recycled
   name can re-own its code (a plain unique index cannot exclude soft-deleted rows).
7. `OrganizationImportService` — set `short_code` on created rows (else CSV-imported campuses
   arrive null and Phase 1 cannot generate their codes).
8. Factories — derive `short_code` via the shared helper so the unique index never trips in tests.
9. Resources — `'shortCode' => $this->short_code,` after `name`.
10. Tests — `OrgLookupTest`, `ImportTest`: auto-derivation, explicit value kept, update,
    422 same-entity duplicate (`errors.shortCode`), 422 >16 chars, cross-entity duplicate allowed,
    import-populated. Existing tests **updated, never deleted**.

**Out of scope:** web UI for `short_code`; pre-existing dev rows stay null (recreate/edit via the UI).

---

## Phase 1 — `Modules/Payments`

### Skeleton
Mirror `Modules/Invoices`: `module.json`, `config/config.php`, `Providers/{Payments,Route,Event}ServiceProvider`,
repository-interface binding + `Gate::policy(...)` in `boot()`, `"Payments": true` in `modules_statuses.json`.

Routes — Products-style multi-resource group:
```php
Route::prefix('v1/payments')->name('payments.')->middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('cash-requests',        CashRequestController::class)->names('cash-requests');
    Route::apiResource('cash-clearances',      CashClearanceController::class)->names('cash-clearances');
    Route::apiResource('advance-liquidations', AdvanceLiquidationController::class)->names('advance-liquidations');
    // PATCH .../status per resource
});
```

### Schema

**`cash_requests`** — `type: petty_cash|advance|cash`;
`status: draft|in_review|approved|rejected|returned|cancelled|cleared`
`id, entity_id, code, type, status, campus, division, department, request_date, exchange_rate,
mode_of_payment?, payment_to?, bank_account_no?, bank_name?, advance_request_for? (AD only),
total_usd, remark?, created_by, updated_by, timestamps, deleted_at`
`unique([entity_id, code])`, `index([entity_id, status])`
> `mode_of_payment`/`payment_to`/bank trio used by **AD + CR**; `advance_request_for` by **AD only**.

**`cash_request_lines`** — `position, description, currency, amount, amount_usd`

**`cash_clearances`** (PCC) — `status: draft|in_review|approved|rejected|returned|cancelled`
`id, entity_id, code, status, cash_request_id, clearance_date, exchange_rate,
total_usd, request_amount_usd, balance_usd, audit, timestamps, deleted_at`
> Header has **no** campus — it is **per line**.

**`cash_clearance_lines`** — `position, invoice_date, pr_ref, purchaser, description, vendor,
vendor_phone, campus, division, department, qty, uom, unit_price_usd, discount_usd, vat_usd, amount_usd`

**`advance_liquidations`** (LAD) — same status set
`id, entity_id, code, status, cash_request_id, liquidation_date, exchange_rate, campus, division,
department, total_actual_expense_usd, total_cash_advance_usd, remaining_cash_usd, audit, timestamps, deleted_at`

**`advance_liquidation_lines`** — `position, description, currency, amount, amount_usd`

**Totals (server-only, never client-accepted):**
- Cash request / LAD line: `amount_usd = Currency::toUsd($amount, $currency, $exchangeRate)`
  → `220 + 1,500,000/4026 = 592.58`
- PCC line: `amount_usd = round(qty × unit_price − discount + vat, 2)` → `10 − 1 = 9`
- PCC: `request_amount_usd` copied from the source request; `balance = request − total` → `500 − 146 = 354`
- LAD: `total_cash_advance_usd = source.total_usd`; `remaining = advance − expense` → `600 − 592.58 = 7.42`

**One final clearance per request** → service-enforced 422 (`errors.cash_request_id`,
`whereNull('deleted_at')`), **not** a DB unique index, so soft-delete + recreate works.

### Per-resource files (×3, mirroring `Modules/Invoices`)
Controller (repo-interface + concrete Service DI, `$this->authorize`, `ApiResponse::success(..., 201)`),
`Store*/Update*/Index*` + shared `Validates*Payload` trait (`authorize(): true`, `*Data()`),
`*Resource` + `*LineResource` (camelCase, float casts, `@return array{...}` for Scramble),
Model (`BelongsToEntity, HasFactory, HasUlids, SoftDeletes`, `#[Fillable]`, `casts()`, `newFactory()`),
Policy (`payments.view` / `payments.manage` + `canWrite()` ownership),
Repository (`filtered()`), Service (`DB::transaction`, `ensureEditable` draft/returned, `ensureDeletable` draft,
`TRANSITIONS`, totals, code gen, audit stamping).

### Code generation
```php
CodeGenerationService::nextWithCampus($prefix, $modelClass, $entityId, $campus, ?DateTimeInterface $date)
// → "PC-CEN-202606-0001"
```
Prefixes: `petty_cash→PC`, `advance→AD`, `cash→CR`, clearance→`PCC`, liquidation→`LAD`. Existing `next()` untouched.

### Approvals wiring (no engine edits)
5 `ApprovalSubjectRegistry::SUBJECTS` entries — `petty_cash_request`, `advance_request`,
`cash_request` (→ `CashRequest`), `cash_clearance`, `advance_liquidation`; each
`amount_column='total_usd'`, `code_column='code'`, `permission='payments.manage'`,
`exchange_rate_column='exchange_rate'`, `submittable_statuses=['draft','returned']`.
> PC/AD/CR share one model but need distinct subject types — flows are chosen by **amount band only**.

Baseline seeder: one setting + one flow (`0 → null`) + steps + placeholder TOCA rows.
- `petty_cash_request`: `requested(record) → checked(decide) → approved(decide) → received(decide)`
- `advance_request`, `cash_request`: `requested(record) → checked → approved`
- `cash_clearance`, `advance_liquidation`: `prepared(record) → checked → approved`

5 `Sync*ApprovalStatus` listeners in `Payments/Providers/EventServiceProvider::$listen`
(copy `SyncEvaluationApprovalStatus`: `pending→in_review`, `approved→approved`, `rejected→rejected`,
`returned/cancelled→draft`). The clearance listener also marks the source CashRequest `cleared`.

`documentSnapshot()` — 5 branches returning header + lines (today it returns `[]` for non-`evaluation`).

`PermissionSeeder::PERMISSIONS` — add `payments.view`, `payments.manage`.

### Web
- Sidebar group `payments` inserted after the `invoices` group (`app-sidebar.tsx:104`).
- `messages/{en,km}.json` — `nav.payments*`, top-level `payments` block, `approvals.subjects.*`.
  **No `payments.print` block** (decision 10).
- `breadcrumbs.ts` — `ROUTE_CRUMBS` per branch (list/new/detail/edit, `null` for the id) +
  `SEGMENT_FALLBACKS` for `payments`, `cash-requests`, `cash-clearances`, `advance-liquidations`.
- `src/app/(app)/payments/page.tsx` = redirect to `/payments/cash-requests` (parent-redirect pattern),
  plus leaf shells + `loading.tsx` for 3 branches × `{,new,[id],[id]/edit}`.
- `src/features/payments/` — `*-tab.tsx`, `*-detail.tsx`, `*-form.tsx`, `*-form-page.tsx`,
  `components/*-status-badge.tsx`, `*-skeleton.tsx`. Cash-request form renders the AD/CR payment
  block and AD-only "Adv. request for" conditionally on `type` — one route, one model.
- Print: backend-rendered PDFs (`PaymentPrintService` + Blade views in
  `Modules/Payments/resources/views/print/`), opened from the web through the shared
  `usePrintPreview()` dialog (native iframe viewer) — no client-side print components.
- Approvals frontend: 3 entries in `SUBJECT_DETAILS`, subject filters in `requests-tab.tsx`.
- Orval: `npm run generate-client` + diff (expect 3 new tag folders + models).

### Tests
`Modules/Payments/Tests/Feature/{CashRequestTest,CashClearanceTest,AdvanceLiquidationTest,PaymentsApprovalsTest}.php`
— Pest `it(...)`, envelope assertions, `PermissionSeeder` via `tests/Pest.php`. Cover auth 401, 403,
code format `PC-CEN-202606-0001`, KHR→USD `592.58`, client totals ignored, PCC `146/500/354`,
LAD `592.58/600/7.42`, VAT-included amount, clearance precondition, one-final-clearance 422,
soft-delete + recreate, ownership, entity isolation, factory code format.
**Factories + a seeder for the 3 new entities.**

---

## Phase 2 — Invoice integration — **delivered**

Landed after Phase 1 (`cash_reference` is required, so the picker ships in the same change).

1. `InvoiceService` — `derivePaidAt()` private method (match on `TRANSACTION_TYPE_PETTY_CASH`/`_ADVANCE`
   → `invoice_date`, default `null`), wired into both `create()` and `update()`. Recomputed on every
   write; never accepted from the client; no schema change.
2. `ValidatesInvoicePayload::invoiceRules()`:
   ```php
   'cash_reference' => ['nullable','string','max:64','required_unless:transaction_type,credit'],
   ```
3. `invoice-form.tsx` — `cashReference` Combobox + `RequiredMark` rendered only when
   `transaction_type` is petty_cash/advance and non-empty; options from
   `paymentsCashRequestsIndex({status:"approved", per_page:100})` read inside `queryFn` + `withAuth()`,
   key `["cashRequests","invoiceOptions"]`, `enabled` only for non-credit types, filtered client-side
   by `type`; switching to credit/empty clears `cashReference`; stale `CASH_REFERENCE_OPTIONS` const
   and "wired from the DB later" comment deleted.
4. `InvoiceTest.php` — 62 tests green: 422 + `errors.cash_reference` for petty_cash/advance without
   it; passes for credit; `paid_at === invoice_date` for petty_cash/advance; `paid_at === null` for
   credit; re-stamps `paid_at` on update when the transaction type changes to credit (existing test
   updated to the new contract — clearing `cash_reference` now means switching to credit).

---

## Verification (per phase)

| | Command |
|---|---|
| API format | `vendor/bin/pint --dirty --format agent` (php84 terminal — default `php` is 8.2) |
| API tests | `php artisan test --compact Modules/<X>/Tests/Feature/<File>.php` |
| Web | in `apps/web`: `npm run typecheck` && `npm run lint` |
| Contract | API on `:8000` → `generate-client` → diff `src/lib/api/**` — only expected changes |

---

## Out of scope / deferred

- Web UI for `short_code` on org lookups (API-only exposure)
- Credit-invoice `paid_at` ("cross department", later)
- Strict `cash_reference` existence validation / dedicated lookup endpoint
- CR ↔ credit-invoice settlement (the CR form has no invoice list)
- User ID-card number column on `users`
- Real per-form signature names (sample data only; runtime-configurable instead)
