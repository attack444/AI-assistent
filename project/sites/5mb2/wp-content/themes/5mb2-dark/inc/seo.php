<?php
/**
 * Техническое SEO 5MB2: title/description, noindex, JSON-LD, хлебные крошки.
 * Работает вместе с Rank Math (фильтры) и даёт fallback без плагина.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Карта SEO для ключевых страниц (slug => [title, description]) */
function mb2_seo_page_map() {
    $brand = '5MB2 Digital';
    return [
        'home' => [
            'title' => 'SEO-продвижение сайтов по России | ' . $brand,
            'desc'  => 'SEO-аудит, продвижение и Local SEO для бизнеса. Прозрачные цены от 29 000 ₽, кабинет с чеклистом работ, самозанятый (НПД).',
        ],
        'services' => [
            'title' => 'Услуги SEO: аудит, продвижение, Local SEO | ' . $brand,
            'desc'  => 'Каталог SEO-услуг 5MB2: аудит от 29 000 ₽, продвижение от 55 000 ₽/мес, Local SEO, техника и контент. Заявка онлайн.',
        ],
        'instrumenty' => [
            'title' => 'Бесплатные SEO-инструменты онлайн | ' . $brand,
            'desc'  => 'Проверка Title/Description, генератор UTM, калькулятор бюджета SEO и быстрая проверка сайта. Без регистрации.',
        ],
        'kejsy' => [
            'title' => 'Проекты и SEO-разборы | ' . $brand,
            'desc'  => 'Кейс VitrA Russia, учебные разборы аудита и живой проект 5mb2.ru. Честно, без выдуманных процентов роста.',
        ],
        'materialy' => [
            'title' => 'Материалы про SEO | ' . $brand,
            'desc'  => 'Статьи о продвижении, Local SEO, бюджетах и старте SEO в 2026 году — коротко и по делу.',
        ],
        'o-nas' => [
            'title' => 'О 5MB2 Digital — SEO для бизнеса | ' . $brand,
            'desc'  => 'Вячеслав Сундуков, самозанятый SEO-специалист. Метод: диагностика → стратегия → кабинет и рост.',
        ],
        'contacts' => [
            'title' => 'Контакты | ' . $brand,
            'desc'  => 'Связаться с 5MB2: заявка на SEO-аудит или продвижение, email и VK.',
        ],
        'rekvizity' => [
            'title' => 'Реквизиты для оплаты | ' . $brand,
            'desc'  => 'Банковские реквизиты самозанятого 5MB2 Digital: счёт Сбербанка, БИК, корр. счёт.',
        ],
        'cabinet' => [
            'title' => 'Личный кабинет клиента | ' . $brand,
            'desc'  => 'Кабинет клиента 5MB2: чеклист SEO, заявки и отчёты.',
        ],
        'spasibo' => [
            'title' => 'Спасибо за заявку | ' . $brand,
            'desc'  => 'Заявка принята. Мы свяжемся с вами.',
        ],
        'oferta' => [
            'title' => 'Публичная оферта | ' . $brand,
            'desc'  => 'Публичная оферта на SEO-услуги 5MB2 Digital (самозанятый, НПД).',
        ],
        'privacy-policy' => [
            'title' => 'Политика конфиденциальности | ' . $brand,
            'desc'  => 'Политика обработки персональных данных 5MB2 Digital (152-ФЗ).',
        ],
        'seo-audit' => [
            'title' => 'SEO-аудит сайта — от 29 000 ₽ | ' . $brand,
            'desc'  => 'Технический SEO-аудит: индекс, скорость, конкуренты, приоритетный план правок. Срок 5–10 рабочих дней.',
        ],
        'prodvizhenie' => [
            'title' => 'SEO-продвижение сайтов — от 55 000 ₽/мес | ' . $brand,
            'desc'  => 'Ежемесячное SEO: семантика, структура, контент, отчёт в кабинете. От 3 месяцев.',
        ],
        'local-seo' => [
            'title' => 'Local SEO — заявки из вашего города | ' . $brand,
            'desc'  => 'Локальное SEO: карты, региональные посадочные, отзывы и NAP. От 40 000 ₽/мес.',
        ],
        'tech-seo' => [
            'title' => 'Техническое SEO — от 35 000 ₽ | ' . $brand,
            'desc'  => 'Core Web Vitals, индексация, Schema, миграции без просадки. Техническое SEO под ключ.',
        ],
        'kontent' => [
            'title' => 'Контент для SEO — от 4 500 ₽/стр | ' . $brand,
            'desc'  => 'Тексты под спрос и E-E-A-T: услуги, статьи, коммерческие блоки с ТЗ по семантике.',
        ],
        'otchetnost' => [
            'title' => 'Отчётность и сопровождение SEO | ' . $brand,
            'desc'  => 'Позиции, трафик и заявки в личном кабинете. Прозрачная коммуникация без «магии».',
        ],
    ];
}

function mb2_seo_current_slug() {
    if (is_front_page()) {
        return 'home';
    }
    if (is_singular('page')) {
        $slug = get_post_field('post_name', get_queried_object_id());
        $svc = get_post_meta(get_queried_object_id(), '_mb2_service_slug', true);
        return $svc ?: $slug;
    }
    if (is_singular('post')) {
        return '';
    }
    if (is_category()) {
        $t = get_queried_object();
        return $t ? $t->slug : '';
    }
    return '';
}

function mb2_seo_lookup() {
    $map = mb2_seo_page_map();
    $slug = mb2_seo_current_slug();
    if ($slug && isset($map[$slug])) {
        return $map[$slug];
    }
    return null;
}

/** Идентичность сайта — только если пусто/дефолт WP (не затираем правки в админке). */
function mb2_seo_apply_identity() {
    $name = (string) get_option('blogname');
    $desc = (string) get_option('blogdescription');
    if ($name === '' || $name === 'WordPress' || $name === 'Мой сайт') {
        update_option('blogname', '5MB2 Digital');
    }
    if ($desc === '' || $desc === 'Ещё один сайт на WordPress') {
        update_option('blogdescription', 'SEO-продвижение сайтов по России: аудит, Local SEO, техника');
    }
}

/** Записать Rank Math meta только если поля ещё пустые — не откатывать правки владельца. */
function mb2_seo_seed_rank_math() {
    $map = mb2_seo_page_map();
    foreach ($map as $slug => $meta) {
        if ($slug === 'home') {
            continue;
        }
        $page = get_page_by_path($slug);
        if (!$page && in_array($slug, ['seo-audit', 'prodvizhenie', 'local-seo', 'tech-seo', 'kontent', 'otchetnost'], true)) {
            $page = get_page_by_path('services/' . $slug);
        }
        if (!$page) {
            continue;
        }
        foreach ([
            'rank_math_title' => $meta['title'],
            'rank_math_description' => $meta['desc'],
            '_rank_math_title' => $meta['title'],
            '_rank_math_description' => $meta['desc'],
        ] as $key => $val) {
            $cur = get_post_meta($page->ID, $key, true);
            if ($cur === '' || $cur === false || $cur === null) {
                update_post_meta($page->ID, $key, $val);
            }
        }
    }

    // Homepage titles в опциях Rank Math — только пустые ключи
    if (class_exists('RankMath') || defined('RANK_MATH_VERSION')) {
        $titles = get_option('rank-math-options-titles', []);
        if (!is_array($titles)) {
            $titles = [];
        }
        $changed = false;
        if (empty($titles['homepage_title'])) {
            $titles['homepage_title'] = $map['home']['title'];
            $changed = true;
        }
        if (empty($titles['homepage_description'])) {
            $titles['homepage_description'] = $map['home']['desc'];
            $changed = true;
        }
        if (empty($titles['title_separator'])) {
            $titles['title_separator'] = '|';
            $changed = true;
        }
        if ($changed) {
            update_option('rank-math-options-titles', $titles, false);
        }
    }
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
});

add_filter('document_title_parts', function ($parts) {
    $seo = mb2_seo_lookup();
    if ($seo) {
        $parts['title'] = preg_replace('/\s*\|\s*5MB2 Digital$/u', '', $seo['title']);
        $parts['site'] = '5MB2 Digital';
    } elseif (is_singular('post')) {
        $parts['site'] = '5MB2 Digital';
    }
    return $parts;
});

add_filter('rank_math/frontend/title', function ($title) {
    $seo = mb2_seo_lookup();
    return $seo ? $seo['title'] : $title;
});

add_filter('rank_math/frontend/description', function ($desc) {
    $seo = mb2_seo_lookup();
    if ($seo) {
        return $seo['desc'];
    }
    if (is_singular('post') && (!$desc || strlen($desc) < 40)) {
        $ex = get_the_excerpt();
        if ($ex) {
            return wp_strip_all_tags($ex);
        }
    }
    return $desc;
});

/** Fallback meta description / OG, если плагин не вывел */
add_action('wp_head', function () {
    if (defined('RANK_MATH_VERSION') && !is_front_page()) {
        // Rank Math обычно закрывает; на главной всё равно дублируем через фильтры
    }

    $seo = mb2_seo_lookup();
    $desc = $seo['desc'] ?? '';
    if (!$desc && is_singular()) {
        $desc = wp_strip_all_tags(get_the_excerpt() ?: wp_trim_words(wp_strip_all_tags(get_post_field('post_content', get_the_ID())), 28));
    }
    if ($desc && !mb2_seo_has_output('name="description"')) {
        echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
    }

    $url = is_singular() ? get_permalink() : home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    if (is_front_page()) {
        $url = home_url('/');
    }
    if (!mb2_seo_has_output('rel="canonical"')) {
        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
    }

    $title = wp_get_document_title();
    $img = get_template_directory_uri() . '/assets/img/slide-1.jpg';
    if (is_singular() && has_post_thumbnail()) {
        $t = wp_get_attachment_image_url(get_post_thumbnail_id(), 'large');
        if ($t) {
            $img = $t;
        }
    }
    if (!mb2_seo_has_output('property="og:title"')) {
        echo '<meta property="og:locale" content="ru_RU" />' . "\n";
        echo '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        if ($desc) {
            echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="5MB2 Digital" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        if ($desc) {
            echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
        }
    }
}, 2);

function mb2_seo_has_output($needle) {
    // При Rank Math title/description/OG уже выводятся плагином (мы правим их фильтрами).
    // Canonical и fallback meta без плагина — оставляем.
    if (!defined('RANK_MATH_VERSION')) {
        return false;
    }
    return true;
}

/** noindex: кабинет и спасибо */
add_filter('wp_robots', function ($robots) {
    if (is_page(['cabinet', 'spasibo'])) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        unset($robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview']);
    }
    return $robots;
});

add_filter('rank_math/frontend/robots', function ($robots) {
    if (is_page(['cabinet', 'spasibo'])) {
        return [
            'index'  => 'noindex',
            'follow' => 'nofollow',
        ];
    }
    return $robots;
});

/** JSON-LD */
add_action('wp_head', function () {
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => ['Organization', 'ProfessionalService'],
        'name'     => '5MB2 Digital',
        'url'      => home_url('/'),
        'logo'     => get_template_directory_uri() . '/assets/img/slide-1.jpg',
        'email'    => 'hello@5mb2.ru',
        'sameAs'   => ['https://vk.com/5mb2online'],
        'areaServed' => 'RU',
        'description' => 'SEO-продвижение сайтов по России: аудит, Local SEO, техническое SEO и контент.',
        'priceRange' => '₽₽',
        'founder'  => [
            '@type' => 'Person',
            'name'  => 'Вячеслав Сундуков',
        ],
    ];

    $graph = [$org];

    if (is_front_page()) {
        $graph[] = [
            '@type' => 'WebSite',
            'name'  => '5MB2 Digital',
            'url'   => home_url('/'),
            'publisher' => ['@type' => 'Organization', 'name' => '5MB2 Digital'],
            'inLanguage' => 'ru-RU',
        ];
        $graph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name'  => 'Как заказать услугу SEO?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => 'Откройте нужную услугу на сайте, заполните форму заявки или оставьте контакты в кабинете — мы свяжемся со следующими шагами.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name'  => 'Сколько ждать результат SEO?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => 'Первые движения обычно через 1–3 месяца. Для устойчивого роста нужно от 3–6 месяцев регулярной работы.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name'  => 'Нужен ли доступ к сайту?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => 'Да — к админке или через вашего разработчика. Статус задач видно в личном кабинете 5MB2.',
                    ],
                ],
            ],
        ];
    }

    if (is_page() && get_post_meta(get_the_ID(), '_mb2_service_slug', true) && function_exists('mb2_get_service')) {
        $slug = get_post_meta(get_the_ID(), '_mb2_service_slug', true);
        $svc = mb2_get_service($slug);
        if ($svc) {
            $offer = [
                '@type'         => 'Offer',
                'priceCurrency' => 'RUB',
                'description'   => trim($svc['price'] . ($svc['term'] ? ', срок: ' . $svc['term'] : '')),
                'url'           => get_permalink(),
            ];
            $digits = preg_replace('/\D+/', '', $svc['price'] ?? '');
            if ($digits !== '') {
                $offer['price'] = $digits;
            }
            $graph[] = [
                '@type'       => 'Service',
                'name'        => $svc['title'],
                'description' => $svc['short'],
                'provider'    => ['@type' => 'Organization', 'name' => '5MB2 Digital'],
                'areaServed'  => 'RU',
                'offers'      => $offer,
            ];
        }
    }

    if (is_singular('post')) {
        $graph[] = [
            '@type'         => 'Article',
            'headline'      => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified'  => get_the_modified_date('c'),
            'author'        => ['@type' => 'Person', 'name' => 'Вячеслав Сундуков'],
            'publisher'     => ['@type' => 'Organization', 'name' => '5MB2 Digital', 'url' => home_url('/')],
            'mainEntityOfPage' => get_permalink(),
            'description'   => wp_strip_all_tags(get_the_excerpt()),
        ];
    }

    $crumbs = mb2_seo_breadcrumbs_data();
    if (count($crumbs) > 1) {
        $items = [];
        foreach ($crumbs as $i => $c) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $c['name'],
                'item'     => $c['url'],
            ];
        }
        $graph[] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 30);

function mb2_seo_breadcrumbs_data() {
    $crumbs = [['name' => 'Главная', 'url' => home_url('/')]];
    if (is_front_page()) {
        return $crumbs;
    }
    if (is_page()) {
        $ancestors = array_reverse(get_post_ancestors(get_the_ID()));
        foreach ($ancestors as $aid) {
            $crumbs[] = ['name' => get_the_title($aid), 'url' => get_permalink($aid)];
        }
        $crumbs[] = ['name' => get_the_title(), 'url' => get_permalink()];
        return $crumbs;
    }
    if (is_singular('post')) {
        $cats = get_the_category();
        if ($cats) {
            $c = $cats[0];
            $crumbs[] = ['name' => $c->name, 'url' => get_category_link($c->term_id)];
        }
        $crumbs[] = ['name' => get_the_title(), 'url' => get_permalink()];
    }
    return $crumbs;
}

function mb2_render_breadcrumbs() {
    $crumbs = mb2_seo_breadcrumbs_data();
    if (count($crumbs) < 2) {
        return;
    }
    echo '<nav class="crumb muted tiny reveal is-in" aria-label="Хлебные крошки">';
    foreach ($crumbs as $i => $c) {
        if ($i > 0) {
            echo ' · ';
        }
        if ($i === count($crumbs) - 1) {
            echo '<span>' . esc_html($c['name']) . '</span>';
        } else {
            echo '<a class="text-link" href="' . esc_url($c['url']) . '">' . esc_html($c['name']) . '</a>';
        }
    }
    echo '</nav>';
}
