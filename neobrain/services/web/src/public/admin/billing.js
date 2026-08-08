// Дашборд: тянем агрегаты и рисуем 3 графика Chart.js.
let charts = {};

async function load() {
  const from = document.getElementById("from").value;
  const to = document.getElementById("to").value;
  const qs = new URLSearchParams();
  if (from) qs.set("from", from);
  if (to) qs.set("to", to);
  const res = await fetch("/api/admin/billing?" + qs.toString());
  if (!res.ok) { alert("Нет доступа (нужна роль admin)"); return; }
  const d = await res.json();

  document.getElementById("totalRub").textContent = d.totals.totalRub + " ₽";
  document.getElementById("tasks").textContent = d.totals.tasks;
  document.getElementById("cacheRate").textContent = d.totals.cacheHitRate + " %";

  draw("byDay", "line", d.byDay.map((x) => x.key), d.byDay.map((x) => x.rub), "₽ по дням");
  draw("byModel", "bar", d.byModel.map((x) => x.key), d.byModel.map((x) => x.rub), "₽ по моделям");
  draw("byProject", "bar", d.byProject.map((x) => x.key), d.byProject.map((x) => x.rub), "₽ по проектам");
}

function draw(id, type, labels, data, label) {
  if (charts[id]) charts[id].destroy();
  charts[id] = new Chart(document.getElementById(id), {
    type,
    data: { labels, datasets: [{ label, data, borderWidth: 2, tension: 0.3 }] },
    options: { responsive: true, plugins: { legend: { display: true } } },
  });
}

document.getElementById("reload").addEventListener("click", load);
load();
