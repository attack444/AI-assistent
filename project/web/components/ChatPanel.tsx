"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import {
  createChat,
  deleteChat,
  getChat,
  getSiteContext,
  listChats,
  listSites,
  streamChat,
  type ChatSummary,
  type SiteContext,
  type SiteInfo,
} from "@/lib/api";

type Msg =
  | { id: string; role: "user" | "assistant"; content: string }
  | {
      id: string;
      role: "tool";
      content: string;
      ok?: boolean;
      edited?: boolean;
      path?: string;
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

function filesHref(absPath: string, site: string) {
  // Prefer site-relative open in file manager
  if (site && absPath.includes(`/sites/${site}`)) {
    return `/files?path=${encodeURIComponent(absPath)}`;
  }
  if (absPath) return `/files?path=${encodeURIComponent(absPath)}`;
  return "/files";
}

function quickPrompts(site: string, ctx: SiteContext | null): { label: string; text: string }[] {
  if (!site) {
    return [
      { label: "Что умеешь?", text: "Кратко перечисли что умеешь на сервере с сайтами и файлами." },
    ];
  }
  const base = [
    { label: "Статус сайта", text: "Проверь статус сайта через site_status и кратко скажи что не так, если есть проблемы." },
    { label: "Список файлов", text: "Покажи структуру корня сайта (list_dir) и что можно править." },
    { label: "Права 755/644", text: "Выставь права на файлы сайта через site_fix_perms." },
  ];
  if (ctx?.is_wordpress) {
    return [
      ...base,
      {
        label: "WP URL",
        text: "Проверь WordPress siteurl/home. Если нужно — предложи wp_replace_urls на актуальный адрес сайта.",
      },
      {
        label: "Белый экран",
        text: "Сайт WordPress белый экран / не открывается. Диагностируй: site_status, wp-config, php_lint ключевых файлов.",
      },
    ];
  }
  return [
    ...base,
    {
      label: "Поправь index",
      text: "Прочитай index.html (или index.php) и скажи что можно улучшить в первом экране.",
    },
  ];
}

export function ChatPanel() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const site = (searchParams.get("site") || "").trim();
  const [sites, setSites] = useState<SiteInfo[]>([]);
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
    listSites()
      .then((r) => setSites(r.sites || []))
      .catch(() => setSites([]));
  }, []);

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

  function selectSite(name: string) {
    if (busy) return;
    if (!name) router.push("/chat");
    else router.push(`/chat?site=${encodeURIComponent(name)}`);
  }

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
            path: typeof meta.path === "string" ? meta.path : undefined,
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

  async function sendMessage(text: string) {
    const trimmed = text.trim();
    if (!trimmed || busy) return;

    setError("");
    setInput("");
    const userMsg: Msg = { id: `u-${Date.now()}`, role: "user", content: trimmed };
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
        trimmed,
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
            const path = ev.result?.path;
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
                    path: typeof path === "string" ? path : undefined,
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
                path: typeof path === "string" ? path : undefined,
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

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    await sendMessage(input);
  }

  const prompts = quickPrompts(site, ctx);

  return (
    <div className="panel chat-shell">
      <aside className="chat-history">
        <div className="chat-history-head">
          <span>Чаты</span>
          <button className="btn ghost" type="button" onClick={onNewChat} disabled={busy}>
            +
          </button>
        </div>
        <label className="chat-site-pick">
          <span className="muted">Сайт</span>
          <select
            value={site}
            disabled={busy}
            onChange={(e) => selectSite(e.target.value)}
          >
            <option value="">— без сайта —</option>
            {sites.map((s) => (
              <option key={s.name} value={s.name}>
                {s.name}{s.is_wordpress ? " (WP)" : ""}
              </option>
            ))}
          </select>
        </label>
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
              {ctx?.is_wordpress ? " · WordPress" : ""}
              {ctx?.domain ? ` · ${ctx.domain}` : ""}
              {ctx?.can_edit ? " · правки на сервере" : ""}
              {ctx?.tree?.length ? (
                <>
                  {" · "}
                  <button type="button" className="linkish" onClick={() => setShowTree((v) => !v)}>
                    {showTree ? "скрыть файлы" : "файлы"}
                  </button>
                </>
              ) : null}
            </span>
          ) : (
            <span className="muted">Выбери сайт слева — ассистент сможет править его файлы на сервере.</span>
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
              <div style={{ marginBottom: 12 }}>
                {site
                  ? `Ассистент работает в папке «${site}»: читает/правит файлы, WP-статус, права, URL.`
                  : "Выбери сайт или просто спроси. Для правок на диске нужен выбранный сайт."}
              </div>
              <div className="chat-chips">
                {prompts.map((p) => (
                  <button
                    key={p.label}
                    type="button"
                    className="chat-chip"
                    disabled={busy}
                    onClick={() => sendMessage(p.text)}
                  >
                    {p.label}
                  </button>
                ))}
              </div>
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
                {m.role === "tool" && m.path && m.edited ? (
                  <div className="tool-actions">
                    <a href={filesHref(m.path, site)}>Открыть в файлах</a>
                  </div>
                ) : null}
                {m.role === "tool" && m.diff ? (
                  <pre className="tool-diff">{m.diff}</pre>
                ) : null}
              </div>
            ))
          )}
          <div ref={bottomRef} />
        </div>
        {messages.length > 0 && !busy ? (
          <div className="chat-chips chat-chips-bar">
            {prompts.slice(0, 3).map((p) => (
              <button
                key={p.label}
                type="button"
                className="chat-chip"
                onClick={() => sendMessage(p.text)}
              >
                {p.label}
              </button>
            ))}
          </div>
        ) : null}
        <form className="chat-compose" onSubmit={onSubmit}>
          <textarea
            className="textarea"
            style={{ minHeight: 72, fontFamily: "var(--font-body)" }}
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder={
              site
                ? `Про сайт ${site}: «поменяй заголовок», «проверь WP», «почини URL»…`
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
