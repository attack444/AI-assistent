// =============================================================================
//  HTTP-интерфейс шлюза моделей. Внутри docker-сети слушает порт 8090.
//  Эндпоинты:
//    GET  /health          — проверка «жив ли»
//    GET  /models          — список моделей и цен (для UI/дашборда)
//    POST /quote           — предварительная смета цены ДО запуска задачи
//    POST /complete        — выполнить запрос к модели (с fallback)
// =============================================================================
const express = require("express");
const gateway = require("./gateway");
const pricing = require("./pricing");
const { MODELS, TASK_MODEL } = require("./models");

const app = express();
app.use(express.json({ limit: "2mb" }));

app.get("/health", (_req, res) => res.json({ ok: true, service: "llm-gateway" }));

app.get("/models", (_req, res) => {
  res.json({ models: MODELS, taskModel: TASK_MODEL, rate: pricing.USD_RUB_RATE, markup: pricing.PRICE_MARKUP });
});

// Смета: сколько примерно будет стоить задача. Клиент видит цену до подтверждения.
app.post("/quote", (req, res) => {
  const { taskType = "default", inputText = "", expectedOutputTokens } = req.body || {};
  const model = gateway.pickModel(taskType);
  res.json(pricing.quote(model, inputText, expectedOutputTokens));
});

// Выполнить запрос. Возвращает текст + модель + токены + цену в рублях.
app.post("/complete", async (req, res) => {
  try {
    const { taskType, messages, forceModel } = req.body || {};
    if (!Array.isArray(messages) || messages.length === 0) {
      return res.status(400).json({ ok: false, error: "messages[] обязателен" });
    }
    const result = await gateway.complete({ taskType, messages, forceModel });
    res.json(result);
  } catch (err) {
    res.status(502).json({ ok: false, error: err.message });
  }
});

const PORT = Number(process.env.GATEWAY_PORT || 8090);
app.listen(PORT, () => console.log(`[llm-gateway] слушает порт ${PORT}`));
