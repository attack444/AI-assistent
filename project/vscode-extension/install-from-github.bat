@echo off
setlocal
chcp 65001 >nul 2>&1
title AI Helper — обновление расширения с GitHub (без всего репо)

echo.
echo  Качаю ТОЛЬКО расширение VS Code (не весь проект).
echo  Если версия уже та же — повторно не качает.
echo.

REM Одна команда: скрипт с GitHub → установка в .vscode\extensions
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "irm https://raw.githubusercontent.com/attack444/AI-assistent/cursor/complete-ai-helper-17f9/project/vscode-extension/update-extension.ps1 | iex"

echo.
pause
