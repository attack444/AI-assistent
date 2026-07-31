"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  chunkedUploadFile,
  fixWpDb,
  getWpStatus,
  importWpSql,
  patchWpConfig,
  replaceWpUrl,
  testWpDb,
} from "@/lib/api";

type Props = {
  siteName: string;
  serverIpHint?: string;
  domainHint?: string;
};

export function WordpressSetup({ siteName, serverIpHint = "ТВОЙ_IP", domainHint }: Props) {
  const defaultUrl = domainHint
    ? `https://${domainHint.replace(/^https?:\/\//, "").split("/")[0]}`
    : `http://${serverIpHint}/sites/${siteName}`;
  const [status, setStatus] = useState<string>("");
  const [error, setError] = useState("");
  const [okMsg, setOkMsg] = useState("");
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState(0);
  const [dbHost, setDbHost] = useState("mysql");
  const [dbName, setDbName] = useState("wordpress");
  const [dbUser, setDbUser] = useState("wp");
  const [dbPassword, setDbPassword] = useState("");
  const [oldUrl, setOldUrl] = useState("");
  const [newUrl, setNewUrl] = useState(defaultUrl);
  const [sqlFile, setSqlFile] = useState<File | null>(null);

  async function refresh() {
    try {
      const s = await getWpStatus(siteName);
      const dbOk = s.db?.ok ? `БД ок (${s.db.tables} таблиц)` : `БД: ${s.db?.error || "нет связи"}`;
      const urls = s.urls?.urls
        ? `siteurl=${s.urls.urls.siteurl || "—"}`
        : "URL в БД ещё нет";
      setStatus(
        [
          s.has_wp_config ? `wp-config: ${s.wp_config}` : "wp-config не найден",
          dbOk,
          urls,
          s.defines?.DB_HOST ? `DB_HOST=${s.defines.DB_HOST}` : "",
        ]
          .filter(Boolean)
          .join("\n"),
      );
      if (s.defaults?.db_name) setDbName(s.defaults.db_name);
      if (s.defaults?.db_user) setDbUser(s.defaults.db_user);
      if (s.defaults?.db_host) setDbHost(s.defaults.db_host);
      if (s.defaults?.db_password) setDbPassword(s.defaults.db_password);
      if (s.defaults?.suggested_site_url) setNewUrl(s.defaults.suggested_site_url);
      if (s.urls?.urls?.siteurl) setOldUrl(String(s.urls.urls.siteurl));
    } catch (err) {
      setError((err as Error).message);
    }
  }

  useEffect(() => {
    void refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [siteName]);

  async function onPatchConfig(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    setOkMsg("");
    try {
      const res = await patchWpConfig({
        name: siteName,
        db_name: dbName,
        db_user: dbUser,
        db_password: dbPassword,
        db_host: dbHost,
      });
      setOkMsg(`wp-config обновлён: ${(res.changed || []).join(", ")}. Бэкап: ${res.backup}`);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onImportSql(e: FormEvent) {
    e.preventDefault();
    if (!sqlFile) {
      setError("Выбери .sql дамп с старого хостинга");
      return;
    }
    setBusy(true);
    setError("");
    setOkMsg("");
    setProgress(0);
    try {
      const up = await chunkedUploadFile({
        file: sqlFile,
        siteName,
        onProgress: (pct, label) => {
          setProgress(pct);
          setOkMsg(label);
        },
      });
      setOkMsg("Импорт SQL в MySQL…");
      const res = await importWpSql({ name: siteName, upload_id: up.upload_id });
      setOkMsg(
        `SQL импортирован: ${res.statements} запросов` +
          (res.errors?.length ? `. Предупреждения: ${res.errors[0]}` : ""),
      );
      setSqlFile(null);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
      setProgress(0);
    }
  }

  async function onReplaceUrl(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    setOkMsg("");
    try {
      const res = await replaceWpUrl({
        name: siteName,
        old_url: oldUrl,
        new_url: newUrl,
      });
      setOkMsg(`URL заменён: ${res.old_url} → ${res.new_url}. ${res.warning || ""}`);
      await refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onTestDb() {
    setBusy(true);
    setError("");
    try {
      const res = await testWpDb();
      if (res.ok) {
        setOkMsg(
          `MySQL OK · таблиц: ${res.tables}` +
            (res.healed ? " (пароль wp синхронизирован)" : ""),
        );
      } else {
        setError(
          `${res.error || "MySQL ошибка"}` +
            (res.hint ? `\n${res.hint}` : "") +
            "\nНа VPS: bash /opt/ai-helper/project/deploy/reset-mysql-password.sh --reinit",
        );
      }
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function onFixDb() {
    setBusy(true);
    setError("");
    setOkMsg("");
    try {
      const res = await fixWpDb();
      if (res.ok) {
        setOkMsg(res.message || "MySQL починен");
        await refresh();
      } else {
        setError(
          (res.error || "Не удалось починить MySQL") +
            (res.hint ? `\n${res.hint}` : "") +
            "\nНа VPS: bash /opt/ai-helper/project/deploy/reset-mysql-password.sh --reinit",
        );
      }
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="panel create-site" style={{ marginTop: 12 }}>
      <div>
        <strong>WordPress · {siteName}</strong>
        <p className="muted" style={{ margin: "4px 0 0" }}>
          1) wp-config → 2) импорт .sql → 3) заменить URL. Пароль БД — из{" "}
          <span className="mono">MYSQL_PASSWORD</span> в `.env`.
        </p>
      </div>

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

      {status ? (
        <pre className="mono muted" style={{ margin: 0, whiteSpace: "pre-wrap", fontSize: "0.82rem" }}>
          {status}
        </pre>
      ) : null}

      {busy && progress > 0 ? (
        <div className="progress-track">
          <div className="progress-fill" style={{ width: `${progress}%` }} />
        </div>
      ) : null}

      <form onSubmit={onPatchConfig} className="migrate-form">
        <div className="muted">Шаг 1 — записать доступ к MySQL в wp-config.php</div>
        <input className="input" value={dbHost} onChange={(e) => setDbHost(e.target.value)} placeholder="DB_HOST (mysql)" />
        <input className="input" value={dbName} onChange={(e) => setDbName(e.target.value)} placeholder="DB_NAME" />
        <input className="input" value={dbUser} onChange={(e) => setDbUser(e.target.value)} placeholder="DB_USER" />
        <input
          className="input"
          type="password"
          value={dbPassword}
          onChange={(e) => setDbPassword(e.target.value)}
          placeholder="DB_PASSWORD (= MYSQL_PASSWORD из .env)"
        />
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
          <button className="btn" type="submit" disabled={busy}>
            Сохранить wp-config
          </button>
          <button className="btn ghost" type="button" disabled={busy} onClick={onTestDb}>
            Проверить MySQL
          </button>
          <button className="btn ghost" type="button" disabled={busy} onClick={onFixDb}>
            Починить MySQL (1045)
          </button>
        </div>
      </form>

      <form onSubmit={onImportSql} className="migrate-form">
        <div className="muted">Шаг 2 — импорт дампа .sql со старого хостинга</div>
        <label className="btn ghost" style={{ justifyContent: "flex-start" }}>
          {sqlFile ? sqlFile.name : "Выбрать .sql дамп"}
          <input
            type="file"
            accept=".sql,application/sql,text/plain,.txt"
            hidden
            onChange={(e) => setSqlFile(e.target.files?.[0] || null)}
          />
        </label>
        <p className="muted" style={{ margin: 0, fontSize: "0.85rem" }}>
          Выбери файл в панели — имя на диске может быть любым.
          Нужна база сайта <span className="mono">u3406909_wp736</span>, не{" "}
          <span className="mono">information_schema</span>.
          Ошибка <span className="mono">1045 Access denied</span> → на VPS:
          <br />
          <span className="mono">bash /opt/ai-helper/project/deploy/reset-mysql-password.sh</span>
          <br />
          SCP (полный путь на ПК):
          <br />
          <span className="mono">
            scp &quot;C:\Users\ТЫ\Downloads\backup.sql&quot; root@IP:/tmp/dump.sql
          </span>
        </p>
        <button className="btn" type="submit" disabled={busy || !sqlFile}>
          Загрузить и импортировать SQL
        </button>
      </form>

      <form onSubmit={onReplaceUrl} className="migrate-form">
        <div className="muted">Шаг 3 — заменить старый домен на адрес VPS</div>
        <input
          className="input"
          value={oldUrl}
          onChange={(e) => setOldUrl(e.target.value)}
          placeholder="старый URL (пусто = взять из БД, обычно https://5mb2.ru)"
        />
        <input
          className="input"
          value={newUrl}
          onChange={(e) => setNewUrl(e.target.value)}
          placeholder={`https://5mb2.ru или http://${serverIpHint}/sites/${siteName}`}
        />
        <p className="muted" style={{ margin: 0, fontSize: "0.85rem" }}>
          Старый URL — как сайт открывался на старом хостинге. Можно оставить пустым: возьмём{" "}
          <span className="mono">siteurl</span> из базы после импорта.
        </p>
        <button className="btn" type="submit" disabled={busy || !newUrl.trim()}>
          Заменить URL в БД
        </button>
      </form>
    </div>
  );
}
