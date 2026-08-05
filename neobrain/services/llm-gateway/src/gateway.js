// =============================================================================
//  ЯДРО ШЛЮЗА: единая точка обращения к любой модели.
//  Задача gateway:
//    1) по типу задачи выбрать модель (routine → дешёвая, сложная → мощная);
//    2) вызвать провайдера, при ошибке — fallback на следующую модель;
//    3) посчитать реальную стоимость в рублях;
//    4) вернуть текст + метрики (модель, токены, цена).
//  Логирование в БД делает web/worker (у них есть prisma) — gateway остаётся
//  без зависимости от БД и легко масштабируется.
// =============================================================================
const { MODELS, TASK_MODEL, FALLBACK_ORDER } = require("./models");
const pricing = require("./pricing");

const providers = {
  mock: require("./providers/mock"),
  deepseek: require("./providers/deepseek"),
  claude: require("./providers/claude"),
};

// Есть ли у нас реальный ключ для провайдера этой модели?
function hasKey(modelId) {
  const p = MODELS[modelId]?.provider;
  if (p === "deepseek") return !!process.env.DEEPSEEK_API_KEY;
  if (p === "claude") return !!process.env.ANTHROPIC_API_KEY;
  return true; // mock всегда доступен
}

// Выбор модели по типу задачи с учётом наличия ключей.
function pickModel(taskType, forceModel) {
  if (forceModel && MODELS[forceModel]) return forceModel;
  const preferred = TASK_MODEL[taskType] || TASK_MODEL.default;
  if (hasKey(preferred)) return preferred;
  // Нет ключа для предпочтительной — берём первую доступную из fallback.
  return FALLBACK_ORDER.find((m) => hasKey(m)) || "mock";
}

// Построить очередь попыток: выбранная модель + остальные доступные (fallback).
function buildChain(primary) {
  const chain = [primary];
  for (const m of FALLBACK_ORDER) {
    if (m !== primary && hasKey(m)) chain.push(m);
  }
  if (!chain.includes("mock")) chain.push("mock"); // mock — последний рубеж
  return chain;
}

// Основной вызов: прогоняем цепочку моделей, пока какая-то не ответит.
async function complete({ taskType = "default", messages = [], forceModel } = {}) {
  const primary = pickModel(taskType, forceModel);
  const chain = buildChain(primary);

  let lastError;
  for (const modelId of chain) {
    const provider = providers[MODELS[modelId].provider];
    try {
      const r = await provider.complete({ model: modelId, messages });
      return {
        ok: true,
        model: modelId,
        tier: MODELS[modelId].tier,
        text: r.text,
        promptTokens: r.promptTokens,
        outputTokens: r.outputTokens,
        costRub: pricing.costRub(modelId, r.promptTokens, r.outputTokens),
        usedFallback: modelId !== primary,
      };
    } catch (err) {
      lastError = err;
      // Пробуем следующую модель в цепочке.
    }
  }
  // Сюда попадём только если даже mock упал (почти невозможно).
  throw new Error(`Все модели недоступны: ${lastError?.message}`);
}

module.exports = { complete, pickModel, buildChain, MODELS };
