"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  Capability,
  DnsInfo,
  SystemOverview,
  getSystemOverview,
} from "@/lib/api";

function Rec({ label, values }: { label: string; values?: string[] }) {
  if (!values?.length) {
    return (
      <div className="muted" style={{ fontSize: "0.85rem" }}>
        <strong>{label}:</strong> —
      </div>
    );
  }
  return (
    <div style={{ fontSize: "0.85rem" }}>
      <strong>{label}:</strong>{" "}
      <span className="mono">{values.join(" · ")}</span>
    </div>
  );
}

function DnsCard({ d }: { d: DnsInfo }) {
  const bad = d.issues?.length || d.points_to_vps === false;
  return (
    <article
      className="panel"
      style={{
        padding: 16,
        borderColor: bad ? "rgba(200,60,60,0.45)" : undefined,
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between", gap: 8, flexWrap: "wrap" }}>
        <strong>{d.domain || "—"}</strong>
        <span className="muted" style={{ fontSize: "0.85rem" }}>
          сайт: {d.site || "—"}
          {d.healthy ? " · DNS OK" : " · есть замечания"}
        </span>
      </div>
      <div style={{ marginTop: 10, display: "grid", gap: 4 }}>
        <Rec label="A" values={d.records?.A} />
        <Rec label="AAAA" values={d.records?.AAAA} />
        <Rec label="NS" values={d.records?.NS} />
        <Rec label="MX" values={d.records?.MX} />
        <Rec label="TXT" values={d.records?.TXT} />
        <Rec label="www A" values={d.www_a} />
        {d.expected_ip ? (
          <div className="muted" style={{ fontSize: "0.85rem" }}>
            Ожидаемый IP VPS: <span className="mono">{d.expected_ip}</span>
            {d.points_to_vps === true
              ? " · совпадает"
              : d.points_to_vps === false
                ? " · НЕ совпадает"
                : ""}
          </div>
        ) : null}
      </div>
      {d.issues?.length ? (
        <ul style={{ margin: "10px 0 0", paddingLeft: 18, color: "#c44" }}>
          {d.issues.map((x) => (
            <li key={x} style={{ marginBottom: 4 }}>
              {x}
            </li>
          ))}
        </ul>
      ) : null}
      {d.error ? <p style={{ color: "#c44" }}>{d.error}</p> : null}
    </article>
  );
}

function CapRow({ c }: { c: Capability }) {
  return (
    <div className="panel" style={{ padding: "12px 16px" }}>
      <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "baseline" }}>
        <strong>{c.label}</strong>
        <span className="muted" style={{ fontSize: "0.8rem" }}>
          DeepSeek: {c.deepseek ? (c.available_now === false ? "да (нужен mount)" : "да") : "нет"}
          {" · "}
          Панель: {c.panel ? "да" : "нет"}
        </span>
      </div>
      <p className="muted" style={{ margin: "6px 0 0", fontSize: "0.9rem" }}>
        {c.how}
      </p>
      {c.note ? (
        <p className="muted" style={{ margin: "4px 0 0", fontSize: "0.85rem" }}>
          {c.note}
        </p>
      ) : null}
      {c.workspace ? (
        <p className="mono muted" style={{ margin: "4px 0 0", fontSize: "0.8rem" }}>
          {c.workspace}
        </p>
      ) : null}
    </div>
  );
}

export default function OverviewPage() {
  const [data, setData] = useState<SystemOverview | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    setError("");
    getSystemOverview()
      .then(setData)
      .catch((e: Error) => setError(e.message || "Ошибка загрузки"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const health = data?.health;
  const api = data?.api_status;

  return (
    <>
      <div className="page-head">
        <h1>Обзор системы</h1>
        <p>
          Единая картина: LLM/DeepSeek, мониторинг, DNS, Docker, что умеет править ассистент.
          Подробный отчёт работ — в репозитории{" "}
          <span className="mono">project/deploy/SYSTEM_REPORT_RU.md</span>.
        </p>
        <div className="hero-actions" style={{ marginTop: 12, flexWrap: "wrap", gap: 8 }}>
          <button className="btn ghost small" type="button" onClick={load} disabled={loading}>
            {loading ? "Обновляю…" : "Обновить"}
          </button>
          <Link className="btn small" href="/chat?site=server">
            Чат → бэкенд (DeepSeek)
          </Link>
          <Link className="btn ghost small" href="/health">
            Здоровье / watchdog
          </Link>
          <Link className="btn ghost small" href="/sites">
            Сайты
          </Link>
        </div>
      </div>

      {error ? <p style={{ color: "#c44" }}>{error}</p> : null}

      <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
        <div style={{ display: "flex", flexWrap: "wrap", gap: 12, alignItems: "center" }}>
          <span className="status-pill">
            <span className="dot" />
            {api?.deepseek ? "DeepSeek ON" : "DeepSeek OFF"}
            {api?.deepseek_model ? ` · ${api.deepseek_model}` : ""}
          </span>
          <span className="muted mono">API {api?.version || "—"}</span>
          <span className="muted mono">VPS IP {data?.vps_ip || "—"}</span>
          <span className="muted">{data?.at || ""}</span>
          {health ? (
            <span style={{ color: health.ok ? "#2ea043" : "#c44" }}>
              Мониторинг: {health.ok ? "OK" : `FAIL ${(health.failed || []).join(", ")}`}
            </span>
          ) : null}
        </div>
        <p className="muted" style={{ margin: "10px 0 0", fontSize: "0.9rem" }}>
          Free LLM prefer: {api?.llm_prefer_free ? "да (виджет)" : "нет"} · Ollama:{" "}
          {api?.ollama ? "да" : "нет"} · Бэкенд editable:{" "}
          {data?.workspaces?.server_editable ? "да" : "нет (смонтируй /opt/ai-helper)"}
        </p>
      </div>

      <h2 style={{ fontSize: "1.15rem" }}>DNS</h2>
      <p className="muted" style={{ marginTop: 0 }}>
        Если A не на IP VPS или NS на старом hosting.reg.ru — с браузера «сайт лежит», хотя по IP
        с Host-заголовком всё может открываться.
      </p>
      <div style={{ display: "grid", gap: 12, marginBottom: 24 }}>
        {(data?.dns || []).length ? (
          data?.dns?.map((d) => <DnsCard key={d.domain || d.error} d={d} />)
        ) : (
          <div className="panel" style={{ padding: 16 }}>
            <p className="muted" style={{ margin: 0 }}>
              {loading ? "Загружаю DNS…" : "Доменов пока нет"}
            </p>
          </div>
        )}
      </div>

      <h2 style={{ fontSize: "1.15rem" }}>Что умеет DeepSeek vs панель</h2>
      <div style={{ display: "grid", gap: 10, marginBottom: 24 }}>
        {(data?.capabilities || []).map((c) => (
          <CapRow key={c.id || c.label} c={c} />
        ))}
      </div>

      <h2 style={{ fontSize: "1.15rem" }}>Docker</h2>
      <div className="panel" style={{ padding: 16, marginBottom: 24 }}>
        {(data?.docker || []).length ? (
          <div style={{ display: "grid", gap: 6 }}>
            {data?.docker?.map((c) => (
              <div key={c.name} className="mono" style={{ fontSize: "0.85rem" }}>
                {c.name} — {c.status}
                {c.ports ? ` · ${c.ports}` : ""}
              </div>
            ))}
          </div>
        ) : (
          <p className="muted" style={{ margin: 0 }}>
            Docker ps недоступен из контейнера API (нормально). Watchdog на хосте делает restart.
          </p>
        )}
      </div>

      <h2 style={{ fontSize: "1.15rem" }}>Последние инциденты</h2>
      <div style={{ display: "grid", gap: 10 }}>
        {(data?.incidents || []).length ? (
          data?.incidents?.map((it, i) => (
            <article key={`${it.at}-${i}`} className="panel" style={{ padding: 14 }}>
              <div className="muted" style={{ fontSize: "0.85rem" }}>
                {it.at} {it.source ? `· ${it.source}` : ""}
              </div>
              <p style={{ margin: "6px 0 0", whiteSpace: "pre-wrap" }}>{it.message}</p>
            </article>
          ))
        ) : (
          <div className="panel" style={{ padding: 16 }}>
            <p className="muted" style={{ margin: 0 }}>Инцидентов нет</p>
          </div>
        )}
      </div>
    </>
  );
}
