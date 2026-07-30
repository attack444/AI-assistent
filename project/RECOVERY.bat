@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - Восстановление

echo.
echo  ╔══════════════════════════════════════════╗
echo  ║    AI Helper — Восстановление            ║
echo  ╚══════════════════════════════════════════╝
echo.
echo  Этот инструмент восстановит работоспособность:
echo  - Проверит и восстановит исходный код из бэкапа
echo  - Очистит кэш Streamlit и __pycache__
echo  - Переустановит зависимости если нужно
echo  - Сбросит повреждённые конфигурации
echo.

REM ── Быстрое восстановление без Python ────────────────────────────────────
echo  [1/4] Удаляю кэш Streamlit и __pycache__...
for /d /r "%~dp0" %%d in (__pycache__) do (
    if exist "%%d" (
        rmdir /s /q "%%d" 2>nul
    )
)
if exist "%USERPROFILE%\.streamlit\cache" rmdir /s /q "%USERPROFILE%\.streamlit\cache" 2>nul
echo        OK

REM ── Проверяем нужно ли пересоздать venv ──────────────────────────────────
echo.
echo  [2/4] Проверка виртуального окружения...
set "VENV_PY=%~dp0.venv\Scripts\python.exe"
if not exist "!VENV_PY!" (
    echo        venv не найден — буду использовать системный Python
    set "VENV_PY="
)

REM ── Ищем Python ───────────────────────────────────────────────────────────
set "PY="
if defined VENV_PY (
    set "PY=!VENV_PY!"
) else (
    where py     >nul 2>&1 && set "PY=py -3"
    if not defined PY where python  >nul 2>&1 && set "PY=python"
    if not defined PY where python3 >nul 2>&1 && set "PY=python3"
)

if not defined PY (
    echo.
    echo  [ОШИБКА] Python не найден!
    echo  Установи Python 3.10+ с https://www.python.org/downloads/
    echo  При установке отметь "Add Python to PATH"
    echo.
    pause
    exit /b 1
)
echo        Python: !PY!

REM ── Быстрое восстановление из последнего бэкапа ───────────────────────────
echo.
echo  [3/4] Проверка исходного кода...
set "BACKUP_BASE=%USERPROFILE%\.ai-helper\backups\source"
set "LATEST_BK="

if exist "!BACKUP_BASE!" (
    for /f "delims=" %%D in ('dir "!BACKUP_BASE!" /b /ad /o:-d 2^>nul') do (
        if not defined LATEST_BK set "LATEST_BK=!BACKUP_BASE!\%%D"
    )
)

if defined LATEST_BK (
    echo        Найден бэкап: !LATEST_BK!
) else (
    echo        Бэкапов нет — восстановление из файлов не возможно
)

REM ── Запускаем полный recover.py ───────────────────────────────────────────
echo.
echo  [4/4] Запускаю полный анализ и восстановление...
echo.

if exist "!VENV_PY!" (
    "!VENV_PY!" "%~dp0recover.py"
) else (
    %PY% "%~dp0recover.py"
)

set "EC=!ERRORLEVEL!"
if !EC! NEQ 0 (
    echo.
    echo  Восстановление завершено с предупреждениями (код !EC!).
    echo.
)

REM ── Предложить запуск ────────────────────────────────────────────────────
echo.
set /p "LAUNCH=Запустить AI Helper сейчас? (y/n): "
if /i "!LAUNCH!"=="y" (
    call "%~dp0START.bat"
)
exit /b 0
