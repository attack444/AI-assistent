// =============================================================================
//  КЭШ ОТВЕТОВ в Redis. Экономит деньги: одинаковый запрос к модели не гоняем
//  дважды. Ключ = хэш от (тип задачи + сообщения). TTL — например, сутки.
//
//  Использование в контроллере:
//     const key = cache.key("agent", messages);
//     const hit = await cache.get(key);
//     if (hit) { ...отдать hit, залогировать как cached... }
//     else    { const r = await llm.complete(...); await cache.set(key, r); }
// =============================================================================
const crypto = require("crypto");
const redis = require("../redis");

const DEFAULT_TTL = 60 * 60 * 24; // 24 часа

function key(taskType, payload) {
  const h = crypto
    .createHash("sha256")
    .update(taskType + "|" + JSON.stringify(payload))
    .digest("hex");
  return `cache:${taskType}:${h}`;
}

async function get(k) {
  const raw = await redis.get(k);
  return raw ? JSON.parse(raw) : null;
}

async function set(k, value, ttl = DEFAULT_TTL) {
  await redis.set(k, JSON.stringify(value), "EX", ttl);
}

module.exports = { key, get, set, DEFAULT_TTL };
