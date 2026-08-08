# Образ web-сервиса. Контекст сборки = корень репозитория neobrain/,
# потому что нужен и prisma/schema.prisma (общая схема), и код сервиса.
FROM node:22-alpine
WORKDIR /app

# 1) Зависимости (кэшируемый слой).
COPY services/web/package.json ./package.json
RUN npm install

# 2) Общая схема Prisma + генерация клиента.
COPY prisma ./prisma
RUN npx prisma generate --schema=prisma/schema.prisma

# 3) Код сервиса.
COPY services/web/src ./src

EXPOSE 8080
# Миграции применяются командой из docker-compose перед стартом.
CMD ["node", "src/index.js"]
