const TOKEN_KEY = "ai-helper-token";

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
  url: string;
  files: number;
  size_bytes: number;
  has_index: boolean;
  domain?: string | null;
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
    filename: file.name,
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
  const q = new URLSearchParams({ name, filename: file.name });
  return uploadBinary<{ ok: boolean; site: SiteInfo }>(`/sites/deploy?${q}`, file);
}

export function migrateSite(opts: {
  name: string;
  domain?: string;
  file: File;
}) {
  if (opts.file.size > 180 * 1024 * 1024) {
    return Promise.reject(
      new Error("ZIP больше 180 МБ — сожми архив или залей через SCP на сервер"),
    );
  }
  const q = new URLSearchParams({ name: opts.name, filename: opts.file.name });
  if (opts.domain) q.set("domain", opts.domain);
  return uploadBinary<{ ok: boolean; site: SiteInfo & { nginx_conf?: string; created?: boolean } }>(
    `/sites/migrate?${q}`,
    opts.file,
  );
}

export function bindSiteDomain(name: string, domain: string) {
  return request<{ ok: boolean; site: SiteInfo; hint?: string }>("/sites/domain", {
    method: "POST",
    body: JSON.stringify({ name, domain }),
  });
}

/** Stream File as raw body — no base64, low browser memory. */
async function uploadBinary<T>(path: string, file: File): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    method: "POST",
    headers: {
      ...authHeaders(),
      "Content-Type": file.type || "application/octet-stream",
      "X-Filename": file.name,
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

export type ChatEvent =
  | { type: "text"; content: string }
  | { type: "error"; content: string }
  | { type: "tool_call"; name: string; args?: unknown }
  | { type: "info"; content: string }
  | { type: "done" };

export async function streamChat(
  message: string,
  history: { role: string; content: string }[],
  onEvent: (ev: ChatEvent) => void,
  signal?: AbortSignal,
) {
  const res = await fetch(`${API_BASE}/chat/stream`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...authHeaders(),
    },
    body: JSON.stringify({ message, history }),
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
