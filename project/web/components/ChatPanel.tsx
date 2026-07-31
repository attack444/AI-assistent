"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import {
  createChat,
  deleteChat,
  getChat,
  getSiteContext,
  listChats,
  streamChat,
  type ChatSummary,
  type SiteContext,
} from "@/lib/api";

type Msg =
  | { id: string; role: "user" | "assistant"; content: string }
  | {
      id: string;
      role: "tool";
      content: string;
      ok?: boolean;
      edited?: boolean;
      diff?: string;
    };

function formatToolLine(
  name: string,
  result?: {
    ok?: boolean;
    path?: string;
    edited?: boolean;
    added?: number;
    removed?: number;
    error?: string;
  },
) {
  if (!result) return `⚙ ${name}`;
  const path = result.path ? ` → ${result.path}` : "";
  if (result.ok === false) return `✗ ${name}${path}: ${result.error || "ошибка"}`;
  const stats =
    result.edited && (result.added != null || result.removed != null)
      ? ` (+${result.added ?? 0}/-${result.removed ?? 0})`
      : "";
  const mark = result.edited ? "✎" : "✓";
  return `${mark} ${name}${path}${stats}`;
}

export function ChatPanel() {
  const searchParams = useSearchParams();
  const site = (searchParams.get("site") || "").trim();
  const [chats, setChats] = useState<ChatSummary[]>([]);
  const [chatId, setChatId] = useState<string>("");
  const [messages, setMessages] = useState<Msg[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [ctx, setCtx] = useState<SiteContext | null>(null);
  const [showTree, setShowTree] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  const refreshChats = useCallback(async () => {
    try {
      const res = await listChats(site || undefined);
      setChats(res.chats || []);
    } catch {
      /* ignore */
    }
  }, [site]);

  useEffect(() => {
    refreshChats();
    getSiteContext(site || undefined)
      .then(setCtx)
      .catch(() => setCtx(null));
    setChatId("");
    setMessages([]);
  }, [site, refreshChats]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  async function openChat(id: string) {
    if (busy) return;
    setError("");
    setChatId(id);
    try {
      const res = await getChat(id);
      const mapped: Msg[] = (res.chat.messages || []).map((m) => {
        if (m.role === "tool") {
          const meta = m.meta || {};
          return {
            id: `m-${m.id}`,
            role: "tool" as const,
            content: m.content,
            ok: Boolean(meta.ok),
            edited: Boolean(meta.edited),
            diff: typeof meta.diff === "string" ? meta.diff : undefined,
          };
        }
        return {
          id: `m-${m.id}`,
          role: (m.role === "user" ? "user" : "assistant") as "user" | "assistant",
          content: m.content || "",
        };
      });
      setMessages(mapped);
    } catch (err) {
      setError((err as Error).message || "Не удалось открыть чат");
    }
  }

  async function onNewChat() {
    if (busy) return;
    try {
      const res = await createChat(site || undefined);
      setChatId(res.chat.id);
      setMessages([]);
      await refreshChats();
    } catch (err) {
      setError((err as Error).message || "Не удалось создать чат");
    }
  }

  async function onDeleteChat(id: string) {
    if (busy) return;
    try {
      await deleteChat(id);
      if (chatId === id) {
        setChatId("");
        setMessages([]);
      }
      await refreshChats();
    } catch (err) {
      setError((err as Error).message || "Не удалось удалить чат");
    }
  }

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

    let activeChatId = chatId;

    try {
      await streamChat(
        text,
        history.slice(0, -1),
        (ev) => {
          if (ev.type === "chat" && ev.chat_id) {
            activeChatId = ev.chat_id;
            setChatId(ev.chat_id);
          } else if (ev.type === "text") {
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
                content: `… ${ev.name}`,
              },
            ]);
          } else if (ev.type === "tool_result") {
            const line = formatToolLine(ev.name, ev.result);
            setMessages((m) => {
              const next = [...m];
              for (let i = next.length - 1; i >= 0; i--) {
                const msg = next[i];
                if (msg.role === "tool" && msg.content.startsWith(`… ${ev.name}`)) {
                  next[i] = {
                    id: msg.id,
                    role: "tool",
                    content: line,
                    ok: ev.result?.ok,
                    edited: ev.result?.edited,
                    diff: ev.result?.diff,
                  };
                  return next;
                }
              }
              next.push({
                id: `tr-${Date.now()}-${ev.name}`,
                role: "tool",
                content: line,
                ok: ev.result?.ok,
                edited: ev.result?.edited,
                diff: ev.result?.diff,
              });
              return next;
            });
          } else if (ev.type === "error") {
            setError(ev.content);
          } else if (ev.type === "done") {
            if (ev.chat_id) setChatId(ev.chat_id);
          }
        },
        ac.signal,
        {
          ...(site ? { site } : {}),
          ...(activeChatId ? { chat_id: activeChatId } : {}),
        },
      );
      await refreshChats();
    } catch (err) {
      if ((err as Error).name !== "AbortError") {
        setError((err as Error).message || "Ошибка чата");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="panel chat-shell">
      <aside className="chat-history">
        <div className="chat-history-head">
          <span>Чаты</span>
          <button className="btn ghost" type="button" onClick={onNewChat} disabled={busy}>
            +
          </button>
        </div>
        <div className="chat-history-list">
          {chats.length === 0 ? (
            <div className="muted" style={{ padding: 10, fontSize: "0.85rem" }}>
              Пока пусто — начни диалог
            </div>
          ) : (
            chats.map((c) => (
              <div
                key={c.id}
                className={`chat-history-item${c.id === chatId ? " active" : ""}`}
              >
                <button type="button" className="chat-history-open" onClick={() => openChat(c.id)}>
                  <span className="chat-history-title">{c.title || "Без названия"}</span>
                  {c.site_id ? <span className="chat-history-site mono">{c.site_id}</span> : null}
                </button>
                <button
                  type="button"
                  className="chat-history-del"
                  title="Удалить"
                  onClick={() => onDeleteChat(c.id)}
                >
                  ×
                </button>
              </div>
            ))
          )}
        </div>
      </aside>

      <div className="chat-layout">
        <div className="chat-context-bar">
          {site ? (
            <span>
              Сайт <strong className="mono">{site}</strong>
              {ctx?.can_edit ? " · правки файлов на сервере включены" : ""}
              {" · "}
              <a href="/chat">сбросить</a>
              {ctx?.tree?.length ? (
                <>
                  {" · "}
                  <button type="button" className="linkish" onClick={() => setShowTree((v) => !v)}>
                    {showTree ? "скрыть файлы" : "файлы сайта"}
                  </button>
                </>
              ) : null}
            </span>
          ) : (
            <span className="muted">
              Выбери сайт в «Сайты» → «Чат», чтобы ассистент редактировал файлы в его папке.
            </span>
          )}
        </div>
        {showTree && ctx?.tree?.length ? (
          <div className="chat-tree mono">
            {(ctx.tree || []).slice(0, 40).map((line) => (
              <div key={line}>{line}</div>
            ))}
          </div>
        ) : null}
        {error ? <div className="error-banner" style={{ margin: 14 }}>{error}</div> : null}
        <div className="chat-messages">
          {messages.length === 0 ? (
            <div className="empty">
              {site
                ? `Спроси про файлы «${site}» — ассистент читает и правит их на сервере (str_replace / write_file). Чаты сохраняются.`
                : "Спроси про код или перенос сайта. Из «Сайты» открой чат по сайту — тогда доступно редактирование файлов."}
            </div>
          ) : (
            messages.map((m) => (
              <div
                key={m.id}
                className={`bubble ${m.role}${m.role === "tool" && m.edited ? " edited" : ""}${
                  m.role === "tool" && m.ok === false ? " fail" : ""
                }`}
              >
                {m.content || (busy && m.role === "assistant" ? "…" : "")}
                {m.role === "tool" && m.diff ? (
                  <pre className="tool-diff">{m.diff}</pre>
                ) : null}
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
            placeholder={
              site
                ? `Про сайт ${site}: «поменяй заголовок в index.html»…`
                : "Напиши сообщение…"
            }
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
    </div>
  );
}
