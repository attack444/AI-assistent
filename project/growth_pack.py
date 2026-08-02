"""Пакет текстов для размещения NeoBrain / 5MB2 (каталоги, посты, питчи)."""
from __future__ import annotations

from typing import Any, Dict, List


def build_pack() -> Dict[str, Any]:
    neo = {
        "name": "NeoBrain",
        "url": "https://neobrain.site",
        "tagline": "AI для сайтов: деплой, чат и панель на своём сервере",
        "short": (
            "NeoBrain — платформа: загрузи сайт, правь файлы, чат с AI, "
            "виджет для клиентов. Тарифы Free / Starter / Pro."
        ),
        "long": (
            "NeoBrain помогает запускать и сопровождать сайты с AI-помощником: "
            "деплой ZIP/WordPress, файловый менеджер, чат с DeepSeek, "
            "публичный виджет и панель владельца. Всё на вашем VPS — данные у вас. "
            "Старт бесплатно, оплата тарифа через ЮKassa."
        ),
        "keywords": [
            "AI для сайта",
            "деплой WordPress AI",
            "чатбот для сайта",
            "панель управления сайтом",
            "DeepSeek сайт",
            "SaaS AI Россия",
        ],
    }
    mb2 = {
        "name": "5MB2 Digital",
        "url": "https://5mb2.ru",
        "tagline": "SEO-продвижение сайтов по России",
        "short": (
            "5MB2 — SEO-агентство: аудит, продвижение, локальное SEO, "
            "контент и прозрачная отчётность."
        ),
        "keywords": [
            "SEO продвижение",
            "SEO аудит",
            "продвижение сайта Россия",
            "локальное SEO",
        ],
    }

    channels: List[Dict[str, Any]] = [
        {
            "id": "yandex_webmaster",
            "title": "Яндекс.Вебмастер",
            "auto": False,
            "action": "Добавить оба сайта, sitemap, переобход главной",
            "urls": ["https://webmaster.yandex.ru/"],
        },
        {
            "id": "gsc",
            "title": "Google Search Console",
            "auto": False,
            "action": "Добавить оба сайта, sitemap, URL inspection",
            "urls": ["https://search.google.com/search-console"],
        },
        {
            "id": "metrika_ga",
            "title": "Метрика + GA4",
            "auto": False,
            "action": "Счётчики → панель Настройки (уже подключены к сайтам)",
            "urls": ["https://metrika.yandex.ru/", "https://analytics.google.com/"],
        },
        {
            "id": "vc_ru",
            "title": "VC.ru — экспертный пост",
            "auto": False,
            "action": "Статья: «Как мы держим AI и WordPress на одном VPS» + ссылка NeoBrain",
            "template": (
                f"# {neo['tagline']}\n\n{neo['long']}\n\n"
                f"Попробовать: {neo['url']}\nSEO-услуги: {mb2['url']}\n"
            ),
            "urls": ["https://vc.ru/"],
        },
        {
            "id": "habr",
            "title": "Habr (песочница / блог компании)",
            "auto": False,
            "action": "Технический разбор: nginx vhost, панель /console, watchdog",
            "urls": ["https://habr.com/"],
        },
        {
            "id": "telegram",
            "title": "Telegram-каналы / чаты",
            "auto": False,
            "action": "Индихакеры, AI builders, SEO-чаты — ценность, не спам",
            "pitch": f"{neo['tagline']}. Старт: {neo['url']}",
        },
        {
            "id": "tenchat",
            "title": "TenChat",
            "auto": False,
            "action": "Короткий пост + кейс 5MB2",
            "urls": ["https://tenchat.ru/"],
        },
        {
            "id": "avito",
            "title": "Авито / услуги",
            "auto": False,
            "action": "Услуга «SEO под ключ» + «Настройка AI на VPS» с ссылкой",
            "urls": ["https://www.avito.ru/"],
        },
        {
            "id": "directories",
            "title": "Каталоги SaaS / стартапов",
            "auto": "semi",
            "action": "Один раз заполнить карточку (текст ниже). Авто-спам ботами — нельзя.",
            "list": [
                "https://www.producthunt.com/",
                "https://www.betalist.com/",
                "https://alternativeto.net/",
                "https://startupstash.com/",
                "https://www.saashub.com/",
                "Яндекс Бизнес / Google Business (для 5MB2)",
            ],
            "card": {
                "name": neo["name"],
                "tagline": neo["tagline"],
                "description": neo["short"],
                "website": neo["url"],
                "categories": ["AI", "Developer Tools", "Website Builder"],
            },
        },
        {
            "id": "seo_content",
            "title": "Контент 5MB2 (авточерновики)",
            "auto": True,
            "action": "Панель → SEO → черновики новостей / cron install-seo-cron.sh",
            "note": "Публикация руками — качество > количество",
        },
        {
            "id": "partners",
            "title": "Партнёрки",
            "auto": False,
            "action": "Веб-студии: white-label NeoBrain; SEO-фрилансеры → лиды 5MB2",
        },
    ]

    weekly = [
        "Пн: 1 пост Telegram + проверка Вебмастер/GSC",
        "Ср: черновик VC/Habr или кейс клиента",
        "Пт: 1 каталог SaaS + правки SEO-страниц NeoBrain",
        "Ежедневно 10 мин: утвердить 1 WP-черновик 5MB2",
    ]

    dont = [
        "Массовый автопост одинакового текста в 50 каталогов — бан и минус к SEO",
        "Покупные ссылки с мусорных сетей",
        "Накрутка поведенческих в Метрике",
        "Спам в чужих чатах без пользы",
    ]

    return {
        "ok": True,
        "brand": neo,
        "agency": mb2,
        "channels": channels,
        "weekly_rhythm": weekly,
        "dont": dont,
        "cta": {
            "primary": neo["url"],
            "secondary": mb2["url"],
            "panel": "https://neobrain.site/console/",
        },
    }
