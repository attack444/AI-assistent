# AI Helper — Next.js control panel
FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install

FROM node:20-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
# basePath печётся на build — runtime ENV в compose недостаточно
ARG PANEL_BASE_PATH=/console
ENV PANEL_BASE_PATH=$PANEL_BASE_PATH
ENV NEXT_PUBLIC_PANEL_BASE_PATH=$PANEL_BASE_PATH
ENV NEXT_TELEMETRY_DISABLED=1
ENV API_INTERNAL_URL=http://app:8502
RUN npm run build

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0
ENV API_INTERNAL_URL=http://app:8502
ARG PANEL_BASE_PATH=/console
ENV PANEL_BASE_PATH=$PANEL_BASE_PATH
ENV NEXT_PUBLIC_PANEL_BASE_PATH=$PANEL_BASE_PATH

COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static

EXPOSE 3000
CMD ["node", "server.js"]
