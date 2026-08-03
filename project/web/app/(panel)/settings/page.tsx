"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { getOwnerSettings, saveOwnerSettings, verifyYookassa } from "@/lib/api";

type Settings = Record<string, string | boolean | undefined>;

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
    hint: "Строка test_… или live_… (Секретный ключ API). Не OAuth и не ключ мобильного SDK. Webhook: https://neobrain.site/api/public/pay/webhook",
  },
  {
    key: "metrika_id",
    label: "Яндекс.Метрика ID",
    hint: "На витрине уже вшит 111275874 — поле для смены/дубля в панели",
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
    hint: "Только content из meta (можно с префиксом google-site-verification=)",
  },
  {
    key: "yandex_webmaster_verification",
    label: "Яндекс.Вебмастер",
    hint: "На витрине уже 1e58779d59cc0fce",
  },
  {
    key: "turnstile_site_key",
    label: "Cloudflare Turnstile site key",
    hint: "Антибот на формах",
  },
  { key: "turnstile_secret_key", label: "Turnstile secret", secret: true },
  {
    key: "smtp_user",
    label: "Почта для писем (Яндекс 360)",
    hint: "Полный email. Host/port подставятся сами (smtp.yandex.ru:465)",
  },
  {
    key: "smtp_password",
    label: "Пароль приложения почты",
    secret: true,
    hint: "Яндекс 360 → Пароли приложений — не обычный пароль входа",
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
      .then((d) => setS((d.settings || {}) as Settings))
      .catch((e: Error) => setError(e.message || "Ошибка загрузки"));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError("");
    setOk("");
    try {
      const payload: Record<string, string> = {};
      for (const f of FIELDS) {
        const v = s[f.key];
        if (typeof v === "string") payload[f.key] = v;
      }
      if (payload.smtp_user && !String(s.smtp_host || "").trim()) {
        const u = payload.smtp_user.toLowerCase();
        if (u.includes("yandex") || u.endsWith("@ya.ru")) {
          payload.smtp_host = "smtp.yandex.ru";
          payload.smtp_port = "465";
        }
      }
      const res = await saveOwnerSettings(payload);
      setS((res.settings || {}) as Settings);
      setOk("Сохранено. Дальше нажмите «Проверить ЮKassa», затем оплату на витрине.");
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
      // Сначала сохраним свежие значения из формы (если секрет не маска)
      const shop = String(s.yookassa_shop_id || "").trim();
      const secret = String(s.yookassa_secret_key || "").trim();
      const patch: Record<string, string> = {};
      if (shop && !shop.startsWith("••••")) patch.yookassa_shop_id = shop;
      if (secret && !secret.startsWith("••••")) patch.yookassa_secret_key = secret;
      if (Object.keys(patch).length) {
        await saveOwnerSettings(patch);
      }
      const res = await verifyYookassa({
        yookassa_shop_id: patch.yookassa_shop_id || "",
        yookassa_secret_key: patch.yookassa_secret_key || "",
      });
      if (res.ok) {
        setYkMsg(
          res.message ||
            `ОК: ЮKassa приняла ключи` +
              (res.test ? " (тестовый магазин)" : " (боевой)") +
              (res.shop_id_tail ? `, shop …${res.shop_id_tail}` : ""),
        );
        setOk(res.message || "ЮKassa подключена");
      } else {
        setYkMsg(res.error || "Ключи не приняты");
        setError(res.error || "ЮKassa отклонила ключи");
      }
      load();
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
          Ключи ЮKassa, аналитика, антибот, почта — без правки `.env`. Секреты маскируются.
          Панель: <span className="mono">https://neobrain.site/console</span>
        </p>
      </div>

      {error ? <p style={{ color: "#c44" }}>{error}</p> : null}
      {ok ? <p style={{ color: "#2ea043" }}>{ok}</p> : null}

      <div className="panel" style={{ padding: 16, marginBottom: 16 }}>
        <div className="muted" style={{ fontSize: "0.9rem" }}>
          ЮKassa: {s.yookassa_configured ? "ключи сохранены" : "не настроена"}
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
            Реальный запрос GET /v3/me — если ОК, оплата заработает.
          </span>
        </div>
        {ykMsg ? (
          <p style={{ margin: "10px 0 0", color: ykMsg.startsWith("ОК") || ykMsg.includes("верные") ? "#2ea043" : "#c44" }}>
            {ykMsg}
          </p>
        ) : null}
      </div>

      <form className="panel" style={{ padding: 20, display: "grid", gap: 14 }} onSubmit={onSubmit} autoComplete="off">
        {FIELDS.map((f) => (
          <label key={f.key} style={{ display: "grid", gap: 6 }}>
            <span style={{ fontWeight: 600 }}>{f.label}</span>
            {f.hint ? <span className="muted" style={{ fontSize: "0.85rem" }}>{f.hint}</span> : null}
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
              placeholder={
                f.key === "yookassa_secret_key"
                  ? "test_… или live_…"
                  : f.key === "yookassa_shop_id"
                    ? "например 1234567"
                    : undefined
              }
            />
          </label>
        ))}
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
