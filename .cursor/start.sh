#!/usr/bin/env bash
# Per-boot service reconciliation for the AI Helper environment.
# Launches Ollama, the REST API, the Streamlit UI, and the Next.js panel as
# background services if they are not already listening. Idempotent: safe to
# run on every boot and to re-run manually. Logs go to /tmp/ai-helper-*.log.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PROJECT_DIR="$REPO_ROOT/project"
DATA_DIR="$HOME/.ai-helper"
mkdir -p "$DATA_DIR/sites"

port_up() { curl -sf -o /dev/null "http://127.0.0.1:$1/" 2>/dev/null; }

wait_for() { # port, seconds, path
  local path="${3:-/}"
  for _ in $(seq 1 "${2:-30}"); do
    curl -sf -o /dev/null "http://127.0.0.1:$1$path" 2>/dev/null && return 0
    sleep 1
  done
  return 1
}

# ── Ollama (11434) ──────────────────────────────────────────────
if curl -sf -o /dev/null "http://127.0.0.1:11434/api/version" 2>/dev/null; then
  echo "ollama: already running"
else
  echo "ollama: starting"
  OLLAMA_HOST=0.0.0.0:11434 nohup ollama serve >/tmp/ai-helper-ollama.log 2>&1 &
  for _ in $(seq 1 30); do
    curl -sf -o /dev/null "http://127.0.0.1:11434/api/version" 2>/dev/null && break
    sleep 1
  done
fi

# Shared runtime configuration for the Python services.
export OLLAMA_HOST="http://127.0.0.1:11434"
export LLM_MODEL="qwen2.5:1.5b"
export FREE_LLM_MODEL="qwen2.5:1.5b"
export FAST_LLM_MODEL="qwen2.5:1.5b"
export LLM_PREFER_FREE="1"

# ── REST API (8502) ─────────────────────────────────────────────
if curl -sf -o /dev/null "http://127.0.0.1:8502/status" 2>/dev/null; then
  echo "api: already running"
else
  echo "api: starting"
  ( cd "$PROJECT_DIR" && \
    AI_HELPER_API_HOST=0.0.0.0 AI_HELPER_API_PORT=8502 \
    SITES_ROOT="$DATA_DIR/sites" HOST_SITES_PATH="$DATA_DIR/sites" \
    AI_HELPER_PROJECT="$PROJECT_DIR" WORKSPACE_ROOTS="$REPO_ROOT" \
    nohup ./.venv/bin/python api.py >/tmp/ai-helper-api.log 2>&1 & )
fi

# ── Streamlit UI (8501) ─────────────────────────────────────────
if port_up 8501; then
  echo "streamlit: already running"
else
  echo "streamlit: starting"
  ( cd "$PROJECT_DIR" && \
    STREAMLIT_BROWSER_GATHER_USAGE_STATS=false STREAMLIT_SERVER_SHOW_EMAIL_PROMPT=false \
    nohup ./.venv/bin/streamlit run app.py \
      --server.port 8501 --server.address 0.0.0.0 --server.headless true \
      >/tmp/ai-helper-streamlit.log 2>&1 & )
fi

# ── Next.js control panel (3000) ────────────────────────────────
if port_up 3000; then
  echo "web: already running"
else
  echo "web: starting"
  ( cd "$PROJECT_DIR/web" && \
    API_INTERNAL_URL=http://127.0.0.1:8502 \
    nohup npm run dev >/tmp/ai-helper-web.log 2>&1 & )
fi

# ── Readiness (best-effort; do not fail the boot if slow) ───────
wait_for 8502 40 /status && echo "api: ready"       || echo "api: not ready yet (see /tmp/ai-helper-api.log)"
wait_for 8501 40 && echo "streamlit: ready" || echo "streamlit: not ready yet (see /tmp/ai-helper-streamlit.log)"
wait_for 3000 60 && echo "web: ready"        || echo "web: not ready yet (see /tmp/ai-helper-web.log)"

echo ">>> start complete"
