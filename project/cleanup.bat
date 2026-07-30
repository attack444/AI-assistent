@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - Очистка

set "PY="
if exist "%~dp0.venv\Scripts\python.exe" set "PY=%~dp0.venv\Scripts\python.exe"
if not defined PY where py     >nul 2>&1 && set "PY=py -3"
if not defined PY where python >nul 2>&1 && set "PY=python"
if not defined PY where python3>nul 2>&1 && set "PY=python3"

if not defined PY (
    echo [ОШИБКА] Python не найден.
    pause
    exit /b 1
)

%PY% cleanup.py
exit /b %ERRORLEVEL%
