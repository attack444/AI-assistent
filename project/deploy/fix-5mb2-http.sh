#!/bin/bash
# Устаревшая обёртка → полный фикс WP/HTTP: fix-5mb2-wp.sh
#   bash project/deploy/fix-5mb2-http.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$SCRIPT_DIR/fix-5mb2-wp.sh" "$@"
