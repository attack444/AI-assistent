# AI Helper для VS Code — 1.3

## Обновить расширение БЕЗ `git pull` всего репо

Качается только папка расширения (~несколько файлов). Если версия уже стоит — **ничего не качает**.

### Способ 1 — одна команда (рекомендуется)

В **PowerShell**:

```powershell
irm https://raw.githubusercontent.com/attack444/AI-assistent/cursor/complete-ai-helper-17f9/project/vscode-extension/update-extension.ps1 | iex
```

Потом **полностью закрой и открой VS Code**.

### Способ 2 — bat-файл

Если репо уже есть локально — запусти  
`project\vscode-extension\install-from-github.bat`  
(он всё равно тянет свежие файлы с GitHub, не копирует устаревшую локальную копию).

### Что делает скрипт

1. Скачивает только `package.json` → смотрит версию  
2. Если такая же уже в `%USERPROFILE%\.vscode\extensions\` → выход  
3. Иначе качает ~7 файлов расширения и удаляет старые папки `ai-helper*`  
4. Не трогает остальной проект / сайты / Docker  

---

## Настройка после установки

`Ctrl+Shift+P` → **AI Helper: Настройка VPS**  
→ пароль панели → сайт → авто-синк  

В Settings → AI Helper должны быть: **Password**, **Site**, **Auto Sync On Save**.  
Если только Api Url / Chat Url — VS Code ещё держит старое расширение: закрой редактор и повтори команду выше.

Форматтеры в списке Settings к AI Helper не относятся.

---

## Сервер (панель / API) — отдельно

Расширение ≠ сервер. API на VPS обновляй так:

```bash
curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/cursor/complete-ai-helper-17f9/project/deploy/bootstrap-update.sh | bash
```

`git pull` всего репо на ПК для расширения **не нужен**.
