@echo off
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper

REM Автозапуск: двойной клик по этому файлу — больше ничего не нужно

set PY=
where py >nul 2>&1 && set PY=py -3
if not defined PY where python >nul 2>&1 && set PY=python
if not defined PY where python3 >nul 2>&1 && set PY=python3

if not defined PY (
    echo.
    echo  Python не найден.
    echo  Установи Python 3.10+ с https://www.python.org/downloads/
    echo  При установке отметь "Add Python to PATH"
    echo.
    pause
    exit /b 1
)

%PY% launcher.py
if errorlevel 1 pause
