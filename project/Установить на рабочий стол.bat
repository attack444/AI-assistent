@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - ярлык на рабочем столе

echo.
echo  Создаю ярлык "AI Helper" на рабочем столе...
echo.

REM Ищем Python
set "PY="
where py     >nul 2>&1 && set "PY=py -3"
if not defined PY where python  >nul 2>&1 && set "PY=python"
if not defined PY where python3 >nul 2>&1 && set "PY=python3"

if not defined PY (
    echo  [ОШИБКА] Python не найден.
    echo  Установи Python 3.10+ с https://www.python.org/downloads/
    echo  При установке отметь галочку "Add Python to PATH"
    echo.
    pause
    exit /b 1
)

%PY% launcher.py --install-shortcut

if errorlevel 1 (
    echo.
    pause
    exit /b 1
)

echo.
echo  Запустить приложение сейчас? (нажми любую клавишу или закрой окно)
pause >nul
start "" "%~dp0START.bat"
