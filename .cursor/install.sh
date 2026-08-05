#!/usr/bin/env bash
# Idempotent bootstrap for the AI Helper Cloud Agent environment.
# Prepares: system packages, Ollama, the Python virtualenv, the Next.js panel
# dependencies, and the small default local models used by the assistant.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PROJECT_DIR="$REPO_ROOT/project"
OLLAMA_MODELS_DEFAULT=("nomic-embed-text" "qwen2.5:1.5b")

echo ">>> [1/5] System packages (python venv, zstd for the Ollama installer)"
if command -v apt-get >/dev/null 2>&1; then
  sudo apt-get update -qq || true
  sudo apt-get install -y -qq python3-venv python3-pip zstd curl ca-certificates
fi

echo ">>> [2/5] Ollama runtime"
if ! command -v ollama >/dev/null 2>&1; then
  curl -fsSL https://ollama.com/install.sh | sh
fi
ollama --version || true

echo ">>> [3/5] Python virtualenv + dependencies"
cd "$PROJECT_DIR"
python3 -m venv .venv
./.venv/bin/python -m pip install --upgrade pip setuptools wheel
./.venv/bin/python -m pip install -r requirements.txt

echo ">>> [4/5] Next.js control panel dependencies"
cd "$PROJECT_DIR/web"
npm install

echo ">>> [5/5] Default local models (small, RU-friendly)"
export OLLAMA_HOST="127.0.0.1:11434"
OLLAMA_PID=""
if ! curl -sf "http://127.0.0.1:11434/api/version" >/dev/null 2>&1; then
  nohup ollama serve >/tmp/ollama-install.log 2>&1 &
  OLLAMA_PID="$!"
  for _ in $(seq 1 30); do
    curl -sf "http://127.0.0.1:11434/api/version" >/dev/null 2>&1 && break
    sleep 1
  done
fi
for model in "${OLLAMA_MODELS_DEFAULT[@]}"; do
  echo "    pulling $model"
  ollama pull "$model"
done
if [ -n "$OLLAMA_PID" ]; then
  # Leave nothing running; the environment's terminals start ollama on boot.
  kill "$OLLAMA_PID" 2>/dev/null || true
fi

echo ">>> install complete"
