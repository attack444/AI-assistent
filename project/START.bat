@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>&1
title AI Helper

REM ============================================================
REM  AI Helper — запуск
REM  Работает из папки project И через ярлык с рабочего стола
REM ============================================================

set "SCRIPT_DIR=%~dp0"
if "!SCRIPT_DIR:~-1!"=="\" set "SCRIPT_DIR=!SCRIPT_DIR:~0,-1!"

REM Если launcher.py не рядом — ищем сохранённый путь (ярлык с рабочего стола)
if not exist "!SCRIPT_DIR!\launcher.py" (
    set "PATH_FILE=%USERPROFILE%\.ai-helper\project_dir.txt"
    if exist "!PATH_FILE!" (
        set /p "SCRIPT_DIR=" < "!PATH_FILE!"
        if "!SCRIPT_DIR:~-1!"=="\" set "SCRIPT_DIR=!SCRIPT_DIR:~0,-1!"
    )
)

if not exist "!SCRIPT_DIR!\launcher.py" (
    echo.
    echo  [ОШИБКА] Не найден launcher.py
    echo  Папка: !SCRIPT_DIR!
    echo.
    echo  Запусти один раз "Установить на рабочий стол.bat" из папки project.
    echo.
    pause
    exit /b 1
)

REM Запоминаем папку проекта для будущих запусков через ярлык
if not exist "%USERPROFILE%\.ai-helper" mkdir "%USERPROFILE%\.ai-helper" >nul 2>&1
(echo !SCRIPT_DIR!) > "%USERPROFILE%\.ai-helper\project_dir.txt"

cd /d "!SCRIPT_DIR!"

set "PYTHONIOENCODING=utf-8"
set "PYTHONUTF8=1"
set "STREAMLIT_BROWSER_GATHER_USAGE_STATS=false"
set "STREAMLIT_SERVER_SHOW_EMAIL_PROMPT=false"

REM ============================================================
REM  Путь к моделям Ollama: всегда D:\Ollama\.ollama\models
REM  если диск D: есть, иначе — стандартный путь на C:
REM ============================================================
set "OLLAMA_D=D:\Ollama\.ollama\models"

if exist "D:\" (
    REM D: есть — используем его
    set "OLLAMA_MODELS=!OLLAMA_D!"
    if not exist "!OLLAMA_D!" mkdir "!OLLAMA_D!" >nul 2>&1
    echo  [Ollama] Папка моделей: !OLLAMA_D!
) else (
    REM D: нет — стандартный путь
    set "OLLAMA_MODELS=%USERPROFILE%\.ollama\models"
    echo  [Ollama] Диска D: нет, папка: %USERPROFILE%\.ollama\models
)

echo.
echo  ========================================
echo    AI Helper
echo  ========================================
echo    Папка: !SCRIPT_DIR!
echo.

REM Ищем Python
set "PY="
where py     >nul 2>&1 && set "PY=py -3"
if not defined PY where python  >nul 2>&1 && set "PY=python"
if not defined PY where python3 >nul 2>&1 && set "PY=python3"

if not defined PY (
    echo  [ОШИБКА] Python не найден.
    echo.
    echo  Установи Python 3.10+ с https://www.python.org/downloads/
    echo  При установке отметь "Add Python to PATH"
    echo  После установки перезагрузи компьютер и запусти START.bat снова.
    echo.
    pause
    exit /b 1
)

echo  Python: %PY%
echo.

%PY% "!SCRIPT_DIR!\launcher.py"
set "EC=%ERRORLEVEL%"

if %EC% NEQ 0 (
    echo.
    echo  Завершено с кодом %EC%
    echo.
    pause
    exit /b %EC%
)

echo.
pause
exit /b 0
