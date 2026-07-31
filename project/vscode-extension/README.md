# AI Helper для VS Code — 1.3.0

## Почему в Settings только Api Url и Chat Url?

Значит стоит **старое** расширение (1.1). В **1.3** есть:

- Password  
- Site  
- Auto Sync On Save  
- Token, Local Root  
- команда **AI Helper: Настройка VPS**

### Как обновить (Windows)

1. Закрой VS Code полностью.  
2. Запусти `install-copy.bat` из этой папки  
   **или** скопируй папку вручную в  
   `%USERPROFILE%\.vscode\extensions\ai-helper-local.ai-helper-1.3.0`  
3. Открой VS Code.  
4. `Ctrl+Shift+P` → **AI Helper: Настройка VPS**  
   (пароль панели → сайт → авто-синк).

Форматтеры из списка VS Code к AI Helper **не относятся** — их можно не трогать.

## Быстрая проверка

Settings → поиск `AI Helper` → должны быть **Password**, **Site**, **Auto Sync On Save**.  
Если нет — всё ещё старая копия расширения (удали папки `ai-helper*` в `.vscode\extensions` и поставь 1.3 заново).
