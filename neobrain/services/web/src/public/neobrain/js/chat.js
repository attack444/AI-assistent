// Агент: сначала «Показать цену» (quote), затем «Запустить» (run).
document.getElementById("quoteBtn")?.addEventListener("click", async () => {
  const res = await nbFetch("/api/agent/quote", {
    method: "POST",
    body: JSON.stringify({
      instruction: document.getElementById("instruction").value,
      diffText: document.getElementById("diffText").value,
    }),
  });
  const q = await res.json();
  document.getElementById("priceBadge").textContent =
    `≈ ${q.costRub} ₽ · модель ${q.model} · ~${q.promptTokens} вход. токенов`;
});

document.getElementById("runBtn")?.addEventListener("click", async () => {
  const btn = document.getElementById("runBtn");
  btn.disabled = true;
  btn.textContent = "Работаю…";
  const res = await nbFetch("/api/agent/run", {
    method: "POST",
    body: JSON.stringify({
      instruction: document.getElementById("instruction").value,
      diffText: document.getElementById("diffText").value || undefined,
    }),
  });
  const d = await res.json();
  const box = document.getElementById("answerBox");
  box.style.display = "block";
  if (res.ok) {
    document.getElementById("answerMeta").textContent =
      `Модель: ${d.model} · токены ${d.promptTokens}/${d.outputTokens} · стоимость ${d.costRub} ₽ ${d.cached ? "(из кэша, 0 ₽)" : ""}`;
    document.getElementById("answerText").textContent = d.text;
  } else {
    document.getElementById("answerMeta").textContent = "Ошибка";
    document.getElementById("answerText").textContent = d.error;
  }
  btn.disabled = false;
  btn.textContent = "Запустить";
});
