FROM node:22-bookworm-slim

WORKDIR /app

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
ENV PNPM_HOME=/pnpm
ENV PATH="${PNPM_HOME}:${PATH}"

RUN corepack enable

EXPOSE 3000

CMD ["sh", "-lc", "pnpm install --frozen-lockfile --store-dir /pnpm/store && pnpm dev --host 0.0.0.0 --port 3000"]
