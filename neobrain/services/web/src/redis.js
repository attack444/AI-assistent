// =============================================================================
//  Подключение к Redis (ioredis). Один клиент используем для кэша, квот,
//  rate-limit и хранения сессий. Для BullMQ создаём отдельные соединения
//  в queue.js (у очередей свои требования к соединению).
// =============================================================================
const IORedis = require("ioredis");
const config = require("./config");

const redis = new IORedis(config.redisUrl, {
  // Не роняем процесс при кратком недоступности Redis — переподключаемся.
  maxRetriesPerRequest: null,
  lazyConnect: false,
});

redis.on("error", (e) => console.error("[redis] ошибка:", e.message));

module.exports = redis;
