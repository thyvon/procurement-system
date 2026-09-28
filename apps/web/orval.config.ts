import { defineConfig } from 'orval';

/**
 * OpenAPI spec source — usually the local API (`php artisan serve` on :8000).
 * Override with ORVAL_SPEC_URL only when you intentionally generate against
 * another environment's spec.
 */
const specOrigin = (process.env.ORVAL_SPEC_URL ?? 'http://localhost:8000').replace(
  /\/$/,
  '',
);

export default defineConfig({
  api: {
    input: `${specOrigin}/docs/api.json`,
    output: {
      target: './src/lib/api/generated.ts',
      schemas: './src/lib/api/model',
      client: 'react-query',
      mode: 'tags-split',
      // Scramble strips the leading `api` segment from paths and emits
      // server "/api" — Orval ignores that server, so we rebuild the base
      // here. The origin is emitted as a runtime env expression (single
      // quotes keep `${...}` literal) so no domain is ever baked into the
      // generated client: dev reads apps/web/.env.local, the Docker image
      // gets NEXT_PUBLIC_API_URL as a build arg.
      baseUrl: '${process.env.NEXT_PUBLIC_API_URL}/api',
      override: {
        query: {
          useQuery: true,
          useSuspenseQuery: true,
        },
      },
    },
  },
});
