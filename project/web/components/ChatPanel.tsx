"use client";

import { FormEvent, useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { streamChat } from "@/lib/api";

type Msg =
  | { id: string; role: "user" | "assistant"; content: string }
  | { id: string; role: "tool"; content: string };

export function ChatPanel() {
  const searchParams = useSearchParams();
  const site = (searchParams.get("site") || "").trim();
  const [messages, setMessages] = useState<Msg[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy) return;

    setError("");
    setInput("");
    const userMsg: Msg = { id: `u-${Date.now()}`, role: "user", content: text };
    setMessages((m) => [...m, userMsg]);
    setBusy(true);

    const history = [...messages, userMsg]
      .filter((m): m is Msg & { role: "user" | "assistant" } => m.role === "user" || m.role === "assistant")
      .map((m) => ({ role: m.role, content: m.content }));

    const assistantId = `a-${Date.now()}`;
    setMessages((m) => [...m, { id: assistantId, role: "assistant", content: "" }]);

    abortRef.current?.abort();
    const ac = new AbortController();
    abortRef.current = ac;

    try {
      await streamChat(
        text,
        history.slice(0, -1),
        (ev) => {
          if (ev.type === "text") {
            setMessages((m) =>
              m.map((msg) =>
                msg.id === assistantId && msg.role === "assistant"
                  ? { ...msg, content: msg.content + ev.content }
                  : msg,
              ),
            );
          } else if (ev.type === "tool_call") {
            setMessages((m) => [
              ...m,
              {
                id: `t-${Date.now()}-${ev.name}`,
                role: "tool",
                content: `tool: ${ev.name}`,
              },
            ]);
          } else if (ev.type === "error") {
            setError(ev.content);
          }
        },
        ac.signal,
        site ? { site } : undefined,
      );
    } catch (err) {
      if ((err as Error).name !== "AbortError") {
        setError((err as Error).message || "Ошибка чата");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="panel chat-layout">
      {site ? (
        <div className="muted" style={{ margin: "12px 14px 0", fontSize: "0.9rem" }}>
          Работаем с сайтом <strong className="mono">{site}</strong>
          {" · "}
          <a href="/chat">сбросить</a>
        </div>
      ) : null}
      {error ? <div className="error-banner" style={{ margin: 14 }}>{error}</div> : null}
      <div className="chat-messages">
        {messages.length === 0 ? (
          <div className="empty">
            {site
              ? `Спроси про файлы сайта «${site}» — ассистент работает в его папке.`
              : "Спроси про код, файлы или перенос сайта. Из «Сайты» можно открыть чат по конкретному сайту."}
          </div>
        ) : (
          messages.map((m) => (
            <div key={m.id} className={`bubble ${m.role}`}>
              {m.content || (busy && m.role === "assistant" ? "…" : "")}
            </div>
          ))
        )}
        <div ref={bottomRef} />
      </div>
      <form className="chat-compose" onSubmit={onSubmit}>
        <textarea
          className="textarea"
          style={{ minHeight: 72, fontFamily: "var(--font-body)" }}
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder={site ? `Про сайт ${site}…` : "Напиши сообщение…"}
          disabled={busy}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !e.shiftKey) {
              e.preventDefault();
              onSubmit(e);
            }
          }}
        />
        <button className="btn" type="submit" disabled={busy || !input.trim()}>
          {busy ? "…" : "Отправить"}
        </button>
        {busy ? (
          <button
            className="btn ghost"
            type="button"
            onClick={() => abortRef.current?.abort()}
          >
            Стоп
          </button>
        ) : null}
      </form>
    </div>
  );
}
