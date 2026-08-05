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

// -----------------------------------------------------------------------------
//  Страховка безопасности: в проде НЕЛЬЗЯ стартовать с дефолтным/слабым
//  секретом сессии (иначе куки можно подделать). В dev — только предупреждаем.
//  Вызывается из index.js при старте.
// -----------------------------------------------------------------------------
const WEAK_SECRETS = new Set(["dev-insecure-secret", "change-me-please-32-chars-minimum-secret", ""]);
module.exports.assertSafeConfig = function assertSafeConfig() {
  const s = module.exports.sessionSecret;
  const weak = WEAK_SECRETS.has(s) || s.length < 24;
  if (module.exports.nodeEnv === "production") {
    if (weak) {
      throw new Error(
        "SESSION_SECRET не задан или слишком слабый. В проде задай случайную строку ≥24 символов (например: openssl rand -hex 32)."
      );
    }
    if (!module.exports.databaseUrl) throw new Error("DATABASE_URL обязателен в проде.");
  } else if (weak) {
    console.warn("[web] ВНИМАНИЕ: слабый SESSION_SECRET — ок для dev, но в проде замени на случайный.");
  }
};
