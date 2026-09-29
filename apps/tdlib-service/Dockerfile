FROM node:22-slim AS base
WORKDIR /app

COPY pnpm-lock.yaml pnpm-workspace.yaml package.json ./
COPY apps/tdlib-service/package.json apps/tdlib-service/

RUN corepack enable && pnpm install --filter @procurement/tdlib-service --frozen-lockfile

COPY apps/tdlib-service apps/tdlib-service

ENV NODE_ENV=production
EXPOSE 3080

WORKDIR /app/apps/tdlib-service
CMD ["node", "src/index.js"]
