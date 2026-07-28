# core.py
from __future__ import annotations

import hashlib
import json
import os
import subprocess
import tempfile
import time
from dataclasses import asdict, dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple, Type, TypeVar

from pydantic import BaseModel, Field

from llama_index.core import (
    Settings,
    SimpleDirectoryReader,
    StorageContext,
    VectorStoreIndex,
    load_index_from_storage,
)
from llama_index.core.node_parser import SentenceSplitter
from llama_index.embeddings.ollama import OllamaEmbedding
from llama_index.llms.ollama import Ollama

from ddgs import DDGS


APP_DIR = Path.home() / ".ai-helper"
PROJECTS_FILE = APP_DIR / "projects.json"
INDICES_DIR = APP_DIR / "indices"
BACKUPS_DIR = APP_DIR / "backups"
HISTORY_FILE = APP_DIR / "history.json"

INDEX_EXTENSIONS = {
    ".py", ".pyi", ".js", ".jsx", ".ts", ".tsx",
    ".java", ".kt", ".kts", ".go", ".rs", ".c", ".h", ".cpp", ".hpp",
    ".cs", ".php", ".rb", ".swift", ".m", ".mm",
    ".html", ".htm", ".css", ".scss", ".sass", ".less",
    ".json", ".jsonl", ".yaml", ".yml", ".toml", ".ini", ".cfg", ".env",
    ".md", ".txt", ".rst", ".sql", ".sh", ".bash", ".zsh", ".ps1",
    ".xml", ".gradle", ".dockerfile", ".gitignore", ".editorconfig",
    ".lua", ".r", ".jl", ".ex", ".exs", ".erl", ".hrl", ".clj", ".scala",
    ".dart", ".zig", ".nim", ".v", ".pl", ".pm",
}
IGNORE_DIRS = {
    ".git", ".venv", "venv", "env", "__pycache__", ".pytest_cache",
    "node_modules", "dist", "build", "out", ".next", ".turbo",
    ".idea", ".vscode", ".ai-helper",
}

MAX_FILE_SIZE_BYTES = 500_000
MAX_EDIT_FILE_SIZE_BYTES = 120_000
MAX_FILES_IN_PROMPT = 8
MAX_CONTENT_CHARS_PER_FILE = 20_000
MAX_BACKUP_KEEP_DAYS = 14
MAX_HISTORY_ENTRIES = 500

T = TypeVar("T", bound=BaseModel)


@dataclass
class ProjectConfig:
    name: str
    root: str


@dataclass
class EditResult:
    success: bool
    applied_files: List[str]
    test_output: str
    errors: str


class PatchItem(BaseModel):
    path: str
    reason: str
    patch: str


class EditPlan(BaseModel):
    summary: str
    patches: List[PatchItem] = Field(default_factory=list)
    warnings: List[str] = Field(default_factory=list)


# ---------------------------------------------------------------------------
# Storage helpers
# ---------------------------------------------------------------------------

def ensure_dirs() -> None:
    APP_DIR.mkdir(parents=True, exist_ok=True)
    INDICES_DIR.mkdir(parents=True, exist_ok=True)
    BACKUPS_DIR.mkdir(parents=True, exist_ok=True)
    if not PROJECTS_FILE.exists():
        PROJECTS_FILE.write_text("{}", encoding="utf-8")


def load_projects() -> Dict[str, ProjectConfig]:
    ensure_dirs()
    raw = json.loads(PROJECTS_FILE.read_text(encoding="utf-8"))
    return {name: ProjectConfig(name=name, root=data["root"]) for name, data in raw.items()}


def save_projects(projects: Dict[str, ProjectConfig]) -> None:
    ensure_dirs()
    raw = {name: asdict(cfg) for name, cfg in projects.items()}
    PROJECTS_FILE.write_text(json.dumps(raw, indent=2, ensure_ascii=False), encoding="utf-8")


def register_project(name: str, project_root: Path) -> None:
    projects = load_projects()
    projects[name] = ProjectConfig(name=name, root=str(project_root.resolve()))
    save_projects(projects)


def delete_project(name: str) -> None:
    projects = load_projects()
    if name in projects:
        del projects[name]
        save_projects(projects)


def print_projects() -> None:
    projects = load_projects()
    if not projects:
        print("Проекты не добавлены.")
        return
    for name, cfg in projects.items():
        print(f"- {name}: {cfg.root}")


# ---------------------------------------------------------------------------
# Path / ID helpers
# ---------------------------------------------------------------------------

def normalize_path(path_str: str) -> Path:
    return Path(path_str).expanduser().resolve()


def project_id(project_root: Path) -> str:
    return hashlib.sha256(str(project_root).encode("utf-8")).hexdigest()[:16]


def project_storage_dir(project_root: Path) -> Path:
    return INDICES_DIR / project_id(project_root)


def project_manifest_path(project_root: Path) -> Path:
    return project_storage_dir(project_root) / "manifest.json"


def is_within_root(root: Path, path: Path) -> bool:
    try:
        path.resolve().relative_to(root.resolve())
        return True
    except Exception:
        return False


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def is_indexable_file(path: Path) -> bool:
    if not path.is_file():
        return False
    if path.name.startswith(".") and path.suffix not in {".gitignore", ".env"}:
        return False
    if path.stat().st_size > MAX_FILE_SIZE_BYTES:
        return False
    suffix = path.suffix.lower()
    return suffix in INDEX_EXTENSIONS or path.name.lower() in {
        "dockerfile", "makefile", "license", "readme",
    }


def iter_project_files(root: Path) -> List[Path]:
    files: List[Path] = []
    for current_root, dirnames, filenames in os.walk(root):
        current = Path(current_root)
        dirnames[:] = [d for d in dirnames if d not in IGNORE_DIRS and not d.startswith(".")]
        for filename in filenames:
            path = current / filename
            if is_indexable_file(path):
                files.append(path)
    return sorted(files)


def file_metadata_factory(project_root: Path):
    def _metadata(file_path: str) -> Dict[str, Any]:
        p = Path(file_path).resolve()
        try:
            rel = str(p.relative_to(project_root))
        except ValueError:
            rel = str(p)
        return {
            "path": rel,
            "abs_path": str(p),
            "project_root": str(project_root),
            "language_hint": p.suffix.lower().lstrip("."),
        }
    return _metadata


# ---------------------------------------------------------------------------
# Manifest
# ---------------------------------------------------------------------------

def load_manifest(project_root: Path) -> Dict[str, Any]:
    p = project_manifest_path(project_root)
    if not p.exists():
        return {}
    return json.loads(p.read_text(encoding="utf-8"))


def save_manifest(project_root: Path, manifest: Dict[str, Any]) -> None:
    storage = project_storage_dir(project_root)
    storage.mkdir(parents=True, exist_ok=True)
    project_manifest_path(project_root).write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )


def build_manifest(project_root: Path, files: List[Path]) -> Dict[str, Any]:
    entries = []
    for f in files:
        stat = f.stat()
        entries.append(
            {
                "path": str(f.relative_to(project_root)),
                "sha256": sha256_file(f),
                "size": stat.st_size,
                "mtime": int(stat.st_mtime),
            }
        )
    return {"project_root": str(project_root), "file_count": len(entries), "files": entries}


def manifest_signature(manifest: Dict[str, Any]) -> str:
    payload = json.dumps(manifest, sort_keys=True, ensure_ascii=False).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()


def get_index_info(project_root: Path) -> Optional[Dict[str, Any]]:
    """Return index stats: file count and last build timestamp."""
    manifest = load_manifest(project_root)
    if not manifest:
        return None
    storage_dir = project_storage_dir(project_root)
    last_built: Optional[str] = None
    if storage_dir.exists():
        ts = storage_dir.stat().st_mtime
        last_built = datetime.fromtimestamp(ts).strftime("%Y-%m-%d %H:%M:%S")
    return {
        "file_count": manifest.get("file_count", 0),
        "last_built": last_built,
    }


# ---------------------------------------------------------------------------
# Pydantic helpers
# ---------------------------------------------------------------------------

def model_schema(model_cls: Type[T]) -> Dict[str, Any]:
    if hasattr(model_cls, "model_json_schema"):
        return model_cls.model_json_schema()
    return model_cls.schema()


def parse_model_json(model_cls: Type[T], raw: str) -> T:
    if hasattr(model_cls, "model_validate_json"):
        return model_cls.model_validate_json(raw)
    return model_cls.parse_raw(raw)


# ---------------------------------------------------------------------------
# LLM / embedding setup
# ---------------------------------------------------------------------------

def load_llm(
    model: str,
    base_url: str,
    request_timeout: float = 300.0,
    context_window: Optional[int] = None,
) -> Ollama:
    kwargs: Dict[str, Any] = {
        "model": model,
        "base_url": base_url,
        "request_timeout": request_timeout,
    }
    if context_window is not None:
        kwargs["context_window"] = context_window
    return Ollama(**kwargs)


def load_embed_model(model: str, base_url: str) -> OllamaEmbedding:
    return OllamaEmbedding(model_name=model, base_url=base_url)


def configure_settings(
    llm_model: str,
    embed_model: str,
    ollama_host: str,
    context_window: Optional[int] = None,
) -> None:
    Settings.llm = load_llm(
        model=llm_model,
        base_url=ollama_host,
        context_window=context_window,
    )
    Settings.embed_model = load_embed_model(model=embed_model, base_url=ollama_host)


# ---------------------------------------------------------------------------
# Index build / load
# ---------------------------------------------------------------------------

def build_index(
    project_root: Path,
    llm_model: str,
    embed_model: str,
    ollama_host: str,
    chunk_size: int,
    chunk_overlap: int,
    force: bool = False,
    context_window: Optional[int] = None,
) -> Path:
    project_root = project_root.resolve()
    files = iter_project_files(project_root)

    if not files:
        raise RuntimeError(f"В проекте не найдено индексируемых файлов: {project_root}")

    storage_dir = project_storage_dir(project_root)
    storage_dir.mkdir(parents=True, exist_ok=True)

    new_manifest = build_manifest(project_root, files)
    old_manifest = load_manifest(project_root)

    if not force and old_manifest and manifest_signature(old_manifest) == manifest_signature(new_manifest):
        return storage_dir

    configure_settings(
        llm_model=llm_model,
        embed_model=embed_model,
        ollama_host=ollama_host,
        context_window=context_window,
    )

    reader = SimpleDirectoryReader(
        input_files=[str(p) for p in files],
        filename_as_id=True,
        file_metadata=file_metadata_factory(project_root),
        exclude_hidden=True,
        exclude_empty=True,
    )
    documents = reader.load_data()

    storage_context = StorageContext.from_defaults(persist_dir=str(storage_dir))

    index = VectorStoreIndex.from_documents(
        documents,
        storage_context=storage_context,
        transformations=[SentenceSplitter(chunk_size=chunk_size, chunk_overlap=chunk_overlap)],
        show_progress=True,
    )

    index.storage_context.persist(persist_dir=str(storage_dir))
    save_manifest(project_root, new_manifest)
    return storage_dir


def load_index(project_root: Path) -> VectorStoreIndex:
    project_root = project_root.resolve()
    storage_dir = project_storage_dir(project_root)

    if not storage_dir.exists():
        raise FileNotFoundError(
            f"Индекс не найден для проекта {project_root}. Сначала запусти построение индекса."
        )

    storage_context = StorageContext.from_defaults(persist_dir=str(storage_dir))
    return load_index_from_storage(storage_context)


def load_configured_index(
    project_root: Path,
    llm_model: str,
    embed_model: str,
    ollama_host: str,
    context_window: Optional[int] = None,
) -> VectorStoreIndex:
    """Configure LLM/embed settings, then load the persisted index."""
    configure_settings(llm_model, embed_model, ollama_host, context_window)
    return load_index(project_root)


# ---------------------------------------------------------------------------
# Retrieval helpers
# ---------------------------------------------------------------------------

def read_text_file(path: Path, limit: int = MAX_CONTENT_CHARS_PER_FILE) -> str:
    text = path.read_text(encoding="utf-8", errors="ignore")
    if len(text) <= limit:
        return text
    head = text[: limit // 2]
    tail = text[-limit // 2 :]
    return head + "\n\n... [TRUNCATED] ...\n\n" + tail


def get_relevant_files(index: VectorStoreIndex, query: str, top_k: int) -> List[str]:
    retriever = index.as_retriever(similarity_top_k=top_k)
    results = retriever.retrieve(query)

    seen = set()
    paths: List[str] = []
    for item in results:
        meta = getattr(item.node, "metadata", {}) or {}
        rel = meta.get("path") or meta.get("file_path") or meta.get("abs_path")
        if not rel or rel in seen:
            continue
        seen.add(rel)
        paths.append(rel)
    return paths


def choose_candidates(project_root: Path, index: VectorStoreIndex, query: str, top_k: int) -> List[Path]:
    rel_paths = get_relevant_files(index, query, top_k=top_k)
    candidates: List[Path] = []
    for rel in rel_paths:
        p = (project_root / rel).resolve()
        if p.exists() and is_within_root(project_root, p):
            candidates.append(p)

    seen = set()
    unique: List[Path] = []
    for p in candidates:
        s = str(p)
        if s not in seen:
            seen.add(s)
            unique.append(p)
    return unique[:MAX_FILES_IN_PROMPT]


# ---------------------------------------------------------------------------
# Q&A
# ---------------------------------------------------------------------------

def query_project(
    project_root: Path,
    query: str,
    top_k: int,
    llm_model: str = "llama3.1:8b",
    embed_model: str = "nomic-embed-text",
    ollama_host: str = "http://localhost:11434",
    context_window: Optional[int] = None,
) -> Tuple[str, List[Dict[str, str]]]:
    configure_settings(llm_model, embed_model, ollama_host, context_window)
    index = load_index(project_root)
    query_engine = index.as_query_engine(similarity_top_k=top_k)
    response = query_engine.query(query)

    sources: List[Dict[str, str]] = []
    source_nodes = getattr(response, "source_nodes", None) or []
    for node in source_nodes[:8]:
        meta = getattr(node.node, "metadata", {}) or {}
        snippet = (node.node.get_text() or "").strip().replace("\n", " ")
        sources.append(
            {
                "path": meta.get("path", "unknown"),
                "snippet": snippet[:300],
            }
        )

    return str(response), sources


# ---------------------------------------------------------------------------
# Patch generation & application
# ---------------------------------------------------------------------------

def build_patch_prompt(project_root: Path, query: str, candidate_files: List[Path]) -> str:
    blocks: List[str] = []
    for p in candidate_files:
        rel = str(p.relative_to(project_root))
        size = p.stat().st_size
        if size > MAX_EDIT_FILE_SIZE_BYTES:
            snippet = read_text_file(p, limit=8_000)
            note = "This file is large. Use it only for context."
        else:
            snippet = read_text_file(p, limit=MAX_CONTENT_CHARS_PER_FILE)
            note = "This file may be patched."
        blocks.append(
            f"""### FILE: {rel}
{note}
```text
{snippet}
```"""
        )

    schema_text = json.dumps(model_schema(EditPlan), ensure_ascii=False, indent=2)

    return f"""
Ты — локальный AI-ассистент программиста.

Верни ТОЛЬКО JSON, который соответствует схеме. Без markdown-обёртки.

Правила:
1) Меняй только файлы из списка ниже.
2) Для каждого файла в patches верни unified diff patch (git apply compatible).
3) При создании нового файла в patch используй заголовок: --- /dev/null и +++ b/<path>.
4) Если изменений не нужно, верни пустой массив patches.
5) Если не хватает контекста — не выдумывай.
6) Не удаляй существующие файлы.
7) Не добавляй markdown вокруг JSON.

Запрос пользователя:
{query}

Корень проекта:
{project_root}

Файлы-кандидаты:
{os.linesep.join(blocks)}

JSON schema:
{schema_text}
""".strip()


def ask_llm_for_patch_plan(
    llm_model: str,
    ollama_host: str,
    project_root: Path,
    query: str,
    candidate_files: List[Path],
) -> EditPlan:
    from ollama import Client

    client = Client(host=ollama_host)
    prompt = build_patch_prompt(project_root, query, candidate_files)

    response = client.chat(
        model=llm_model,
        messages=[
            {"role": "system", "content": "Return only valid JSON. Do not wrap in markdown."},
            {"role": "user", "content": prompt},
        ],
        format=model_schema(EditPlan),
        options={"temperature": 0},
    )
    return parse_model_json(EditPlan, response.message.content)


def safe_backup_file(project_root: Path, file_path: Path) -> Path:
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    rel = file_path.relative_to(project_root)
    backup_dir = BACKUPS_DIR / project_id(project_root) / timestamp / rel.parent
    backup_dir.mkdir(parents=True, exist_ok=True)
    backup_path = backup_dir / file_path.name
    backup_path.write_text(file_path.read_text(encoding="utf-8", errors="ignore"), encoding="utf-8")
    return backup_path


def atomic_write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp_name = tempfile.mkstemp(prefix=f".{path.name}.", dir=str(path.parent))
    tmp_path = Path(tmp_name)
    try:
        with os.fdopen(fd, "w", encoding="utf-8", newline="\n") as f:
            f.write(content)
        tmp_path.replace(path)
    finally:
        if tmp_path.exists():
            try:
                tmp_path.unlink()
            except Exception:
                pass


def restore_files(originals: Dict[Path, str]) -> None:
    for file_path, content in originals.items():
        atomic_write_text(file_path, content)


def compile_python_files(files: List[Path]) -> Tuple[bool, str]:
    import py_compile

    errors: List[str] = []
    for f in files:
        if f.suffix != ".py":
            continue
        try:
            py_compile.compile(str(f), doraise=True)
        except Exception as exc:
            errors.append(f"{f}: {exc}")
    return (len(errors) == 0, "\n".join(errors))


def run_tests(project_root: Path, tests_cmd: Optional[str] = None) -> Tuple[int, str]:
    cmd = tests_cmd.split() if tests_cmd else ["pytest", "-q"]
    try:
        proc = subprocess.run(
            cmd,
            cwd=str(project_root),
            capture_output=True,
            text=True,
            timeout=1200,
        )
        out = (proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")
        return proc.returncode, out.strip()
    except FileNotFoundError:
        return 127, "Тестовая команда не найдена."
    except subprocess.TimeoutExpired:
        return 124, "Тесты превысили лимит времени."
    except Exception as exc:
        return 1, str(exc)


def shutil_which(cmd: str) -> Optional[str]:
    import shutil
    return shutil.which(cmd)


def apply_git_patch(project_root: Path, patch_text: str) -> Tuple[bool, str]:
    if not shutil_which("git"):
        return False, "git не найден в системе."

    with tempfile.NamedTemporaryFile("w", delete=False, suffix=".patch", encoding="utf-8") as tf:
        tf.write(patch_text)
        patch_path = tf.name

    try:
        check = subprocess.run(
            ["git", "apply", "--check", "--whitespace=nowarn", patch_path],
            cwd=str(project_root),
            capture_output=True,
            text=True,
        )
        if check.returncode != 0:
            return False, check.stdout + "\n" + check.stderr

        apply = subprocess.run(
            ["git", "apply", "--whitespace=nowarn", patch_path],
            cwd=str(project_root),
            capture_output=True,
            text=True,
        )
        if apply.returncode != 0:
            return False, apply.stdout + "\n" + apply.stderr

        return True, "patch applied"
    finally:
        try:
            os.unlink(patch_path)
        except Exception:
            pass


def apply_edit_plan(
    project_root: Path,
    plan: EditPlan,
    run_tests_after: bool = True,
    tests_cmd: Optional[str] = None,
) -> EditResult:
    originals: Dict[Path, str] = {}
    new_files: List[Path] = []
    changed_python_files: List[Path] = []
    applied_files: List[str] = []
    test_output = ""

    patch_text = "\n\n".join(item.patch.strip() for item in plan.patches if item.patch.strip())

    try:
        for item in plan.patches:
            target = (project_root / item.path).resolve()
            if not is_within_root(project_root, target):
                raise RuntimeError(f"Неверный путь вне проекта: {item.path}")
            if target.exists():
                originals[target] = target.read_text(encoding="utf-8", errors="ignore")
                safe_backup_file(project_root, target)
            else:
                new_files.append(target)
            if target.suffix == ".py":
                changed_python_files.append(target)
            applied_files.append(item.path)

        ok, msg = apply_git_patch(project_root, patch_text)
        if not ok:
            raise RuntimeError(f"Не удалось применить patch:\n{msg}")

        ok, errors = compile_python_files([p for p in changed_python_files if p.exists()])
        if not ok:
            raise RuntimeError(f"Проверка Python-синтаксиса не прошла:\n{errors}")

        if run_tests_after:
            code, test_output = run_tests(project_root, tests_cmd=tests_cmd)
            if code != 0:
                raise RuntimeError(f"Тесты упали (code={code}).\n{test_output}")

        return EditResult(
            success=True,
            applied_files=applied_files,
            test_output=test_output,
            errors="",
        )

    except Exception as exc:
        if originals:
            restore_files(originals)
        for nf in new_files:
            try:
                if nf.exists():
                    nf.unlink()
            except Exception:
                pass
        return EditResult(
            success=False,
            applied_files=[],
            test_output=test_output,
            errors=str(exc),
        )


# ---------------------------------------------------------------------------
# Web search
# ---------------------------------------------------------------------------

def web_search_text(query: str, max_results: int = 5, region: str = "ru-ru") -> List[Dict[str, str]]:
    with DDGS() as ddgs:
        results = ddgs.text(
            query,
            region=region,
            safesearch="moderate",
            max_results=max_results,
        )
    return [_normalize_web_item(r) for r in results]


def web_search_news(
    query: str,
    max_results: int = 5,
    region: str = "ru-ru",
    timelimit: str = "m",
) -> List[Dict[str, str]]:
    with DDGS() as ddgs:
        results = ddgs.news(
            query,
            region=region,
            safesearch="moderate",
            timelimit=timelimit,
            max_results=max_results,
        )
    return [_normalize_web_item(r) for r in results]


def _normalize_web_item(item: Dict[str, Any]) -> Dict[str, str]:
    return {
        "title": str(item.get("title") or "").strip(),
        "url": str(
            item.get("href")
            or item.get("url")
            or item.get("link")
            or item.get("content")
            or ""
        ).strip(),
        "snippet": str(
            item.get("body")
            or item.get("snippet")
            or item.get("description")
            or item.get("content")
            or ""
        ).strip(),
        "date": str(item.get("date") or "").strip(),
        "source": str(item.get("source") or "web").strip(),
    }


def format_web_results(results: List[Dict[str, str]]) -> str:
    if not results:
        return "Нет результатов."

    parts: List[str] = []
    for i, r in enumerate(results, start=1):
        title = r.get("title") or "Без названия"
        url = r.get("url") or ""
        snippet = r.get("snippet") or ""
        date = r.get("date") or ""
        parts.append(f"[{i}] {title}\nURL: {url}\nDATE: {date}\nSNIPPET: {snippet}")
    return "\n\n".join(parts)


def answer_with_web(
    project_root: Path,
    llm_model: str,
    embed_model: str,
    ollama_host: str,
    query: str,
    top_k: int,
    search_kind: str = "text",
    max_web_results: int = 5,
    context_window: Optional[int] = None,
) -> Tuple[str, List[Dict[str, str]], List[Dict[str, str]]]:
    from ollama import Client

    local_sources: List[Dict[str, str]] = []
    local_response_text = ""

    try:
        configure_settings(llm_model, embed_model, ollama_host, context_window)
        index = load_index(project_root)
        query_engine = index.as_query_engine(similarity_top_k=top_k)
        local_response = query_engine.query(query)
        local_response_text = str(local_response)

        source_nodes = getattr(local_response, "source_nodes", None) or []
        for node in source_nodes[:8]:
            meta = getattr(node.node, "metadata", {}) or {}
            snippet = (node.node.get_text() or "").strip().replace("\n", " ")
            local_sources.append(
                {
                    "path": meta.get("path", "unknown"),
                    "snippet": snippet[:300],
                }
            )
    except FileNotFoundError:
        local_response_text = "Индекс не построен. Используются только интернет-результаты."

    if search_kind == "news":
        web_results = web_search_news(query, max_results=max_web_results)
    else:
        web_results = web_search_text(query, max_results=max_web_results)

    client = Client(host=ollama_host)

    prompt = f"""
Ты — AI-ассистент программиста.
Используй локальный контекст проекта и интернет-результаты.

Правила:
1) Если интернет-источники полезны — используй их.
2) Если данных недостаточно — скажи об этом прямо.
3) В ответе отдельно перечисли, что взято из проекта, а что из интернета.
4) Пиши по-русски.
5) Не выдумывай факты.

Вопрос пользователя:
{query}

ЛОКАЛЬНЫЙ КОНТЕКСТ ПРОЕКТА:
{local_response_text}

ИНТЕРНЕТ-РЕЗУЛЬТАТЫ:
{format_web_results(web_results)}
""".strip()

    resp = client.chat(
        model=llm_model,
        messages=[
            {"role": "system", "content": "Answer in Russian. Be precise."},
            {"role": "user", "content": prompt},
        ],
        options={"temperature": 0},
    )

    return resp.message.content, local_sources, web_results


# ---------------------------------------------------------------------------
# History
# ---------------------------------------------------------------------------

def load_history() -> List[Dict[str, Any]]:
    ensure_dirs()
    if not HISTORY_FILE.exists():
        return []
    try:
        return json.loads(HISTORY_FILE.read_text(encoding="utf-8"))
    except Exception:
        return []


def save_history_entry(entry: Dict[str, Any]) -> None:
    ensure_dirs()
    history = load_history()
    history.append({**entry, "timestamp": datetime.now().isoformat()})
    if len(history) > MAX_HISTORY_ENTRIES:
        history = history[-MAX_HISTORY_ENTRIES:]
    HISTORY_FILE.write_text(json.dumps(history, indent=2, ensure_ascii=False), encoding="utf-8")


def clear_history() -> None:
    ensure_dirs()
    HISTORY_FILE.write_text("[]", encoding="utf-8")


# ---------------------------------------------------------------------------
# Backups
# ---------------------------------------------------------------------------

def cleanup_old_backups(project_root: Path) -> None:
    root = BACKUPS_DIR / project_id(project_root)
    if not root.exists():
        return

    cutoff = time.time() - (MAX_BACKUP_KEEP_DAYS * 24 * 60 * 60)
    for p in root.rglob("*"):
        try:
            if p.is_file() and p.stat().st_mtime < cutoff:
                p.unlink()
        except Exception:
            pass


# ---------------------------------------------------------------------------
# CLI helpers
# ---------------------------------------------------------------------------

def resolve_project_path(project_name: Optional[str], project_root: Optional[str]) -> Tuple[str, Path]:
    projects = load_projects()

    if project_root:
        root = normalize_path(project_root)
        return (project_name or root.name), root

    if not project_name:
        raise ValueError("Нужно указать project_name или project_root")

    if project_name not in projects:
        raise KeyError(f"Проект '{project_name}' не найден")

    cfg = projects[project_name]
    return cfg.name, normalize_path(cfg.root)
