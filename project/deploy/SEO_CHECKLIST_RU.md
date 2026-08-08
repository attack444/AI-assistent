# SEO после деплоя темы 1.8.0

## На VPS

```bash
cd /opt/ai-helper
git pull origin cursor/complete-ai-helper-17f9
bash project/deploy/sync-5mb2-theme.sh
bash project/deploy/create-ai-site.sh
```

Проверки:

```bash
curl -sS https://5mb2.ru/robots.txt
curl -sS https://5mb2.ru/ | grep -E 'description|ld\+json|SEO-продвижение' | head
curl -sS https://5mb2.ru/cabinet/ | grep -i 'noindex'
curl -sS -o /dev/null -w '%{http_code}\n' https://5mb2.ru/sitemap_index.xml
```

## В Вебмастерах (бесплатно)

1. [Яндекс.Вебмастер](https://webmaster.yandex.ru/) — добавить `https://5mb2.ru`, подтвердить, указать sitemap `https://5mb2.ru/sitemap_index.xml`.
2. [Google Search Console](https://search.google.com/search-console) — то же.
3. Переобход главной и `/services/` после смены title.

## Что сделано в теме

- Title/description под SEO-агентство (вместо старого «цифровые решения»)
- JSON-LD: Organization, FAQ, Service, Article, BreadcrumbList
- noindex `/cabinet/`, `/spasibo/`
- robots.txt: закрыты кабинет и спасибо
- alt у карточек услуг, крошки, related posts
- AI Helper: meta/OG/schema
