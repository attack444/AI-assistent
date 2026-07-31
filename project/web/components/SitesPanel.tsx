"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  bindSiteDomain,
  createSite,
  deleteSite,
  deploySiteZip,
  fixSitePerms,
  listSites,
  migrateSite,
  SiteInfo,
} from "@/lib/api";

function formatSize(n: number) {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export function SitesPanel() {
  const [sites, setSites] = useState<SiteInfo[]>([]);
  const [sitesRoot, setSitesRoot] = useState("");
  const [name, setName] = useState("");
  const [domain, setDomain] = useState("");
  const [zip, setZip] = useState<File | null>(null);
  const [error, setError] = useState("");
  const [okMsg, setOkMsg] = useState("");
  const [busy, setBusy] = useState(false);
  const [step, setStep] = useState(1);

  async function refresh() {
    setBusy(true);
    setError("");
    try {
      const data = await listSites();
      setSites(data.sites || []);
      setSitesRoot(data.sites_root || "");
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    void refresh();
  }, []);

  async function onMigrate(e: FormEvent) {
    e.preventDefault();
    if (!name.trim() || !zip) {
      setError("Укажи имя сайта и выбери ZIP с хостинга");
      return;
    }
    if (zip.size > 180 * 1024 * 1024) {
      setError("ZIP больше 180 МБ — сожми или залей через SCP");
      return;
    }
    setBusy(true);
    setError("");
    setOkMsg("");
    try {
      const res = await migrateSite({
        name: name.trim(),
        domain: domain.trim() || undefined,
        file: zip,
      });
      setOkMsg(
        `Готово: ${res.site.url}` +
          (res.site.has_index ? "" : " (проверь index.html в Файлах)") +
          (domain.trim() ? `. Домен: ${domain.trim()}` : ""),
      );
      setName("");
      setDomain("");
      setZip(null);
      setStep(1);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onCreate(e: FormEvent) {
    e.preventDefault();
    if (!name.trim()) return;
    setBusy(true);
    setError("");
    try {
      await createSite(name.trim(), domain.trim());
      setName("");
      setDomain("");
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(siteName: string) {
    if (!window.confirm(`Удалить сайт «${siteName}» и все файлы?`)) return;
    try {
      await deleteSite(siteName);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function onDeployZip(siteName: string, file: File | null) {
    if (!file) return;
    setBusy(true);
    setError("");
    try {
      await deploySiteZip(siteName, file);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onFixPerms(siteName?: string) {
    setBusy(true);
    setError("");
    try {
      const res = await fixSitePerms(siteName);
      setOkMsg(
        `Права исправлены: ${(res.fixed || []).join(", ") || "ok"}. Обнови страницу сайта. ` +
          (res.hint || ""),
      );
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onBindDomain(siteName: string) {
    const d = window.prompt("Домен (например site.ru)", "");
    if (!d) return;
    setBusy(true);
    setError("");
    try {
      const res = await bindSiteDomain(siteName, d.trim());
      setOkMsg(res.hint || `Домен ${d} привязан`);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      {error ? <div className="error-banner">{error}</div> : null}
      {okMsg ? (
        <div
          className="error-banner"
          style={{
            background: "rgba(26, 127, 75, 0.1)",
            color: "var(--ok)",
            borderColor: "rgba(26, 127, 75, 0.25)",
          }}
        >
          {okMsg}
        </div>
      ) : null}

      <div className="panel create-site migrate-wizard">
        <div>
          <strong>Перенос с хостинга</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Скачай ZIP со старого хостинга (public_html / www) → залей сюда.
            Большие архивы лучше без лишних бэкапов. Альтернатива:{" "}
            <span className="mono">scp site.zip root@IP:/var/ai-helper/sites/имя/</span>
          </p>
        </div>

        <div className="wizard-steps">
          <button type="button" className={step === 1 ? "active" : ""} onClick={() => setStep(1)}>
            1. Имя
          </button>
          <button type="button" className={step === 2 ? "active" : ""} onClick={() => setStep(2)}>
            2. ZIP
          </button>
          <button type="button" className={step === 3 ? "active" : ""} onClick={() => setStep(3)}>
            3. Готово
          </button>
        </div>

        <form onSubmit={onMigrate} className="migrate-form">
          {step === 1 && (
            <>
              <input
                className="input"
                placeholder="имя сайта (mysite)"
                value={name}
                onChange={(e) => setName(e.target.value)}
                pattern="[a-zA-Z0-9_-]+"
                required
              />
              <input
                className="input"
                placeholder="домен сейчас не обязателен"
                value={domain}
                onChange={(e) => setDomain(e.target.value)}
              />
              <button className="btn" type="button" disabled={!name.trim()} onClick={() => setStep(2)}>
                Дальше
              </button>
            </>
          )}
          {step === 2 && (
            <>
              <label className="btn ghost" style={{ justifyContent: "flex-start" }}>
                {zip ? zip.name : "Выбрать ZIP с хостинга"}
                {zip ? ` (${formatSize(zip.size)})` : ""}
                <input
                  type="file"
                  accept=".zip,application/zip"
                  hidden
                  onChange={(e) => setZip(e.target.files?.[0] || null)}
                />
              </label>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button className="btn ghost" type="button" onClick={() => setStep(1)}>
                  Назад
                </button>
                <button className="btn" type="button" disabled={!zip} onClick={() => setStep(3)}>
                  Дальше
                </button>
              </div>
            </>
          )}
          {step === 3 && (
            <>
              <p className="muted" style={{ margin: 0 }}>
                Сайт <strong>{name || "—"}</strong>
                {domain ? <> · домен <strong>{domain}</strong></> : null}
                {zip ? <> · файл <span className="mono">{zip.name}</span></> : null}
              </p>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button className="btn ghost" type="button" onClick={() => setStep(2)}>
                  Назад
                </button>
                <button className="btn" type="submit" disabled={busy || !name.trim() || !zip}>
                  {busy ? "Загрузка…" : "Перенести на VPS"}
                </button>
              </div>
            </>
          )}
        </form>
      </div>

      <div className="panel create-site" style={{ marginTop: 14 }}>
        <div>
          <strong>Или пустой сайт</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Создать папку без ZIP — потом загрузить файлы вручную.
          </p>
        </div>
        <form onSubmit={onCreate}>
          <input
            className="input"
            placeholder="имя"
            value={name}
            onChange={(e) => setName(e.target.value)}
            pattern="[a-zA-Z0-9_-]+"
            required
          />
          <input
            className="input"
            placeholder="домен"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
          />
          <button className="btn ghost" type="submit" disabled={busy}>
            Создать
          </button>
        </form>
      </div>

      <div className="panel create-site" style={{ marginTop: 14 }}>
        <div>
          <strong>403 Forbidden?</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Обычно Nginx не может прочитать файлы. Нажми кнопку или на VPS:{" "}
            <span className="mono">bash project/deploy/fix-sites-403.sh</span>
          </p>
        </div>
        <button className="btn ghost" type="button" disabled={busy} onClick={() => onFixPerms()}>
          Исправить права всех сайтов
        </button>
      </div>

      <div className="sites-grid" style={{ marginTop: 14 }}>
        {sites.length === 0 ? (
          <div className="panel empty">
            Сайтов пока нет. Используй мастер «Перенос с хостинга» выше.
          </div>
        ) : (
          sites.map((site) => (
            <div key={site.name} className="panel site-row">
              <div>
                <h3>{site.name}</h3>
                <p className="muted" style={{ margin: "6px 0 0" }}>
                  <a href={site.url} target="_blank" rel="noreferrer">
                    {site.url}
                  </a>
                  {" · "}
                  {site.files} файлов · {formatSize(site.size_bytes)}
                  {site.has_index ? " · index найден" : " · нет index"}
                  {site.domain ? ` · ${site.domain}` : ""}
                </p>
                <p className="mono muted" style={{ margin: "6px 0 0" }}>
                  {site.path}
                </p>
              </div>
              <div className="site-actions">
                <a className="btn ghost small" href={site.url} target="_blank" rel="noreferrer">
                  Открыть
                </a>
                <label className="btn small">
                  ZIP
                  <input
                    type="file"
                    accept=".zip,application/zip"
                    hidden
                    onChange={(e) => onDeployZip(site.name, e.target.files?.[0] || null)}
                  />
                </label>
                <button className="btn ghost small" type="button" onClick={() => onBindDomain(site.name)}>
                  Домен
                </button>
                <button className="btn ghost small" type="button" onClick={() => onFixPerms(site.name)}>
                  403→fix
                </button>
                <button className="btn danger small" type="button" onClick={() => onDelete(site.name)}>
                  Удалить
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
