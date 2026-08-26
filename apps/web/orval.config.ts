import { defineConfig } from 'orval';

export default defineConfig({
  api: {
    input: 'http://localhost:8000/docs/api.json',
    output: {
      target: './src/lib/api/generated.ts',
      schemas: './src/lib/api/model',
      client: 'react-query',
      mode: 'tags-split',
      // Scramble strips the leading `api` segment from paths and emits
      // server "/api" — Orval ignores that server, so we rebuild the base
      // here. NEXT_PUBLIC_API_URL must NOT already include /api.
      baseUrl: process.env.NEXT_PUBLIC_API_URL
        ? `${process.env.NEXT_PUBLIC_API_URL}/api`
        : 'http://localhost:8000/api',
      override: {
        query: {
          useQuery: true,
          useSuspenseQuery: true,
        },
      },
    },
  },
});

