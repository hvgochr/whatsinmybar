FROM node:22-bookworm-slim AS base

WORKDIR /app

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
ENV PNPM_HOME=/pnpm
ENV PATH="${PNPM_HOME}:${PATH}"

RUN corepack enable

FROM base AS development

EXPOSE 3000

CMD ["sh", "-lc", "pnpm install --frozen-lockfile --store-dir /pnpm/store && pnpm dev --host 0.0.0.0 --port 3000"]

FROM base AS production-build

COPY apps/web/package.json apps/web/pnpm-lock.yaml apps/web/pnpm-workspace.yaml ./

RUN pnpm install --frozen-lockfile

COPY apps/web ./

RUN pnpm build

FROM node:22-bookworm-slim AS production

WORKDIR /app

ENV HOST=0.0.0.0
ENV NODE_ENV=production
ENV PORT=3000

COPY --from=production-build --chown=node:node /app/.output ./.output

USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
