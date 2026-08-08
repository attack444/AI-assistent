// =============================================================================
//  MOCK-провайдер: работает без интернета и без ключей.
//  Нужен, чтобы разрабатывать и тестировать всю систему бесплатно.
//  Возвращает осмысленный ответ и ЧЕСТНО считает «токены» по длине текста.
// =============================================================================
const { estimateTokens } = require("../pricing");

async function complete({ messages }) {
  const inputText = messages.map((m) => m.content).join("\n");
  const promptTokens = estimateTokens(inputText);

  // Простой предсказуемый «ответ» — эхо + подсказка, что это mock.
  const last = messages[messages.length - 1]?.content || "";
  const text =
    `[mock-ответ] Принял задачу: "${last.slice(0, 120)}". ` +
    `Это ответ MOCK-провайдера (нет ключей API). ` +
    `В проде здесь будет ответ реальной модели.`;

  const outputTokens = estimateTokens(text);
  return { text, promptTokens, outputTokens };
}

module.exports = { complete };
