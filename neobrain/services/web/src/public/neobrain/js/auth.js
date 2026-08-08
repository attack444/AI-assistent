// Вход и регистрация. При успехе — редирект в кабинет.
document.getElementById("loginForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await nbFetch("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email: f.email.value, password: f.password.value }),
  });
  const data = await res.json();
  document.getElementById("loginMsg").textContent = res.ok ? "Готово!" : data.error;
  if (res.ok) location.href = "/neobrain/cabinet";
});

document.getElementById("registerForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await nbFetch("/api/auth/register", {
    method: "POST",
    body: JSON.stringify({
      email: f.email.value,
      password: f.password.value,
      displayName: f.displayName.value || undefined,
    }),
  });
  const data = await res.json();
  document.getElementById("regMsg").textContent = res.ok ? "Аккаунт создан!" : (data.error || JSON.stringify(data.details));
  if (res.ok) location.href = "/neobrain/cabinet";
});
