FROM node:20-alpine

WORKDIR /app

RUN corepack enable && corepack prepare pnpm@10.33.3 --activate

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml ./
COPY packages ./packages
COPY apps/web ./apps/web

RUN pnpm install --frozen-lockfile

WORKDIR /app/apps/web

ARG COGNIFY_API_URL
ENV COGNIFY_API_URL=${COGNIFY_API_URL}

RUN pnpm build

RUN chown -R node:node /app

USER node

EXPOSE 3000

CMD ["pnpm", "start"]
