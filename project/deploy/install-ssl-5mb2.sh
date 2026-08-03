#!/bin/bash
# Обёртка → полный enable HTTPS для 5mb2.
#   bash project/deploy/install-ssl-5mb2.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$SCRIPT_DIR/enable-https-5mb2.sh" "$@"
