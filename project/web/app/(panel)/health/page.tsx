"use client";

import { useCallback, useEffect, useState } from "react";
import {
  FeedbackItem,
  HealthCheck,
  SystemHealthReport,
  getSystemHealth,
  listSystemIncidents,
  runSystemWatchdog,
} from "@/lib/api";

export default function HealthPage() {
  const [report, setReport] = useState<SystemHealthReport | null>(null);
  const [incidents, setIncidents] = useState<FeedbackItem[]>([]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");

  const load = useCallback(() => {
    setLoading(true);
    setError("");
    Promise.all([getSystemHealth(), listSystemIncidents(40)])
      .then(([h, inc]) => {
        setReport(h);
        setIncidents(inc.items || []);
      })
      .catch((e: Error) => setError(e.message || "Не удалось загрузить"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const run = async (askDeepseek: boolean) => {
    setBusy(askDeepseek ? "deepseek" : "fix");
    setError("");
    try {
      const r = await runSystemWatchdog({
        remediate: true,
        ask_deepseek: askDeepseek,
      });
      setReport(r);
      const inc = await listSystemIncidents(40);
      setIncidents(inc.items || []);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Ошибка watchdog");
    } finally {
      setBusy("");
    }
  };

  const checks: HealthCheck[] = report?.checks || [];

  return (
    <>
      <div className="page-head">
        <h1>Здоровье системы</h1>
        <p>
          Мониторинг <strong>панели</strong>, <strong>API/DeepSeek</strong>,{" "}
          <strong>5mb2</strong> и <strong>NeoBrain</strong>. При сбое —
          безопасный restart контейнеров и запись в inbox; DeepSeek — для
          разбора и правок (в приоритете над бесплатной моделью).
        </p>
        <div className="hero-actions" style={{ marginTop: 12, flexWrap: "wrap", gap: 8 }}>
          <button className="btn ghost small" type="button" onClick={load} disabled={loading || !!busy}>
            {loading ? "Обновляю…" : "Обновить статус"}
          </button>
          <button
            className="btn small"
            type="button"
            onClick={() => run(false)}
            disabled={!!busy}
          >
            {busy === "fix" ? "Чиню…" : "Проверить + safe fix"}
          </button>
          <button
            className="btn ghost small"
            type="button"
            onClick={() => run(true)}
            disabled={!!busy}
          >
            {busy === "deepseek" ? "DeepSeek…" : "Чинить через DeepSeek"}
          </button>
        </div>
      </div>

      {error ? (
        <p className="muted" style={{ color: "#c44" }}>
          {error}
        </p>
      ) : null}

      <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
        <div style={{ display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap" }}>
          <span
            className="status-pill"
            style={{
              background: report?.ok ? "rgba(46,160,67,0.15)" : "rgba(200,60,60,0.15)",
            }}
          >
            <span className="dot" />
            {loading
              ? "проверяю…"
              : report?.ok
                ? "Все проверки OK"
                : `Сбой: ${(report?.failed || []).join(", ") || "unknown"}`}
          </span>
          {report?.at ? <span className="muted mono">{report.at}</span> : null}
          {report?.priority_ok === false ? (
            <span className="muted" style={{ color: "#c44" }}>
              Приоритет (панель/API/DeepSeek) нарушен
            </span>
          ) : null}
          {report?.recovered ? (
            <span className="muted" style={{ color: "#2ea043" }}>
              Восстановлено после safe fix
            </span>
          ) : null}
        </div>
      </div>

      <div style={{ display: "grid", gap: 10, marginBottom: 24 }}>
        {checks.map((c) => (
          <div
            key={c.id || c.label}
            className="panel"
            style={{
              padding: "12px 16px",
              display: "grid",
              gridTemplateColumns: "auto 1fr auto",
              gap: 12,
              alignItems: "center",
            }}
          >
            <span style={{ color: c.ok ? "#2ea043" : "#c44", fontWeight: 600 }}>
              {c.ok ? "OK" : "FAIL"}
            </span>
            <div>
              <div>
                {c.label || c.id}
                {c.priority === 1 ? (
                  <span className="muted" style={{ marginLeft: 8, fontSize: "0.8rem" }}>
                    приоритет
                  </span>
                ) : null}
              </div>
              {(c.error || c.warn) && (
                <div className="muted" style={{ fontSize: "0.85rem" }}>
                  {c.error || c.warn}
                </div>
              )}
            </div>
            <span className="muted mono" style={{ fontSize: "0.8rem" }}>
              {c.status ?? "—"}
              {typeof c.ms === "number" ? ` · ${c.ms}ms` : ""}
            </span>
          </div>
        ))}
      </div>

      {report?.ai_repair?.reply ? (
        <div className="panel" style={{ padding: 16, marginBottom: 24 }}>
          <h2 style={{ marginTop: 0, fontSize: "1.1rem" }}>Ответ DeepSeek</h2>
          <p style={{ whiteSpace: "pre-wrap", margin: 0 }}>{report.ai_repair.reply}</p>
          {report.ai_repair.tools?.length ? (
            <p className="muted mono" style={{ marginTop: 8, fontSize: "0.8rem" }}>
              tools: {report.ai_repair.tools.join(", ")}
            </p>
          ) : null}
        </div>
      ) : null}

      <h2 style={{ fontSize: "1.15rem" }}>Инциденты watchdog</h2>
      <p className="muted" style={{ marginTop: 0 }}>
        Дублируются в «Обратная связь» (source=watchdog). Cron:{" "}
        <span className="mono">project/deploy/system-watchdog.sh</span>
      </p>
      {!incidents.length ? (
        <div className="panel" style={{ padding: 16 }}>
          <p className="muted" style={{ margin: 0 }}>
            Инцидентов пока нет — так и должно быть.
          </p>
        </div>
      ) : (
        <div style={{ display: "grid", gap: 10 }}>
          {incidents.map((it, idx) => (
            <article key={`${it.at}-${idx}`} className="panel" style={{ padding: 14 }}>
              <div className="muted" style={{ fontSize: "0.85rem", marginBottom: 6 }}>
                {it.at || "—"}
                {it.source ? ` · ${it.source}` : ""}
              </div>
              <p style={{ margin: 0, whiteSpace: "pre-wrap" }}>{it.message}</p>
            </article>
          ))}
        </div>
      )}
    </>
  );
}
