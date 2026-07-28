# Запуск AI Helper (Windows PowerShell)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

$env:STREAMLIT_BROWSER_GATHER_USAGE_STATS = "false"
$env:STREAMLIT_SERVER_SHOW_EMAIL_PROMPT = "false"

$ollamaUrl = if ($env:OLLAMA_HOST) { $env:OLLAMA_HOST } else { "http://localhost:11434" }

try {
    Invoke-RestMethod -Uri "$ollamaUrl/api/tags" -TimeoutSec 3 | Out-Null
    Write-Host "Ollama уже работает на $ollamaUrl — команду ollama serve запускать не нужно." -ForegroundColor Green
} catch {
    Write-Host "Ollama не отвечает на $ollamaUrl." -ForegroundColor Yellow
    Write-Host "  Запусти Ollama Desktop из меню Пуск или выполни в другом терминале: ollama serve"
    Write-Host ""
    Write-Host "  Если ollama serve выдаёт ошибку bind: address already in use,"
    Write-Host "  значит Ollama уже запущен — это нормально, просто продолжай."
}

Write-Host ""
Write-Host "Запускаю Streamlit..."
streamlit run app.py
