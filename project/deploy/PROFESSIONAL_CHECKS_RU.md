# Профессиональные проверки NeoBrain (что гоняем и зачем)

Не путать с уже сделанным bughunt 2.15 (порты, compose, redirect). Ниже — продуктовые и security-проверки «как у живого SaaS».

## 1. Аутентификация и кабинет

| Проверка | Зачем | Статус в коде |
|---|---|---|
| Пароль задаёт пользователь при регистрации | Нет «случайного» пароля, который никто не знает | ✅ `public_users.register` + UI |
| Вход только с тем же паролем | Логика кабинета | ✅ PBKDF2 + rate-limit |
| Смена пароля в кабинете | Пользователь управляет доступом | ✅ `POST /public/auth/password` |
| OAuth-only может задать пароль без «текущего» | Google/GitHub без локального пароля | ✅ |
| Панель: только `PANEL_PASSWORD` из `.env` | Владелец сам задаёт пароль панели | ✅ нет автогена по умолчанию |
| Подтверждение пароля (повтор) | Опечатки при регистрации/смене | ✅ UI |
| Rate-limit login/register | Brute-force | ✅ |
| Turnstile на register/pay | Боты | ⚙️ нужно ключи в Настройках |
| OAuth Google/GitHub | Вход без пароля | ⚙️ код готов, нужны Client ID/Secret |
| Сессия: logout + expiry | Утечка токена | ✅ |
| Оплата только на email сессии | Нельзя купить план «чужому» email | ✅ |

## 2. Интеграции (живой статус до деплоя 2.16)

Проверено снаружи `2026-08`:

| Сервис | Live | Что сделать |
|---|---|---|
| DeepSeek | ✅ `deepseek: true` в `/api/status` | Работает; модель `deepseek-coder` |
| Ollama (free LLM) | ✅ | Fallback/бесплатный слой |
| ЮKassa | ❌ `yookassa_ready: false` | Панель → Настройки → shopId + secret |
| SMTP | ❌ (до 2.16 флаг `smtp_ready`) | host/user/password в Настройках |
| Google OAuth | ❌ | Google Cloud OAuth + callback URL |
| GitHub OAuth | ❌ | GitHub OAuth App + callback URL |
| Turnstile | ❌ пустой site key | Cloudflare Turnstile |
| Метрика / GA | ❌ пустые | ID в Настройках |
| Сайты `neobrain.site`, `/console`, `5mb2.ru` | ✅ HTTP 200 | — |

Callback URLs (вписать у провайдеров):

- Google: `https://neobrain.site/api/public/auth/oauth/google/callback`
- GitHub: `https://neobrain.site/api/public/auth/oauth/github/callback`

## 3. Другие профессиональные проверки (чеклист)

### Безопасность
- [ ] Порты 3000/8502/3306/9000 только `127.0.0.1` + UFW deny (`harden-vps.sh`)
- [ ] `SECRET_KEY` не дефолтный
- [ ] Webhook ЮKassa сверяет платёж через API (уже в коде)
- [ ] Нет секретов в git / в HTML витрины
- [ ] HTTPS only, HSTS на vhost
- [ ] CSP / X-Frame-Options на nginx (желательно усилить)
- [ ] 2FA панели (ещё нет — в backlog)

### Логика продукта
- [ ] Регистрация → вход тем же паролем → чат → деплой
- [ ] Смена пароля → старый не пускает
- [ ] Оплата Starter/Pro → план в `/public/auth/me`
- [ ] Лимиты free исчерпаны → понятная ошибка
- [ ] Owner email всегда план owner
- [ ] Виджет 5mb2 guest chat без ломания auth витрины

### Работоспособность / конфликты
- [ ] `/console` без redirect loop
- [ ] 401 панели → `/console/login/`, не витрина
- [ ] API rewrite Next `basePath: false`
- [ ] `create-ai-site.sh` после деплоя синхронизирует `index.html`
- [ ] Cron SEO/news/watchdog не конфликтуют с ручными правками

### Наблюдаемость
- [ ] `/system/health`, incidents, overview в панели
- [ ] Watchdog с `WATCHDOG_TOKEN`
- [ ] Логи app: `PANEL_PASSWORD задан вручную`

## 4. Как прогнать локально/на VPS

```bash
cd /opt/ai-helper/project
python3 -m unittest discover -s tests -v

# После pull 2.16:
sudo bash deploy/reset-panel-password.sh 'твой_пароль_панели'
sudo bash deploy/harden-vps.sh   # если PANEL_PASSWORD уже в .env
cd deploy && docker compose --env-file ../.env -f docker-compose.prod.yml build app web
docker compose --env-file ../.env -f docker-compose.prod.yml up -d --force-recreate
bash create-ai-site.sh

curl -s https://neobrain.site/api/status | jq '{version,deepseek,yookassa:.}'
curl -s https://neobrain.site/api/public/config | jq .
curl -s https://neobrain.site/api/public/auth/oauth/status | jq .
```

Ручной сценарий кабинета: регистрация → выход → вход → «Пароль» → смена → вход новым → (опц.) Google/GitHub.
