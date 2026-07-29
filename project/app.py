# app.py
from __future__ import annotations

import os
from pathlib import Path
from typing import Optional

import streamlit as st

from core import (
    DEFAULT_EMBED_MODEL,
    DEFAULT_LLM_MODEL,
    DEFAULT_OLLAMA_HOST,
    REQUIRED_OLLAMA_MODELS,
    AppSettings,
    EditPlan,
    OllamaStatus,
    answer_with_web,
    apply_edit_plan,
    ask_llm_for_patch_plan,
    build_index,
    check_ollama_status,
    choose_candidates,
    cleanup_old_backups,
    clear_history,
    clear_index_storage,
    delete_project,
    ensure_dirs,
    get_index_info,
    get_missing_models,
    get_project_stats,
    iter_project_files,
    load_configured_index,
    load_history,
    load_projects,
    load_settings,
    needs_reindex,
    ollama_serve_error_hint,
    pull_ollama_models,
    query_project,
    register_project,
    save_history_entry,
    save_settings,
    stream_chat_with_context,
    try_start_ollama,
    verify_ollama_for_indexing,
    web_search_news,
    web_search_text,
)

ensure_dirs()

# ---------------------------------------------------------------------------
# Page config
# ---------------------------------------------------------------------------
st.set_page_config(
    page_title="AI Helper",
    layout="wide",
    page_icon="🤖",
    initial_sidebar_state="expanded",
)

# ---------------------------------------------------------------------------
# Load persisted settings once per session
# ---------------------------------------------------------------------------
if "settings" not in st.session_state:
    s = load_settings()
    # Allow env var to override host
    env_host = os.environ.get("OLLAMA_HOST", "")
    if env_host:
        s.ollama_host = env_host
    st.session_state["settings"] = s

settings: AppSettings = st.session_state["settings"]

# ---------------------------------------------------------------------------
# Sidebar
# ---------------------------------------------------------------------------
with st.sidebar:
    # ── Projects ────────────────────────────────────────────────────────────
    st.header("📁 Проекты")
    projects = load_projects()
    project_names = list(projects.keys())

    with st.expander("➕ Добавить проект", expanded=not project_names):
        new_name = st.text_input("Имя проекта", key="new_name")
        new_root = st.text_input("Путь к папке проекта", key="new_root",
                                 placeholder="C:\\Users\\User\\my-project")
        if st.button("Добавить", use_container_width=True, type="primary"):
            if new_name and new_root:
                root_path = Path(new_root).expanduser().resolve()
                if not root_path.exists():
                    st.error(f"Папка не найдена:\n{root_path}")
                elif new_name in projects:
                    st.error("Проект с таким именем уже существует")
                else:
                    register_project(new_name, root_path)
                    st.success(f"Проект «{new_name}» добавлен")
                    st.rerun()
            else:
                st.warning("Заполни оба поля")

    selected_project: Optional[str] = st.selectbox(
        "Активный проект",
        options=project_names if project_names else ["<нет проектов>"],
        index=0 if project_names else None,
        key="selected_project",
    )

    if project_names and selected_project and selected_project != "<нет проектов>":
        col_del, col_gap = st.columns([1, 2])
        with col_del:
            if st.button("🗑 Удалить", use_container_width=True, key="del_proj"):
                delete_project(selected_project)
                st.session_state.pop("chat_messages", None)
                st.session_state.pop("edit_plan", None)
                st.rerun()

    # ── Ollama status ───────────────────────────────────────────────────────
    st.divider()
    st.header("⚙️ Модель")

    llm_model    = st.text_input("LLM",        value=settings.llm_model)
    embed_model  = st.text_input("Embeddings", value=settings.embed_model)
    ollama_host  = st.text_input("Ollama host", value=settings.ollama_host)

    # Save settings when changed
    new_settings = AppSettings(
        llm_model=llm_model,
        embed_model=embed_model,
        ollama_host=ollama_host,
        context_window=settings.context_window,
        top_k=settings.top_k,
        chunk_size=settings.chunk_size,
        chunk_overlap=settings.chunk_overlap,
        use_web=settings.use_web,
        search_kind=settings.search_kind,
        max_web_results=settings.max_web_results,
    )

    # Ollama status block
    if "ollama_status" not in st.session_state:
        st.session_state["ollama_status"] = check_ollama_status(ollama_host)

    col_a, col_b = st.columns(2)
    with col_a:
        if st.button("Проверить", use_container_width=True):
            st.session_state["ollama_status"] = check_ollama_status(ollama_host)
    with col_b:
        if st.button("Запустить", use_container_width=True):
            with st.spinner("Запускаю Ollama..."):
                ok = try_start_ollama(ollama_host)
            st.session_state["ollama_status"] = check_ollama_status(ollama_host)
            if not ok:
                st.warning("Не удалось запустить. Установи Ollama с ollama.com")

    ollama_st: OllamaStatus = st.session_state["ollama_status"]
    if ollama_st.reachable:
        st.success(f"Ollama OK — {len(ollama_st.models)} моделей")
        missing = get_missing_models(REQUIRED_OLLAMA_MODELS, ollama_st.models)
        if missing:
            st.warning(f"Нужны модели: {', '.join(missing)}")
            if st.button(f"⬇ Скачать ({len(missing)})", use_container_width=True):
                with st.spinner("Скачиваю модели — подожди..."):
                    ok_m, fail_m = pull_ollama_models(missing, ollama_host)
                st.session_state["ollama_status"] = check_ollama_status(ollama_host)
                if ok_m:
                    st.success(f"Готово: {', '.join(ok_m)}")
                if fail_m:
                    st.error("\n".join(fail_m))
                st.rerun()
        else:
            with st.expander("Модели"):
                for m in ollama_st.models:
                    st.caption(m)
    else:
        st.error("Ollama не отвечает")
        with st.expander("Что делать?"):
            st.info(ollama_serve_error_hint())

    # ── Advanced settings ───────────────────────────────────────────────────
    with st.expander("Расширенные настройки"):
        context_window = st.number_input("Context window", value=settings.context_window, step=1024)
        top_k          = st.number_input("Top K", value=settings.top_k, step=1, min_value=1, max_value=20)
        chunk_size     = st.number_input("Chunk size", value=settings.chunk_size, step=128)
        chunk_overlap  = st.number_input("Chunk overlap", value=settings.chunk_overlap, step=10)
        new_settings.context_window = int(context_window)
        new_settings.top_k          = int(top_k)
        new_settings.chunk_size     = int(chunk_size)
        new_settings.chunk_overlap  = int(chunk_overlap)

    # ── Web search ──────────────────────────────────────────────────────────
    st.divider()
    st.header("🌐 Интернет")
    use_web         = st.checkbox("Использовать в ответах", value=settings.use_web)
    search_kind     = st.selectbox("Тип поиска", ["text", "news"], index=0 if settings.search_kind=="text" else 1)
    max_web_results = st.number_input("Результатов", value=settings.max_web_results, min_value=1, max_value=20, step=1)
    new_settings.use_web         = use_web
    new_settings.search_kind     = search_kind
    new_settings.max_web_results = int(max_web_results)

    # Persist any change
    from dataclasses import asdict as _asdict
    if _asdict(new_settings) != _asdict(settings):
        save_settings(new_settings)
        st.session_state["settings"] = new_settings
        settings = new_settings

# ---------------------------------------------------------------------------
# Guard: no project selected
# ---------------------------------------------------------------------------
if selected_project == "<нет проектов>" or not selected_project or selected_project not in projects:
    st.markdown("## Добро пожаловать в AI Helper 🤖")
    st.info("Добавь проект в боковой панели слева, чтобы начать.")
    with st.expander("Что умеет AI Helper?"):
        st.markdown("""
- **Чат с кодом** — задавай вопросы о проекте, ИИ видит весь код
- **Автоправки** — описываешь задачу → ИИ генерирует patch → применяешь
- **Интернет** — поиск документации с объединением с кодом проекта
- **История** — все запросы и правки сохраняются
- **Работает локально** — через Ollama, без облака и API-ключей
        """)
    st.stop()

project_root = Path(projects[selected_project].root)

# ---------------------------------------------------------------------------
# Auto-detect index staleness (once per project switch)
# ---------------------------------------------------------------------------
if st.session_state.get("_last_project") != selected_project:
    st.session_state["_last_project"] = selected_project
    st.session_state["_reindex_needed"] = needs_reindex(project_root)
    st.session_state.pop("chat_messages", None)  # clear chat on project switch

if st.session_state.get("_reindex_needed"):
    with st.container():
        c1, c2 = st.columns([5, 1])
        with c1:
            st.warning(
                "Файлы проекта изменились или индекс не построен — "
                "вопросы и правки могут быть неточными."
            )
        with c2:
            if st.button("Перестроить", type="primary"):
                with st.spinner("Строю индекс..."):
                    try:
                        build_index(
                            project_root=project_root,
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            chunk_size=int(chunk_size),
                            chunk_overlap=int(chunk_overlap),
                            force=True,
                            context_window=int(context_window),
                        )
                        cleanup_old_backups(project_root)
                        st.session_state["_reindex_needed"] = False
                        st.success("Индекс обновлён")
                        st.rerun()
                    except Exception as exc:
                        st.error(str(exc))

st.caption(f"Проект: **{selected_project}** — `{project_root}`")

# ---------------------------------------------------------------------------
# Tabs
# ---------------------------------------------------------------------------
tab_chat, tab_index, tab_edit, tab_web, tab_history = st.tabs(
    ["💬 Чат", "📦 Индекс", "✏️ Правки", "🌐 Интернет", "📜 История"]
)

# ===========================================================================
# TAB: Чат  (primary interface)
# ===========================================================================
with tab_chat:

    # Initialise chat session per project
    chat_key = f"chat_{selected_project}"
    if chat_key not in st.session_state:
        st.session_state[chat_key] = []
    chat_messages: list = st.session_state[chat_key]

    # Toolbar row
    tc1, tc2, tc3 = st.columns([3, 1, 1])
    with tc1:
        index_ok = get_index_info(project_root)
        if index_ok and index_ok.get("status") == "ready":
            st.caption(
                f"Индекс: {index_ok['file_count']} файлов · "
                f"последнее обновление {index_ok.get('last_built', '—')}"
            )
        else:
            st.caption("Индекс не построен — ответы без кода проекта")
    with tc2:
        chat_web = st.checkbox("🌐 Интернет", value=use_web, key="chat_web_toggle")
    with tc3:
        if st.button("🗑 Очистить чат", use_container_width=True):
            st.session_state[chat_key] = []
            st.rerun()

    st.divider()

    # Render history
    for msg in chat_messages:
        with st.chat_message(msg["role"]):
            st.markdown(msg["content"])
            if msg["role"] == "assistant" and msg.get("sources"):
                with st.expander("📂 Источники в проекте"):
                    for s in msg["sources"]:
                        st.markdown(f"**{s['path']}**")
                        st.code(s["snippet"][:400])
            if msg["role"] == "assistant" and msg.get("web_results"):
                with st.expander("🌐 Интернет-источники"):
                    for r in msg["web_results"]:
                        st.markdown(f"**{r['title']}**")
                        if r.get("url"):
                            st.write(r["url"])

    # Input
    if user_input := st.chat_input(
        "Задай вопрос о коде, попроси объяснить функцию, найти баг...",
        key="chat_input",
    ):
        # Add user bubble
        with st.chat_message("user"):
            st.markdown(user_input)
        chat_messages.append({"role": "user", "content": user_input})

        # Generate response
        with st.chat_message("assistant"):
            with st.spinner(""):
                try:
                    stream_gen, sources, web_results = stream_chat_with_context(
                        user_message=user_input,
                        chat_history=chat_messages[:-1],
                        project_root=project_root,
                        llm_model=llm_model,
                        embed_model=embed_model,
                        ollama_host=ollama_host,
                        top_k=int(top_k),
                        context_window=int(context_window),
                        search_web=chat_web,
                        max_web_results=int(max_web_results),
                        search_kind=search_kind,
                    )
                    full_response: str = st.write_stream(stream_gen)  # type: ignore[arg-type]
                except Exception as exc:
                    full_response = f"Ошибка: {exc}"
                    st.error(full_response)
                    sources, web_results = [], []

            if sources:
                with st.expander("📂 Источники в проекте"):
                    for s in sources:
                        st.markdown(f"**{s['path']}**")
                        st.code(s["snippet"][:400])
            if web_results:
                with st.expander("🌐 Интернет-источники"):
                    for r in web_results:
                        st.markdown(f"**{r['title']}**")
                        if r.get("url"):
                            st.write(r["url"])

        # Save to session + history
        chat_messages.append({
            "role": "assistant",
            "content": full_response,
            "sources": sources,
            "web_results": web_results,
        })
        save_history_entry({
            "type": "question",
            "project": selected_project,
            "query": user_input,
            "answer": full_response,
            "use_web": chat_web,
        })

# ===========================================================================
# TAB: Индекс
# ===========================================================================
with tab_index:
    index_info   = get_index_info(project_root)
    project_stat = get_project_stats(project_root)

    # Status row
    col_s1, col_s2, col_s3 = st.columns(3)
    with col_s1:
        st.metric("Файлов в проекте", project_stat["total_files"])
    with col_s2:
        st.metric("Размер", f"{project_stat['total_size_kb']} KB")
    with col_s3:
        if index_info:
            st.metric("Файлов в индексе", index_info.get("file_count", 0))
        else:
            st.metric("Файлов в индексе", "—")

    # Index status
    if index_info:
        status = index_info.get("status", "")
        if status == "ready":
            st.success(f"Индекс готов · {index_info.get('last_built', '')}")
        elif status == "corrupted":
            st.warning("Индекс повреждён — нужна пересборка")
        else:
            st.info("Индекс не построен")
    else:
        st.info("Индекс не построен")

    # File type breakdown
    if project_stat["top_extensions"]:
        with st.expander("Типы файлов в проекте"):
            for ext, cnt in project_stat["top_extensions"]:
                st.text(f"{ext:25s} {cnt}")

    st.divider()

    # Build / clear buttons
    col_b1, col_b2 = st.columns(2)
    with col_b1:
        if st.button("🔄 Построить / обновить индекс", use_container_width=True, type="primary"):
            if project_stat["total_files"] == 0:
                st.error("В проекте нет файлов для индексации. Проверь путь.")
            elif not ollama_st.reachable:
                st.error("Ollama не отвечает. Запусти Ollama через боковую панель.")
            else:
                progress = st.progress(0, text="Проверка Ollama...")
                try:
                    ok_emb, msg_emb = verify_ollama_for_indexing(embed_model, ollama_host)
                    if not ok_emb:
                        st.error(msg_emb)
                    else:
                        progress.progress(
                            20,
                            text=f"Индексирую {project_stat['total_files']} файлов..."
                        )
                        build_index(
                            project_root=project_root,
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            chunk_size=int(chunk_size),
                            chunk_overlap=int(chunk_overlap),
                            force=True,
                            context_window=int(context_window),
                        )
                        cleanup_old_backups(project_root)
                        st.session_state["_reindex_needed"] = False
                        progress.progress(100, text="Готово!")
                        st.success("Индекс построен успешно")
                        st.rerun()
                except Exception as exc:
                    st.error(f"Ошибка: {exc}")
                finally:
                    progress.empty()

    with col_b2:
        if st.button("Очистить индекс", use_container_width=True):
            clear_index_storage(project_root)
            st.session_state["_reindex_needed"] = True
            st.success("Очищено. Построй индекс заново.")
            st.rerun()

    st.caption(
        f"Хранится в `~/.ai-helper/indices/`. "
        "Перестраивай после крупных изменений в проекте."
    )

# ===========================================================================
# TAB: Правки
# ===========================================================================
with tab_edit:
    st.markdown("Опиши задачу — ИИ найдёт нужные файлы, сгенерирует diff, ты применишь кнопкой.")

    task = st.text_area(
        "Что нужно сделать",
        height=120,
        placeholder="Например: добавь логирование в функцию авторизации",
    )

    col_e1, col_e2 = st.columns(2)
    with col_e1:
        run_tests_after = st.checkbox("Запустить тесты после правок", value=True)
    with col_e2:
        tests_cmd_input = st.text_input("Команда тестов", placeholder="pytest -q")

    if st.button("⚙️ Сгенерировать patch", use_container_width=True, type="primary"):
        if not task.strip():
            st.warning("Опиши задачу")
        elif not index_ok or index_ok.get("status") != "ready":
            st.error("Сначала построй индекс на вкладке Индекс")
        else:
            with st.spinner("Анализирую код..."):
                try:
                    idx = load_configured_index(
                        project_root=project_root,
                        llm_model=llm_model,
                        embed_model=embed_model,
                        ollama_host=ollama_host,
                        context_window=int(context_window),
                    )
                    candidates = choose_candidates(project_root, idx, task, int(top_k))
                    if not candidates:
                        st.warning("Не нашлось подходящих файлов. Попробуй перефразировать или перестрой индекс.")
                    else:
                        with st.expander("Файлы-кандидаты"):
                            for p in candidates:
                                st.code(str(p.relative_to(project_root)))

                        plan = ask_llm_for_patch_plan(
                            llm_model=llm_model,
                            ollama_host=ollama_host,
                            project_root=project_root,
                            query=task,
                            candidate_files=candidates,
                        )
                        st.session_state["edit_plan"] = (
                            plan.model_dump() if hasattr(plan, "model_dump") else plan.dict()
                        )
                except FileNotFoundError as exc:
                    st.error(str(exc))
                except Exception as exc:
                    st.error(f"Ошибка: {exc}")

    # Show plan
    plan_data = st.session_state.get("edit_plan")
    if plan_data:
        plan = EditPlan(**plan_data)
        st.subheader("План изменений")
        st.write(plan.summary)
        if plan.warnings:
            for w in plan.warnings:
                st.warning(w)
        if not plan.patches:
            st.info("Нет изменений.")
        else:
            for item in plan.patches:
                with st.expander(f"📄 {item.path} — {item.reason}"):
                    st.code(item.patch, language="diff")

    st.divider()

    if st.button("✅ Применить patch", use_container_width=True):
        if not plan_data:
            st.error("Сначала сгенерируй patch")
        else:
            plan = EditPlan(**plan_data)
            if not plan.patches:
                st.warning("Нечего применять")
            else:
                with st.spinner("Применяю изменения..."):
                    result = apply_edit_plan(
                        project_root=project_root,
                        plan=plan,
                        run_tests_after=run_tests_after,
                        tests_cmd=tests_cmd_input.strip() or None,
                    )
                    cleanup_old_backups(project_root)

                if result.success:
                    st.success(f"Применено: {', '.join(result.applied_files)}")
                    if result.test_output:
                        with st.expander("Результаты тестов"):
                            st.code(result.test_output)
                    st.session_state["edit_plan"] = None
                    st.session_state["_reindex_needed"] = True
                    save_history_entry({
                        "type": "edit",
                        "project": selected_project,
                        "query": plan.summary,
                        "applied_files": result.applied_files,
                        "success": True,
                        "test_output": result.test_output,
                    })
                else:
                    st.error("Ошибка. Изменения откатаны.")
                    st.code(result.errors)
                    if result.test_output:
                        with st.expander("Вывод тестов"):
                            st.code(result.test_output)
                    save_history_entry({
                        "type": "edit",
                        "project": selected_project,
                        "query": plan.summary,
                        "applied_files": [],
                        "success": False,
                        "errors": result.errors,
                    })

# ===========================================================================
# TAB: Интернет
# ===========================================================================
with tab_web:
    web_q = st.text_input(
        "Поиск",
        placeholder="Например: FastAPI dependency injection best practices",
    )
    col_w1, col_w2 = st.columns(2)
    with col_w1:
        web_with_ctx = st.checkbox("Объединить с кодом проекта", value=True, key="web_ctx")
    with col_w2:
        kind_w = st.selectbox("Тип", ["text", "news"], key="kind_w")

    if st.button("🔎 Найти", use_container_width=True, type="primary"):
        if not web_q.strip():
            st.warning("Введи запрос")
        else:
            with st.spinner("Ищу..."):
                try:
                    if web_with_ctx:
                        answer, local_src, w_res = answer_with_web(
                            project_root=project_root,
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            query=web_q,
                            top_k=int(top_k),
                            search_kind=kind_w,
                            max_web_results=int(max_web_results),
                            context_window=int(context_window),
                        )
                        st.subheader("Ответ")
                        st.markdown(answer)
                        if local_src:
                            with st.expander("📂 Источники в проекте"):
                                for s in local_src:
                                    st.markdown(f"**{s['path']}**")
                                    st.code(s["snippet"])
                    else:
                        w_res = (
                            web_search_news(web_q, max_results=int(max_web_results))
                            if kind_w == "news"
                            else web_search_text(web_q, max_results=int(max_web_results))
                        )
                        answer = ""

                    if w_res:
                        st.subheader("Интернет")
                        for r in w_res:
                            with st.container(border=True):
                                st.markdown(f"**{r['title']}**")
                                if r.get("date"):
                                    st.caption(r["date"])
                                if r.get("url"):
                                    st.write(r["url"])
                                if r.get("snippet"):
                                    st.write(r["snippet"])
                    else:
                        st.info("Ничего не найдено")

                    save_history_entry({
                        "type": "web_search",
                        "project": selected_project,
                        "query": web_q,
                        "answer": answer,
                        "results_count": len(w_res),
                    })
                except Exception as exc:
                    st.error(f"Ошибка поиска: {exc}")

# ===========================================================================
# TAB: История
# ===========================================================================
with tab_history:
    col_ht, col_hc = st.columns([4, 1])
    with col_ht:
        st.subheader("История")
    with col_hc:
        if st.button("Очистить", use_container_width=True):
            clear_history()
            st.rerun()

    history = load_history()
    if not history:
        st.info("История пуста")
    else:
        all_projects = sorted({h.get("project", "") for h in history if h.get("project")})
        fp = st.selectbox("Проект", ["Все"] + all_projects, key="hist_proj")
        ft = st.selectbox("Тип", ["Все", "question", "edit", "web_search"], key="hist_type")

        filtered = [
            h for h in reversed(history)
            if (fp == "Все" or h.get("project") == fp)
            and (ft == "Все" or h.get("type") == ft)
        ]
        st.caption(f"Записей: {len(filtered)}")

        for entry in filtered[:100]:
            ts    = entry.get("timestamp", "")[:16]
            etype = entry.get("type", "?")
            proj  = entry.get("project", "")
            query = entry.get("query", "")[:90]
            icon  = {"question": "💬", "edit": "✏️", "web_search": "🌐"}.get(etype, "•")

            with st.expander(f"{icon} [{ts}] {proj} — {query}"):
                if etype in ("question", "web_search") and entry.get("answer"):
                    st.markdown(entry["answer"])
                if etype == "edit":
                    if entry.get("success"):
                        st.success(f"Применено: {', '.join(entry.get('applied_files', []))}")
                    else:
                        st.error("Не применено")
                    if entry.get("errors"):
                        st.code(entry["errors"])
                    if entry.get("test_output"):
                        with st.expander("Тесты"):
                            st.code(entry["test_output"])
