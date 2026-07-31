"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  bindSiteDomain,
  createSite,
  deleteSite,
  deploySiteZip,
  fixSitePerms,
  inspectSites,
  listSites,
  migrateSite,
  SiteInfo,
} from "@/lib/api";
import { WordpressSetup } from "@/components/WordpressSetup";

function formatSize(n: number) {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export function SitesPanel() {
  const [sites, setSites] = useState<SiteInfo[]>([]);
  const [sitesRoot, setSitesRoot] = useState("");
  const [hostPath, setHostPath] = useState("/var/ai-helper/sites");
  const [name, setName] = useState("mysite");
  const [domain, setDomain] = useState("");
  const [zip, setZip] = useState<File | null>(null);
  const [error, setError] = useState("");
  const [okMsg, setOkMsg] = useState("");
  const [busy, setBusy] = useState(false);
  const [step, setStep] = useState(1);
  const [progress, setProgress] = useState(0);
  const [progressLabel, setProgressLabel] = useState("");
  const [diagnosis, setDiagnosis] = useState("");
  const [wpOpen, setWpOpen] = useState<string | null>(null);

  async function refresh() {
    setBusy(true);
    setError("");
    try {
      const data = await listSites();
      setSites(data.sites || []);
      setSitesRoot(data.sites_root || "");
      const insp = await inspectSites();
      if (insp.host_sites_path) setHostPath(insp.host_sites_path);
      if (insp.pending_uploads && (insp.pending_uploads as unknown[]).length) {
        setDiagnosis(
          `Незавершённые загрузки: ${(insp.pending_uploads as unknown[]).length}. ` +
            `Файлы ищутся в ${insp.host_sites_path || hostPath}/ и .uploads/`,
        );
      }
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
      setError("Укажи имя сайта и выбери ZIP (WordPress бэкап)");
      return;
    }
    setBusy(true);
    setError("");
    setOkMsg("");
    setProgress(0);
    setProgressLabel("Старт…");
    try {
      const res = await migrateSite({
        name: name.trim(),
        domain: domain.trim() || undefined,
        file: zip,
        onProgress: (pct, label) => {
          setProgress(pct);
          setProgressLabel(label);
        },
      });
      setOkMsg(
        `${res.message || "Готово"} → ${res.site.url} · ${res.site.files} файлов · ` +
          `${formatSize(res.site.size_bytes)}` +
          (res.site.is_wordpress ? " · WordPress" : "") +
          (res.site.host_path ? ` · ${res.site.host_path}` : ""),
      );
      setZip(null);
      setStep(1);
      setProgress(100);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
      setProgress(0);
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
    setProgress(0);
    try {
      if (file.size > 20 * 1024 * 1024) {
        await migrateSite({
          name: siteName,
          file,
          onProgress: (pct, label) => {
            setProgress(pct);
            setProgressLabel(label);
          },
        });
      } else {
        await deploySiteZip(siteName, file);
      }
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
      setOkMsg(`Права: ${(res.fixed || []).join(", ") || "ok"}`);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onInspect(siteName?: string) {
    setBusy(true);
    setError("");
    try {
      const res = await inspectSites(siteName);
      const lines = [
        res.diagnosis || "",
        res.host_path ? `Хост: ${res.host_path}` : `Хост корень: ${res.host_sites_path || hostPath}`,
        res.container_path ? `Контейнер: ${res.container_path}` : "",
        res.site
          ? `Файлов: ${res.site.files}, размер: ${formatSize(res.site.size_bytes)}, WP: ${res.site.is_wordpress ? "да" : "нет"}`
          : "",
        res.site?.top_entries?.length
          ? `Содержимое: ${res.site.top_entries.map((e) => e.name).join(", ")}`
          : "Папка пустая или сайта нет",
      ].filter(Boolean);
      setDiagnosis(lines.join("\n"));
      setOkMsg(siteName ? `Диагностика «${siteName}»` : "Диагностика всех сайтов");
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onBindDomain(siteName: string) {
    const d = window.prompt("Домен сайта (останется как основной URL)", "5mb2.ru");
    if (!d) return;
    setBusy(true);
    setError("");
    try {
      const res = await bindSiteDomain(siteName, d.trim());
      setOkMsg(
        (res.hint || `Домен ${d} привязан`) +
          `\n\nНа VPS (HTTP):\ncurl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/fix-5mb2-http.sh | bash` +
          `\n\nHTTPS (когда HTTP ок):\ncurl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/install-ssl-5mb2.sh | bash`,
      );
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
            whiteSpace: "pre-wrap",
          }}
        >
          {okMsg}
        </div>
      ) : null}
      {diagnosis ? (
        <div className="panel create-site" style={{ whiteSpace: "pre-wrap", fontFamily: "var(--font-mono)", fontSize: "0.85rem" }}>
          {diagnosis}
        </div>
      ) : null}

      <div className="panel create-site migrate-wizard">
        <div>
          <strong>Перенос с хостинга (в т.ч. WordPress)</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Большие ZIP грузятся чанками по 4 МБ — до ~2 ГБ. Файлы на сервере:{" "}
            <span className="mono">{hostPath}/имя/</span>
            {" "}(= <span className="mono">{sitesRoot || "/opt/sites"}</span> в Docker).
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
            3. Загрузка
          </button>
        </div>

        {busy && progress > 0 ? (
          <div>
            <div className="muted" style={{ marginBottom: 6 }}>{progressLabel}</div>
            <div className="progress-track">
              <div className="progress-fill" style={{ width: `${progress}%` }} />
            </div>
          </div>
        ) : null}

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
                placeholder="домен (позже можно)"
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
                {zip ? `${zip.name} (${formatSize(zip.size)})` : "Выбрать ZIP / бэкап WordPress"}
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
                <strong>{name}</strong>
                {zip ? <> · {zip.name} · {formatSize(zip.size)}</> : null}
              </p>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button className="btn ghost" type="button" onClick={() => setStep(2)} disabled={busy}>
                  Назад
                </button>
                <button className="btn" type="submit" disabled={busy || !name.trim() || !zip}>
                  {busy ? "Загрузка…" : "Загрузить и распаковать"}
                </button>
              </div>
            </>
          )}
        </form>
      </div>

      <div className="panel create-site" style={{ marginTop: 14 }}>
        <div>
          <strong>Где файлы / 403 / диагностика</strong>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            Если после загрузки «0 файлов» — ZIP не дошёл. Жми «Найти файлы».
          </p>
        </div>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
          <button className="btn ghost" type="button" disabled={busy} onClick={() => onInspect()}>
            Найти файлы
          </button>
          <button className="btn ghost" type="button" disabled={busy} onClick={() => onFixPerms()}>
            Исправить права
          </button>
        </div>
        <form onSubmit={onCreate} style={{ marginTop: 10 }}>
          <input
            className="input"
            placeholder="или создать пустой сайт"
            value={name}
            onChange={(e) => setName(e.target.value)}
            pattern="[a-zA-Z0-9_-]+"
          />
          <button className="btn ghost" type="submit" disabled={busy || !name.trim()}>
            Создать пустой
          </button>
        </form>
      </div>

      <div className="sites-grid" style={{ marginTop: 14 }}>
        {sites.length === 0 ? (
          <div className="panel empty">
            Сайтов нет или папки пустые. Залей ZIP мастером выше — для WordPress нужен ещё PHP+MySQL.
          </div>
        ) : (
          <>
          <div className="panel create-site" style={{ background: "var(--accent-soft)" }}>
            <strong>WordPress</strong>
            <p className="muted" style={{ margin: "4px 0 0" }}>
              У карточки сайта нажми зелёную кнопку <strong>«Настроить WP»</strong> —
              там wp-config, импорт .sql и замена URL. Если кнопки нет — сначала обнови сервер:
              <span className="mono"> bash /opt/ai-helper/project/deploy/update.sh</span>
            </p>
          </div>
          {sites.map((site) => (
            <div key={site.name}>
            <div className="panel site-row">
              <div>
                <h3>
                  {site.name}
                  {site.is_wordpress ? " · WordPress" : ""}
                </h3>
                <p className="muted" style={{ margin: "6px 0 0" }}>
                  <a href={site.url} target="_blank" rel="noreferrer">
                    {site.url}
                  </a>
                  {" · "}
                  {site.files} файлов · {formatSize(site.size_bytes)}
                  {site.has_index ? " · index ок" : " · нет index"}
                  {site.domain ? ` · ${site.domain}` : ""}
                </p>
                <p className="mono muted" style={{ margin: "6px 0 0" }}>
                  {site.host_path || site.path}
                </p>
                {site.files === 0 ? (
                  <p style={{ margin: "6px 0 0", color: "var(--danger)" }}>
                    Пусто — ZIP не распакован. Залей снова или «Найти файлы».
                  </p>
                ) : null}
              </div>
              <div className="site-actions">
                <button
                  className="btn small"
                  type="button"
                  onClick={() => setWpOpen(wpOpen === site.name ? null : site.name)}
                >
                  {wpOpen === site.name ? "Скрыть WP" : "Настроить WP"}
                </button>
                <a className="btn ghost small" href={site.url} target="_blank" rel="noreferrer">
                  Открыть
                </a>
                <a
                  className="btn ghost small"
                  href={`/files?path=${encodeURIComponent(site.path)}`}
                >
                  Файлы
                </a>
                <a
                  className="btn ghost small"
                  href={`/chat?site=${encodeURIComponent(site.name)}`}
                >
                  Чат
                </a>
                <button className="btn ghost small" type="button" onClick={() => onInspect(site.name)}>
                  Где файлы
                </button>
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
            {wpOpen === site.name ? (
              <WordpressSetup
                siteName={site.name}
                domainHint={site.domain || "5mb2.ru"}
              />
            ) : null}
            </div>
          ))}
          </>
        )}
      </div>
    </div>
  );
}
