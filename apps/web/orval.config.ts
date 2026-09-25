import { defineConfig } from 'orval';

const apiOrigin = process.env.NEXT_PUBLIC_API_URL;

if (!apiOrigin) {
  throw new Error(
    'NEXT_PUBLIC_API_URL is required. Set it to your API origin, for example https://api.example.com',
  );
}

const normalizedApiOrigin = apiOrigin.replace(/\/$/, '');

export default defineConfig({
  api: {
    input: `${normalizedApiOrigin}/docs/api.json`,
    output: {
      target: './src/lib/api/generated.ts',
      schemas: './src/lib/api/model',
      client: 'react-query',
      mode: 'tags-split',
      // Scramble strips the leading `api` segment from paths and emits
      // server "/api" — Orval ignores that server, so we rebuild the base
      // here. NEXT_PUBLIC_API_URL must NOT already include /api.
      baseUrl: `${normalizedApiOrigin}/api`,
      override: {
        query: {
          useQuery: true,
          useSuspenseQuery: true,
        },
      },
    },
  },
});

