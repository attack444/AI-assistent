@echo off
setlocal EnableExtensions
chcp 65001 >nul 2>&1
title AI Helper - установка ярлыка

REM Запусти этот файл ОДИН РАЗ из папки project — появится ярлык на рабочем столе

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"

if not exist "%PROJECT_DIR%\launcher.py" (
    echo.
    echo  [ОШИБКА] Запускай этот файл из папки project, где лежит launcher.py
    echo.
    pause
    exit /b 1
)

if not exist "%USERPROFILE%\.ai-helper" mkdir "%USERPROFILE%\.ai-helper"
> "%USERPROFILE%\.ai-helper\project_dir.txt" echo %PROJECT_DIR%

echo.
echo  Создаю ярлык "AI Helper" на рабочем столе...
echo  Папка проекта: %PROJECT_DIR%
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$Wsh = New-Object -ComObject WScript.Shell; ^
   $Desk = [Environment]::GetFolderPath('Desktop'); ^
   $Lnk = $Wsh.CreateShortcut((Join-Path $Desk 'AI Helper.lnk')); ^
   $Lnk.TargetPath = '%PROJECT_DIR%\START.bat'; ^
   $Lnk.WorkingDirectory = '%PROJECT_DIR%'; ^
   $Lnk.Description = 'AI Helper - локальный AI-ассистент'; ^
   $Lnk.WindowStyle = 1; ^
   $Lnk.Save(); ^
   Write-Host '  Готово! Ярлык создан:' (Join-Path $Desk 'AI Helper.lnk')"

if errorlevel 1 (
    echo.
    echo  [ОШИБКА] Не удалось создать ярлык через PowerShell.
    echo  Скопируй START.bat на рабочий стол вручную и запусти install_desktop.bat снова.
    echo.
    pause
    exit /b 1
)

echo.
echo  ========================================
echo    Установка завершена!
echo  ========================================
echo.
echo  На рабочем столе появился ярлык "AI Helper".
echo  Запускай AI Helper двойным кликом по этому ярлыку.
echo.
echo  Сейчас можно сразу запустить приложение...
echo.
pause

start "" "%PROJECT_DIR%\START.bat"
exit /b 0
