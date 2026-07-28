# Запуск AI Helper — делегирует в launcher.py
Set-Location $PSScriptRoot
& python launcher.py
if ($LASTEXITCODE -ne 0) { Read-Host "Нажми Enter для выхода" }
