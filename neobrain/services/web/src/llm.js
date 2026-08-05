// =============================================================================
//  Клиент к llm-gateway + запись расхода токенов в БД.
//  Всё общение web-сервиса с моделями идёт ТОЛЬКО через эту функцию,
//  чтобы каждая задача автоматически логировалась (экономика/биллинг).
// =============================================================================
const config = require("./config");
const prisma = require("./db");

// Предварительная смета (цена ДО запуска задачи) — для показа клиенту.
async function quote({ taskType, inputText, expectedOutputTokens }) {
  const resp = await fetch(`${config.llmGatewayUrl}/quote`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ taskType, inputText, expectedOutputTokens }),
  });
  return resp.json();
}

// Выполнить задачу через gateway и записать расход в token_logs.
// cached-результат (см. middleware/cache.js) логируем с costRub=0.
async function complete({ taskType, messages, userId, projectId, forceModel }) {
  const resp = await fetch(`${config.llmGatewayUrl}/complete`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ taskType, messages, forceModel }),
  });
  if (!resp.ok) {
    const body = await resp.text();
    throw new Error(`gateway ${resp.status}: ${body.slice(0, 200)}`);
  }
  const r = await resp.json();

  // Списываем деньги с баланса клиента и пишем лог (в одной транзакции).
  await prisma.$transaction([
    prisma.tokenLog.create({
      data: {
        userId: userId || null,
        projectId: projectId || null,
        taskType: taskType || "default",
        model: r.model,
        promptTokens: r.promptTokens,
        outputTokens: r.outputTokens,
        costRub: r.costRub,
        cached: false,
      },
    }),
    ...(userId
      ? [
          prisma.user.update({
            where: { id: userId },
            data: { balanceRub: { decrement: r.costRub } },
          }),
        ]
      : []),
  ]);

  return r;
}

// Записать «бесплатный» лог для ответа, отданного из кэша (стоимость 0).
async function logCached({ taskType, model, userId, projectId }) {
  await prisma.tokenLog.create({
    data: {
      userId: userId || null,
      projectId: projectId || null,
      taskType: taskType || "default",
      model: model || "cache",
      promptTokens: 0,
      outputTokens: 0,
      costRub: 0,
      cached: true,
    },
  });
}

module.exports = { quote, complete, logCached };
