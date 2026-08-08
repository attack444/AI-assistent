# Лёгкий образ шлюза моделей.
FROM node:22-alpine
WORKDIR /app
# Сначала только манифесты — чтобы слой с зависимостями кэшировался.
COPY package.json ./
RUN npm install --omit=dev
COPY src ./src
EXPOSE 8090
CMD ["node", "src/index.js"]
