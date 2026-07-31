"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { getStatus } from "@/lib/api";

export default function HomePage() {
  const [status, setStatus] = useState<string>("проверяю API…");
  const [authHint, setAuthHint] = useState("");

  useEffect(() => {
    getStatus()
      .then((s) => {
        const bits = [
          s.deepseek ? "DeepSeek" : null,
          s.groq ? "Groq" : null,
          s.ollama ? "Ollama" : null,
        ].filter(Boolean);
        setStatus(bits.length ? `API онлайн · ${bits.join(" · ")}` : "API онлайн");
        setAuthHint(s.auth_required ? "Нужен пароль панели" : "Пароль не задан");
      })
      .catch(() => setStatus("API пока недоступен — запусти backend"));
  }, []);

  return (
    <main className="hero-home">
      <div className="panel hero-card">
        <div className="brand">
          AI Helper
          <span>серверная панель</span>
        </div>
        <h1>Файлы, сайты и AI в одном интерфейсе</h1>
        <p>
          Открой эту страницу по адресу <span className="mono">http://IP/</span> —
          это и есть интерфейс сервера. Дальше: файлы, сайты, чат.
        </p>
        <div className="hero-actions">
          <Link className="btn" href="/files">
            Открыть файлы
          </Link>
          <Link className="btn ghost" href="/sites">
            Мои сайты
          </Link>
          <Link className="btn ghost" href="/chat">
            Чат
          </Link>
          <Link className="btn ghost" href="/login">
            Вход
          </Link>
        </div>
        <div className="status-pill">
          <span className="dot" />
          {status}
          {authHint ? ` · ${authHint}` : ""}
        </div>
      </div>
    </main>
  );
}
