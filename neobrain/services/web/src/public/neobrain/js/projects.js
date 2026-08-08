// Список проектов + создание + кнопка деплоя (ставит задачу в очередь).
async function loadProjects() {
  const res = await fetch("/api/projects");
  const { projects } = await res.json();
  const box = document.getElementById("projectList");
  if (!projects || projects.length === 0) {
    box.innerHTML = '<p class="hint">Проектов пока нет — добавь первый выше.</p>';
    return;
  }
  box.innerHTML = projects
    .map(
      (p) => `<div class="card">
        <h3>${p.name}</h3>
        <p class="hint">${p.repoUrl || "без репозитория"} · ${p.domain || "без домена"}</p>
        <button class="btn-ghost" data-id="${p.id}">Деплой</button>
        <span class="msg" id="dep-${p.id}"></span>
      </div>`
    )
    .join("");
  box.querySelectorAll("button[data-id]").forEach((b) =>
    b.addEventListener("click", async () => {
      const res = await nbFetch(`/api/projects/${b.dataset.id}/deploy`, { method: "POST" });
      const d = await res.json();
      document.getElementById(`dep-${b.dataset.id}`).textContent = res.ok
        ? `Задача поставлена: ${d.task_id}`
        : d.error;
    })
  );
}

document.getElementById("projectForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await nbFetch("/api/projects", {
    method: "POST",
    body: JSON.stringify({
      name: f.name.value,
      repoUrl: f.repoUrl.value || undefined,
      domain: f.domain.value || undefined,
    }),
  });
  if (res.ok) {
    f.reset();
    loadProjects();
  }
});

loadProjects();
