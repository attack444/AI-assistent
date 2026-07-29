"""
self_update.py — система самообновления AI Helper.

Возможности:
  1. Обновление моделей Ollama (ollama pull)
  2. Безопасная самомодификация кода (backup → write → validate → rollback)
  3. Поиск новых моделей / улучшений через web_search
  4. Лог всех изменений в ~/.ai-helper/improvement_log.jsonl
"""
from __future__ import annotations

import importlib
import json
import os
import shutil
import subprocess
import sys
import urllib.request
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------
SELF_DIR   = Path(__file__).resolve().parent
DATA_DIR   = Path.home() / ".ai-helper"
BACKUP_DIR = DATA_DIR / "backups" / "source"
IMPROV_LOG = DATA_DIR / "improvement_log.jsonl"

SOURCE_FILES = [
    "app.py", "core.py", "agent.py", "tools.py",
    "memory.py", "profile.py", "launcher.py",
    "self_update.py", "ollama_paths.py",
]


# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

def _log_event(event: str, details: Dict[str, Any]) -> None:
    IMPROV_LOG.parent.mkdir(parents=True, exist_ok=True)
    entry = {"ts": datetime.now().isoformat(), "event": event, **details}
    with IMPROV_LOG.open("a", encoding="utf-8") as f:
        f.write(json.dumps(entry, ensure_ascii=False) + "\n")


def get_improvement_log(limit: int = 30) -> List[Dict[str, Any]]:
    if not IMPROV_LOG.exists():
        return []
    lines = IMPROV_LOG.read_text(encoding="utf-8").strip().splitlines()
    result = []
    for ln in reversed(lines[-limit * 2:]):
        try:
            result.append(json.loads(ln))
        except Exception:
            pass
        if len(result) >= limit:
            break
    return result


# ---------------------------------------------------------------------------
# Backup & rollback
# ---------------------------------------------------------------------------

def backup_all_sources(label: str = "") -> Path:
    """
    Копирует все исходные файлы в ~/.ai-helper/backups/source/<timestamp>/.
    Возвращает путь к папке бэкапа.
    """
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    tag = f"_{label}" if label else ""
    backup_path = BACKUP_DIR / f"{ts}{tag}"
    backup_path.mkdir(parents=True, exist_ok=True)

    copied = []
    for name in SOURCE_FILES:
        src = SELF_DIR / name
        if src.exists():
            shutil.copy2(src, backup_path / name)
            copied.append(name)

    _log_event("backup_created", {
        "path": str(backup_path),
        "files": copied,
        "label": label,
    })
    return backup_path


def rollback_sources(backup_dir: Path) -> Tuple[bool, List[str]]:
    """
    Восстанавливает исходники из бэкапа.
    Возвращает (ok, список восстановленных файлов).
    """
    if not backup_dir.exists():
        return False, [f"Бэкап не найден: {backup_dir}"]
    restored = []
    errors = []
    for f in backup_dir.iterdir():
        if f.suffix == ".py":
            dst = SELF_DIR / f.name
            try:
                shutil.copy2(f, dst)
                restored.append(f.name)
            except Exception as exc:
                errors.append(f"{f.name}: {exc}")
    ok = len(errors) == 0
    _log_event("rollback", {
        "backup": str(backup_dir),
        "restored": restored,
        "errors": errors,
        "ok": ok,
    })
    return ok, restored if ok else errors


def list_backups(limit: int = 10) -> List[Dict[str, Any]]:
    """Список последних бэкапов."""
    if not BACKUP_DIR.exists():
        return []
    dirs = sorted(BACKUP_DIR.iterdir(), reverse=True)
    result = []
    for d in dirs[:limit]:
        if d.is_dir():
            files = [f.name for f in d.iterdir() if f.suffix == ".py"]
            result.append({
                "path": str(d),
                "name": d.name,
                "files": files,
                "count": len(files),
            })
    return result


# ---------------------------------------------------------------------------
# Code validation & safe apply
# ---------------------------------------------------------------------------

def validate_python(path: Path) -> Tuple[bool, str]:
    """Компилирует файл Python. Возвращает (ok, сообщение об ошибке)."""
    try:
        result = subprocess.run(
            [sys.executable, "-m", "py_compile", str(path)],
            capture_output=True, text=True, timeout=15,
        )
        if result.returncode == 0:
            return True, ""
        return False, (result.stderr or result.stdout or "Ошибка компиляции").strip()
    except Exception as exc:
        return False, str(exc)


def validate_imports(path: Path) -> Tuple[bool, str]:
    """Пробует импортировать модуль. Более строгая проверка чем py_compile."""
    try:
        spec = importlib.util.spec_from_file_location("_test_import_", str(path))
        if spec is None:
            return False, "Не удалось создать spec"
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)  # type: ignore[union-attr]
        return True, ""
    except Exception as exc:
        return False, str(exc)


def safe_apply_patch(
    path: Path,
    new_content: str,
    reason: str = "",
    backup_dir: Optional[Path] = None,
) -> Dict[str, Any]:
    """
    Безопасно применяет изменения к Python-файлу:
    1. Создаёт бэкап
    2. Записывает новый контент
    3. Проверяет компиляцию
    4. При ошибке — откатывает автоматически
    """
    import difflib

    if not path.exists():
        return {"ok": False, "error": f"Файл не найден: {path}"}

    old_content = path.read_text(encoding="utf-8", errors="ignore")

    # Diff
    diff_lines = list(difflib.unified_diff(
        old_content.splitlines(keepends=True),
        new_content.splitlines(keepends=True),
        fromfile=f"{path.name} (было)",
        tofile=f"{path.name} (стало)",
        n=3,
    ))
    diff_str = "".join(diff_lines[:300])
    added   = sum(1 for l in diff_lines if l.startswith("+") and not l.startswith("+++"))
    removed = sum(1 for l in diff_lines if l.startswith("-") and not l.startswith("---"))

    if not diff_str:
        return {"ok": True, "changed": False, "message": "Файл не изменился", "diff": ""}

    # Backup
    if backup_dir is None:
        backup_dir = backup_all_sources(label=path.stem)

    # Write
    path.write_text(new_content, encoding="utf-8")

    # Validate
    ok_compile, err_compile = validate_python(path)
    if not ok_compile:
        # Rollback immediately
        path.write_text(old_content, encoding="utf-8")
        _log_event("patch_rejected", {
            "file": path.name,
            "reason": reason,
            "error": err_compile,
            "rolled_back": True,
        })
        return {
            "ok": False,
            "error": f"Ошибка компиляции — откат: {err_compile}",
            "rolled_back": True,
            "diff": diff_str,
        }

    _log_event("patch_applied", {
        "file": path.name,
        "reason": reason,
        "added": added,
        "removed": removed,
        "backup": str(backup_dir),
    })

    return {
        "ok": True,
        "changed": True,
        "file": str(path),
        "added": added,
        "removed": removed,
        "diff": diff_str,
        "backup": str(backup_dir),
        "message": f"Применено: +{added} -{removed} строк",
    }


# ---------------------------------------------------------------------------
# Ollama model updates
# ---------------------------------------------------------------------------

def get_local_model_digest(model: str, host: str = "http://localhost:11434") -> Optional[str]:
    """Получить digest локально установленной модели."""
    try:
        url = f"{host.rstrip('/')}/api/show"
        body = json.dumps({"name": model}).encode()
        req = urllib.request.Request(url, data=body, method="POST",
                                     headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=10) as r:
            data = json.loads(r.read())
        return data.get("details", {}).get("parameter_size") or data.get("digest", "")[:12]
    except Exception:
        return None


def pull_model_update(model: str) -> Dict[str, Any]:
    """
    Запускает ollama pull для модели.
    Ollama сам проверяет — если уже последняя версия, говорит «up to date».
    """
    try:
        result = subprocess.run(
            ["ollama", "pull", model],
            capture_output=True, text=True, timeout=600,
        )
        output = (result.stdout or result.stderr or "").strip()
        up_to_date = "up to date" in output.lower() or "already" in output.lower()
        updated = result.returncode == 0 and not up_to_date
        _log_event("model_pull", {
            "model": model,
            "ok": result.returncode == 0,
            "updated": updated,
            "up_to_date": up_to_date,
        })
        return {
            "ok": result.returncode == 0,
            "model": model,
            "updated": updated,
            "up_to_date": up_to_date,
            "output": output[:1000],
        }
    except FileNotFoundError:
        return {"ok": False, "error": "ollama CLI не найден"}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "Timeout (10 мин)"}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def check_for_better_models(current_model: str) -> Dict[str, Any]:
    """
    Ищет в интернете информацию о новых моделях для программирования.
    Возвращает список рекомендаций.
    """
    try:
        from tools import web_search
        base = current_model.split(":")[0]
        results = web_search(
            f"best ollama coding models 2026 better than {base} local",
            max_results=5,
        )
        return {
            "ok": True,
            "current": current_model,
            "search_results": results.get("output", ""),
            "note": "Используй эти данные чтобы решить стоит ли переходить на другую модель",
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


# ---------------------------------------------------------------------------
# Self-improvement orchestration
# ---------------------------------------------------------------------------

IMPROVEMENT_ASPECTS = {
    "performance": "Проанализируй код на предмет медленных операций, лишних вызовов, неэффективного кода",
    "reliability": "Найди возможные ошибки, необработанные исключения, race conditions",
    "features": "Предложи новые полезные функции которых не хватает для кодинг-ассистента",
    "ui": "Найди проблемы в пользовательском интерфейсе Streamlit и улучшения",
    "tools": "Проанализируй инструменты агента — что добавить, что улучшить",
    "windows": "Найди возможности лучше интегрироваться с Windows",
}


def build_self_improvement_prompt(aspect: str, file_contents: Dict[str, str]) -> str:
    """Формирует промпт для анализа собственного кода."""
    desc = IMPROVEMENT_ASPECTS.get(aspect, aspect)
    files_summary = "\n\n".join(
        f"=== {name} ===\n{content[:3000]}{'...[обрезано]' if len(content) > 3000 else ''}"
        for name, content in file_contents.items()
    )

    return f"""Ты анализируешь собственный код AI Helper для самоулучшения.

Аспект анализа: {desc}

Исходный код:
{files_summary}

Задача:
1. Найди КОНКРЕТНУЮ проблему или улучшение в коде выше
2. Предложи точное изменение (какой файл, какие строки, что именно заменить)
3. Напиши готовый код для применения
4. Объясни почему это улучшит работу

Формат ответа:
- ФАЙЛ: <имя файла>
- ПРОБЛЕМА: <описание>
- РЕШЕНИЕ: <краткое описание>
- КОД: ```python
<новый/изменённый код>
```
- ОБОСНОВАНИЕ: <почему это улучшение>

Будь конкретным. Только одно улучшение за раз."""


def run_self_check() -> Dict[str, Any]:
    """
    Быстрая проверка собственного кода:
    - Компиляция всех файлов
    - Проверка зависимостей
    - Последние ошибки из лога
    """
    results: Dict[str, Any] = {
        "compile_errors": [],
        "compile_ok": [],
        "log_errors": [],
        "deps_ok": True,
    }

    for name in SOURCE_FILES:
        p = SELF_DIR / name
        if p.exists():
            ok, err = validate_python(p)
            if ok:
                results["compile_ok"].append(name)
            else:
                results["compile_errors"].append({"file": name, "error": err})

    # Last errors from agent log
    agent_log = DATA_DIR / "agent.log"
    if agent_log.exists():
        lines = agent_log.read_text(encoding="utf-8").strip().splitlines()[-50:]
        for ln in lines:
            try:
                e = json.loads(ln)
                if not e.get("ok"):
                    results["log_errors"].append({
                        "ts": e.get("ts", "")[:16],
                        "tool": e.get("tool", ""),
                        "args": str(e.get("args", ""))[:80],
                    })
            except Exception:
                pass

    results["ok"] = len(results["compile_errors"]) == 0
    results["summary"] = (
        f"Компиляция: {len(results['compile_ok'])} OK, {len(results['compile_errors'])} ошибок. "
        f"Ошибок агента за последнее время: {len(results['log_errors'])}"
    )
    return results


def full_update_cycle(llm_model: str, ollama_host: str) -> Dict[str, Any]:
    """
    Полный цикл проверки обновлений:
    1. ollama pull (обновить модель если есть новая версия)
    2. Проверка компиляции всех файлов
    3. Проверка устаревших pip-зависимостей
    Возвращает отчёт.
    """
    report: Dict[str, Any] = {
        "ts": datetime.now().isoformat(),
        "model_update": None,
        "self_check": None,
        "deps": None,
    }

    # 1. Model update
    report["model_update"] = pull_model_update(llm_model)

    # 2. Self-check
    report["self_check"] = run_self_check()

    # 3. Pip deps (non-blocking, best-effort)
    try:
        result = subprocess.run(
            ["pip", "list", "--outdated", "--format=json"],
            capture_output=True, text=True, timeout=30,
        )
        if result.returncode == 0:
            outdated = json.loads(result.stdout or "[]")
            report["deps"] = {
                "ok": True,
                "outdated": [
                    f"{p['name']} {p['version']} → {p['latest_version']}"
                    for p in outdated[:15]
                ],
                "count": len(outdated),
            }
    except Exception as exc:
        report["deps"] = {"ok": False, "error": str(exc)}

    _log_event("full_update_cycle", {
        "model": llm_model,
        "model_updated": report["model_update"].get("updated", False),
        "compile_errors": len(report["self_check"].get("compile_errors", [])),
    })
    return report
