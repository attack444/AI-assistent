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
        <p>
          Только из{" "}
          <span className="mono">/opt/ai-helper/project/.env</span> →{" "}
          <span className="mono">PANEL_PASSWORD</span>. Не пароль WordPress / SSH /
          reg.ru.
        </p>
        <p className="muted" style={{ fontSize: "0.85rem" }}>
          Сброс на VPS:{" "}
          <span className="mono">sudo bash project/deploy/reset-panel-password.sh</span>
        </p>
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
