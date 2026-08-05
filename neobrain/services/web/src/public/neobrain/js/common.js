// Общие помощники фронта: CSRF-обёртка над fetch и логаут.
// Все изменяющие запросы (POST/DELETE) шлём через nbFetch — он добавит CSRF.
function getCookie(name) {
  return document.cookie.split("; ").find((c) => c.startsWith(name + "="))?.split("=")[1];
}

async function nbFetch(url, options = {}) {
  const opts = { headers: {}, ...options };
  opts.headers["Content-Type"] = "application/json";
  // Double-submit CSRF: отправляем тот же токен, что лежит в куки.
  opts.headers["X-CSRF-Token"] = getCookie("csrf") || "";
  const res = await fetch(url, opts);
  return res;
}

// Логаут (кнопка в шапке).
document.getElementById("logoutBtn")?.addEventListener("click", async () => {
  await nbFetch("/api/auth/logout", { method: "POST" });
  location.href = "/neobrain";
});

window.nbFetch = nbFetch;
