# Если в VS Code: `enable-neobrain.sh: No such file`

Скрипт лежит **только** в ветке `cursor/neobrain-launch-17f9` (ещё не в `main`).

## Точные команды (копируй целиком)

```bash
cd /opt/ai-helper
git fetch origin
git checkout cursor/neobrain-launch-17f9
git pull origin cursor/neobrain-launch-17f9

ls -la project/deploy/enable-neobrain.sh
ls -la project/deploy/fix-neobrain-vhost.sh

bash project/deploy/create-ai-site.sh
cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

# сертификат NeoBrain (не путать с 5mb2)
CERTBOT_EMAIL=ТВОЙ@MAIL.ru sudo bash /opt/ai-helper/project/deploy/fix-neobrain-vhost.sh
```

Проверка:

```bash
curl -sk https://neobrain.site/ | grep -o 'NeoBrain' | head -1
echo | openssl s_client -connect neobrain.site:443 -servername neobrain.site 2>/dev/null | openssl x509 -noout -subject
```

Должно быть: в HTML `NeoBrain`, в сертификате `CN = neobrain.site` (не `5mb2.ru`).

## DNS, которые ещё нужны в reg.ru

| Домен | Запись | Значение |
|---|---|---|
| neobrain.site | A `@` | 80.78.248.195 ✅ уже |
| neobrain.site | A `www` | 80.78.248.195 ✅ |
| neobrain.site | A **`panel`** | 80.78.248.195 ← добавь |
| 5mb2.ru | A **`@`** | 80.78.248.195 ← сейчас пусто, есть только www |
| 5mb2.ru | A `www` | 80.78.248.195 ✅ |

После A для 5mb2:

```bash
sudo bash /opt/ai-helper/project/deploy/repair-https-5mb2.sh
# или
sudo bash /opt/ai-helper/project/deploy/enable-https-5mb2.sh
```

## Про ЮKassa «после soft launch» — простыми словами

**Сейчас можно открывать NeoBrain людям** (регистрация, Free, чат, деплой) и писать в рекламе/Telegram — это и есть soft launch.

**Автооплату картой подключать не обязательно сразу.** Пока ключей ЮKassa нет:
- человек регистрируется на Free;
- если хочет Starter/Pro — пишет тебе;
- ты активируешь план вручную в панели.

Когда будешь готов к автооплате — впишешь `YOOKASSA_SHOP_ID` и `YOOKASSA_SECRET_KEY` в `.env`, перезапустишь app. До этого продвижение **не ждёт** ЮKassa.
