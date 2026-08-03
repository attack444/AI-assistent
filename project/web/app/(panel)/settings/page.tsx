"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import {
  connectYookassa,
  getOwnerSettings,
  saveOwnerSettings,
  verifyYookassa,
} from "@/lib/api";

type Settings = Record<string, string | boolean | undefined>;

const SECRET_KEYS = new Set([
  "turnstile_secret_key",
  "smtp_password",
  "google_client_secret",
  "github_client_secret",
]);

/** ЮKassa вынесена в отдельную форму — сюда не включаем. */
const FIELDS: { key: string; label: string; hint?: string; secret?: boolean }[] = [
  { key: "brand_name", label: "Бренд" },
  { key: "owner_email", label: "Email владельца (OWNER)" },
  { key: "public_site_url", label: "URL витрины", hint: "https://neobrain.site" },
  {
    key: "metrika_id",
    label: "Яндекс.Метрика ID",
    hint: "На витрине уже вшит 111275874",
  },
  {
    key: "ga4_id",
    label: "Google Analytics 4 ID",
    hint: "На витрине уже G-3DPQC7HKJL",
  },
  {
    key: "gtm_id",
    label: "Google Tag Manager",
    hint: "На витрине уже GTM-5GWQ97XF",
  },
  {
    key: "gsc_verification",
    label: "Google Search Console",
    hint: "content из meta google-site-verification",
  },
  {
    key: "yandex_webmaster_verification",
    label: "Яндекс.Вебмастер",
    hint: "На витрине уже 1e58779d59cc0fce",
  },
  { key: "turnstile_site_key", label: "Cloudflare Turnstile site key" },
  { key: "turnstile_secret_key", label: "Turnstile secret", secret: true },
  {
    key: "smtp_user",
    label: "Почта для писем (Яндекс 360)",
    hint: "Полный email. Host/port подставятся сами",
  },
  {
    key: "smtp_password",
    label: "Пароль приложения почты",
    secret: true,
    hint: "Яндекс 360 → Пароли приложений",
  },
  {
    key: "google_client_id",
    label: "Google OAuth Client ID",
    hint: "Redirect: https://neobrain.site/api/public/auth/oauth/google/callback",
  },
  { key: "google_client_secret", label: "Google OAuth Client Secret", secret: true },
  {
    key: "github_client_id",
    label: "GitHub OAuth Client ID",
    hint: "Callback: https://neobrain.site/api/public/auth/oauth/github/callback",
  },
  { key: "github_client_secret", label: "GitHub OAuth Client Secret", secret: true },
];

function formStateFromApi(settings: Settings): Settings {
  const next: Settings = { ...settings };
  for (const key of SECRET_KEYS) next[key] = "";
  // ЮKassa не в общем стейте секретов
  delete next.yookassa_secret_key;
  return next;
}

export default function SettingsPage() {
  const [s, setS] = useState<Settings>({});
  const [error, setError] = useState("");
  const [ok, setOk] = useState("");
  const [busy, setBusy] = useState(false);
  const [ykBusy, setYkBusy] = useState(false);
  const [ykMsg, setYkMsg] = useState("");
  const [ykFp, setYkFp] = useState("");

  // Uncontrolled: React НЕ владеет value — нечему «заменять» вставку
  const shopRef = useRef<HTMLInputElement>(null);
  const secretRef = useRef<HTMLTextAreaElement>(null);

  const load = useCallback(() => {
    setError("");
    getOwnerSettings()
      .then((d) => {
        const settings = formStateFromApi((d.settings || {}) as Settings);
        setS(settings);
        if (shopRef.current) {
          shopRef.current.value = String(settings.yookassa_shop_id || "");
        }
        if (secretRef.current) {
          secretRef.current.value = "";
        }
        const configured = Boolean(settings.yookassa_configured);
        setYkFp(
          configured
            ? "На сервере ключи есть (секрет в поле не показываем — вставьте заново только если меняете)."
            : "На сервере ключей ЮKassa ещё нет.",
        );
      })
      .catch((e: Error) => setError(e.message || "Ошибка загрузки"));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onConnectYookassa() {
    setYkBusy(true);
    setYkMsg("");
    setError("");
    setOk("");
    try {
      const shop = (shopRef.current?.value || "").trim();
      const secret = (secretRef.current?.value || "").trim();
      if (!shop || !secret) {
        setError("Вставьте shopId и полный секретный ключ в поля ЮKassa ниже.");
        setYkBusy(false);
        return;
      }
      const res = await connectYookassa({
        yookassa_shop_id: shop,
        yookassa_secret_key: secret,
      });
      const fp = res.stored || res;
      const fpLine = res.ok || res.saved
        ? `Сохранено: shopId=${String(fp.shop_id || shop)} · секрет ${String(fp.secret_prefix || "")}…${String(fp.secret_tail || "")} · длина ${String(fp.secret_len || secret.length)}`
        : "";
      if (fpLine) setYkFp(fpLine);
      if (res.ok) {
        setYkMsg(res.message || "ЮKassa приняла ключи");
        setOk(res.message || "ЮKassa подключена");
        if (secretRef.current) secretRef.current.value = "";
        setS((prev) => ({ ...prev, yookassa_configured: true }));
      } else {
        setYkMsg(res.error || "Ключи не приняты");
        setError(res.error || "ЮKassa отклонила ключи");
        // Секрет НЕ очищаем при ошибке — можно поправить и повторить
      }
    } catch (err) {
      const msg = (err as Error).message || "Не удалось подключить";
      setYkMsg(msg);
      setError(msg);
    } finally {
      setYkBusy(false);
    }
  }

  async function onVerifyStored() {
    setYkBusy(true);
    setYkMsg("");
    try {
      const res = await verifyYookassa();
      if (res.ok) {
        setYkMsg(res.message || "ОК");
        setOk(res.message || "ОК");
        setYkFp(
          `На сервере: …${res.shop_id_tail || ""} · ${res.secret_prefix || ""}…${res.secret_tail || ""} · длина ${res.secret_len ?? "?"}`,
        );
      } else {
        setYkMsg(res.error || "Не принято");
        setError(res.error || "Не принято");
      }
    } catch (err) {
      setError((err as Error).message || "Ошибка проверки");
    } finally {
      setYkBusy(false);
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    setOk("");
    try {
      const payload: Record<string, string> = {};
      for (const f of FIELDS) {
        const v = s[f.key];
        if (typeof v !== "string") continue;
        if (f.secret) {
          const trimmed = v.trim();
          if (!trimmed || trimmed.startsWith("•")) continue;
          payload[f.key] = trimmed;
          continue;
        }
        payload[f.key] = v;
      }
      if (payload.smtp_user && !String(s.smtp_host || "").trim()) {
        const u = payload.smtp_user.toLowerCase();
        if (u.includes("yandex") || u.endsWith("@ya.ru")) {
          payload.smtp_host = "smtp.yandex.ru";
          payload.smtp_port = "465";
        }
      }
      const res = await saveOwnerSettings(payload);
      setS(formStateFromApi((res.settings || {}) as Settings));
      setOk("Сохранено (остальные настройки). ЮKassa — кнопкой выше.");
    } catch (err) {
      setError((err as Error).message || "Не сохранилось");
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <div className="page-head">
        <h1>Настройки</h1>
        <p>ЮKassa — отдельный блок сверху. Секрет виден при вставке и не перезаписывается маской.</p>
      </div>

      {error ? <p style={{ color: "#c44", whiteSpace: "pre-wrap" }}>{error}</p> : null}
      {ok ? <p style={{ color: "#2ea043", whiteSpace: "pre-wrap" }}>{ok}</p> : null}

      <div className="panel" style={{ padding: 20, marginBottom: 16, display: "grid", gap: 12 }}>
        <h2 style={{ margin: 0, fontSize: "1.15rem" }}>ЮKassa — оплата картой</h2>
        <p className="muted" style={{ margin: 0, fontSize: "0.9rem" }}>
          ЛК ЮKassa → магазин с интеграцией <strong>API</strong> → скопируйте{" "}
          <strong>shopId</strong> (цифры) и <strong>Секретный ключ</strong> (
          <code>test_…</code> или <code>live_…</code>). Webhook:{" "}
          <code>https://neobrain.site/api/public/pay/webhook</code>
        </p>
        {ykFp ? (
          <p className="mono" style={{ margin: 0, fontSize: "0.85rem" }}>
            {ykFp}
          </p>
        ) : null}

        <label style={{ display: "grid", gap: 6 }}>
          <span style={{ fontWeight: 600 }}>shopId</span>
          <input
            ref={shopRef}
            className="input"
            type="text"
            inputMode="numeric"
            name="yk_shop_id_only"
            autoComplete="off"
            autoCorrect="off"
            spellCheck={false}
            data-1p-ignore="true"
            data-lpignore="true"
            data-form-type="other"
            placeholder="только цифры"
            defaultValue=""
          />
        </label>

        <label style={{ display: "grid", gap: 6 }}>
          <span style={{ fontWeight: 600 }}>Секретный ключ (видимый — чтобы видеть, что вставилось)</span>
          <textarea
            ref={secretRef}
            className="input"
            name="yk_secret_key_only"
            rows={3}
            autoComplete="off"
            autoCorrect="off"
            spellCheck={false}
            data-1p-ignore="true"
            data-lpignore="true"
            data-form-type="other"
            placeholder="test_… или live_… — вставьте целиком; текст должен остаться без замены"
            defaultValue=""
            style={{
              fontFamily: "IBM Plex Mono, ui-monospace, monospace",
              fontSize: "0.9rem",
              resize: "vertical",
            }}
          />
        </label>

        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <button className="btn" type="button" onClick={onConnectYookassa} disabled={ykBusy}>
            {ykBusy ? "Сохраняю и проверяю…" : "Сохранить и проверить ЮKassa"}
          </button>
          <button className="btn ghost" type="button" onClick={onVerifyStored} disabled={ykBusy}>
            Проверить то, что на сервере
          </button>
        </div>
        {ykMsg ? (
          <p
            style={{
              margin: 0,
              whiteSpace: "pre-wrap",
              color:
                ykMsg.includes("приняла") || ykMsg.includes("верные") || ykMsg.startsWith("ЮKassa отвечает")
                  ? "#2ea043"
                  : "#c44",
            }}
          >
            {ykMsg}
          </p>
        ) : null}
      </div>

      <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
        <div className="muted" style={{ fontSize: "0.9rem" }}>
          ЮKassa: {s.yookassa_configured ? "ключи на сервере есть" : "не настроена"}
          {" · "}
          Turnstile: {s.turnstile_configured ? "ON" : "OFF"}
          {" · "}
          SMTP: {s.smtp_configured ? "ON" : "OFF"}
          {" · "}
          Google: {s.oauth_google_configured ? "ON" : "OFF"}
          {" · "}
          GitHub: {s.oauth_github_configured ? "ON" : "OFF"}
        </div>
      </div>

      <form
        className="panel"
        style={{ padding: 20, display: "grid", gap: 14 }}
        onSubmit={onSubmit}
        autoComplete="off"
      >
        <h2 style={{ margin: 0, fontSize: "1.05rem" }}>Прочие настройки</h2>
        {FIELDS.map((f) => {
          const setFlag = Boolean(s[`${f.key}_set`]);
          return (
            <label key={f.key} style={{ display: "grid", gap: 6 }}>
              <span style={{ fontWeight: 600 }}>
                {f.label}
                {f.secret ? (
                  <span className="muted" style={{ fontWeight: 400, marginLeft: 8, fontSize: "0.85rem" }}>
                    {setFlag ? "· на сервере сохранён" : "· ещё не задан"}
                  </span>
                ) : null}
              </span>
              {f.hint ? (
                <span className="muted" style={{ fontSize: "0.85rem" }}>
                  {f.hint}
                </span>
              ) : null}
              <input
                className="input"
                type={f.secret ? "password" : "text"}
                value={String(s[f.key] ?? "")}
                onChange={(ev) => setS((prev) => ({ ...prev, [f.key]: ev.target.value }))}
                autoComplete="new-password"
                name={`nb_${f.key}`}
                data-1p-ignore="true"
                data-lpignore="true"
                placeholder={f.secret ? (setFlag ? "новый — или пусто" : "вставьте секрет") : undefined}
              />
            </label>
          );
        })}
        <div className="hero-actions" style={{ marginTop: 8 }}>
          <button className="btn" type="submit" disabled={busy}>
            {busy ? "Сохраняю…" : "Сохранить прочее"}
          </button>
          <button className="btn ghost" type="button" onClick={load} disabled={busy}>
            Обновить
          </button>
        </div>
      </form>
    </>
  );
}
