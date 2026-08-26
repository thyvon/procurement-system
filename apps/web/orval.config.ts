import { defineConfig } from 'orval';

export default defineConfig({
  api: {
    input: 'http://localhost:8000/docs/api.json',
    output: {
      target: './src/lib/api/generated.ts',
      schemas: './src/lib/api/model',
      client: 'react-query',
      mode: 'tags-split',
      baseUrl: process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000',
      override: {
        query: {
          useQuery: true,
          useSuspenseQuery: true,
        },
      },
    },
  },
});
