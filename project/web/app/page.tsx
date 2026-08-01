"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { getStatus, listSites, SiteInfo } from "@/lib/api";

export default function HomePage() {
  const [status, setStatus] = useState("проверяю API…");
  const [authHint, setAuthHint] = useState("");
  const [sites, setSites] = useState<SiteInfo[]>([]);

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

    listSites()
      .then((d) => setSites(d.sites || []))
      .catch(() => setSites([]));
  }, []);

  return (
    <main className="hero-home">
      <div className="panel hero-card">
        <div className="brand">
          AI Helper
          <span>хостинг + ассистент</span>
        </div>
        <h1>Один сервер — сайты, редактор и AI</h1>
        <p>
          Панель на этом VPS: сайт <strong>5mb2</strong> уже на хостинге,
          сайт <strong>ai</strong> — витрина и среда для правок. HTTPS позже.
        </p>
        <div className="hero-actions">
          <Link className="btn" href="/overview">
            Обзор системы
          </Link>
          <Link className="btn ghost" href="/sites">
            Сайты
          </Link>
          <Link className="btn ghost" href="/health">
            Здоровье
          </Link>
          <Link className="btn ghost" href="/chat?site=server">
            DeepSeek → бэкенд
          </Link>
          <Link className="btn ghost" href="/feedback">
            Обратная связь
          </Link>
          <Link className="btn ghost" href="/files?path=/opt/sites/ai">
            Редактор ai
          </Link>
          <Link className="btn ghost" href="/login">
            Вход
          </Link>
        </div>
        {sites.length ? (
          <div className="muted" style={{ marginTop: 18, fontSize: "0.95rem" }}>
            На сервере:{" "}
            {sites.map((s, i) => (
              <span key={s.name}>
                {i ? " · " : ""}
                <Link href={`/files?path=${encodeURIComponent(s.path)}`}>
                  {s.name}
                </Link>
                {s.domain ? ` (${s.domain})` : ""}
              </span>
            ))}
          </div>
        ) : null}
        <div className="status-pill">
          <span className="dot" />
          {status}
          {authHint ? ` · ${authHint}` : ""}
        </div>
      </div>
    </main>
  );
}
