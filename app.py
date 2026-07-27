# app.py
from __future__ import annotations

from pathlib import Path

import streamlit as st

from core import (
    EditPlan,
    answer_with_web,
    apply_edit_plan,
    ask_llm_for_patch_plan,
    build_index,
    choose_candidates,
    cleanup_old_backups,
    ensure_dirs,
    load_index,
    load_projects,
    query_project,
    register_project,
)

ensure_dirs()

st.set_page_config(page_title="AI Helper", layout="wide")
st.title("AI Helper для проектов")

with st.sidebar:
    st.header("Проекты")
    projects = load_projects()
    project_names = list(projects.keys())

    new_name = st.text_input("Имя проекта")
    new_root = st.text_input("Путь к проекту")
    if st.button("Добавить проект"):
        if new_name and new_root:
            register_project(new_name, Path(new_root))
            st.success("Проект добавлен")
            st.rerun()

    selected_project = st.selectbox(
        "Выбери проект",
        options=project_names if project_names else ["<нет проектов>"],
        index=0 if project_names else None,
    )

    st.divider()
    st.header("Модель")
    llm_model = st.text_input("LLM", value="llama3.1:8b")
    embed_model = st.text_input("Embeddings", value="nomic-embed-text")
    ollama_host = st.text_input("Ollama host", value="http://localhost:11434")
    context_window = st.number_input("Context window", value=64000, step=1024)
    top_k = st.number_input("Top K", value=5, step=1, min_value=1, max_value=20)
    chunk_size = st.number_input("Chunk size", value=1024, step=128)
    chunk_overlap = st.number_input("Chunk overlap", value=150, step=10)

    st.divider()
    st.header("Интернет")
    use_web = st.checkbox("Использовать интернет в ответах", value=False)
    search_kind = st.selectbox("Тип поиска", ["text", "news"])
    max_web_results = st.number_input("Web results", value=5, min_value=1, max_value=20, step=1)

if selected_project == "<нет проектов>":
    st.info("Сначала добавь проект слева.")
    st.stop()

project_root = Path(projects[selected_project].root)
st.caption(f"Проект: {selected_project} — {project_root}")

tab1, tab2, tab3, tab4 = st.tabs(["Индекс", "Вопросы", "Правки", "Интернет"])

with tab1:
    col1, col2 = st.columns(2)
    with col1:
        if st.button("Построить / обновить индекс"):
            with st.spinner("Строю индекс..."):
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
            st.success("Индекс готов")

    with col2:
        st.write("Индекс хранится отдельно от кода в `~/.ai-helper/indices`.")

with tab2:
    question = st.text_area(
        "Вопрос к коду",
        height=140,
        placeholder="Например: где создаётся JWT и как он валидируется?",
    )
    if st.button("Спросить"):
        if question.strip():
            with st.spinner("Ищу ответ..."):
                if use_web:
                    answer, local_sources, web_results = answer_with_web(
                        project_root=project_root,
                        llm_model=llm_model,
                        ollama_host=ollama_host,
                        query=question,
                        top_k=int(top_k),
                        search_kind=search_kind,
                        max_web_results=int(max_web_results),
                    )
                    st.subheader("Ответ")
                    st.write(answer)

                    st.subheader("Источники проекта")
                    for s in local_sources:
                        st.markdown(f"**{s['path']}**")
                        st.code(s["snippet"])

                    st.subheader("Интернет-результаты")
                    for r in web_results:
                        st.markdown(f"**{r['title']}**")
                        if r["url"]:
                            st.write(r["url"])
                        if r["snippet"]:
                            st.write(r["snippet"])
                else:
                    answer, sources = query_project(project_root, question, int(top_k))
                    st.subheader("Ответ")
                    st.write(answer)

                    st.subheader("Источники")
                    for s in sources:
                        st.markdown(f"**{s['path']}**")
                        st.code(s["snippet"])

with tab3:
    task = st.text_area(
        "Что нужно изменить",
        height=140,
        placeholder="Например: упрости валидацию JWT и добавь тесты",
    )
    apply_changes = st.checkbox("Применить изменения", value=False)
    run_tests_after = st.checkbox("Запускать тесты после правок", value=True)

    if st.button("Сгенерировать patch"):
        if task.strip():
            with st.spinner("Ищу файлы и собираю patch..."):
                index = load_index(project_root)
                candidates = choose_candidates(project_root, index, task, int(top_k))
                st.write("Файлы-кандидаты:")
                for p in candidates:
                    st.code(str(p.relative_to(project_root)))

                plan = ask_llm_for_patch_plan(
                    llm_model=llm_model,
                    project_root=project_root,
                    query=task,
                    candidate_files=candidates,
                )
                st.session_state["plan"] = plan.model_dump() if hasattr(plan, "model_dump") else plan.dict()

            st.subheader("План")
            st.write(plan.summary)
            if plan.warnings:
                st.warning("\n".join(plan.warnings))
            for item in plan.patches:
                st.markdown(f"**{item.path}** — {item.reason}")
                st.code(item.patch, language="diff")

    if st.button("Применить patch"):
        plan_data = st.session_state.get("plan")
        if not plan_data:
            st.error("Сначала сгенерируй patch")
        else:
            plan = EditPlan(**plan_data)
            with st.spinner("Применяю изменения..."):
                apply_edit_plan(
                    project_root=project_root,
                    plan=plan,
                    run_tests_after=run_tests_after,
                )
                cleanup_old_backups(project_root)
            st.success("Изменения применены")

with tab4:
    web_query = st.text_input("Поиск в интернете")
    if st.button("Искать в интернете"):
        if web_query.strip():
            with st.spinner("Ищу..."):
                answer, local_sources, web_results = answer_with_web(
                    project_root=project_root,
                    llm_model=llm_model,
                    ollama_host=ollama_host,
                    query=web_query,
                    top_k=int(top_k),
                    search_kind=search_kind,
                    max_web_results=int(max_web_results),
                )
            st.subheader("Ответ")
            st.write(answer)

            st.subheader("Интернет-результаты")
            for r in web_results:
                st.markdown(f"**{r['title']}**")
                if r["url"]:
                    st.write(r["url"])
                if r["snippet"]:
                    st.write(r["snippet"])

            st.subheader("Локальные источники проекта")
            for s in local_sources:
                st.markdown(f"**{s['path']}**")
                st.code(s["snippet"])