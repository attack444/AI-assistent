# AI Helper — обновить ТОЛЬКО VS Code расширение (без git pull всего репо)
# Запуск (из любой папки):
#   powershell -ExecutionPolicy Bypass -Command "irm https://raw.githubusercontent.com/attack444/AI-assistent/cursor/complete-ai-helper-17f9/project/vscode-extension/update-extension.ps1 | iex"
#
# Или локально:  powershell -ExecutionPolicy Bypass -File update-extension.ps1

param(
    [string]$Branch = "cursor/complete-ai-helper-17f9",
    [string]$Repo = "attack444/AI-assistent",
    [switch]$Force
)

$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$RawBase = "https://raw.githubusercontent.com/$Repo/$Branch/project/vscode-extension"
$ExtRoot = Join-Path $env:USERPROFILE ".vscode\extensions"

# Файлы расширения (только они качаются — не весь репозиторий)
$Files = @(
    "package.json",
    "extension.js",
    "README.md",
    "install.bat",
    "install-copy.bat",
    "install-from-github.bat",
    "update-extension.ps1"
)

function Get-RemoteText([string]$Url) {
    $wc = New-Object System.Net.WebClient
    $wc.Headers.Add("User-Agent", "AI-Helper-Extension-Updater")
    $wc.Encoding = [System.Text.Encoding]::UTF8
    return $wc.DownloadString($Url)
}

Write-Host ""
Write-Host " AI Helper — обновление расширения (только нужные файлы)" -ForegroundColor Cyan
Write-Host " Ветка: $Branch"
Write-Host ""

# 1) Сначала только package.json (~1 KB) — узнать версию
Write-Host " [1/3] Проверяю версию на GitHub..."
try {
    $remotePkgJson = Get-RemoteText "$RawBase/package.json"
} catch {
    Write-Host " [ОШИБКА] Не скачать package.json: $_" -ForegroundColor Red
    Write-Host " Проверь интернет / ветку $Branch"
    exit 1
}

$remotePkg = $remotePkgJson | ConvertFrom-Json
$version = [string]$remotePkg.version
if (-not $version) { $version = "0.0.0" }
$publisher = if ($remotePkg.publisher) { $remotePkg.publisher } else { "ai-helper-local" }
$name = if ($remotePkg.name) { $remotePkg.name } else { "ai-helper" }
$destName = "$publisher.$name-$version"
$Dest = Join-Path $ExtRoot $destName

Write-Host "       Удалённая версия: $version"

# 2) Сравнить с уже установленным
$localPkgPath = Join-Path $Dest "package.json"
$skipDownload = $false
if ((Test-Path $localPkgPath) -and -not $Force) {
    try {
        $localVer = (Get-Content $localPkgPath -Raw | ConvertFrom-Json).version
        if ($localVer -eq $version) {
            Write-Host " [OK] Уже установлено $version — качать нечего." -ForegroundColor Green
            Write-Host "       $Dest"
            Write-Host ""
            Write-Host " Перезапусти VS Code. Настройка: Ctrl+Shift+P → AI Helper: Настройка VPS"
            $skipDownload = $true
        } else {
            Write-Host "       Локально было: $localVer → обновляю до $version"
        }
    } catch {}
}

if (-not $skipDownload) {
    # Убрать старые копии ai-helper (чтобы VS Code не брал 1.1)
    Write-Host " [2/3] Чищу старые папки ai-helper* ..."
    if (-not (Test-Path $ExtRoot)) { New-Item -ItemType Directory -Path $ExtRoot | Out-Null }
    Get-ChildItem $ExtRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match 'ai-helper' -and $_.FullName -ne $Dest } |
        ForEach-Object {
            Write-Host "       удаляю $($_.Name)"
            Remove-Item $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
        }

    if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }
    New-Item -ItemType Directory -Path $Dest | Out-Null

    Write-Host " [3/3] Качаю $($Files.Count) файлов расширения..."
    $ok = 0
    foreach ($f in $Files) {
        $url = "$RawBase/$f"
        $out = Join-Path $Dest $f
        try {
            $wc = New-Object System.Net.WebClient
            $wc.Headers.Add("User-Agent", "AI-Helper-Extension-Updater")
            $wc.DownloadFile($url, $out)
            $ok++
            Write-Host "       + $f"
        } catch {
            # необязательные bat/ps1 можно пропустить
            if ($f -in @("package.json", "extension.js")) {
                Write-Host " [ОШИБКА] Не скачать $f : $_" -ForegroundColor Red
                exit 1
            }
            Write-Host "       ~ $f пропущен"
        }
    }
    Write-Host " [OK] Установлено $ok файлов → $Dest" -ForegroundColor Green
}

Write-Host ""
Write-Host " Дальше:"
Write-Host "  1) Полностью закрой и открой VS Code"
Write-Host "  2) Ctrl+Shift+P → «AI Helper: Настройка VPS»"
Write-Host "  3) Settings → AI Helper → должны быть Password / Site / Auto Sync"
Write-Host ""
