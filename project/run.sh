#!/usr/bin/env bash
# Запуск AI Helper (Linux / macOS)
set -euo pipefail

cd "$(dirname "$0")"

export STREAMLIT_BROWSER_GATHER_USAGE_STATS=false
export STREAMLIT_SERVER_SHOW_EMAIL_PROMPT=false

OLLAMA_URL="${OLLAMA_HOST:-http://localhost:11434}"

if curl -sf "${OLLAMA_URL}/api/tags" >/dev/null 2>&1; then
    echo "✓ Ollama уже работает на ${OLLAMA_URL} — команду «ollama serve» запускать не нужно."
else
    echo "⚠ Ollama не отвечает на ${OLLAMA_URL}."
    echo "  Запусти Ollama Desktop или выполни в другом терминале: ollama serve"
    echo ""
    echo "  Если «ollama serve» выдаёт ошибку «bind: address already in use»,"
    echo "  значит Ollama уже запущен — это нормально, просто продолжай."
fi

echo ""
echo "Запускаю Streamlit..."
exec streamlit run app.py
