@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - Установка VS Code расширения

echo.
echo  =========================================
echo   Установка AI Helper для VS Code
echo  =========================================
echo.

REM Check npm
where npm >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] npm не найден.
    echo  Установи Node.js с https://nodejs.org/
    echo  При установке отметь "Add to PATH"
    pause
    exit /b 1
)

echo  [1/3] Устанавливаю зависимости...
call npm install --silent 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] npm install не удался
    pause
    exit /b 1
)
echo        OK

echo  [2/3] Собираю VSIX пакет...
call npx @vscode/vsce package --no-dependencies --allow-missing-repository --no-git-tag-version 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] Сборка не удалась. Пробую без флагов...
    call npx vsce package --no-dependencies --allow-missing-repository 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo  [ОШИБКА] Не удалось собрать пакет
        pause
        exit /b 1
    )
)
echo        OK

echo  [3/3] Устанавливаю в VS Code...
set "VSIX="
for %%f in (*.vsix) do set "VSIX=%%f"

if not defined VSIX (
    echo  [ОШИБКА] VSIX файл не найден
    pause
    exit /b 1
)

where code >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    code --install-extension "%VSIX%" --force
    echo.
    echo  [OK] Расширение установлено: %VSIX%
    echo.
    echo  Перезапусти VS Code.
    echo  В статус-баре появится: [robot] AI Helper
) else (
    echo  [!] code CLI не найден. Установи вручную:
    echo      1. Открой VS Code
    echo      2. Ctrl+Shift+P
    echo      3. Введи: Extensions: Install from VSIX
    echo      4. Выбери файл: %CD%\%VSIX%
)

echo.
pause
