@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - копирование расширения в VS Code

echo.
echo  Копирую AI Helper 1.3 в папку расширений VS Code...
echo  (так появятся Password / Site / Auto Sync)
echo.

set "DEST=%USERPROFILE%\.vscode\extensions\ai-helper-local.ai-helper-1.3.0"
if exist "%DEST%" (
  echo  Удаляю старую копию...
  rmdir /s /q "%DEST%"
)
mkdir "%DEST%" 2>nul
xcopy /E /I /Y /Q "%CD%\*" "%DEST%\" >nul
if exist "%DEST%\*.vsix" del /q "%DEST%\*.vsix" 2>nul
if exist "%DEST%\node_modules" rmdir /s /q "%DEST%\node_modules" 2>nul

echo.
echo  [OK] Установлено в:
echo       %DEST%
echo.
echo  1) Полностью закрой VS Code
echo  2) Открой снова
echo  3) Ctrl+Shift+P → "AI Helper: Настройка VPS"
echo     или Settings → AI Helper → Password / Site / Auto Sync
echo.
pause
