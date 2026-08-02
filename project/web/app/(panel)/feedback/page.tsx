"use client";

import { useCallback, useEffect, useState } from "react";
import { FeedbackItem, listFeedback } from "@/lib/api";

export default function FeedbackPage() {
  const [items, setItems] = useState<FeedbackItem[]>([]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    setError("");
    listFeedback(150)
      .then((d) => setItems(d.items || []))
      .catch((e: Error) => setError(e.message || "Не удалось загрузить"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <>
      <div className="page-head">
        <h1>Обратная связь</h1>
        <p>
          Идеи и ошибки с витрины <strong>NeoBrain</strong> и (если прокси работает) с{" "}
          <strong>5mb2</strong>. Дублируется в файл{" "}
          <span className="mono">~/.ai-helper/public_feedback.jsonl</span> на сервере.
          В WordPress те же сообщения — в «Заявки 5MB2 → Обратная связь».
        </p>
        <div className="hero-actions" style={{ marginTop: 12 }}>
          <button className="btn ghost small" type="button" onClick={load} disabled={loading}>
            {loading ? "Обновляю…" : "Обновить"}
          </button>
        </div>
      </div>

      {error ? <p className="muted" style={{ color: "#c44" }}>{error}</p> : null}

      {!loading && !items.length && !error ? (
        <div className="panel" style={{ padding: 20 }}>
          <p className="muted" style={{ margin: 0 }}>
            Пока пусто. На сайтах форма «Идея / ошибка» внизу страницы и блок «Что вам нужно?».
          </p>
        </div>
      ) : null}

      <div style={{ display: "grid", gap: 12 }}>
        {items.map((it, idx) => (
          <article key={`${it.at || ""}-${idx}`} className="panel" style={{ padding: 16 }}>
            <div className="muted" style={{ fontSize: "0.85rem", marginBottom: 8 }}>
              {(it.type_label || it.type || "Сообщение")}
              {it.source ? ` · ${it.source}` : ""}
              {it.at ? ` · ${it.at}` : ""}
              {it.email ? ` · ${it.email}` : ""}
            </div>
            <p style={{ margin: "0 0 8px", whiteSpace: "pre-wrap" }}>{it.message}</p>
            {it.page ? (
              <p className="muted mono" style={{ margin: 0, fontSize: "0.8rem", wordBreak: "break-all" }}>
                {it.page}
              </p>
            ) : null}
          </article>
        ))}
      </div>
    </>
  );
}
