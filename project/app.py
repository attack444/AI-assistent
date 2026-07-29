# app.py — chat-centric AI coding agent UI
from __future__ import annotations

import json
import os
from dataclasses import asdict
from pathlib import Path
from typing import Dict, Generator, List, Optional

import streamlit as st

from agent import run_agent
from core import (
    AppSettings,
    REQUIRED_OLLAMA_MODELS,
    build_index,
    check_ollama_status,
    cleanup_old_backups,
    clear_history,
    delete_project,
    ensure_dirs,
    format_web_results,
    get_index_info,
    get_missing_models,
    load_history,
    load_projects,
    load_settings,
    needs_reindex,
    pull_ollama_models,
    register_project,
    save_history_entry,
    save_settings,
    try_start_ollama,
    web_search_text,
)
from memory import MemoryStore
from profile import UserProfile, load_profile, save_profile
from tools import AGENT_LOG, git_run, scan_for_projects

ensure_dirs()

# ---------------------------------------------------------------------------
# Page config
# ---------------------------------------------------------------------------
st.set_page_config(
    page_title="AI Helper",
    layout="wide",
    page_icon="🤖",
    initial_sidebar_state="collapsed",
)

st.markdown(
    """
<style>
.status-bar{display:flex;gap:14px;font-size:.78em;padding:4px 0 8px;opacity:.7}
.tc{padding:5px 10px;border-radius:5px;font-family:monospace;font-size:.8em;margin:2px 0}
.tc-run{background:#1e293b;border-left:3px solid #3b82f6}
.tc-ok {background:#052e16;border-left:3px solid #22c55e}
.tc-err{background:#2d0a0a;border-left:3px solid #ef4444}
.diff-add{color:#4ade80}
.diff-rem{color:#f87171}
.quick-chip button{font-size:.75em !important;padding:2px 8px !important;border-radius:12px !important}
</style>
""",
    unsafe_allow_html=True,
)

# ---------------------------------------------------------------------------
# Session-level singletons (loaded once per session)
# ---------------------------------------------------------------------------
if "settings" not in st.session_state:
    s = load_settings()
    env_host = os.environ.get("OLLAMA_HOST", "")
    if env_host:
        s.ollama_host = env_host
    st.session_state["settings"] = s

if "profile" not in st.session_state:
    st.session_state["profile"] = load_profile()

if "memory" not in st.session_state:
    st.session_state["memory"] = MemoryStore()

# LlamaIndex is expensive to import — cache it per project
# Key: "idx_cache_{project_name}" → VectorStoreIndex object
# Invalidated when _reindex flag is set or index is rebuilt

settings: AppSettings = st.session_state["settings"]
profile: UserProfile  = st.session_state["profile"]
memory: MemoryStore   = st.session_state["memory"]

# ---------------------------------------------------------------------------
# Sidebar
# ---------------------------------------------------------------------------
with st.sidebar:
    st.title("⚙ Настройки")

    # ── Projects ────────────────────────────────────────────────────────────
    st.subheader("Проекты")
    projects = load_projects()
    pnames = list(projects.keys())

    selected_project: Optional[str] = st.selectbox(
        "Активный",
        options=pnames if pnames else ["<нет>"],
        index=0 if pnames else None,
        key="sel_proj",
        label_visibility="collapsed",
    )

    if pnames and selected_project and selected_project != "<нет>":
        if st.button("🗑 Удалить проект", use_container_width=True, key="del_proj"):
            delete_project(selected_project)
            st.session_state.pop(f"chat_{selected_project}", None)
            st.rerun()

    # ── Model ────────────────────────────────────────────────────────────────
    st.subheader("Модель")
    llm_model   = st.text_input("LLM",        value=settings.llm_model,   key="sb_llm")
    embed_model = st.text_input("Embeddings", value=settings.embed_model, key="sb_emb")
    ollama_host = st.text_input("Ollama",     value=settings.ollama_host, key="sb_host")

    # Persist settings changes
    _new_s = AppSettings(
        llm_model=llm_model, embed_model=embed_model, ollama_host=ollama_host,
        context_window=settings.context_window, top_k=settings.top_k,
        chunk_size=settings.chunk_size, chunk_overlap=settings.chunk_overlap,
        use_web=settings.use_web, search_kind=settings.search_kind,
        max_web_results=settings.max_web_results,
    )
    if asdict(_new_s) != asdict(settings):
        save_settings(_new_s)
        st.session_state["settings"] = _new_s
        settings = _new_s

    # ── Ollama ───────────────────────────────────────────────────────────────
    if "ost" not in st.session_state:
        st.session_state["ost"] = check_ollama_status(ollama_host)
    ost = st.session_state["ost"]

    c1, c2 = st.columns(2)
    with c1:
        if st.button("Проверить", use_container_width=True):
            st.session_state["ost"] = check_ollama_status(ollama_host)
            st.rerun()
    with c2:
        if st.button("Запустить", use_container_width=True):
            with st.spinner():
                try_start_ollama(ollama_host)
            st.session_state["ost"] = check_ollama_status(ollama_host)
            ost = st.session_state["ost"]

    if ost.reachable:
        st.success(f"Ollama: {len(ost.models)} моделей")
        miss = get_missing_models(REQUIRED_OLLAMA_MODELS, ost.models)
        if miss:
            st.warning(f"Нужны: {', '.join(miss)}")
            if st.button("⬇ Скачать", use_container_width=True):
                with st.spinner("Скачиваю..."):
                    pull_ollama_models(miss, ollama_host)
                st.session_state["ost"] = check_ollama_status(ollama_host)
                st.rerun()
    else:
        st.error("Ollama недоступен")

    # ── Profile ──────────────────────────────────────────────────────────────
    st.subheader("Профиль")
    with st.expander("Редактировать"):
        profile.name    = st.text_input("Имя",    value=profile.name)
        profile.style   = st.text_input("Стиль",  value=profile.style)
        profile.verbosity = st.selectbox(
            "Verbosity", ["minimal", "normal", "verbose"],
            index=["minimal", "normal", "verbose"].index(profile.verbosity),
        )
        profile.confirm_before_apply = st.checkbox(
            "Подтверждение перед правками", value=profile.confirm_before_apply
        )
        profile.auto_index = st.checkbox("Авто-индекс", value=profile.auto_index)
        new_rule = st.text_input("+ Правило", placeholder="всегда пиши тесты")
        if st.button("Добавить правило"):
            if new_rule.strip():
                profile.rules.append(new_rule.strip())
                save_profile(profile)
                st.rerun()
        for idx, rule in enumerate(profile.rules):
            r1, r2 = st.columns([5, 1])
            r1.caption(rule)
            if r2.button("×", key=f"dr_{idx}"):
                profile.rules.pop(idx)
                save_profile(profile)
                st.rerun()
        if st.button("Сохранить", type="primary"):
            save_profile(profile)
            st.session_state["profile"] = profile
            st.success("Сохранено")

    # ── Memory viewer ────────────────────────────────────────────────────────
    st.subheader("Память")
    with st.expander(f"{len(memory.entries)} записей"):
        kw_forget = st.text_input("Удалить по слову", key="mem_forget_kw")
        if st.button("Удалить", key="mem_forget_btn"):
            n = memory.forget_matching(kw_forget)
            st.success(f"Удалено: {n}")
            st.rerun()
        for e in memory.all_entries()[:30]:
            m1, m2 = st.columns([5, 1])
            m1.caption(f"[{e.type}] {e.content[:65]}")
            if m2.button("×", key=f"dm_{e.id}"):
                memory.forget(e.id)
                st.rerun()

    # ── Git panel ────────────────────────────────────────────────────────────
    if project_root and (project_root / ".git").exists():
        st.subheader("Git")
        with st.expander("Статус репозитория"):
            git_cols = st.columns(2)
            if git_cols[0].button("Status", use_container_width=True, key="git_status"):
                r = git_run("status --short", str(project_root))
                st.code(r.get("output") or "(чисто)", language="")
            if git_cols[1].button("Log", use_container_width=True, key="git_log"):
                r = git_run("log --oneline -8", str(project_root))
                st.code(r.get("output") or "нет коммитов", language="")
            git_cols2 = st.columns(2)
            if git_cols2[0].button("Diff", use_container_width=True, key="git_diff"):
                r = git_run("diff --stat", str(project_root))
                st.code(r.get("output") or "(нет изменений)", language="diff")
            if git_cols2[1].button("Branch", use_container_width=True, key="git_branch"):
                r = git_run("branch -a", str(project_root))
                st.code(r.get("output") or "", language="")
            commit_msg = st.text_input("Сообщение коммита", placeholder="fix: ...", key="git_commit_msg")
            if st.button("Commit all", use_container_width=True, key="git_commit_btn"):
                if commit_msg.strip():
                    git_run("add -A", str(project_root))
                    r = git_run(f'commit -m "{commit_msg.strip()}"', str(project_root))
                    st.code(r.get("output") or "", language="")
                else:
                    st.warning("Введи сообщение коммита")

    # ── File tree ─────────────────────────────────────────────────────────────
    if project_root:
        st.subheader("Файлы проекта")
        with st.expander("Дерево файлов"):
            _HIDE = {"node_modules", ".git", "__pycache__", ".venv", "venv", "dist", "build"}
            def _tree(d: Path, depth: int = 0, max_depth: int = 3) -> None:
                if depth > max_depth:
                    return
                try:
                    items = sorted(d.iterdir(), key=lambda x: (x.is_file(), x.name.lower()))
                except (PermissionError, OSError):
                    return
                for item in items[:50]:
                    if item.name in _HIDE or item.name.startswith("."):
                        continue
                    indent = "  " * depth
                    if item.is_dir():
                        st.caption(f"{indent}📁 {item.name}/")
                        _tree(item, depth + 1, max_depth)
                    else:
                        try:
                            size = item.stat().st_size
                            label = f"{indent}📄 {item.name}  *{size:,} B*"
                        except OSError:
                            label = f"{indent}📄 {item.name}"
                        st.caption(label)
            _tree(project_root)

    # ── Advanced ─────────────────────────────────────────────────────────────
    with st.expander("Расширенные"):
        cw  = st.number_input("Context window", value=settings.context_window, step=1024)
        tk  = st.number_input("Top K", value=settings.top_k, step=1, min_value=1, max_value=20)
        cs  = st.number_input("Chunk size", value=settings.chunk_size, step=128)
        co  = st.number_input("Chunk overlap", value=settings.chunk_overlap, step=10)
        uw  = st.checkbox("Интернет по умолчанию", value=settings.use_web)
        if any([cw != settings.context_window, tk != settings.top_k,
                cs != settings.chunk_size, co != settings.chunk_overlap, uw != settings.use_web]):
            settings.context_window = int(cw)
            settings.top_k          = int(tk)
            settings.chunk_size     = int(cs)
            settings.chunk_overlap  = int(co)
            settings.use_web        = uw
            save_settings(settings)
            st.session_state["settings"] = settings

    # ── History quick ─────────────────────────────────────────────────────────
    with st.expander("История"):
        hist = load_history()
        for h in list(reversed(hist))[:20]:
            st.caption(f"[{h.get('timestamp','')[:16]}] {h.get('query','')[:55]}")
        if hist and st.button("Очистить"):
            clear_history()
            st.rerun()


# ---------------------------------------------------------------------------
# Project root
# ---------------------------------------------------------------------------
project_root: Optional[Path] = None
if pnames and selected_project and selected_project != "<нет>" and selected_project in projects:
    project_root = Path(projects[selected_project].root)

# ---------------------------------------------------------------------------
# Auto-reindex on project switch  (throttled: check at most every 60 s)
# ---------------------------------------------------------------------------
import time as _time

if project_root and st.session_state.get("_last_proj") != selected_project:
    st.session_state["_last_proj"] = selected_project
    st.session_state[f"chat_{selected_project}"] = []
    st.session_state.pop(f"idx_obj_{selected_project}", None)  # invalidate index cache
    st.session_state["_reindex"] = needs_reindex(project_root)
    st.session_state["_reindex_checked_at"] = _time.time()
elif project_root and not st.session_state.get("_reindex"):
    # Re-check staleness every 60 seconds at most (avoids scanning FS on every render)
    last_checked = st.session_state.get("_reindex_checked_at", 0)
    if _time.time() - last_checked > 60:
        st.session_state["_reindex"] = needs_reindex(project_root)
        st.session_state["_reindex_checked_at"] = _time.time()

# ---------------------------------------------------------------------------
# Status bar
# ---------------------------------------------------------------------------
idx_info = get_index_info(project_root) if project_root else None
idx_ok   = idx_info and idx_info.get("status") == "ready"
status_html = (
    f'<div class="status-bar">'
    f'<span>📁 {selected_project or "нет"}</span>'
    f'<span>🤖 {llm_model}</span>'
    f'<span>{"✓" if idx_ok else "⚠"} индекс</span>'
    f'<span>{"✓" if ost.reachable else "✗"} ollama</span>'
    f'<span>🧠 {len(memory.entries)}</span>'
    f'</div>'
)
st.markdown(status_html, unsafe_allow_html=True)

# Reindex banner
if project_root and st.session_state.get("_reindex"):
    b1, b2 = st.columns([5, 1])
    b1.warning("Файлы изменились — индекс устарел.")
    if b2.button("Перестроить", type="primary"):
        with st.spinner("Строю индекс..."):
            try:
                build_index(
                    project_root=project_root,
                    llm_model=llm_model, embed_model=embed_model,
                    ollama_host=ollama_host,
                    chunk_size=settings.chunk_size, chunk_overlap=settings.chunk_overlap,
                    force=True, context_window=settings.context_window,
                )
                cleanup_old_backups(project_root)
                st.session_state["_reindex"] = False
                st.session_state.pop(f"idx_obj_{selected_project}", None)
                st.rerun()
            except Exception as exc:
                st.error(str(exc))

# ---------------------------------------------------------------------------
# Cached index loader (avoids re-loading LlamaIndex from disk every message)
# ---------------------------------------------------------------------------
def _get_cached_index(project_root: Optional[Path]):
    """Return cached VectorStoreIndex or None. Invalidated when _reindex is set."""
    if not project_root:
        return None
    cache_key = f"idx_obj_{selected_project}"
    if st.session_state.get("_reindex") or cache_key not in st.session_state:
        return None
    return st.session_state.get(cache_key)


def _store_cached_index(project_root: Optional[Path], idx) -> None:
    if not project_root:
        return
    st.session_state[f"idx_obj_{selected_project}"] = idx


# ---------------------------------------------------------------------------
# Helper: format tool args for display
# ---------------------------------------------------------------------------
def _fmt_args(args: dict, max_len: int = 45) -> str:
    parts = []
    for k, v in list(args.items())[:3]:
        s = str(v)
        if len(s) > max_len:
            s = s[:max_len - 3] + "..."
        parts.append(f"{k}={repr(s)}")
    return ", ".join(parts)


# ---------------------------------------------------------------------------
# Command processor  (/help, /index, /web, /model, /projects, /add, ...)
# ---------------------------------------------------------------------------
def _cmd(raw: str) -> Optional[str]:
    raw = raw.strip()
    if not raw.startswith("/"):
        return None
    parts = raw[1:].split(None, 1)
    name  = parts[0].lower()
    arg   = parts[1].strip() if len(parts) > 1 else ""

    if name == "help":
        return (
            "**Команды:**\n"
            "`/index` — перестроить индекс\n"
            "`/web <запрос>` — поиск в интернете\n"
            "`/model <название|list>` — сменить / показать модели\n"
            "`/projects` — список проектов\n"
            "`/add <путь>` — добавить проект\n"
            "`/scan <путь>` — найти проекты на диске\n"
            "`/git <команда>` — git в текущем проекте\n"
            "`/clip` — показать буфер обмена\n"
            "`/deps` — проверить устаревшие зависимости\n"
            "`/profile` — показать профиль\n"
            "`/memory` — показать память\n"
            "`/forget <слово>` — удалить записи по ключевому слову\n"
            "`/clear` — очистить текущий чат\n"
            "`/log` — последние 10 действий агента"
        )

    elif name == "index":
        if not project_root:
            return "Нет активного проекта."
        try:
            build_index(
                project_root=project_root,
                llm_model=llm_model, embed_model=embed_model, ollama_host=ollama_host,
                chunk_size=settings.chunk_size, chunk_overlap=settings.chunk_overlap,
                force=True, context_window=settings.context_window,
            )
            cleanup_old_backups(project_root)
            st.session_state["_reindex"] = False
            st.session_state.pop(f"idx_obj_{selected_project}", None)
            info = get_index_info(project_root)
            return f"Индекс готов. Файлов: {info['file_count'] if info else '?'}"
        except Exception as e:
            return f"Ошибка: {e}"

    elif name == "web":
        if not arg:
            return "Использование: `/web запрос`"
        try:
            res = web_search_text(arg, max_results=5)
            return format_web_results(res) or "Ничего не найдено."
        except Exception as e:
            return f"Ошибка: {e}"

    elif name == "model":
        if arg == "list":
            models = st.session_state.get("ost", check_ollama_status(ollama_host)).models
            return ("**Доступные модели:**\n" + "\n".join(f"- `{m}`" for m in models)) if models else "Моделей нет."
        elif arg:
            settings.llm_model = arg
            save_settings(settings)
            st.session_state["settings"] = settings
            return f"Модель: `{arg}`"
        return f"Текущая: `{settings.llm_model}`"

    elif name == "projects":
        projs = load_projects()
        if not projs:
            return "Проектов нет. Добавь: `/add /путь/к/проекту`"
        return "\n".join(f"- **{n}**: `{c.root}`" for n, c in projs.items())

    elif name == "add":
        if not arg:
            return "Использование: `/add /путь/к/проекту`"
        p = Path(arg).expanduser().resolve()
        if not p.exists():
            return f"Путь не существует: `{p}`"
        register_project(p.name, p)
        return f"Проект **{p.name}** добавлен: `{p}`"

    elif name == "scan":
        if not arg:
            return "Использование: `/scan D:\\`"
        r = scan_for_projects(arg)
        if not r["ok"]:
            return f"Ошибка: {r['error']}"
        if not r["projects"]:
            return f"Проектов не найдено в `{arg}`"
        lines = [f"- `{p['path']}` ({', '.join(p['markers'])})" for p in r["projects"]]
        return f"Найдено {r['count']}:\n" + "\n".join(lines)

    elif name == "profile":
        return (
            f"**Профиль {profile.name}:**\n"
            f"- Стиль: {profile.style}\n"
            f"- Модель: {profile.preferred_model}\n"
            f"- Авто-применение: {profile.auto_apply_edits}\n"
            f"- Авто-индекс: {profile.auto_index}\n"
            f"- Подтверждение правок: {profile.confirm_before_apply}\n"
            f"- Правила: {', '.join(profile.rules) or 'нет'}"
        )

    elif name == "memory":
        entries = memory.all_entries()[:20]
        if not entries:
            return "Память пуста."
        return "\n".join(f"- `{e.id}` [{e.type}] {e.content}" for e in entries)

    elif name == "forget":
        if not arg:
            return "Использование: `/forget слово`"
        n = memory.forget_matching(arg)
        return f"Удалено записей: {n}"

    elif name == "clear":
        key = f"chat_{selected_project or '__none__'}"
        st.session_state[key] = []
        st.rerun()
        return None

    elif name == "git":
        if not project_root:
            return "Нет активного проекта."
        if not arg:
            arg = "status --short"
        r = git_run(arg, str(project_root))
        out = r.get("output", "").strip() or "(пусто)"
        return f"```\n{out}\n```"

    elif name == "clip":
        from tools import clipboard_get
        r = clipboard_get()
        if not r.get("ok"):
            return f"Ошибка: {r.get('error', '?')}"
        text = r.get("text", "").strip()
        if not text:
            return "Буфер обмена пуст."
        return f"**Буфер обмена:**\n```\n{text[:2000]}\n```"

    elif name == "deps":
        if not project_root:
            return "Нет активного проекта."
        from tools import check_deps
        r = check_deps(str(project_root))
        if not r.get("ok"):
            return f"Ошибка: {r.get('error', '?')}"
        lines = []
        for c in r.get("checks", []):
            lines.append(f"**{c['type']}** (`{Path(c['file']).name}`):\n```\n{c['outdated']}\n```")
        return "\n\n".join(lines) or "Всё актуально."

    elif name == "log":
        if not AGENT_LOG.exists():
            return "Лог пуст."
        lines = AGENT_LOG.read_text(encoding="utf-8").strip().splitlines()[-10:]
        rows = []
        for ln in lines:
            try:
                d = json.loads(ln)
                ok = "✓" if d.get("ok") else "✗"
                rows.append(f"{ok} {d['tool']:20s} {d.get('ts','')[:16]}")
            except Exception:
                rows.append(ln)
        return "```\n" + "\n".join(rows) + "\n```"

    else:
        return f"Неизвестная команда: `/{name}`. Введи `/help`."


# ---------------------------------------------------------------------------
# Chat render
# ---------------------------------------------------------------------------
chat_key = f"chat_{selected_project or '__none__'}"
if chat_key not in st.session_state:
    st.session_state[chat_key] = []
chat_msgs: List[Dict] = st.session_state[chat_key]

# Welcome if no project
if not project_root:
    st.markdown("## AI Helper 🤖")
    st.markdown(
        "Добавь проект через боковую панель, либо напиши в чате:\n\n"
        "`открой D:\\my-project`\n\n"
        "или `/add D:\\my-project`\n\n"
        "Используй `/help` для списка команд."
    )

# ── Quick-prompt chips ────────────────────────────────────────────────────
if project_root:
    _CHIPS = [
        ("🔍 Что не так?",    "Проверь все файлы проекта, найди проблемы и исправь"),
        ("📋 Структура",      "Покажи структуру проекта: файлы, папки, что за что отвечает"),
        ("🔀 Git статус",     "Покажи git status и последние коммиты"),
        ("📦 Зависимости",    "Проверь устаревшие зависимости проекта"),
        ("✨ Форматировать",   "Отформатируй все Python-файлы проекта через black"),
        ("📝 README",          "Напиши или обнови README.md для проекта"),
    ]
    chip_cols = st.columns(len(_CHIPS))
    _quick_input: Optional[str] = None
    for col, (label, prompt) in zip(chip_cols, _CHIPS):
        if col.button(label, use_container_width=True, key=f"chip_{label}"):
            _quick_input = prompt
else:
    _quick_input = None

# Render existing messages
for hist_msg in chat_msgs:
    with st.chat_message(hist_msg["role"]):
        st.markdown(hist_msg["content"])
        if hist_msg.get("tool_steps"):
            with st.expander(f"Шаги агента ({len(hist_msg['tool_steps'])})"):
                for step in hist_msg["tool_steps"]:
                    ok = step.get("result", {}).get("ok", False)
                    st.code(
                        f"{'✓' if ok else '✗'} {step['name']}({_fmt_args(step['args'])})",
                        language="",
                    )

# ---------------------------------------------------------------------------
# Input
# ---------------------------------------------------------------------------
_effective_input = _quick_input or None

if _effective_input is None:
    _typed = st.chat_input("Задай вопрос, дай задание, или /help...")
    if _typed:
        _effective_input = _typed

if user_input := _effective_input:
    with st.chat_message("user"):
        st.markdown(user_input)
    chat_msgs.append({"role": "user", "content": user_input})

    # ── Commands ──────────────────────────────────────────────────────────────
    cmd_resp = _cmd(user_input)
    if cmd_resp is not None:
        with st.chat_message("assistant"):
            st.markdown(cmd_resp)
        chat_msgs.append({"role": "assistant", "content": cmd_resp})

    else:
        # ── Inline "открой <path>" detection ─────────────────────────────────
        path_added: Optional[Path] = None
        low = user_input.lower()
        if any(kw in low for kw in ("открой ", "open ", "добавь проект", "/add ")):
            for token in user_input.split():
                candidate = Path(token).expanduser()
                if candidate.exists() and candidate.is_dir():
                    path_added = candidate.resolve()
                    break

        if path_added:
            pname = path_added.name
            register_project(pname, path_added)
            resp = f"Проект **{pname}** добавлен: `{path_added}`"
            if profile.auto_index:
                try:
                    with st.spinner("Строю индекс..."):
                        build_index(
                            project_root=path_added,
                            llm_model=llm_model, embed_model=embed_model, ollama_host=ollama_host,
                            chunk_size=settings.chunk_size, chunk_overlap=settings.chunk_overlap,
                            force=True, context_window=settings.context_window,
                        )
                    resp += "  Индекс построен."
                except Exception as e:
                    resp += f"  Ошибка индекса: {e}"
            with st.chat_message("assistant"):
                st.markdown(resp)
            chat_msgs.append({"role": "assistant", "content": resp})
            st.rerun()

        else:
            # ── Auto-reindex if stale ─────────────────────────────────────────
            if project_root and profile.auto_index and needs_reindex(project_root):
                try:
                    build_index(
                        project_root=project_root,
                        llm_model=llm_model, embed_model=embed_model, ollama_host=ollama_host,
                        chunk_size=settings.chunk_size, chunk_overlap=settings.chunk_overlap,
                        context_window=settings.context_window,
                    )
                    st.session_state["_reindex"] = False
                except Exception:
                    pass

            # ── Agent call ────────────────────────────────────────────────────
            with st.chat_message("assistant"):
                tool_area = st.container()
                text_slot = st.empty()

                full_text = ""
                tool_steps: List[Dict] = []

                def _stream_agent() -> Generator[str, None, None]:
                    for ev in run_agent(
                        user_message=user_input,
                        chat_history=[m for m in chat_msgs[:-1]],
                        project_root=project_root,
                        profile=profile,
                        memory=memory,
                        llm_model=llm_model,
                        ollama_host=ollama_host,
                        context_window=settings.context_window,
                    ):
                        if ev.type == "tool_call":
                            with tool_area:
                                st.markdown(
                                    f'<div class="tc tc-run">→ <b>{ev.tool_name}</b>'
                                    f"({_fmt_args(ev.tool_args)})</div>",
                                    unsafe_allow_html=True,
                                )
                            tool_steps.append(
                                {"name": ev.tool_name, "args": ev.tool_args, "result": {}}
                            )

                        elif ev.type == "tool_result":
                            ok = ev.tool_result.get("ok", False)
                            css, icon = ("tc-ok", "✓") if ok else ("tc-err", "✗")

                            # Build summary label
                            if ev.tool_name == "write_file" and ok:
                                added   = ev.tool_result.get("added", 0)
                                removed = ev.tool_result.get("removed", 0)
                                is_new  = ev.tool_result.get("is_new", False)
                                fname   = Path(ev.tool_result.get("path", "")).name
                                action  = "создан" if is_new else f"+{added} -{removed} строк"
                                label   = f"{icon} <b>write_file</b> {fname} ({action})"
                            elif ev.tool_name == "git_run" and ok:
                                cmd = ev.tool_args.get("command", "")
                                label = f"{icon} <b>git</b> {cmd}"
                            else:
                                label = f"{icon} <b>{ev.tool_name}</b>"

                            with tool_area:
                                st.markdown(
                                    f'<div class="tc {css}">{label}</div>',
                                    unsafe_allow_html=True,
                                )
                                # Show diff inline for write_file
                                diff_text = ev.tool_result.get("diff", "")
                                if ev.tool_name == "write_file" and ok and diff_text and diff_text != "(файл не изменится)":
                                    with st.expander(f"Diff {Path(ev.tool_result.get('path','')).name}"):
                                        st.code(diff_text, language="diff")
                                # Show output for git/run_command/run_powershell
                                if ev.tool_name in ("git_run", "run_command", "run_powershell") and ok:
                                    out = ev.tool_result.get("output", "").strip()
                                    if out:
                                        with st.expander("Вывод"):
                                            st.code(out, language="")
                                # Show diff_preview
                                if ev.tool_name == "diff_preview" and ok:
                                    diff_text = ev.tool_result.get("diff", "")
                                    if diff_text:
                                        with st.expander("Предпросмотр изменений"):
                                            st.code(diff_text, language="diff")

                            if tool_steps:
                                tool_steps[-1]["result"] = ev.tool_result
                            # Mark reindex if FS changed
                            if ev.tool_name in ("write_file", "create_file", "delete_file"):
                                st.session_state["_reindex"] = True

                        elif ev.type == "text":
                            yield ev.content

                        elif ev.type == "error":
                            yield f"\n\n**Ошибка:** {ev.content}"

                full_text = st.write_stream(_stream_agent())  # type: ignore[arg-type]

                if tool_steps:
                    with st.expander(f"Шаги агента ({len(tool_steps)})"):
                        for step in tool_steps:
                            ok = step["result"].get("ok", False)
                            st.code(
                                f"{'✓' if ok else '✗'} {step['name']}({_fmt_args(step['args'])})",
                                language="",
                            )

            chat_msgs.append(
                {
                    "role": "assistant",
                    "content": full_text or "",
                    "tool_steps": tool_steps,
                }
            )

            save_history_entry(
                {
                    "type": "question",
                    "project": selected_project or "",
                    "query": user_input,
                    "answer": full_text or "",
                }
            )
