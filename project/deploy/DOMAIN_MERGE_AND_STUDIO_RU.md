# Объединение доменов + игровая студия SUNDUK

## Идея

| Сейчас | Цель |
|---|---|
| `neobrain.site` — AI-платформа | **Бизнес-дом** — NeoBrain + SEO-услуги 5MB2 |
| `5mb2.ru` — SEO WordPress | **SUNDUK** — игровая студия: игры, обзоры, анонсы |

Так один домен = деньги/продукт, второй = творчество и аудитория геймеров.

## Почему так

- NeoBrain уже на своём домене (API, `/console`, оплата, OAuth).
- 5mb2.ru освобождается под новый бренд студии без ломки AI.
- SEO-услуги 5MB2 переезжают разделом на NeoBrain (`/seo/` или поддомен `seo.neobrain.site`).

## Этапы (без спешки)

1. **Сейчас:** студия в репо `project/sites/sunduk/`, превью: `https://neobrain.site/sites/sunduk/` (после `create-sunduk-site.sh`).
2. **Перенос SEO:** страницы услуг/кейсов → раздел на NeoBrain; редиректы со старых URL 5mb2.
3. **DNS 5mb2.ru → SUNDUK:** когда контент SEO перенесён, `enable-sunduk.sh` вешает студию на `5mb2.ru` (или новый домен, если решите иначе).
4. **Редиректы:** старые коммерческие URL 5mb2 → соответствующие страницы NeoBrain.

## Бренд студии

**SUNDUK** — короткая вывеска (от «Сундуков»). Специализация: **мобильные игры** (iOS / Android) + web-демо.

## Структура сайта (`project/sites/sunduk/`)

| Раздел | Путь | Что внутри |
|---|---|---|
| Главная | `/` | Hero, игры, обзоры, анонсы |
| Каталог | `/games/` | Поиск, фильтры платформа/статус/жанр |
| Карточки игр | `/games/<slug>/` | Chest Dash (+ canvas), Neon Alley, Signal Deck, Pocket Forge |
| Играть | `/play/` | Браузерное демо Chest Dash |
| Обзоры | `/reviews/` + статьи | UX / процесс / подборки |
| Анонсы | `/news/` + статьи | Soft-launch, трейлеры, плейтесты |
| Студия | `/about/` | Специализация и принципы |
| Контакт | `/contact/` | Форма → mailto |
| Пресса | `/press/` | Press kit |
| PWA | `manifest.webmanifest`, `sw.js` | Установка на домашний экран |

Общий chrome: `assets/js/main.js` + `assets/css/main.css` (Syne / Figtree, lime–ember–teal).

## Деплой превью

```bash
cd /opt/ai-helper && git pull origin cursor/sunduk-studio-17f9
cd project/deploy
bash create-sunduk-site.sh
# открыть: https://neobrain.site/sites/sunduk/
# или:    http://IP/sites/sunduk/
```

На домен 5mb2.ru — только после переноса SEO (скрипт `enable-sunduk.sh`).
