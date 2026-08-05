// =============================================================================
//  Единая точка конфигурации web-сервиса. Всё берём из окружения (.env),
//  чтобы секреты не попадали в код (требование безопасности).
// =============================================================================
module.exports = {
  port: Number(process.env.WEB_PORT || 8080),
  nodeEnv: process.env.NODE_ENV || "development",

  databaseUrl: process.env.DATABASE_URL,
  redisUrl: process.env.REDIS_URL || "redis://127.0.0.1:6379",
  llmGatewayUrl: process.env.LLM_GATEWAY_URL || "http://127.0.0.1:8090",

  // SSO: куки ставим на общий родительский домен, чтобы её видели оба сайта.
  cookieDomain: process.env.COOKIE_DOMAIN || undefined,
  sessionSecret: process.env.SESSION_SECRET || "dev-insecure-secret",
  sessionTtlSec: 60 * 60 * 24 * 7, // 7 дней

  // Какой хост — какой сайт. Помогает одному web-контейнеру обслуживать 2 домена.
  hostsGames: (process.env.HOSTS_GAMES || "5mb2.local,5mb2.ru,localhost").split(","),
};
