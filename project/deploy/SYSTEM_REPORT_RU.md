# Полный отчёт: что сделано на VPS (5mb2 + AI Helper + панель)

Документ для владельца (Вячеслав). Актуальность: ветка `cursor/complete-ai-helper-17f9`, API **2.10.0**.  
Живой снимок в панели: раздел **«Обзор»** (`/overview`) — DNS, мониторинг, Docker, матрица возможностей DeepSeek.

---

## 1. Архитектура (как всё связано)

```
Интернет
   │
   ├─ DNS 5mb2.ru  ──должно──► A → IP VPS (80.78.248.195)
   │
   ▼
Nginx на VPS
   ├─ 5mb2.ru      → WordPress (/var/ai-helper/sites/5mb2) + PHP-FPM
   ├─ /sites/ai/   → статика AI Helper
   ├─ /            → панель Next.js (:3000)
   └─ /api         → AI Helper API (:8502)  ← DeepSeek, файлы, watchdog
         │
         ├─ Docker: app (API), web (панель), php, mysql, ollama
         ├─ Данные: ~/.ai-helper (feedback, incidents, memory)
         └─ Репозиторий: /opt/ai-helper  (git) + volume в app для правок бэкенда
```

**Плотное взаимодействие:** ошибка на сайте → health-check / mu-plugin / watchdog → инцидент в inbox → safe restart и/или DeepSeek с tools → правка файлов сайта или бэкенда (`site=server`).

---

## 2. Ответ на главный вопрос про DeepSeek

### Сейчас (после 2.10.0)

| Задача | Может DeepSeek? | Как |
|---|---|---|
| Править HTML/CSS/PHP **сайта** | **Да** | Чат с сайтом `5mb2` / `ai` → write_file, str_replace, … |
| Править **бэкенд панели** (api.py, agent, system_health, web…) | **Да** | Чат **`site=server`** → workspace `/opt/ai-helper/project` (нужен mount в Docker — добавлен в compose) |
| Самоулучшение ключевых py | **Да** | tool `apply_self_improvement` (с бэкапом и откатом) |
| Смотреть DNS и писать «почему сайт не открывается» | **Да** | `dns_lookup`, `system_overview` |
| WordPress URL / health сайта | **Да** | `site_health_check`, `wp_replace_urls`, … |
| Писать nginx vhost | **Нет напрямую** | Панель «привязать домен» / `setup-domain.sh`. Tool только `nginx_test` |
| Менять DNS у регистратора | **Нет** | Только диагностика; A/NS меняешь в кабинете домена |
| `docker restart` | **Не tool’ом** | Watchdog / кнопка «Здоровье» на хосте |
| После правки Next/API увидеть результат | Нужен **rebuild** | `docker compose build app web && up -d` |

**Раньше** DeepSeek в чате сайта был ограничен корнем `/opt/sites/<site>` — бэкенд не монтировался в контейнер.  
**Сейчас** compose монтирует `/opt/ai-helper`, чат `server` открывает тот же код, что ты правишь через Cursor/git.

Публичный виджет на сайтах может идти в **free Ollama** (`LLM_PREFER_FREE=1`).  
Ремонт/tools и watchdog — **DeepSeek** (`LLM_PREFER_FREE=0` в cron).

---

## 3. Сайт 5mb2 (WordPress + тема)

### Тема `5mb2-dark` (текущая **1.9.7**)
- Тёмный фронт, услуги SEO, инструменты, кейсы, кабинет клиента.
- **Кабинет:** планы (start / audit / monthly / local / …), чеклисты по услуге, обзор проекта, заявки, онбординг.
- Клиентам (subscriber) скрыт WP admin bar.
- **Обратная связь:** секция `#feedback` + ссылка «Идея» (не FAB — не перекрывается чатом). Пишет в WP option + `POST /api/public/feedback`.
- Блок «Что вам нужно?» — быстрые пути к услугам/кабинету.

### Критичный фикс «сайт лёг после правок в WP»
До 1.9.6 при смене версии темы на **каждом фронтовом запросе** вызывался `mb2_ensure_site_structure()` и **перезаписывал** страницы + Rank Math — таймауты и ощущение «сайт упал» после сохранения SEO в админке.

С 1.9.6:
- seed только `admin_init` / `sync-5mb2-theme.sh`, с lock;
- существующий контент и Rank Math **не затираются**;
- mu-plugin `mb2-health-guard`: `/?mb2_health=1`, фаталы → `wp-content/mb2-fatal.log`.

### Деплой темы
`bash project/deploy/sync-5mb2-theme.sh` — rsync темы + mu-plugins, безопасный seed, restart php.

---

## 4. Витрина AI Helper (`/sites/ai/`)

- Статика + виджет чата, форма идеи/ошибки → тот же API feedback.
- Деплой: `create-ai-site.sh`.

---

## 5. Панель сервера (Next.js)

| Раздел | URL | Содержание |
|---|---|---|
| Обзор | `/overview` | LLM, DNS, мониторинг, Docker, матрица возможностей, инциденты |
| Чат | `/chat` | DeepSeek; сайты + виртуальный **server** (бэкенд) |
| Файлы | `/files` | Редактор в `ALLOWED_ROOTS` |
| Сайты | `/sites` | Создать/деплой/домен/WP helpers |
| Здоровье | `/health` | Watchdog, safe fix, «Чинить через DeepSeek» |
| Обратная связь | `/feedback` | Inbox идей/багов (+ watchdog) |
| Вход | `/login` | `PANEL_PASSWORD` |

API базовый: `/api` → контейнер `app:8502`.

---

## 6. Мониторинг (watchdog)

**Файлы:** `system_health.py`, `deploy/system-watchdog.sh`, `install-system-watchdog.sh`.

**Проверки (каждые 2 мин по cron):**
1. API `/status` (приоритет)
2. DeepSeek флаг (приоритет)
3. Панель `:3000` (приоритет)
4. 5mb2 `/`, `/?mb2_health=1`, `/cabinet/`
5. `/sites/ai/`

**При сбое:**
1. Инцидент → `~/.ai-helper/system_incidents.jsonl` + inbox feedback (`source=watchdog`)
2. Safe: `docker restart` app / web / php
3. При `WATCHDOG_ASK_ON_FAIL=1` — DeepSeek repair (cooldown 8 мин), `LLM_PREFER_FREE=0`

**Не делает:** слепые правки PHP без разбора; не меняет DNS у регистратора.

Панель: **Здоровье** + сводка в **Обзор**.

Тесты: `python3 -m unittest tests.test_system_health -v`

---

## 7. DNS (важно для «сайт не открывается»)

Панель **Обзор** показывает для доменов сайтов (+ всегда `5mb2.ru`):

- A / AAAA / NS / MX / TXT / www A  
- Сравнение с `VPS_PUBLIC_IP` (по умолчанию `80.78.248.195`)  
- Предупреждение, если NS ещё на `hosting.reg.ru`

Типичная ловушка: по IP с `Host: 5mb2.ru` сайт **живой**, а в браузере по имени — старый хостинг или пустой A.

Нужно у регистратора:
| Тип | Имя | Значение |
|---|---|---|
| A | `@` | IP VPS |
| A | `www` | IP VPS |
| NS | — | NS регистратора (не обязательно старый hosting.*) |

См. `DOMAIN_5mb2.md`. DeepSeek tool: `dns_lookup`.

---

## 8. Авторедактирование и обновления

### Когда обновляешь код (Cursor / git)
```bash
cd /opt/ai-helper
git pull origin cursor/complete-ai-helper-17f9
bash project/deploy/sync-5mb2-theme.sh
bash project/deploy/create-ai-site.sh
cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web
# watchdog (один раз)
sudo bash project/deploy/install-system-watchdog.sh
```

Или: `bash project/deploy/update.sh`

### Когда DeepSeek правил сайт
Файлы сразу на диске `/var/ai-helper/sites/...` — nginx отдаёт без rebuild.

### Когда DeepSeek правил бэкенд (`site=server`)
Файлы в `/opt/ai-helper/project` на хосте (volume).  
Чтобы API/панель подхватили: **rebuild/recreate** app (и web, если трогал Next).

### Watchdog
Сам перезапускает контейнеры при падении; код не переписывает без DeepSeek-ветки.

---

## 9. API (ключевые маршруты)

| Метод | Путь | Назначение |
|---|---|---|
| GET | `/status` | LLM, roots, version |
| GET | `/system/overview` | полный обзор для панели |
| GET | `/system/dns` | DNS всех доменов или `?domain=` |
| GET | `/system/health` | проверки |
| GET | `/system/incidents` | инциденты |
| POST | `/system/watchdog` | check + safe fix (+ DeepSeek) |
| GET/POST | `/feedback`, `/public/feedback` | inbox |
| POST | `/chat`, `/chat/stream` | агент |
| * | `/sites/*`, `/fs/*`, `/wp/*` | хостинг |

---

## 10. Tools DeepSeek (группы)

- **Файлы:** read/write/str_replace/list/find/search/apply_edits… (в workspace чата)
- **Хостинг:** site_status, wp_replace_urls, site_fix_perms, flatten, php_lint, nginx_test, site_health_check
- **DNS/система:** dns_lookup, system_overview
- **Команды:** run_command (cwd = workspace)
- **Self:** apply_self_improvement, self_update_check, …
- **Git:** git_run, diff_preview

Ограничение путей: sandbox `project_root` чата. Для сайтов — папка сайта; для `server` — `/opt/ai-helper/project`.

---

## 11. Что сделано по этапам (хронология работ)

1. **Хостинг на одном VPS** — Docker, сайты в `/var/ai-helper/sites`, панель Next, API.
2. **5mb2** — тема, SEO, кабинет, услуги, обратная связь, скрытие admin bar клиентам.
3. **AI Helper витрина** + публичный feedback в панель.
4. **DeepSeek** — ключ, tools для правок сайтов, free Ollama для лёгкого чата.
5. **Фикс падения после WP** — тема 1.9.6, mu-plugin health.
6. **Watchdog** — 3 поверхности, inbox, safe restart, DeepSeek repair.
7. **Обзор + DNS + бэкенд-workspace** — эта итерация (2.10.0): панель видит DNS/возможности; DeepSeek может править бэкенд через `site=server`.

---

## 12. Отложенное / на тебе

- Перенос DNS с `*.hosting.reg.ru` → NS регистратора + A на VPS; отмена старого хостинга.
- Реквизиты НПД в WP, SMTP (скрипты `setup-smtp-5mb2.sh` уже есть).
- Пароль панели `PANEL_PASSWORD` в `.env`, если ещё не задан.
- HTTPS панели по IP/отдельному домену — по желанию.

---

## 13. Быстрые проверки

```bash
curl -sS http://127.0.0.1:8502/status | head -c 400; echo
curl -sS http://127.0.0.1:8502/system/dns?domain=5mb2.ru -H "Authorization: Bearer …"
bash project/deploy/system-watchdog.sh
python3 -m unittest tests.test_system_health tests.test_system_overview -v
```

В UI: **Обзор** → DNS и матрица; **Здоровье** → watchdog; **Чат → server** → бэкенд.
