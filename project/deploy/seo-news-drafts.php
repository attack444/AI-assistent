#!/usr/bin/env php
<?php
/**
 * Авточерновики SEO-новостей для 5mb2 (не публикует само).
 *
 * Источники RSS → категория «Материалы» / опционально «Новости SEO» → status=draft.
 * Запуск на VPS (cron 1–2 раза в день):
 *   php /opt/ai-helper/project/deploy/seo-news-drafts.php
 *   DRY_RUN=1 php ...  — только показать, что нашлось
 *
 * Оформление: короткая выжимка + ссылка на источник. Без копипаста чужого текста целиком.
 */
declare(strict_types=1);

$sites = getenv('SITES_ROOT') ?: '/var/ai-helper/sites';
$wp = rtrim($sites, '/') . '/5mb2/wp-load.php';
if (!is_file($wp)) {
    fwrite(STDERR, "Нет wp-load: $wp\n");
    exit(1);
}

define('WP_USE_THEMES', false);
require $wp;

$dry = (string) (getenv('DRY_RUN') ?: '') === '1';
$max_new = (int) (getenv('SEO_NEWS_MAX') ?: 3);

$feeds = [
    'https://www.seonews.ru/rss/',
    'https://webmaster.yandex.ru/blog/rss',
    'https://developers.google.com/search/blog/feed.xml',
];

if (!term_exists('materialy', 'category')) {
    wp_insert_term('Материалы', 'category', ['slug' => 'materialy']);
}
if (!term_exists('novosti-seo', 'category')) {
    wp_insert_term('Новости SEO', 'category', ['slug' => 'novosti-seo']);
}
$mat = get_term_by('slug', 'materialy', 'category');
$news = get_term_by('slug', 'novosti-seo', 'category');
$cat_ids = array_values(array_filter([
    $mat ? (int) $mat->term_id : 0,
    $news ? (int) $news->term_id : 0,
]));

function mb2_news_http_get(string $url): string {
    if (function_exists('wp_remote_get')) {
        $r = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['User-Agent' => '5MB2-NewsBot/1.0 (+https://5mb2.ru/)'],
        ]);
        if (is_wp_error($r)) {
            return '';
        }
        return (string) wp_remote_retrieve_body($r);
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => 20, 'header' => "User-Agent: 5MB2-NewsBot/1.0\r\n"],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function mb2_news_parse_rss(string $xml): array {
    $items = [];
    if ($xml === '') {
        return $items;
    }
    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xml);
    if (!$sx) {
        return $items;
    }
    $nodes = [];
    if (isset($sx->channel->item)) {
        $nodes = $sx->channel->item;
    } elseif (isset($sx->entry)) {
        $nodes = $sx->entry;
    }
    foreach ($nodes as $it) {
        $title = trim(html_entity_decode((string) ($it->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $link = '';
        if (isset($it->link['href'])) {
            $link = (string) $it->link['href'];
        } elseif (isset($it->link)) {
            $link = (string) $it->link;
        }
        $link = trim($link);
        $summary = trim(strip_tags((string) ($it->description ?? $it->summary ?? $it->content ?? '')));
        $summary = preg_replace('/\s+/u', ' ', $summary) ?: '';
        if ($title === '' || $link === '') {
            continue;
        }
        $items[] = [
            'title'   => $title,
            'link'    => $link,
            'summary' => mb_substr($summary, 0, 400),
        ];
    }
    return $items;
}

function mb2_news_already(string $link): bool {
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => ['draft', 'publish', 'pending', 'future'],
        'posts_per_page' => 1,
        'meta_key'       => '_mb2_news_source',
        'meta_value'     => $link,
        'fields'         => 'ids',
    ]);
    return $q->have_posts();
}

function mb2_news_relevant(string $title, string $summary): bool {
    $t = mb_strtolower($title . ' ' . $summary);
    $keys = [
        'seo', 'search', 'яндекс', 'google', 'webmaster', 'вебмастер', 'ранжир',
        'индекс', 'алгоритм', 'core update', 'поисков', 'органическ', 'serp',
        'sitemap', 'robots', 'сниппет', 'local', 'карт',
    ];
    foreach ($keys as $k) {
        if (mb_strpos($t, $k) !== false) {
            return true;
        }
    }
    return false;
}

function mb2_news_body(array $item): string {
    $sum = $item['summary'] !== '' ? '<p>' . esc_html($item['summary']) . '</p>' : '';
    return $sum
        . '<p><strong>Что это значит для бизнеса:</strong> следим за изменениями в поиске и при необходимости '
        . 'корректируем технику, контент и структуру сайтов клиентов. Если новость затрагивает ваш проект — напишите в кабинете.</p>'
        . '<p>Источник: <a href="' . esc_url($item['link']) . '" target="_blank" rel="noopener noreferrer">'
        . esc_html($item['title']) . '</a></p>'
        . '<p><em>Черновик собран автоматически. Перед публикацией отредактируйте тон 5MB2 и уберите лишнее.</em></p>'
        . '<p><a href="' . esc_url(home_url('/#contact')) . '">Обсудить влияние на ваш сайт</a></p>';
}

$candidates = [];
foreach ($feeds as $feed) {
    $xml = mb2_news_http_get($feed);
    $parsed = mb2_news_parse_rss($xml);
    echo "[feed] $feed → " . count($parsed) . " items\n";
    foreach ($parsed as $item) {
        if (!mb2_news_relevant($item['title'], $item['summary'])) {
            continue;
        }
        if (mb2_news_already($item['link'])) {
            continue;
        }
        $candidates[] = $item;
    }
}

$created = 0;
foreach ($candidates as $item) {
    if ($created >= $max_new) {
        break;
    }
    $title = 'Обзор: ' . $item['title'];
    if (mb_strlen($title) > 120) {
        $title = mb_substr($title, 0, 117) . '…';
    }
    echo ($dry ? '[dry] ' : '[new] ') . $title . "\n  " . $item['link'] . "\n";
    if ($dry) {
        $created++;
        continue;
    }
    $id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => mb2_news_body($item),
        'post_status'  => 'draft',
        'post_type'    => 'post',
        'post_excerpt' => $item['summary'] !== '' ? mb_substr($item['summary'], 0, 160) : 'Авточерновик SEO-новости',
    ], true);
    if (is_wp_error($id)) {
        fwrite(STDERR, $id->get_error_message() . "\n");
        continue;
    }
    if ($cat_ids) {
        wp_set_post_categories((int) $id, $cat_ids);
    }
    update_post_meta((int) $id, '_mb2_news_source', $item['link']);
    update_post_meta((int) $id, '_mb2_news_auto', '1');
    $created++;
}

echo "Готово: " . ($dry ? "показано" : "черновиков") . " = $created (лимит $max_new). Проверьте Записи → Черновики в WP.\n";
