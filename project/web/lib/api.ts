const TOKEN_KEY = "ai-helper-token";

/** HTTP headers must be Latin-1 — Cyrillic filenames crash upload. */
function safeHeaderFilename(name: string): string {
  const base = name.split(/[/\\]/).pop() || "upload.bin";
  const ascii = base.replace(/[^\x20-\x7E]/g, "_");
  return ascii.replace(/\s+/g, "_") || "upload.bin";
}

export function getToken(): string {
  if (typeof window === "undefined") return "";
  return localStorage.getItem(TOKEN_KEY) || "";
}

export function setToken(token: string) {
  if (typeof window === "undefined") return;
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export function clearToken() {
  setToken("");
}

export type ApiStatus = {
  ok: boolean;
  ollama?: boolean;
  models?: string[];
  groq?: boolean;
  deepseek?: boolean;
  deepseek_model?: string;
  llm_model?: string;
  projects?: string[];
  sites_root?: string;
  auth_required?: boolean;
  version?: string;
};

export type FsEntry = {
  name: string;
  path: string;
  type: "file" | "dir";
  size?: number;
  mtime?: number;
};

export type SiteInfo = {
  name: string;
  path: string;
  host_path?: string;
  url: string;
  files: number;
  size_bytes: number;
  has_index: boolean;
  domain?: string | null;
  is_wordpress?: boolean;
  top_entries?: { name: string; type: string; size?: number | null }[];
  suggested_webroot?: string | null;
};

const API_BASE = typeof window === "undefined"
  ? process.env.API_INTERNAL_URL || "http://127.0.0.1:8502"
  : "/api";

function authHeaders(): Record<string, string> {
  const token = getToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...authHeaders(),
      ...(init?.headers || {}),
    },
    cache: "no-store",
  });
  const data = await res.json().catch(() => ({}));
  if (res.status === 401) {
    clearToken();
    if (typeof window !== "undefined" && !window.location.pathname.startsWith("/login")) {
      window.location.href = "/login";
    }
    throw new Error((data as { error?: string }).error || "Нужен вход");
  }
  if (!res.ok) {
    throw new Error((data as { error?: string }).error || `HTTP ${res.status}`);
  }
  return data as T;
}

export function getStatus() {
  return request<ApiStatus>("/status");
}

export function login(password: string) {
  return request<{ ok: boolean; token: string; auth_required: boolean }>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ password }),
  });
}

export function checkAuth() {
  return request<{ ok: boolean; auth_required: boolean }>("/auth/check");
}

export function listFs(path = "") {
  const q = path ? `?path=${encodeURIComponent(path)}` : "";
  return request<{ ok: boolean; path: string; parent?: string; entries: FsEntry[] }>(`/fs/list${q}`);
}

export function readFs(path: string) {
  return request<{ ok: boolean; path: string; content: string; truncated?: boolean }>("/fs/read", {
    method: "POST",
    body: JSON.stringify({ path }),
  });
}

export function writeFs(path: string, content: string) {
  return request<{ ok: boolean; path: string }>("/fs/write", {
    method: "POST",
    body: JSON.stringify({ path, content }),
  });
}

export function mkdirFs(path: string) {
  return request<{ ok: boolean; path: string }>("/fs/mkdir", {
    method: "POST",
    body: JSON.stringify({ path }),
  });
}

export function deleteFs(path: string) {
  return request<{ ok: boolean }>("/fs/delete", {
    method: "POST",
    body: JSON.stringify({ path }),
  });
}

export function uploadFs(path: string, file: File) {
  const q = new URLSearchParams({
    path: path || "",
    filename: safeHeaderFilename(file.name),
  });
  return uploadBinary<{ ok: boolean; path: string; bytes: number }>(
    `/fs/upload?${q}`,
    file,
  );
}

export function listSites() {
  return request<{ ok: boolean; sites: SiteInfo[]; sites_root: string }>("/sites");
}

export function createSite(name: string, domain = "") {
  return request<{ ok: boolean; site: SiteInfo }>("/sites", {
    method: "POST",
    body: JSON.stringify({ name, domain }),
  });
}

export function deleteSite(name: string) {
  return request<{ ok: boolean }>(`/sites/${encodeURIComponent(name)}`, {
    method: "DELETE",
  });
}

export function deploySiteZip(name: string, file: File) {
  const q = new URLSearchParams({ name, filename: safeHeaderFilename(file.name) });
  return uploadBinary<{ ok: boolean; site: SiteInfo }>(`/sites/deploy?${q}`, file);
}

export function migrateSite(opts: {
  name: string;
  domain?: string;
  file: File;
  onProgress?: (pct: number, label: string) => void;
}) {
  return chunkedMigrate(opts);
}

export function bindSiteDomain(name: string, domain: string) {
  return request<{ ok: boolean; site: SiteInfo; hint?: string }>("/sites/domain", {
    method: "POST",
    body: JSON.stringify({ name, domain }),
  });
}

export function fixSitePerms(name?: string) {
  return request<{ ok: boolean; fixed: string[]; hint?: string }>("/sites/fix-perms", {
    method: "POST",
    body: JSON.stringify(name ? { name } : {}),
  });
}

export function inspectSites(name?: string) {
  const q = name ? `?name=${encodeURIComponent(name)}` : "";
  return request<{
    ok: boolean;
    sites_root?: string;
    host_sites_path?: string;
    host_path?: string;
    container_path?: string;
    diagnosis?: string;
    site?: SiteInfo | null;
    sites?: SiteInfo[];
    pending_uploads?: unknown[];
    hint?: string;
  }>(`/sites/inspect${q}`);
}

export function getWpStatus(name: string) {
  return request<{
    ok: boolean;
    has_wp_config?: boolean;
    wp_config?: string | null;
    defines?: Record<string, string>;
    db?: { ok: boolean; tables?: number; error?: string; sample_tables?: string[]; healed?: boolean };
    urls?: { ok?: boolean; urls?: Record<string, string>; error?: string };
    defaults?: {
      db_name?: string;
      db_user?: string;
      db_host?: string;
      db_password?: string;
      suggested_site_url?: string;
      domain?: string;
    };
    site?: SiteInfo;
  }>(`/wp/status?name=${encodeURIComponent(name)}`);
}

export function testWpDb() {
  return request<{
    ok: boolean;
    tables?: number;
    error?: string;
    host?: string;
    healed?: boolean;
    message?: string;
    hint?: string;
  }>("/wp/db-test");
}

export function fixWpDb() {
  return request<{
    ok: boolean;
    healed?: boolean;
    message?: string;
    error?: string;
    hint?: string;
    db?: { ok?: boolean; tables?: number; error?: string };
  }>("/wp/fix-db", {
    method: "POST",
    body: JSON.stringify({ force: true }),
  });
}

export function patchWpConfig(opts: {
  name: string;
  db_name: string;
  db_user: string;
  db_password: string;
  db_host: string;
  table_prefix?: string;
}) {
  return request<{ ok: boolean; path: string; backup: string; changed: string[]; mysql?: unknown }>(
    "/wp/config",
    {
      method: "POST",
      body: JSON.stringify(opts),
    },
  );
}

export function importWpSql(opts: { name: string; upload_id: string }) {
  return request<{ ok: boolean; statements: number; errors?: string[]; path?: string }>(
    "/wp/import-sql",
    {
      method: "POST",
      body: JSON.stringify(opts),
    },
  );
}

export function replaceWpUrl(opts: {
  name: string;
  old_url?: string;
  new_url: string;
  table_prefix?: string;
}) {
  return request<{
    ok: boolean;
    old_url: string;
    new_url: string;
    updated?: Record<string, unknown>;
    warning?: string;
  }>("/wp/replace-url", {
    method: "POST",
    body: JSON.stringify(opts),
  });
}

const DEFAULT_CHUNK = 4 * 1024 * 1024;

/** Upload any large file in chunks; returns upload_id (assembled on server). */
export async function chunkedUploadFile(opts: {
  file: File;
  siteName?: string;
  onProgress?: (pct: number, label: string) => void;
}) {
  const { file, siteName, onProgress } = opts;
  const chunkSize = DEFAULT_CHUNK;
  const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));

  onProgress?.(1, "Инициализация…");
  const init = await request<{
    ok: boolean;
    upload_id: string;
    chunk_size: number;
    total_chunks: number;
  }>("/upload/init", {
    method: "POST",
    body: JSON.stringify({
      // Always ASCII on disk — Cyrillic names are kept only as original_filename
      filename: file.name.toLowerCase().endsWith(".sql")
        ? "dump.sql"
        : safeHeaderFilename(file.name) || "upload.bin",
      size: file.size,
      site_name: siteName || "",
      chunk_size: chunkSize,
      total_chunks: totalChunks,
      original_filename: file.name,
    }),
  });

  const uploadId = init.upload_id;
  for (let i = 0; i < totalChunks; i++) {
    const start = i * chunkSize;
    const end = Math.min(file.size, start + chunkSize);
    const blob = file.slice(start, end);
    const pct = Math.round(((i + 1) / totalChunks) * 95);
    onProgress?.(pct, `Чанк ${i + 1}/${totalChunks}`);

    let attempt = 0;
    while (true) {
      attempt += 1;
      try {
        const res = await fetch(
          `${API_BASE}/upload/chunk?id=${encodeURIComponent(uploadId)}&index=${i}`,
          {
            method: "POST",
            headers: {
              ...authHeaders(),
              "Content-Type": "application/octet-stream",
            },
            body: blob,
          },
        );
        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
          clearToken();
          throw new Error("Нужен вход");
        }
        if (!res.ok) {
          throw new Error((data as { error?: string }).error || `Чанк ${i}: HTTP ${res.status}`);
        }
        break;
      } catch (err) {
        if (attempt >= 3) throw err;
        await new Promise((r) => setTimeout(r, 500 * attempt));
      }
    }
  }

  // Assemble without extracting (for SQL dumps etc.)
  onProgress?.(97, "Сборка файла на сервере…");
  await request("/upload/complete", {
    method: "POST",
    body: JSON.stringify({
      upload_id: uploadId,
      name: siteName || "tmp",
      action: "keep",
    }),
  });
  onProgress?.(100, "Файл на сервере");
  return { upload_id: uploadId };
}

async function chunkedMigrate(opts: {
  name: string;
  domain?: string;
  file: File;
  onProgress?: (pct: number, label: string) => void;
}) {
  const { file, name, domain, onProgress } = opts;
  const chunkSize = DEFAULT_CHUNK;
  const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));

  onProgress?.(1, "Инициализация загрузки…");
  const init = await request<{
    ok: boolean;
    upload_id: string;
    chunk_size: number;
    total_chunks: number;
  }>("/upload/init", {
    method: "POST",
    body: JSON.stringify({
      filename: safeHeaderFilename(file.name) || "site.zip",
      size: file.size,
      site_name: name,
      chunk_size: chunkSize,
      total_chunks: totalChunks,
      original_filename: file.name,
    }),
  });

  const uploadId = init.upload_id;
  for (let i = 0; i < totalChunks; i++) {
    const start = i * chunkSize;
    const end = Math.min(file.size, start + chunkSize);
    const blob = file.slice(start, end);
    const pct = Math.round(((i + 1) / totalChunks) * 90);
    onProgress?.(pct, `Чанк ${i + 1}/${totalChunks} (${formatBytes(end)} / ${formatBytes(file.size)})`);

    let attempt = 0;
    while (true) {
      attempt += 1;
      try {
        const res = await fetch(
          `${API_BASE}/upload/chunk?id=${encodeURIComponent(uploadId)}&index=${i}`,
          {
            method: "POST",
            headers: {
              ...authHeaders(),
              "Content-Type": "application/octet-stream",
            },
            body: blob,
          },
        );
        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
          clearToken();
          throw new Error("Нужен вход");
        }
        if (!res.ok) {
          throw new Error((data as { error?: string }).error || `Чанк ${i}: HTTP ${res.status}`);
        }
        break;
      } catch (err) {
        if (attempt >= 3) throw err;
        await new Promise((r) => setTimeout(r, 500 * attempt));
      }
    }
  }

  onProgress?.(95, "Сборка ZIP и распаковка…");
  const done = await request<{
    ok: boolean;
    site: SiteInfo;
    message?: string;
    assembled_path?: string;
  }>("/upload/complete", {
    method: "POST",
    body: JSON.stringify({
      upload_id: uploadId,
      name,
      domain: domain || "",
      action: "migrate",
    }),
  });
  onProgress?.(100, "Готово");
  return done;
}

function formatBytes(n: number) {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

/** Stream File as raw body — for small files only. */
async function uploadBinary<T>(path: string, file: File): Promise<T> {
  // Never put filename in headers (Latin-1 only) — use query string (URL-encoded)
  const sep = path.includes("?") ? "&" : "?";
  const safeName = encodeURIComponent(safeHeaderFilename(file.name));
  const url = `${API_BASE}${path}${sep}filename=${safeName}`;
  const res = await fetch(url, {
    method: "POST",
    headers: {
      ...authHeaders(),
      "Content-Type": file.type || "application/octet-stream",
    },
    body: file,
  });
  const data = await res.json().catch(() => ({}));
  if (res.status === 401) {
    clearToken();
    if (typeof window !== "undefined") window.location.href = "/login";
    throw new Error("Нужен вход");
  }
  if (!res.ok) {
    throw new Error((data as { error?: string }).error || `HTTP ${res.status}`);
  }
  return data as T;
}

export type ChatSummary = {
  id: string;
  title: string;
  site_id?: string;
  created_at: number;
  updated_at: number;
};

export type ChatMessage = {
  id: number | string;
  role: "user" | "assistant" | "tool" | string;
  content: string;
  meta?: Record<string, unknown>;
  created_at?: number;
};

export type ChatDetail = ChatSummary & {
  messages: ChatMessage[];
};

export type SiteContext = {
  ok: boolean;
  site?: string | null;
  project?: string | null;
  project_root?: string | null;
  snapshot?: string;
  card?: string;
  tree?: string[];
  can_edit?: boolean;
  is_wordpress?: boolean;
  domain?: string | null;
  has_index?: boolean;
  url?: string;
};

export type ChatEvent =
  | { type: "text"; content: string }
  | { type: "error"; content: string }
  | { type: "tool_call"; name: string; args?: unknown }
  | {
      type: "tool_result";
      name: string;
      result?: {
        ok?: boolean;
        path?: string;
        edited?: boolean;
        added?: number;
        removed?: number;
        error?: string;
        diff?: string;
      };
    }
  | { type: "info"; content: string }
  | { type: "chat"; chat_id: string; site?: string | null; project?: string | null; project_root?: string | null }
  | { type: "done"; chat_id?: string };

export function listChats(site?: string) {
  const q = site ? `?site=${encodeURIComponent(site)}` : "";
  return request<{ ok: boolean; chats: ChatSummary[] }>(`/chats${q}`);
}

export function getChat(id: string) {
  return request<{ ok: boolean; chat: ChatDetail }>(`/chats/${encodeURIComponent(id)}`);
}

export function createChat(site?: string, title?: string) {
  return request<{ ok: boolean; chat: ChatDetail }>("/chats", {
    method: "POST",
    body: JSON.stringify({ site: site || "", title: title || "Новый чат" }),
  });
}

export function renameChat(id: string, title: string) {
  return request<{ ok: boolean; chat: ChatDetail }>("/chats/rename", {
    method: "POST",
    body: JSON.stringify({ id, title }),
  });
}

export function deleteChat(id: string) {
  return request<{ ok: boolean }>(`/chats/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

export function getSiteContext(site?: string) {
  const q = site ? `?site=${encodeURIComponent(site)}` : "";
  return request<SiteContext>(`/context${q}`);
}

export async function streamChat(
  message: string,
  history: { role: string; content: string }[],
  onEvent: (ev: ChatEvent) => void,
  signal?: AbortSignal,
  opts?: { site?: string; project?: string; chat_id?: string },
) {
  const res = await fetch(`${API_BASE}/chat/stream`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...authHeaders(),
    },
    body: JSON.stringify({
      message,
      history,
      ...(opts?.site ? { site: opts.site } : {}),
      ...(opts?.project ? { project: opts.project } : {}),
      ...(opts?.chat_id ? { chat_id: opts.chat_id } : {}),
    }),
    signal,
  });
  if (res.status === 401) {
    clearToken();
    if (typeof window !== "undefined") window.location.href = "/login";
    throw new Error("Нужен вход");
  }
  if (!res.ok || !res.body) {
    const err = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
    throw new Error(err.error || `HTTP ${res.status}`);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    const chunks = buffer.split("\n\n");
    buffer = chunks.pop() || "";
    for (const chunk of chunks) {
      const line = chunk.trim();
      if (!line.startsWith("data:")) continue;
      const raw = line.slice(5).trim();
      if (!raw) continue;
      try {
        onEvent(JSON.parse(raw) as ChatEvent);
      } catch {
        /* ignore partial JSON */
      }
    }
  }
}
