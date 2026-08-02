"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { login, setToken } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      const res = await login(password);
      setToken(res.token || "open");
      router.push("/overview/");
    } catch (err) {
      // 401 «Неверный пароль» остаётся на этой странице (не редирект на витрину)
      setError((err as Error).message || "Ошибка входа");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="hero-home">
      <div className="panel hero-card" style={{ maxWidth: 420 }}>
        <div className="brand">
          NeoBrain
          <span>вход в панель</span>
        </div>
        <h1>Пароль сервера</h1>
        <p>Тот же, что в `PANEL_PASSWORD` в файле `.env` на VPS.</p>
        {error ? <div className="error-banner">{error}</div> : null}
        <form onSubmit={onSubmit} style={{ display: "grid", gap: 12 }}>
          <input
            className="input"
            type="password"
            autoFocus
            placeholder="Пароль"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          <button className="btn" type="submit" disabled={busy}>
            {busy ? "…" : "Войти"}
          </button>
        </form>
      </div>
    </main>
  );
}
