# Репозиторий: экосистема NeoBrain

Основная (каноническая) реализация продукта живёт в папке [`neobrain/`](neobrain/) — единый монорепозиторий на Node.js + Docker, объединяющий два сайта под одной инфраструктурой:

- **neobrain** — B2B SaaS: AI-агент (правки кода через git diff), SEO-инструмент, биллинг токенов в рублях.
- **5mb2.ru** — игровая студия: каталог игр, релизы/патчи/новости, галерея анимаций, «приколюшки», админ-CMS (контент без правки кода).

Общие: PostgreSQL, Redis, LLM-gateway, worker (BullMQ), единый вход (SSO), маршрутизация двух доменов через nginx. Полная архитектура и запуск — в [`neobrain/README.md`](neobrain/README.md).

## Быстрый старт

```bash
cd neobrain
cp .env.example .env
docker compose up -d --build
# neobrain: http://localhost/neobrain   игры: http://localhost/games
```

## Про папку `project/` (legacy)

`project/` — прежняя реализация (Python «AI Helper»: Streamlit + REST API + Ollama и WordPress-сайт `sites/5mb2` — SEO-агентство). Она **выводится из эксплуатации** в пользу `neobrain/`:

- SEO-функциональность бывшего 5mb2 перенесена в SaaS `neobrain` (автоматический аудит + генерация мета-тегов);
- домен `5mb2.ru` переориентирован на **игровую студию** внутри `neobrain`.

Код `project/` пока сохранён в истории git на случай, если понадобится что-то доперенести. Удалять его целиком — отдельный осознанный шаг (см. обсуждение в PR).
