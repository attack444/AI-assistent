@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title AI Helper - настройка Ollama на диск D

echo.
echo  Перенос моделей Ollama на D:  (удаление с C:)
echo.

set "PY="
where py     >nul 2>&1 && set "PY=py -3"
if not defined PY where python  >nul 2>&1 && set "PY=python"
if not defined PY where python3 >nul 2>&1 && set "PY=python3"

if not defined PY (
    echo  [ОШИБКА] Python не найден.
    echo  Установи Python с python.org и добавь в PATH.
    pause
    exit /b 1
)

%PY% setup_ollama_d.py
exit /b %ERRORLEVEL%
