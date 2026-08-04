#!/usr/bin/env bash
# Прямое сохранение + проверка ЮKassa НА СЕРВЕРЕ (минуя браузер/Cloudflare UI).
#
#   YOOKASSA_SHOP_ID='1428273' \
#   YOOKASSA_SECRET_KEY='test_ПОЛНЫЙ_КЛЮЧ_БЕЗ_ЗВЁЗДОЧЕК' \
#   bash project/deploy/apply-yookassa.sh
#
# Ключ должен быть БЕЗ символа *. Если в ЛК видите test_*… — это маска:
# нажмите «Выпустить ключ» и скопируйте полную строку (показывают один раз).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/project"

SHOP="${YOOKASSA_SHOP_ID:-}"
SECRET="${YOOKASSA_SECRET_KEY:-}"

if [[ -z "$SHOP" || -z "$SECRET" ]]; then
  echo "ERROR: задайте YOOKASSA_SHOP_ID и YOOKASSA_SECRET_KEY" >&2
  exit 1
fi

if [[ "$SECRET" == *"*"* || "$SECRET" == *"•"* ]]; then
  echo "ERROR: в секрете есть * — это маска, не ключ. Перевыпустите в ЛК ЮKassa." >&2
  exit 1
fi

python3 - <<'PY'
import json, os, sys
sys.path.insert(0, ".")
import payments_yookassa as pay

shop = os.environ["YOOKASSA_SHOP_ID"]
secret = os.environ["YOOKASSA_SECRET_KEY"]
print("==> validate")
err = pay.validate_api_creds(shop, secret)
if err:
    print("FAIL:", err)
    sys.exit(2)
print("==> save_and_verify → api.yookassa.ru/v3/me")
res = pay.save_and_verify(shop, secret)
print(json.dumps(res, ensure_ascii=False, indent=2))
sys.exit(0 if res.get("ok") else 3)
PY

echo ""
echo "Если ok=true — попробуйте оплату:"
echo "  https://neobrain.site/seo/  или тариф на витрине"
echo "Webhook: https://neobrain.site/api/public/pay/webhook"
