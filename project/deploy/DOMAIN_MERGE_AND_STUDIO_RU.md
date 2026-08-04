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

**SUNDUK** — короткая вывеска (от «Сундуков»). Меняется в `index.html` (title, `.brand`, JSON-LD).

## Деплой превью

```bash
cd /opt/ai-helper && git pull origin cursor/sunduk-studio-17f9
cd project/deploy
bash create-sunduk-site.sh
# открыть: https://neobrain.site/sites/sunduk/
# или:    http://IP/sites/sunduk/
```

На домен 5mb2.ru — только после переноса SEO (скрипт `enable-sunduk.sh`).
