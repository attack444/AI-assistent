@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>&1
title AI Helper

REM ============================================================
REM  AI Helper — запуск (работает из папки project и с рабочего стола)
REM ============================================================

set "SCRIPT_DIR=%~dp0"
if "%SCRIPT_DIR:~-1%"=="\" set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Если launcher.py не рядом — читаем путь из сохранённого файла (ярлык с рабочего стола)
if not exist "%SCRIPT_DIR%\launcher.py" (
    set "PATH_FILE=%USERPROFILE%\.ai-helper\project_dir.txt"
    if exist "!PATH_FILE!" (
        set /p "SCRIPT_DIR=" < "!PATH_FILE!"
    )
)

cd /d "%SCRIPT_DIR%" 2>nul
if errorlevel 1 (
    echo.
    echo  [ОШИБКА] Не удалось перейти в папку проекта:
    echo  %SCRIPT_DIR%
    echo.
    goto :FAIL
)

if not exist "%SCRIPT_DIR%\launcher.py" (
    echo.
    echo  [ОШИБКА] Не найден launcher.py
    echo  Папка: %SCRIPT_DIR%
    echo.
    echo  Запусти один раз "Установить на рабочий стол.bat" из папки project,
    echo  или положи START.bat в ту же папку, где лежит launcher.py
    echo.
    goto :FAIL
)

REM Запоминаем путь проекта для ярлыков с рабочего стола
if not exist "%USERPROFILE%\.ai-helper" mkdir "%USERPROFILE%\.ai-helper" >nul 2>&1
> "%USERPROFILE%\.ai-helper\project_dir.txt" echo %SCRIPT_DIR%

set "PYTHONIOENCODING=utf-8"
set "PYTHONUTF8=1"
set "STREAMLIT_BROWSER_GATHER_USAGE_STATS=false"
set "STREAMLIT_SERVER_SHOW_EMAIL_PROMPT=false"

echo.
echo  ========================================
echo    AI Helper - запуск
echo  ========================================
echo    Папка: %SCRIPT_DIR%
echo.

REM --- Поиск Python ---
set "PYTHON_CMD="
where py >nul 2>&1
if not errorlevel 1 set "PYTHON_CMD=py -3"

if not defined PYTHON_CMD (
    where python >nul 2>&1
    if not errorlevel 1 set "PYTHON_CMD=python"
)

if not defined PYTHON_CMD (
    where python3 >nul 2>&1
    if not errorlevel 1 set "PYTHON_CMD=python3"
)

if not defined PYTHON_CMD (
    echo  [ОШИБКА] Python не найден.
    echo.
    echo  Установи Python 3.10+ с https://www.python.org/downloads/
    echo  При установке отметь галочку "Add Python to PATH"
    echo  После установки перезагрузи компьютер.
    echo.
    goto :FAIL
)

echo  Python: %PYTHON_CMD%
echo.

REM --- Запуск ---
%PYTHON_CMD% "%SCRIPT_DIR%\launcher.py"
set "EXITCODE=%ERRORLEVEL%"

if %EXITCODE% NEQ 0 (
    echo.
    echo  [ОШИБКА] Завершено с кодом %EXITCODE%
    echo.
    goto :FAIL
)

echo.
echo  AI Helper завершён.
echo.
pause
exit /b 0

:FAIL
echo  Нажми любую клавишу для выхода...
pause >nul
exit /b 1
