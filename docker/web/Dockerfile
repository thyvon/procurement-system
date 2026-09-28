# Production-grade web image (standalone output recommended later).
FROM node:22-alpine
WORKDIR /app

RUN corepack enable

COPY pnpm-workspace.yaml package.json pnpm-lock.yaml ./
COPY apps/web/package.json apps/web/

RUN pnpm install --frozen-lockfile

COPY apps/web apps/web

# The generated API client resolves its origin at build time from
# NEXT_PUBLIC_API_URL (no domain is baked into src/lib/api), so the image
# build must receive it — docker-compose passes it as a build arg.
ARG NEXT_PUBLIC_API_URL
RUN test -n "$NEXT_PUBLIC_API_URL" || (echo "missing build arg: NEXT_PUBLIC_API_URL" >&2 && exit 1)
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL

RUN pnpm --filter web build

EXPOSE 3000
CMD ["pnpm", "--filter", "web", "start"]
