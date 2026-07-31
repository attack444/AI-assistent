"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  createSite,
  deleteSite,
  deploySiteZip,
  listSites,
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
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

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
      const buf = await file.arrayBuffer();
      const bytes = new Uint8Array(buf);
      let binary = "";
      const chunk = 0x8000;
      for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
      }
      await deploySiteZip(siteName, btoa(binary), file.name);
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

      <div className="panel create-site">
        <div>
          <strong>Новый сайт</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Файлы попадут в {sitesRoot || "/opt/sites"}/имя — доступ по{" "}
            <span className="mono">/sites/имя/</span>
          </p>
        </div>
        <form onSubmit={onCreate}>
          <input
            className="input"
            placeholder="имя (mysite)"
            value={name}
            onChange={(e) => setName(e.target.value)}
            pattern="[a-zA-Z0-9_-]+"
            required
          />
          <input
            className="input"
            placeholder="домен (опционально)"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
          />
          <button className="btn" type="submit" disabled={busy}>
            Создать
          </button>
        </form>
      </div>

      <div className="sites-grid">
        {sites.length === 0 ? (
          <div className="panel empty">
            Сайтов пока нет. Создай сайт и загрузи ZIP с текущего хостинга.
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
                  {site.has_index ? " · index найден" : " · нет index.html"}
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
