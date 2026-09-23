<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->

# Frontend rules (`apps/web`)

Project-wide rules live in the root `AGENTS.md` (API-first principle, consistency rule, verification commands). This file repeats the frontend-specific parts so they are always in scope when working here.

## Structure

- `src/app` = thin route shells (5–7 lines) delegating to `src/features/<name>/`. Shared `unwrap`/`withAuth` live in `src/lib/api-client.ts`. Feature anatomy: kebab-case `*-page|tab|dialog.tsx` + `components/` for large pieces.
- Kebab-case filenames; no barrel files; `"use client"` only where hooks/browser APIs are needed.
- Before building a new UI piece, copy the nearest existing sibling (tabs → `categories-tab.tsx`, dialogs → existing `*-dialog.tsx`) exactly. Never invent a second pattern.

## Data fetching

- Only orval-generated functions from `src/lib/api/**`. Never hand-edit generated files; after API changes run `generate-client` (API must run on `:8000`) and diff the output.
- `unwrap()` **inside** `queryFn`/`mutationFn` (never in `onSuccess`) + `withAuth()` headers. Never hand-roll envelope/status checks — if you see one, it's debt, don't copy it.
- Query keys: plural string literals (`["products"]`, `["categories"]`, `["brands"]`). One key → one queryFn → one type shape. Never template-build keys (the `${entity}s` bug).
- Mutations invalidate the exact literal keys in `onSuccess`; then the UI re-reads server state — never patch cache/data locally.

## Errors and mutations

- No per-mutation `onError` toasts — the global `MutationCache` in `src/app/providers.tsx` toasts the API `message`. Opt out with `meta: { silent: true }` only when the form shows the error inline (e.g. login).
- All mutations use `useMutation` — no manual `saving` state + try/catch (see `entity-quick-add-modal.tsx` for the anti-pattern).
- Success/error text comes from the API message where available; client checks are UX-only hints — the API response is the source of truth.

## UI

- shadcn `components/ui` + Tailwind tokens only; one `cn` import source; one Toaster (sonner from `providers.tsx` — the extra `Toaster` in `(app)/layout.tsx` is dead debt, don't add more); no raw palette classes or inline gradient styles.

## Known debt (do not copy)

See root `AGENTS.md` → "Known inconsistencies". The main ones here: `product-form.tsx` (`payload as never`), `products-tab.tsx` (status filter never sent to the API), `entity-quick-add-modal.tsx` (manual saving/try-catch instead of `useMutation`), two `cn` import sources, hardcoded English in the Products feature.

## Verification

- `npm run typecheck` && `npm run lint` before finishing. If typecheck errors point at `.next/types/validator.ts`, delete `.next/types` (gitignored, stale build output) and rerun.
- Husky pre-commit also runs lint + typecheck.
