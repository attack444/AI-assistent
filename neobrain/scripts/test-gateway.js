// =============================================================================
//  Юнит-проверки чистой логики llm-gateway (без сети и без БД).
//  Проверяем: выбор модели по типу задачи, расчёт цены, работу mock-провайдера.
//  ЗАПУСК:  node scripts/test-gateway.js
// =============================================================================
const assert = require("assert");
const path = require("path");
const base = path.join(__dirname, "../services/llm-gateway/src");

process.env.USD_RUB_RATE = "95";
process.env.PRICE_MARKUP = "1.5";
delete process.env.DEEPSEEK_API_KEY; // без ключей → должен выбираться mock
delete process.env.ANTHROPIC_API_KEY;

const pricing = require(path.join(base, "pricing"));
const gateway = require(path.join(base, "gateway"));

(async () => {
  // 1) Цена в рублях считается по формуле (DeepSeek, 8000/2000 токенов).
  const rub = pricing.costRub("deepseek-chat", 8000, 2000);
  // (8000/1e6*0.27 + 2000/1e6*1.10)*95*1.5 ≈ 0.6213
  assert.ok(Math.abs(rub - 0.6213) < 0.01, `ожидали ~0.62₽, получили ${rub}`);
  console.log("✓ расчёт цены DeepSeek:", rub, "₽");

  // 2) Claude заметно дороже на тех же токенах.
  const rubClaude = pricing.costRub("claude-3-5-sonnet", 8000, 2000);
  assert.ok(rubClaude > rub * 5, "Claude должен быть в разы дороже");
  console.log("✓ расчёт цены Claude:", rubClaude, "₽ (дороже в", (rubClaude / rub).toFixed(1) + "x)");

  // 3) Без ключей выбирается mock (fallback).
  const picked = gateway.pickModel("agent");
  assert.strictEqual(picked, "mock", `без ключей ожидали mock, получили ${picked}`);
  console.log("✓ выбор модели без ключей → mock");

  // 4) complete() отрабатывает через mock и считает токены/цену.
  const r = await gateway.complete({ taskType: "agent", messages: [{ role: "user", content: "привет" }] });
  assert.ok(r.ok && r.text.includes("mock"), "mock должен ответить");
  assert.ok(r.costRub >= 0 && r.promptTokens > 0, "должны быть токены и цена");
  console.log("✓ complete() через mock:", { model: r.model, tokens: `${r.promptTokens}/${r.outputTokens}`, costRub: r.costRub });

  console.log("\nВсе проверки llm-gateway пройдены ✅");
})().catch((e) => {
  console.error("✗ провал:", e.message);
  process.exit(1);
});
