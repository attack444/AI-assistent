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
};

const API_BASE = typeof window === "undefined"
  ? process.env.API_INTERNAL_URL || "http://127.0.0.1:8502"
  : "/api";

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers || {}),
    },
    cache: "no-store",
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error((data as { error?: string }).error || `HTTP ${res.status}`);
  }
  return data as T;
}

export function getStatus() {
  return request<ApiStatus>("/status");
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

export function uploadFs(path: string, content_b64: string, filename: string) {
  return request<{ ok: boolean; path: string }>("/fs/upload", {
    method: "POST",
    body: JSON.stringify({ path, content_b64, filename }),
  });
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

export function deploySiteZip(name: string, content_b64: string, filename: string) {
  return request<{ ok: boolean; site: SiteInfo }>("/sites/deploy", {
    method: "POST",
    body: JSON.stringify({ name, content_b64, filename }),
  });
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
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message, history }),
    signal,
  });
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
