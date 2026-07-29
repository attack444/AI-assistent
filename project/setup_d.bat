@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title Ollama - setup D: drive

echo.
echo  Ollama: move models to D: and delete from C:
echo.

set "PY="
where py     >nul 2>&1 && set "PY=py -3"
if not defined PY where python  >nul 2>&1 && set "PY=python"
if not defined PY where python3 >nul 2>&1 && set "PY=python3"

if not defined PY (
    echo  [ERROR] Python not found.
    echo  Install Python from python.org and add to PATH.
    pause
    exit /b 1
)

%PY% setup_ollama_d.py
exit /b %ERRORLEVEL%
