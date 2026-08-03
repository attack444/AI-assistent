"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { getOwnerSettings, saveOwnerSettings, verifyYookassa } from "@/lib/api";

type Settings = Record<string, string | boolean | undefined>;

const SECRET_KEYS = new Set([
  "yookassa_secret_key",
  "turnstile_secret_key",
  "smtp_password",
  "google_client_secret",
  "github_client_secret",
]);

const FIELDS: { key: string; label: string; hint?: string; secret?: boolean }[] = [
  { key: "brand_name", label: "Бренд" },
  { key: "owner_email", label: "Email владельца (OWNER)" },
  { key: "public_site_url", label: "URL витрины", hint: "https://neobrain.site" },
  {
    key: "yookassa_shop_id",
    label: "ЮKassa shopId",
    hint: "Только цифры из ЛК ЮKassa → магазин с интеграцией API",
  },
  {
    key: "yookassa_secret_key",
    label: "ЮKassa секретный ключ",
    secret: true,
    hint: "Вставьте целиком test_… или live_…. Поле всегда пустое: сохранённый ключ не показываем, чтобы браузер его не подменял.",
  },
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
  {
    key: "turnstile_site_key",
    label: "Cloudflare Turnstile site key",
  },
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

/** Маску •••• в input не кладём — иначе после save/load ключ «заменяется». */
function formStateFromApi(settings: Settings): Settings {
  const next: Settings = { ...settings };
  for (const key of SECRET_KEYS) {
    next[key] = "";
  }
  return next;
}

export default function SettingsPage() {
  const [s, setS] = useState<Settings>({});
  const [error, setError] = useState("");
  const [ok, setOk] = useState("");
  const [busy, setBusy] = useState(false);
  const [ykBusy, setYkBusy] = useState(false);
  const [ykMsg, setYkMsg] = useState("");

  const load = useCallback(() => {
    setError("");
    getOwnerSettings()
      .then((d) => setS(formStateFromApi((d.settings || {}) as Settings)))
      .catch((e: Error) => setError(e.message || "Ошибка загрузки"));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function buildPayload(): Record<string, string> {
    const payload: Record<string, string> = {};
    for (const f of FIELDS) {
      const v = s[f.key];
      if (typeof v !== "string") continue;
      if (f.secret) {
        const trimmed = v.trim();
        // Пустое = не менять сохранённый секрет (бэкенд тоже так делает)
        if (!trimmed || trimmed.startsWith("••••") || trimmed.startsWith("•")) continue;
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
    return payload;
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    setOk("");
    try {
      const payload = buildPayload();
      const res = await saveOwnerSettings(payload);
      // Снова пустые secret-поля — не подставляем •••• в input
      setS(formStateFromApi((res.settings || {}) as Settings));
      setOk(
        payload.yookassa_secret_key
          ? "Секрет ЮKassa сохранён. Нажмите «Проверить ЮKassa»."
          : "Сохранено.",
      );
    } catch (err) {
      setError((err as Error).message || "Не сохранилось");
    } finally {
      setBusy(false);
    }
  }

  async function onVerifyYookassa() {
    setYkBusy(true);
    setYkMsg("");
    setError("");
    try {
      const shop = String(s.yookassa_shop_id || "").trim();
      const secret = String(s.yookassa_secret_key || "").trim();
      const patch: Record<string, string> = {};
      if (shop) patch.yookassa_shop_id = shop;
      if (secret && !secret.startsWith("•")) patch.yookassa_secret_key = secret;
      if (Object.keys(patch).length) {
        await saveOwnerSettings(patch);
      }
      const res = await verifyYookassa({
        yookassa_shop_id: patch.yookassa_shop_id || "",
        yookassa_secret_key: patch.yookassa_secret_key || "",
      });
      if (res.ok) {
        const msg =
          res.message ||
          "ЮKassa приняла ключи" +
            (res.test ? " (тест)" : " (боевой)") +
            (res.shop_id_tail ? `, shop …${res.shop_id_tail}` : "");
        setYkMsg(msg);
        setOk(msg);
      } else {
        setYkMsg(res.error || "Ключи не приняты");
        setError(res.error || "ЮKassa отклонила ключи");
      }
      // Обновить флаги *_set, secret-поля оставить пустыми
      const fresh = await getOwnerSettings();
      setS((prev) => {
        const base = formStateFromApi((fresh.settings || {}) as Settings);
        // Если пользователь ещё держит новый секрет в поле — не стирать до явного save/clear
        if (secret && !secret.startsWith("•")) {
          base.yookassa_secret_key = "";
        }
        return { ...base, yookassa_shop_id: prev.yookassa_shop_id || base.yookassa_shop_id };
      });
    } catch (err) {
      const msg = (err as Error).message || "Проверка не удалась";
      setYkMsg(msg);
      setError(msg);
    } finally {
      setYkBusy(false);
    }
  }

  return (
    <>
      <div className="page-head">
        <h1>Настройки</h1>
        <p>
          Секреты в поля не подставляются (чтобы не затирались маской/автозаполнением). Пустое поле секрета =
          оставить как было.
        </p>
      </div>

      {error ? <p style={{ color: "#c44" }}>{error}</p> : null}
      {ok ? <p style={{ color: "#2ea043" }}>{ok}</p> : null}

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
        <div style={{ marginTop: 12, display: "flex", gap: 10, flexWrap: "wrap", alignItems: "center" }}>
          <button className="btn" type="button" onClick={onVerifyYookassa} disabled={ykBusy || busy}>
            {ykBusy ? "Проверяю ЮKassa…" : "Проверить ЮKassa"}
          </button>
          <span className="muted" style={{ fontSize: "0.85rem" }}>
            Запрос к api.yookassa.ru/v3/me с вашими ключами.
          </span>
        </div>
        {ykMsg ? (
          <p
            style={{
              margin: "10px 0 0",
              color:
                ykMsg.includes("приняла") || ykMsg.includes("верные") || ykMsg.includes("ОК")
                  ? "#2ea043"
                  : "#c44",
            }}
          >
            {ykMsg}
          </p>
        ) : null}
      </div>

      <form
        className="panel"
        style={{ padding: 20, display: "grid", gap: 14 }}
        onSubmit={onSubmit}
        autoComplete="off"
      >
        {FIELDS.map((f) => {
          const setFlag = Boolean(s[`${f.key}_set`]);
          return (
            <label key={f.key} style={{ display: "grid", gap: 6 }}>
              <span style={{ fontWeight: 600 }}>
                {f.label}
                {f.secret ? (
                  <span className="muted" style={{ fontWeight: 400, marginLeft: 8, fontSize: "0.85rem" }}>
                    {setFlag || (f.key === "yookassa_secret_key" && s.yookassa_configured)
                      ? "· на сервере сохранён"
                      : "· ещё не задан"}
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
                inputMode={f.key === "yookassa_shop_id" ? "numeric" : undefined}
                value={String(s[f.key] ?? "")}
                onChange={(ev) => setS((prev) => ({ ...prev, [f.key]: ev.target.value }))}
                autoComplete="new-password"
                autoCorrect="off"
                autoCapitalize="off"
                spellCheck={false}
                name={`nb_${f.key}`}
                data-1p-ignore="true"
                data-lpignore="true"
                data-form-type="other"
                placeholder={
                  f.secret
                    ? setFlag || (f.key === "yookassa_secret_key" && s.yookassa_configured)
                      ? "новый ключ — или оставьте пустым"
                      : f.key === "yookassa_secret_key"
                        ? "test_… или live_…"
                        : "вставьте секрет"
                    : f.key === "yookassa_shop_id"
                      ? "например 1234567"
                      : undefined
                }
              />
            </label>
          );
        })}
        <div className="hero-actions" style={{ marginTop: 8 }}>
          <button className="btn" type="submit" disabled={busy}>
            {busy ? "Сохраняю…" : "Сохранить"}
          </button>
          <button className="btn ghost" type="button" onClick={load} disabled={busy}>
            Обновить
          </button>
          <button className="btn ghost" type="button" onClick={onVerifyYookassa} disabled={ykBusy || busy}>
            Проверить ЮKassa
          </button>
        </div>
      </form>
    </>
  );
}
