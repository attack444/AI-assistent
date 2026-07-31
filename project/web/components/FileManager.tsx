"use client";

import { useCallback, useEffect, useState } from "react";
import {
  deleteFs,
  FsEntry,
  listFs,
  mkdirFs,
  readFs,
  uploadFs,
  writeFs,
} from "@/lib/api";

function formatSize(n?: number) {
  if (n == null) return "";
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export function FileManager() {
  const [path, setPath] = useState("");
  const [parent, setParent] = useState<string | undefined>();
  const [entries, setEntries] = useState<FsEntry[]>([]);
  const [selected, setSelected] = useState<string>("");
  const [content, setContent] = useState("");
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async (p = path) => {
    setBusy(true);
    setError("");
    try {
      const data = await listFs(p);
      setPath(data.path);
      setParent(data.parent);
      setEntries(data.entries || []);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }, [path]);

  useEffect(() => {
    void load("");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function openEntry(entry: FsEntry) {
    if (entry.type === "dir") {
      setSelected("");
      setContent("");
      setDirty(false);
      await load(entry.path);
      return;
    }
    setBusy(true);
    setError("");
    try {
      const data = await readFs(entry.path);
      setSelected(data.path);
      setContent(data.content || "");
      setDirty(false);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function save() {
    if (!selected) return;
    setBusy(true);
    setError("");
    try {
      await writeFs(selected, content);
      setDirty(false);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function makeDir() {
    const name = window.prompt("Имя новой папки");
    if (!name) return;
    const target = path.endsWith("/") ? `${path}${name}` : `${path}/${name}`;
    try {
      await mkdirFs(target);
      await load(path);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function makeFile() {
    const name = window.prompt("Имя нового файла", "index.html");
    if (!name) return;
    const target = path.endsWith("/") ? `${path}${name}` : `${path}/${name}`;
    try {
      await writeFs(target, "");
      await load(path);
      setSelected(target);
      setContent("");
      setDirty(false);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function removeSelected() {
    const target = selected || path;
    if (!target) return;
    if (!window.confirm(`Удалить?\n${target}`)) return;
    try {
      await deleteFs(target);
      setSelected("");
      setContent("");
      await load(parent || "");
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function onUpload(file: File | null) {
    if (!file) return;
    setBusy(true);
    setError("");
    try {
      const buf = await file.arrayBuffer();
      const bytes = new Uint8Array(buf);
      let binary = "";
      const chunk = 0x8000;
      for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
      }
      await uploadFs(path, btoa(binary), file.name);
      await load(path);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="panel files-layout">
      <div className="files-tree">
        <div className="files-toolbar">
          <button className="btn ghost small" type="button" disabled={parent == null} onClick={() => load(parent || "")}>
            ↑ Вверх
          </button>
          <button className="btn ghost small" type="button" onClick={() => load(path)} disabled={busy}>
            Обновить
          </button>
          <button className="btn ghost small" type="button" onClick={makeDir}>
            Папка
          </button>
          <button className="btn ghost small" type="button" onClick={makeFile}>
            Файл
          </button>
          <label className="btn ghost small">
            Загрузить
            <input
              type="file"
              hidden
              onChange={(e) => onUpload(e.target.files?.[0] || null)}
            />
          </label>
        </div>
        <div className="crumb">{path || "/"}</div>
        {error ? <div className="error-banner">{error}</div> : null}
        <div>
          {entries.length === 0 ? (
            <div className="empty">Папка пуста</div>
          ) : (
            entries.map((entry) => (
              <button
                key={entry.path}
                type="button"
                className={`file-row ${selected === entry.path ? "active" : ""}`}
                onClick={() => openEntry(entry)}
              >
                <span className="name">
                  {entry.type === "dir" ? "📁 " : "📄 "}
                  {entry.name}
                </span>
                <span className="meta">
                  {entry.type === "dir" ? "dir" : formatSize(entry.size)}
                </span>
              </button>
            ))
          )}
        </div>
      </div>
      <div className="files-editor">
        <div className="editor-head">
          <div className="mono muted">{selected || "Выбери файл"}</div>
          <div style={{ display: "flex", gap: 8 }}>
            <button className="btn small" type="button" disabled={!selected || !dirty || busy} onClick={save}>
              Сохранить
            </button>
            <button className="btn danger small" type="button" disabled={!selected} onClick={removeSelected}>
              Удалить
            </button>
          </div>
        </div>
        <div className="editor-body">
          {selected ? (
            <textarea
              className="textarea"
              value={content}
              onChange={(e) => {
                setContent(e.target.value);
                setDirty(true);
              }}
            />
          ) : (
            <div className="empty">Открой файл слева — как в файловом менеджере хостинга.</div>
          )}
        </div>
      </div>
    </div>
  );
}
