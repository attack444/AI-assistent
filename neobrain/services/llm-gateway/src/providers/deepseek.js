// =============================================================================
//  DeepSeek-провайдер (OpenAI-совместимый API).
//  Ключ берётся из DEEPSEEK_API_KEY. Если ключа нет — этот провайдер бросит
//  ошибку, и gateway сделает fallback (в итоге на mock).
// =============================================================================
const { estimateTokens } = require("../pricing");

const API_URL = "https://api.deepseek.com/chat/completions";

async function complete({ model, messages }) {
  const key = process.env.DEEPSEEK_API_KEY;
  if (!key) throw new Error("DEEPSEEK_API_KEY не задан");

  // Node 22 имеет глобальный fetch — внешние зависимости не нужны.
  const resp = await fetch(API_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${key}`,
    },
    body: JSON.stringify({ model, messages, stream: false }),
  });

  if (!resp.ok) {
    const body = await resp.text();
    throw new Error(`DeepSeek ${resp.status}: ${body.slice(0, 200)}`);
  }

  const data = await resp.json();
  const text = data.choices?.[0]?.message?.content || "";
  // Провайдер обычно возвращает usage — берём его; иначе оцениваем сами.
  const promptTokens =
    data.usage?.prompt_tokens ??
    estimateTokens(messages.map((m) => m.content).join("\n"));
  const outputTokens = data.usage?.completion_tokens ?? estimateTokens(text);

  return { text, promptTokens, outputTokens };
}

module.exports = { complete };
