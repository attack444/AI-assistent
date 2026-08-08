// Админ-CMS студии: создание игр/релизов/страниц + публикация через очередь.
// Использует nbFetch (общий помощник с CSRF из common.js).

// --- Создать игру ---
document.getElementById("gameForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const body = {
    slug: f.slug.value.trim(),
    title: f.title.value.trim(),
    tagline: f.tagline.value || undefined,
    coverUrl: f.coverUrl.value || undefined,
    playUrl: f.playUrl.value || undefined,
    accent: f.accent.value || undefined,
    status: f.status.value,
    featured: f.featured.checked,
    description: f.description.value || undefined,
  };
  const res = await nbFetch("/api/admin/games", { method: "POST", body: JSON.stringify(body) });
  const d = await res.json();
  document.getElementById("gameMsg").textContent = res.ok ? "Игра создана ✓" : (d.error || JSON.stringify(d.details));
  if (res.ok) f.reset();
});

// --- Создать черновик релиза ---
document.getElementById("releaseForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const body = {
    gameSlug: f.gameSlug.value.trim(),
    kind: f.kind.value,
    title: f.title.value.trim(),
    version: f.version.value || undefined,
    body: f.body.value,
  };
  const res = await nbFetch("/api/admin/releases", { method: "POST", body: JSON.stringify(body) });
  const d = await res.json();
  document.getElementById("releaseMsg").textContent = res.ok ? "Черновик создан ✓" : (d.error || JSON.stringify(d.details));
  if (res.ok) { f.reset(); loadReleases(); }
});

// --- Список черновиков + публикация ---
async function loadReleases() {
  const res = await fetch("/api/admin/releases");
  const { releases } = await res.json();
  const box = document.getElementById("releaseList");
  if (!releases || releases.length === 0) { box.innerHTML = '<p class="hint">Пока нет записей.</p>'; return; }
  box.innerHTML = releases
    .map((r) => `<div class="card" style="margin-bottom:10px">
      <b>${r.title}</b> <span class="hint">[${r.kind}${r.version ? " v" + r.version : ""}] · игра ${r.gameSlug} · ${r.published ? "опубликовано" : "черновик"}</span>
      ${r.published ? "" : `<div><button class="btn-ghost" data-id="${r.id}">Опубликовать</button> <span class="msg" id="pub-${r.id}"></span></div>`}
    </div>`)
    .join("");
  box.querySelectorAll("button[data-id]").forEach((b) =>
    b.addEventListener("click", async () => {
      const res = await nbFetch(`/api/admin/releases/${b.dataset.id}/publish`, { method: "POST" });
      const d = await res.json();
      document.getElementById(`pub-${b.dataset.id}`).textContent = res.ok ? `в очереди: ${d.task_id}` : d.error;
      setTimeout(loadReleases, 1200); // обновим статус после публикации воркером
    })
  );
}

// --- Сохранить страницу «О студии» ---
document.getElementById("pageForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await nbFetch("/api/admin/pages", {
    method: "POST",
    body: JSON.stringify({ site: "games", slug: "about", title: f.title.value, html: f.html.value }),
  });
  const d = await res.json();
  document.getElementById("pageMsg").textContent = res.ok ? "Страница сохранена ✓" : (d.error || JSON.stringify(d.details));
});

loadReleases();
