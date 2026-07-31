#!/bin/bash
# Создать второй сайт (заготовка под AI + деплой). Домен купим позже.
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/create-ai-site.sh | bash
set -euo pipefail

NAME="${SITE_NAME:-ai}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$NAME"
mkdir -p "$ROOT"

cat > "$ROOT/index.html" <<'EOF'
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Helper — среда сайтов</title>
  <style>
    :root { color-scheme: light; --bg:#0f1419; --fg:#e7ecf1; --muted:#9aa7b5; --acc:#3d9cf0; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family: "Segoe UI", system-ui, sans-serif;
      background: radial-gradient(1200px 600px at 10% 0%, #1a2a3a, var(--bg)); color: var(--fg);
      display:grid; place-items:center; padding: 32px 20px; }
    main { max-width: 640px; width:100%; }
    h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 0 0 12px; letter-spacing: -0.02em; }
    p { color: var(--muted); line-height: 1.55; margin: 0 0 18px; }
    a.btn { display:inline-block; padding: 12px 18px; border-radius: 10px; background: var(--acc);
      color:#041018; text-decoration:none; font-weight:600; }
    .note { margin-top: 28px; font-size: 0.9rem; color: var(--muted); }
    code { color: #cde7ff; }
  </style>
</head>
<body>
  <main>
    <h1>AI Helper</h1>
    <p>Публичная витрина второго сайта. Управление, редактор кода и деплой — в панели на этом же сервере.</p>
    <a class="btn" href="/">Открыть панель</a>
    <p class="note">Домен привяжем позже. Файлы: <code>/var/ai-helper/sites/ai/</code></p>
  </main>
</body>
</html>
EOF

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chmod -R a+rX "$ROOT"
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

echo "[OK] Сайт-заготовка: $ROOT"
echo "     URL: http://$(curl -s --max-time 3 ifconfig.me)/sites/${NAME}/"
echo "     Домен — когда купишь: в панели Сайты → Домен"
