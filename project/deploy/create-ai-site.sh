#!/bin/bash
# Создать/обновить второй сайт (витрина AI + среда). Домен — позже.
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/create-ai-site.sh | bash
set -euo pipefail

NAME="${SITE_NAME:-ai}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$NAME"
mkdir -p "$ROOT"

cat > "$ROOT/index.html" <<'EOF'
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>AI Helper — среда для сайтов</title>
<style>
:root{--bg:#101820;--fg:#eef3f7;--muted:#9eb0c0;--line:#243140;--acc:#4aa3ff}
*{box-sizing:border-box}
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:
radial-gradient(900px 500px at 0% -10%,#1d3348 0%,transparent 55%),
radial-gradient(700px 400px at 100% 0%,#163028 0%,transparent 50%),var(--bg);color:var(--fg);min-height:100vh}
.wrap{max-width:920px;margin:0 auto;padding:56px 22px 72px}
.brand{font-size:0.85rem;letter-spacing:.12em;text-transform:uppercase;color:var(--acc);margin:0 0 14px}
h1{font-size:clamp(2rem,4.5vw,3rem);line-height:1.1;margin:0 0 14px;letter-spacing:-.03em;max-width:14ch}
.lead{color:var(--muted);font-size:1.1rem;line-height:1.55;max-width:42rem;margin:0 0 28px}
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:48px}
a.btn{display:inline-flex;align-items:center;padding:12px 18px;border-radius:10px;background:var(--acc);color:#061018;text-decoration:none;font-weight:650}
a.btn.ghost{background:transparent;color:var(--fg);border:1px solid var(--line)}
.grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.card{border:1px solid var(--line);border-radius:14px;padding:18px 16px;background:rgba(255,255,255,.02)}
.card h2{margin:0 0 8px;font-size:1.05rem}
.card p{margin:0;color:var(--muted);line-height:1.5;font-size:.95rem}
.foot{margin-top:40px;color:var(--muted);font-size:.9rem}
code{color:#cfe6ff}
</style>
</head>
<body>
<div class="wrap">
  <p class="brand">AI Helper</p>
  <h1>Сервер для сайтов и ассистента</h1>
  <p class="lead">Один VPS: панель, редактор кода, деплой и AI-чат. Это витрина второго сайта — домен позже. 5mb2.ru уже на этом хостинге.</p>
  <div class="actions">
    <a class="btn" href="/">Открыть панель</a>
    <a class="btn ghost" href="/sites">Сайты</a>
    <a class="btn ghost" href="/files">Редактор</a>
    <a class="btn ghost" href="/chat">Чат</a>
  </div>
  <div class="grid">
    <div class="card"><h2>Хостинг</h2><p>Несколько сайтов: ZIP-деплой, домены, WordPress.</p></div>
    <div class="card"><h2>Редактор</h2><p>Правишь в браузере и сразу проверяешь на сервере.</p></div>
    <div class="card"><h2>AI</h2><p>DeepSeek в панели — код и файлы сайта без VPN.</p></div>
  </div>
  <p class="foot">Файлы: <code>/var/ai-helper/sites/ai/</code></p>
</div>
</body>
</html>
EOF

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chmod -R a+rX "$ROOT"
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

echo "[OK] $ROOT"
echo "     http://$(curl -s --max-time 3 ifconfig.me)/sites/${NAME}/"
