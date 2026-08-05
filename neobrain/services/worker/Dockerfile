# Образ worker-сервиса. Контекст сборки = корень neobrain/ (нужна общая схема Prisma).
FROM node:22-alpine
WORKDIR /app

COPY services/worker/package.json ./package.json
RUN npm install

COPY prisma ./prisma
RUN npx prisma generate --schema=prisma/schema.prisma

COPY services/worker/src ./src

CMD ["node", "src/index.js"]
