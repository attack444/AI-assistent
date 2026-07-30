# AI Helper — VS Code Extension

Локальный AI-ассистент прямо в VS Code. Работает с твоим Ollama и Groq.

## Установка

### Вариант 1: Из VSIX (рекомендуется)

```cmd
cd vscode-extension
npm install
npm run package
```

Затем в VS Code: `Ctrl+Shift+P` → "Extensions: Install from VSIX" → выбери `ai-helper-1.0.0.vsix`

### Вариант 2: Символическая ссылка (для разработки)

Скопируй папку `vscode-extension` в:
- Windows: `%USERPROFILE%\.vscode\extensions\ai-helper-1.0.0`
- Перезапусти VS Code

## Требования

- AI Helper должен быть запущен (`START.bat`)
- API сервер работает на `http://localhost:8502`

## Горячие клавиши

| Клавиша | Действие |
|---|---|
| `Ctrl+Alt+A` | Спросить про файл/выделение |
| `Ctrl+Alt+F` | Найти и исправить баги |
| `Ctrl+Alt+C` | Smart Git Commit |

## Команды (Ctrl+Shift+P → "AI Helper:")

- **Спросить про файл** — задать вопрос с контекстом открытого файла
- **Найти и исправить баги** — анализ кода
- **Объяснить код** — подробное объяснение
- **Написать тесты** — pytest/jest тесты
- **Smart Git Commit** — AI генерирует commit message
- **Smart Git Commit + Push** — коммит + пуш

## Контекстное меню

Правый клик в редакторе → раздел "AI Helper"

## SCM (Source Control)

В панели Git появляются кнопки "AI Helper: Smart Commit" и "+ Push"

## Настройки

```json
{
  "aiHelper.apiUrl": "http://localhost:8502",
  "aiHelper.chatUrl": "http://localhost:8501"
}
```
