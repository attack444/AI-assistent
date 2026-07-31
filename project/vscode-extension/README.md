# AI Helper — VS Code → сайт на VPS

Правки из VS Code сразу попадают на сайт (без ручных команд и деплоя).

## Как это работает

1. **Чат в сайдбаре** с выбранным сайтом → агент на сервере пишет файлы в `/var/ai-helper/sites/<сайт>/` → nginx отдаёт их сразу.
2. **Авто-синк** (`Ctrl+S`) → `POST /sites/sync` → тот же файл на сайте.
3. Кнопка **☁ На сайт** у блока кода → записать код в файл на сервере.

## Установка

```bash
# скопируй папку vscode-extension в extensions
# Windows: %USERPROFILE%\.vscode\extensions\ai-helper-1.2.0
# или: Extensions → Install from VSIX после npm run package
```

Перезапусти VS Code.

## Настройки (Settings → AI Helper)

| Ключ | Пример | Зачем |
|------|--------|--------|
| `aiHelper.apiUrl` | `http://80.78.248.195/api` | API панели |
| `aiHelper.password` | пароль панели | логин, токен сам |
| `aiHelper.token` | (опционально) | Bearer вместо password |
| `aiHelper.site` | `5mb2` | какой сайт править |
| `aiHelper.autoSyncOnSave` | `true` | Ctrl+S → сайт |
| `aiHelper.localRoot` | пусто = workspace | корень зеркала сайта |

## Быстрый старт

1. Открой локальную копию файлов сайта (или скачай нужные файлы).
2. `Ctrl+Shift+P` → **AI Helper: Выбрать сайт на VPS** → `5mb2`.
3. Задай `aiHelper.apiUrl` и `aiHelper.password`.
4. Пиши в чате: «поменяй заголовок на …» — правка на сервере.
5. Или правь файл руками и жми **Ctrl+S** — улетит на сайт.
6. **Ctrl+Alt+S** — отправить текущий файл вручную.

## Команды

| Команда | Клавиша |
|---------|---------|
| Чат | `Ctrl+Alt+A` |
| Файл → сайт | `Ctrl+Alt+S` |
| Выбрать сайт | Command Palette |
| Авто-синк вкл/выкл | Command Palette |

## Без VS Code

В веб-панели: **Сайты → Чат** у сайта — тот же эффект (правки на диске сервера).
