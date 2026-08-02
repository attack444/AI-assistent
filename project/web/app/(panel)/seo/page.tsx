"use client";

import { useCallback, useEffect, useState } from "react";
import {
  SeoCheck,
  SeoChecklistItem,
  SeoReport,
  SeoSiteReport,
  getSeoReport,
  runSeoNewsDrafts,
} from "@/lib/api";

function Pill({ ok, label }: { ok: boolean; label: string }) {
  return (
    <span
      className="status-pill"
      style={{
        background: ok ? "rgba(46,160,67,0.15)" : "rgba(200,60,60,0.12)",
        color: ok ? "#2ea043" : "#c44",
      }}
    >
      {label}
    </span>
  );
}

function CheckRow({ c }: { c: SeoCheck }) {
  return (
    <div
      style={{
        display: "flex",
        gap: 10,
        alignItems: "baseline",
        padding: "6px 0",
        borderBottom: "1px solid rgba(0,0,0,0.06)",
        fontSize: "0.9rem",
      }}
    >
      <span style={{ color: c.ok ? "#2ea043" : "#c44", minWidth: 18 }}>{c.ok ? "✓" : "•"}</span>
      <span className="mono" style={{ minWidth: 120 }}>{c.id}</span>
      <span className="muted">{c.detail}</span>
    </div>
  );
}

function SiteCard({ site }: { site: SeoSiteReport }) {
  return (
    <div className="panel" style={{ padding: 16 }}>
      <div style={{ display: "flex", gap: 10, alignItems: "center", flexWrap: "wrap", marginBottom: 8 }}>
        <strong>{site.name}</strong>
        <Pill ok={!!site.ok} label={site.ok ? "OK" : "нужно внимание"} />
        <a href={site.url} target="_blank" rel="noreferrer" className="muted" style={{ fontSize: "0.85rem" }}>
          {site.url}
        </a>
      </div>
      {site.title ? <p className="muted" style={{ marginBottom: 8 }}>Title: {site.title}</p> : null}
      {(site.checks || []).map((c) => (
        <CheckRow key={c.id} c={c} />
      ))}
      <div style={{ marginTop: 12, display: "flex", gap: 10, flexWrap: "wrap", fontSize: "0.85rem" }}>
        <a href={site.links?.sitemap} target="_blank" rel="noreferrer">sitemap</a>
        <a href={site.links?.robots} target="_blank" rel="noreferrer">robots</a>
        <a href={site.links?.webmaster} target="_blank" rel="noreferrer">Яндекс.Вебмастер</a>
        <a href={site.links?.gsc} target="_blank" rel="noreferrer">Google Search Console</a>
      </div>
    </div>
  );
}

export default function SeoPage() {
  const [report, setReport] = useState<SeoReport | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [newsOut, setNewsOut] = useState("");

  const load = useCallback(() => {
    setLoading(true);
    setError("");
    getSeoReport()
      .then(setReport)
      .catch((e: Error) => setError(e.message || "Ошибка"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const runNews = async (dry: boolean) => {
    setBusy(dry ? "dry" : "news");
    setError("");
    setNewsOut("");
    try {
      const r = await runSeoNewsDrafts({ dry_run: dry });
      setNewsOut(r.output || r.error || (r.ok ? "Готово" : "Ошибка"));
      const fresh = await getSeoReport();
      setReport(fresh);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Не удалось запустить");
    } finally {
      setBusy("");
    }
  };

  const checklist: SeoChecklistItem[] = report?.checklist || [];
  const open = checklist.filter((i) => !i.done);

  return (
    <>
      <div className="page-head">
        <h1>SEO и сайты</h1>
        <p>
          Автопроверки 5mb2 + NeoBrain, чеклист запуска и черновики новостей из RSS
          (бот <strong>не публикует</strong> сам — только черновики в WordPress).
        </p>
        <div className="hero-actions" style={{ marginTop: 12, flexWrap: "wrap", gap: 8 }}>
          <button className="btn ghost small" type="button" onClick={load} disabled={loading || !!busy}>
            {loading ? "Проверяю…" : "Проверить сейчас"}
          </button>
          <button className="btn small" type="button" onClick={() => runNews(false)} disabled={!!busy}>
            {busy === "news" ? "Собираю…" : "Собрать черновики новостей"}
          </button>
          <button className="btn ghost small" type="button" onClick={() => runNews(true)} disabled={!!busy}>
            {busy === "dry" ? "…" : "Пробный прогон"}
          </button>
        </div>
      </div>

      {error ? <p style={{ color: "#c44" }}>{error}</p> : null}

      <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "center" }}>
          <Pill ok={!!report?.ok} label={report?.ok ? "SEO-контур в порядке" : "Есть задачи"} />
          <span className="muted" style={{ fontSize: "0.9rem" }}>
            Открыто: {report?.open_count ?? "—"} · {report?.at || ""}
          </span>
          {report?.state?.last_news_run ? (
            <span className="muted" style={{ fontSize: "0.85rem" }}>
              Последние черновики: {report.state.last_news_run}
            </span>
          ) : null}
        </div>
      </div>

      <h2 style={{ fontSize: "1.1rem", margin: "0 0 10px" }}>Что сделать дальше (по порядку)</h2>
      <div className="panel" style={{ padding: 16, marginBottom: 20 }}>
        {checklist.length === 0 && loading ? <p className="muted">Загрузка…</p> : null}
        {checklist.map((item) => (
          <div
            key={item.id}
            style={{
              display: "grid",
              gridTemplateColumns: "28px 1fr",
              gap: 8,
              padding: "8px 0",
              borderBottom: "1px solid rgba(0,0,0,0.06)",
              opacity: item.done ? 0.55 : 1,
            }}
          >
            <span style={{ color: item.done ? "#2ea043" : "#c44" }}>{item.done ? "✓" : String(item.priority)}</span>
            <div>
              <div style={{ fontWeight: 600 }}>{item.title}</div>
              <div className="muted" style={{ fontSize: "0.85rem" }}>{item.where}</div>
            </div>
          </div>
        ))}
        {open.length === 0 && checklist.length > 0 ? (
          <p style={{ color: "#2ea043", marginTop: 12 }}>Критичные пункты закрыты — держи ритм с контентом.</p>
        ) : null}
      </div>

      <h2 style={{ fontSize: "1.1rem", margin: "0 0 10px" }}>Проверки сайтов</h2>
      <div style={{ display: "grid", gap: 16, marginBottom: 20 }}>
        {(report?.sites || []).map((s) => (
          <SiteCard key={s.id} site={s} />
        ))}
      </div>

      {newsOut ? (
        <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
          <strong>Результат черновиков</strong>
          <pre
            className="mono"
            style={{
              whiteSpace: "pre-wrap",
              fontSize: "0.8rem",
              marginTop: 8,
              maxHeight: 240,
              overflow: "auto",
            }}
          >
            {newsOut}
          </pre>
        </div>
      ) : null}

      <div className="panel" style={{ padding: 16 }}>
        <strong>Автоматизация на сервере</strong>
        <p className="muted" style={{ marginTop: 8, fontSize: "0.9rem" }}>
          Один раз на VPS:{" "}
          <span className="mono">sudo bash /opt/ai-helper/project/deploy/install-seo-cron.sh</span>
          — два раза в день RSS → черновики в WordPress. Публикация всегда руками.
        </p>
        <ol className="muted" style={{ marginTop: 10, paddingLeft: 18, fontSize: "0.9rem" }}>
          {(report?.next_human || []).map((line) => (
            <li key={line} style={{ marginBottom: 4 }}>{line}</li>
          ))}
        </ol>
      </div>
    </>
  );
}
