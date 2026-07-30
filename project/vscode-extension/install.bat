@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - Установка VS Code расширения

echo.
echo  Установка AI Helper расширения для VS Code
echo  ============================================
echo.

REM Check npm
where npm >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] npm не найден.
    echo  Установи Node.js с https://nodejs.org/
    pause
    exit /b 1
)

REM Check VS Code
where code >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [!] VS Code CLI не найден в PATH.
    echo  После сборки установи VSIX вручную:
    echo  Ctrl+Shift+P → Extensions: Install from VSIX
    echo.
)

echo  [1/3] Устанавливаю зависимости...
call npm install --silent
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] npm install не удался
    pause
    exit /b 1
)

echo  [2/3] Собираю VSIX пакет...
call npx vsce package --no-dependencies
if %ERRORLEVEL% NEQ 0 (
    echo  [ОШИБКА] Сборка не удалась
    pause
    exit /b 1
)

echo  [3/3] Устанавливаю в VS Code...
for %%f in (*.vsix) do (
    where code >nul 2>&1 && (
        code --install-extension "%%f"
        echo  [OK] Расширение установлено: %%f
    ) || (
        echo  [!] Установи вручную: %%f
        echo      Ctrl+Shift+P → Extensions: Install from VSIX
    )
)

echo.
echo  Готово! Перезапусти VS Code.
echo  В статус-баре появится: [robot] AI Helper
echo.
pause
