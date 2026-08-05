// =============================================================================
//  КВОТЫ и RATE-LIMIT через Redis.
//
//  1) rateLimit — общий ограничитель частоты (защита от флуда/DDoS).
//     Считаем счётчик в окне windowSec, при превышении → 429.
//
//  2) enforceQuota — бизнес-квота: не больше N тяжёлых задач в месяц у клиента.
//     Ключ содержит год-месяц, поэтому счётчик сам «обнуляется» на новый месяц.
//
//  3) requireBalance — у клиента должно быть достаточно денег на балансе.
// =============================================================================
const redis = require("../redis");

// --- Rate limit: max запросов за windowSec с одного ключа (ip или userId) ---
function rateLimit({ windowSec = 60, max = 60, keyFn } = {}) {
  return async (req, res, next) => {
    try {
      const id = keyFn ? keyFn(req) : req.user?.id || req.ip;
      const k = `rl:${req.baseUrl}${req.path}:${id}`;
      // INCR + при первом обращении ставим TTL окна.
      const count = await redis.incr(k);
      if (count === 1) await redis.expire(k, windowSec);
      if (count > max) {
        return res.status(429).json({ error: "слишком много запросов, подождите" });
      }
      next();
    } catch (e) {
      // Если Redis недоступен — не блокируем пользователя (fail-open),
      // но пишем в лог.
      console.error("[rateLimit]", e.message);
      next();
    }
  };
}

// --- Месячная квота на тяжёлые задачи ---------------------------------------
function enforceQuota() {
  return async (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: "нужен вход" });
    const ym = new Date().toISOString().slice(0, 7); // "2026-08"
    const k = `quota:${req.user.id}:${ym}`;
    const used = await redis.incr(k);
    if (used === 1) await redis.expire(k, 60 * 60 * 24 * 40); // ~40 дней
    if (used > req.user.monthlyQuota) {
      // откатываем счётчик, чтобы не «съедать» квоту при отказе
      await redis.decr(k);
      return res.status(429).json({ error: "исчерпана месячная квота задач" });
    }
    next();
  };
}

// --- Проверка баланса (pay-per-action) --------------------------------------
function requireBalance(minRub = 0.01) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: "нужен вход" });
    if (Number(req.user.balanceRub) < minRub) {
      return res.status(402).json({ error: "недостаточно средств, пополните баланс" });
    }
    next();
  };
}

module.exports = { rateLimit, enforceQuota, requireBalance };
