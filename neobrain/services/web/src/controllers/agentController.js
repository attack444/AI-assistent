// =============================================================================
//  AI-АГЕНТ: правки кода через diff + прозрачная стоимость.
//
//  Поток:
//    1) /api/agent/quote  — считаем цену задачи ДО запуска (клиент подтверждает).
//    2) /api/agent/run    — собираем контекст (только git diff!), проверяем кэш,
//       вызываем модель через gateway, списываем деньги, логируем расход.
// =============================================================================
const { z } = require("zod");
const llm = require("../llm");
const cache = require("../middleware/cache");
const { collectDiff, diffToPrompt } = require("../utils/diff");

const runSchema = z.object({
  projectId: z.string().uuid().optional(),
  instruction: z.string().min(3, "опиши задачу"),
  // Для diff-режима: либо путь к репозиторию на сервере, либо готовый diff-текст.
  repoDir: z.string().optional(),
  diffText: z.string().optional(),
});

// Построить сообщения для модели из инструкции и diff.
async function buildMessages({ instruction, repoDir, diffText }) {
  let userContent;
  if (repoDir) {
    const diff = await collectDiff(repoDir); // берём git diff из репозитория
    userContent = diffToPrompt(diff, instruction);
  } else if (diffText) {
    userContent = diffToPrompt({ files: [{ file: "changes", status: "M", patch: diffText }] }, instruction);
  } else {
    userContent = `Задача: ${instruction}`;
  }
  return [
    { role: "system", content: "Ты — аккуратный senior-инженер. Отвечай патчем в формате diff." },
    { role: "user", content: userContent },
  ];
}

// Смета цены (для кнопки «Показать цену» перед запуском).
async function quote(req, res) {
  const { instruction = "", diffText = "" } = req.body || {};
  const q = await llm.quote({
    taskType: "agent",
    inputText: `${instruction}\n${diffText}`,
    expectedOutputTokens: 1500,
  });
  res.json(q);
}

// Запуск агента.
async function run(req, res) {
  try {
    const { projectId, instruction, repoDir, diffText } = req.body;
    const messages = await buildMessages({ instruction, repoDir, diffText });

    // 1) Кэш: одинаковый запрос не оплачиваем повторно.
    const cacheKey = cache.key("agent", messages);
    const cached = await cache.get(cacheKey);
    if (cached) {
      await llm.logCached({ taskType: "agent", model: cached.model, userId: req.user.id, projectId });
      return res.json({ ...cached, cached: true });
    }

    // 2) Живой вызов модели (gateway сам выберет модель и посчитает цену).
    const result = await llm.complete({
      taskType: "agent",
      messages,
      userId: req.user.id,
      projectId,
    });

    // 3) Кладём в кэш на сутки.
    await cache.set(cacheKey, result);
    res.json({ ...result, cached: false });
  } catch (err) {
    res.status(502).json({ error: err.message });
  }
}

module.exports = { quote, run, runSchema, buildMessages };
