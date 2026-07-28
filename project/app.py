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
    delete_project,
    ensure_dirs,
    get_index_info,
    get_missing_models,
    load_configured_index,
    load_history,
    load_projects,
    ollama_serve_error_hint,
    pull_ollama_models,
    query_project,
    register_project,
    save_history_entry,
    try_start_ollama,
    web_search_news,
    web_search_text,
)

ensure_dirs()

st.set_page_config(page_title="AI Helper", layout="wide", page_icon="🤖")
st.title("🤖 AI Helper для проектов")

# ---------------------------------------------------------------------------
# Sidebar — projects & model settings
# ---------------------------------------------------------------------------
with st.sidebar:
    st.header("📁 Проекты")
    projects = load_projects()
    project_names = list(projects.keys())

    with st.expander("Добавить проект", expanded=not project_names):
        new_name = st.text_input("Имя проекта", key="new_name")
        new_root = st.text_input("Путь к проекту", key="new_root")
        if st.button("➕ Добавить"):
            if new_name and new_root:
                root_path = Path(new_root).expanduser().resolve()
                if not root_path.exists():
                    st.error(f"Путь не существует: {root_path}")
                else:
                    register_project(new_name, root_path)
                    st.success(f"Проект «{new_name}» добавлен")
                    st.rerun()
            else:
                st.warning("Заполни оба поля")

    selected_project: Optional[str] = st.selectbox(
        "Выбери проект",
        options=project_names if project_names else ["<нет проектов>"],
        index=0 if project_names else None,
        key="selected_project",
    )

    if project_names and selected_project and selected_project != "<нет проектов>":
        if st.button("🗑️ Удалить проект", key="delete_project_btn"):
            delete_project(selected_project)
            st.success(f"Проект «{selected_project}» удалён")
            st.rerun()

    st.divider()
    st.header("⚙️ Модель")

    default_ollama_host = os.environ.get("OLLAMA_HOST", DEFAULT_OLLAMA_HOST)
    llm_model = st.text_input("LLM", value=DEFAULT_LLM_MODEL)
    embed_model = st.text_input("Embeddings", value=DEFAULT_EMBED_MODEL)
    ollama_host = st.text_input("Ollama host", value=default_ollama_host)

    if "ollama_status" not in st.session_state:
        st.session_state["ollama_status"] = check_ollama_status(ollama_host)

    col_o1, col_o2 = st.columns(2)
    with col_o1:
        if st.button("🔄 Проверить Ollama", use_container_width=True):
            st.session_state["ollama_status"] = check_ollama_status(ollama_host)
    with col_o2:
        if st.button("▶ Запустить Ollama", use_container_width=True):
            with st.spinner("Запускаю Ollama..."):
                started = try_start_ollama(ollama_host)
            st.session_state["ollama_status"] = check_ollama_status(ollama_host)
            if started:
                st.success("Ollama запущен")
            else:
                st.warning("Не удалось запустить автоматически. Установи Ollama с ollama.com")

    ollama_status: OllamaStatus = st.session_state["ollama_status"]
    if ollama_status.reachable:
        st.success(ollama_status.message)
        missing = get_missing_models(REQUIRED_OLLAMA_MODELS, ollama_status.models)
        if missing:
            st.warning(f"Не хватает моделей: {', '.join(missing)}")
            if st.button(f"⬇ Скачать модели ({len(missing)})", use_container_width=True):
                with st.spinner("Скачиваю модели — это может занять несколько минут..."):
                    ok, failed = pull_ollama_models(missing, ollama_host)
                st.session_state["ollama_status"] = check_ollama_status(ollama_host)
                if ok:
                    st.success(f"Скачано: {', '.join(ok)}")
                if failed:
                    st.error("\n".join(failed))
                st.rerun()
        elif ollama_status.models:
            with st.expander("Установленные модели"):
                for m in ollama_status.models:
                    st.text(m)
    else:
        st.error(ollama_status.message)
        with st.expander("Ошибка «порт 11434 занят»?"):
            st.info(ollama_serve_error_hint())

    context_window = st.number_input("Context window", value=64000, step=1024)
    top_k = st.number_input("Top K (поиск)", value=5, step=1, min_value=1, max_value=20)
    chunk_size = st.number_input("Chunk size", value=1024, step=128)
    chunk_overlap = st.number_input("Chunk overlap", value=150, step=10)

    st.divider()
    st.header("🌐 Интернет")
    use_web = st.checkbox("Использовать интернет в ответах", value=False)
    search_kind = st.selectbox("Тип поиска", ["text", "news"])
    max_web_results = st.number_input("Web results", value=5, min_value=1, max_value=20, step=1)

if selected_project == "<нет проектов>" or not selected_project:
    st.info("Сначала добавь проект слева.")
    st.stop()

project_root = Path(projects[selected_project].root)
st.caption(f"Проект: **{selected_project}** — `{project_root}`")

# ---------------------------------------------------------------------------
# Tabs
# ---------------------------------------------------------------------------
tab1, tab2, tab3, tab4, tab5 = st.tabs(["📦 Индекс", "💬 Вопросы", "✏️ Правки", "🌐 Интернет", "📜 История"])

# ===========================================================================
# Tab 1 — Index
# ===========================================================================
with tab1:
    col_btn, col_info = st.columns([1, 2])

    with col_btn:
        if st.button("🔄 Построить / обновить индекс", use_container_width=True):
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
                    st.success("✅ Индекс готов")
                    st.rerun()
                except Exception as exc:
                    st.error(f"Ошибка построения индекса:\n{exc}")

    with col_info:
        info = get_index_info(project_root)
        if info:
            st.metric("Файлов в индексе", info["file_count"])
            st.caption(f"Последнее построение: {info['last_built']}")
        else:
            st.info("Индекс ещё не построен.")

    st.divider()
    st.markdown(
        "Индекс хранится в `~/.ai-helper/indices/` отдельно от кода проекта. "
        "Пересобирай после крупных изменений."
    )

# ===========================================================================
# Tab 2 — Questions
# ===========================================================================
with tab2:
    question = st.text_area(
        "Вопрос к коду",
        height=140,
        placeholder="Например: где создаётся JWT и как он валидируется?",
    )

    if st.button("🔍 Спросить", use_container_width=True):
        if not question.strip():
            st.warning("Введи вопрос")
        else:
            with st.spinner("Ищу ответ..."):
                try:
                    if use_web:
                        answer, local_sources, web_results = answer_with_web(
                            project_root=project_root,
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            query=question,
                            top_k=int(top_k),
                            search_kind=search_kind,
                            max_web_results=int(max_web_results),
                            context_window=int(context_window),
                        )
                    else:
                        answer, local_sources = query_project(
                            project_root=project_root,
                            query=question,
                            top_k=int(top_k),
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            context_window=int(context_window),
                        )
                        web_results = []

                    save_history_entry({
                        "type": "question",
                        "project": selected_project,
                        "query": question,
                        "answer": answer,
                        "use_web": use_web,
                    })

                    st.subheader("Ответ")
                    st.write(answer)

                    if local_sources:
                        with st.expander("📂 Источники в проекте"):
                            for s in local_sources:
                                st.markdown(f"**{s['path']}**")
                                st.code(s["snippet"])

                    if web_results:
                        with st.expander("🌐 Интернет-результаты"):
                            for r in web_results:
                                st.markdown(f"**{r['title']}**")
                                if r["url"]:
                                    st.write(r["url"])
                                if r["snippet"]:
                                    st.caption(r["snippet"])

                except FileNotFoundError:
                    st.error(
                        "Индекс не найден. Перейди на вкладку **Индекс** и построй его."
                    )
                except Exception as exc:
                    st.error(f"Ошибка: {exc}")

# ===========================================================================
# Tab 3 — Edits
# ===========================================================================
with tab3:
    task = st.text_area(
        "Что нужно изменить",
        height=140,
        placeholder="Например: упрости валидацию JWT и добавь тесты",
    )

    col_opt1, col_opt2 = st.columns(2)
    with col_opt1:
        run_tests_after = st.checkbox("Запускать тесты после правок", value=True)
    with col_opt2:
        tests_cmd_input = st.text_input(
            "Команда тестов (оставь пустым для pytest)",
            placeholder="pytest -q tests/",
        )

    if st.button("⚙️ Сгенерировать patch", use_container_width=True):
        if not task.strip():
            st.warning("Опиши задачу")
        else:
            with st.spinner("Ищу файлы и собираю patch..."):
                try:
                    index = load_configured_index(
                        project_root=project_root,
                        llm_model=llm_model,
                        embed_model=embed_model,
                        ollama_host=ollama_host,
                        context_window=int(context_window),
                    )
                    candidates = choose_candidates(project_root, index, task, int(top_k))

                    if not candidates:
                        st.warning("Не найдено подходящих файлов. Попробуй перестроить индекс.")
                        st.stop()

                    st.write("**Файлы-кандидаты:**")
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

                except FileNotFoundError:
                    st.error(
                        "Индекс не найден. Перейди на вкладку **Индекс** и построй его."
                    )
                    st.stop()
                except Exception as exc:
                    st.error(f"Ошибка генерации patch: {exc}")
                    st.stop()

            plan_data = st.session_state.get("edit_plan")
            if plan_data:
                plan = EditPlan(**plan_data)
                st.subheader("📋 План изменений")
                st.write(plan.summary)

                if plan.warnings:
                    for w in plan.warnings:
                        st.warning(w)

                if not plan.patches:
                    st.info("Модель не нашла файлов для изменения.")
                else:
                    for item in plan.patches:
                        with st.expander(f"📄 {item.path} — {item.reason}"):
                            st.code(item.patch, language="diff")

    st.divider()

    if st.button("✅ Применить patch", use_container_width=True):
        plan_data = st.session_state.get("edit_plan")
        if not plan_data:
            st.error("Сначала сгенерируй patch")
        else:
            plan = EditPlan(**plan_data)
            if not plan.patches:
                st.warning("Нечего применять — список изменений пуст")
            else:
                with st.spinner("Применяю изменения..."):
                    tests_cmd = tests_cmd_input.strip() or None
                    result = apply_edit_plan(
                        project_root=project_root,
                        plan=plan,
                        run_tests_after=run_tests_after,
                        tests_cmd=tests_cmd,
                    )
                    cleanup_old_backups(project_root)

                if result.success:
                    st.success(f"✅ Изменения применены: {', '.join(result.applied_files)}")
                    if result.test_output:
                        with st.expander("Результаты тестов"):
                            st.code(result.test_output)
                    save_history_entry({
                        "type": "edit",
                        "project": selected_project,
                        "query": plan.summary,
                        "applied_files": result.applied_files,
                        "success": True,
                        "test_output": result.test_output,
                    })
                else:
                    st.error("❌ Ошибка применения. Изменения откатаны.")
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
# Tab 4 — Internet search
# ===========================================================================
with tab4:
    web_query = st.text_input(
        "Поиск в интернете",
        placeholder="Например: asyncio best practices Python 2025",
    )
    col_sw1, col_sw2 = st.columns(2)
    with col_sw1:
        web_include_project = st.checkbox("Объединить с контекстом проекта", value=True)
    with col_sw2:
        kind_tab4 = st.selectbox("Тип поиска ", ["text", "news"], key="kind_tab4")

    if st.button("🔎 Искать в интернете", use_container_width=True):
        if not web_query.strip():
            st.warning("Введи запрос")
        else:
            with st.spinner("Ищу..."):
                try:
                    if web_include_project:
                        answer, local_sources, web_results = answer_with_web(
                            project_root=project_root,
                            llm_model=llm_model,
                            embed_model=embed_model,
                            ollama_host=ollama_host,
                            query=web_query,
                            top_k=int(top_k),
                            search_kind=kind_tab4,
                            max_web_results=int(max_web_results),
                            context_window=int(context_window),
                        )
                        st.subheader("Ответ")
                        st.write(answer)

                        if local_sources:
                            with st.expander("📂 Локальные источники проекта"):
                                for s in local_sources:
                                    st.markdown(f"**{s['path']}**")
                                    st.code(s["snippet"])
                    else:
                        if kind_tab4 == "news":
                            web_results = web_search_news(web_query, max_results=int(max_web_results))
                        else:
                            web_results = web_search_text(web_query, max_results=int(max_web_results))
                        answer = ""

                    if web_results:
                        st.subheader("🌐 Интернет-результаты")
                        for r in web_results:
                            with st.container(border=True):
                                st.markdown(f"**{r['title']}**")
                                if r.get("date"):
                                    st.caption(r["date"])
                                if r["url"]:
                                    st.write(r["url"])
                                if r["snippet"]:
                                    st.write(r["snippet"])
                    else:
                        st.info("Ничего не найдено")

                    save_history_entry({
                        "type": "web_search",
                        "project": selected_project,
                        "query": web_query,
                        "answer": answer,
                        "results_count": len(web_results),
                    })

                except Exception as exc:
                    st.error(f"Ошибка поиска: {exc}")

# ===========================================================================
# Tab 5 — History
# ===========================================================================
with tab5:
    col_h1, col_h2 = st.columns([3, 1])
    with col_h1:
        st.subheader("📜 История запросов")
    with col_h2:
        if st.button("🗑️ Очистить историю"):
            clear_history()
            st.success("История очищена")
            st.rerun()

    history = load_history()

    if not history:
        st.info("История пуста")
    else:
        filter_project = st.selectbox(
            "Фильтр по проекту",
            options=["Все"] + sorted({h.get("project", "") for h in history if h.get("project")}),
            key="history_filter",
        )
        filter_type = st.selectbox(
            "Фильтр по типу",
            options=["Все", "question", "edit", "web_search"],
            key="history_type_filter",
        )

        filtered = [
            h for h in reversed(history)
            if (filter_project == "Все" or h.get("project") == filter_project)
            and (filter_type == "Все" or h.get("type") == filter_type)
        ]

        st.caption(f"Записей: {len(filtered)}")

        for entry in filtered[:50]:
            ts = entry.get("timestamp", "")
            etype = entry.get("type", "?")
            proj = entry.get("project", "")
            query = entry.get("query", "")

            icon = {"question": "💬", "edit": "✏️", "web_search": "🌐"}.get(etype, "•")
            label = f"{icon} [{ts[:16]}] **{proj}** — {query[:80]}"

            with st.expander(label):
                st.caption(f"Тип: {etype} | Проект: {proj} | Время: {ts}")
                if etype in ("question", "web_search") and entry.get("answer"):
                    st.write(entry["answer"])
                if etype == "edit":
                    if entry.get("success"):
                        st.success(f"Применено: {', '.join(entry.get('applied_files', []))}")
                    else:
                        st.error("Не применено")
                    if entry.get("errors"):
                        st.code(entry["errors"])
                    if entry.get("test_output"):
                        st.code(entry["test_output"])
