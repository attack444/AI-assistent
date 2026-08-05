// SEO-аудит: ставим задачу и поллим статус, пока не completed.
document.getElementById("auditBtn")?.addEventListener("click", async () => {
  const urls = document.getElementById("urls").value.split("\n").map((s) => s.trim()).filter(Boolean);
  const msg = document.getElementById("auditMsg");
  const res = await nbFetch("/api/seo/audit", { method: "POST", body: JSON.stringify({ urls }) });
  const d = await res.json();
  if (!res.ok) { msg.textContent = d.error || JSON.stringify(d.details); return; }
  msg.textContent = `Задача ${d.task_id} запущена…`;
  pollTask("seo-audit", d.task_id);
});

async function pollTask(queue, id) {
  const box = document.getElementById("auditResult");
  const timer = setInterval(async () => {
    const res = await fetch(`/api/tasks/${queue}/${id}`);
    const s = await res.json();
    document.getElementById("auditMsg").textContent = `Статус: ${s.state} (${s.progress || 0}%)`;
    if (s.state === "completed") {
      clearInterval(timer);
      const rows = (s.result?.results || [])
        .map((r) => `<div class="card"><b>${r.url}</b> — HTTP ${r.status}
          <br>title: ${r.title || "—"}<br>issues: ${(r.issues || []).join(", ") || "нет"}</div>`)
        .join("");
      box.innerHTML = `<p>Готово. С проблемами: ${s.result?.summary?.withIssues}/${s.result?.summary?.total}</p>` + rows;
    }
    if (s.state === "failed" || s.state === "not_found") clearInterval(timer);
  }, 1000);
}

document.getElementById("metaBtn")?.addEventListener("click", async () => {
  const res = await nbFetch("/api/seo/meta", {
    method: "POST",
    body: JSON.stringify({ text: document.getElementById("metaText").value }),
  });
  const d = await res.json();
  document.getElementById("metaResult").textContent = res.ok
    ? `${d.text}\n\n(модель ${d.model}, ${d.costRub} ₽${d.cached ? ", из кэша" : ""})`
    : d.error;
});
