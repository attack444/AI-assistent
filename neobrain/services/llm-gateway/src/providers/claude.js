// =============================================================================
//  Claude-провайдер (Anthropic Messages API).
//  Ключ берётся из ANTHROPIC_API_KEY. Формат сообщений отличается от OpenAI:
//  системное сообщение уходит отдельным полем `system`.
// =============================================================================
const { estimateTokens } = require("../pricing");

const API_URL = "https://api.anthropic.com/v1/messages";
// Полное имя модели у Anthropic. Короткий алиас "claude-3-5-sonnet" из models.js
// маппим сюда.
const MODEL_MAP = {
  "claude-3-5-sonnet": "claude-3-5-sonnet-20241022",
};

async function complete({ model, messages }) {
  const key = process.env.ANTHROPIC_API_KEY;
  if (!key) throw new Error("ANTHROPIC_API_KEY не задан");

  const system = messages.find((m) => m.role === "system")?.content;
  const chat = messages.filter((m) => m.role !== "system");

  const resp = await fetch(API_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-api-key": key,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: MODEL_MAP[model] || model,
      max_tokens: 4096,
      system,
      messages: chat.map((m) => ({ role: m.role, content: m.content })),
    }),
  });

  if (!resp.ok) {
    const body = await resp.text();
    throw new Error(`Anthropic ${resp.status}: ${body.slice(0, 200)}`);
  }

  const data = await resp.json();
  const text = data.content?.[0]?.text || "";
  const promptTokens =
    data.usage?.input_tokens ??
    estimateTokens(messages.map((m) => m.content).join("\n"));
  const outputTokens = data.usage?.output_tokens ?? estimateTokens(text);

  return { text, promptTokens, outputTokens };
}

module.exports = { complete };
