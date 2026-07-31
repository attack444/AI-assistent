"use client";

import { useCallback, useEffect, useRef, type KeyboardEvent } from "react";

function langFromPath(path: string): string {
  const ext = (path.split(".").pop() || "").toLowerCase();
  const map: Record<string, string> = {
    js: "javascript",
    jsx: "javascript",
    ts: "typescript",
    tsx: "typescript",
    py: "python",
    php: "php",
    css: "css",
    scss: "css",
    html: "html",
    htm: "html",
    json: "json",
    md: "markdown",
    sh: "bash",
    yml: "yaml",
    yaml: "yaml",
    sql: "sql",
    env: "ini",
    txt: "text",
  };
  return map[ext] || "text";
}

type Props = {
  path: string;
  value: string;
  onChange: (v: string) => void;
  onSave?: () => void;
};

/** Lightweight code editor: mono, Tab indent, Ctrl/Cmd+S — no heavy Monaco deps. */
export function CodeEditor({ path, value, onChange, onSave }: Props) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const lang = langFromPath(path);

  const onKeyDown = useCallback(
    (e: KeyboardEvent<HTMLTextAreaElement>) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
        e.preventDefault();
        onSave?.();
        return;
      }
      if (e.key === "Tab") {
        e.preventDefault();
        const el = e.currentTarget;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const next = value.slice(0, start) + "  " + value.slice(end);
        onChange(next);
        requestAnimationFrame(() => {
          el.selectionStart = el.selectionEnd = start + 2;
        });
      }
    },
    [onChange, onSave, value],
  );

  useEffect(() => {
    ref.current?.focus();
  }, [path]);

  return (
    <div className="code-editor">
      <div className="code-editor-meta mono muted">
        {lang}
        {" · "}
        Ctrl+S сохранить · Tab — отступ
      </div>
      <textarea
        ref={ref}
        className="textarea code-editor-area"
        spellCheck={false}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={onKeyDown}
        aria-label={`Редактор ${path}`}
      />
    </div>
  );
}

export function previewUrlForPath(path: string): string | null {
  const m = path.match(/\/(?:opt\/sites|var\/ai-helper\/sites)\/([^/]+)\/(.*)$/);
  if (!m) return null;
  const [, site, rest] = m;
  if (!rest || rest.endsWith("/")) return `/sites/${site}/`;
  return `/sites/${site}/${rest}`;
}
