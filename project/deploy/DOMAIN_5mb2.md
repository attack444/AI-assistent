# Домен 5mb2.ru на VPS

Цель: сайт открывается как **https://5mb2.ru/** (как на старом хостинге).  
Панель AI Helper остаётся на `http://IP/`.

## 1. DNS

У регистратора домена (где куплен 5mb2.ru):

| Тип | Имя | Значение |
|---|---|---|
| A | `@` | IP твоего VPS |
| A | `www` | IP твоего VPS |

Проверка (с любого ПК):

```bash
nslookup 5mb2.ru
```

Должен показать IP VPS (не старый хостинг).

## 2. Привязка на сервере

Имя папки сайта в панели (например `mysite`):

```bash
bash /opt/ai-helper/project/deploy/update.sh
bash /opt/ai-helper/project/deploy/setup-domain.sh mysite 5mb2.ru --ssl
```

Без SSL сначала:

```bash
bash /opt/ai-helper/project/deploy/setup-domain.sh mysite 5mb2.ru
# проверь http://5mb2.ru/
# потом:
bash /opt/ai-helper/project/deploy/setup-domain.sh mysite 5mb2.ru --ssl
```

Или в панели **Сайты** → **Домен** → введи `5mb2.ru`, затем всё равно запусти `setup-domain.sh` на VPS (из Docker nginx на хост сам не всегда пишется).

## 3. WordPress URL (когда будешь импортировать БД)

В **Настроить WP** → заменить URL:

- старый: что было раньше (или из БД)
- новый: `https://5mb2.ru`

## Важно

- Пока DNS смотрит на старый хостинг — с VPS сайт по домену не откроется.
- Переключай DNS, когда файлы на VPS уже на месте; импорт SQL можно сделать до или после.
- Старый хостинг выключай только после проверки https://5mb2.ru
