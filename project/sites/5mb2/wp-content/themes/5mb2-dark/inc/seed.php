<?php
/**
 * Наполнение: услуги, материалы, кейсы, служебные страницы.
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_ensure_site_structure() {
    update_option('show_on_front', 'posts');
    delete_option('page_on_front');

    mb2_ensure_terms();
    mb2_ensure_core_pages();
    mb2_ensure_service_pages();
    mb2_ensure_sample_posts();
    mb2_ensure_menus();
}

function mb2_ensure_terms() {
    if (!term_exists('materialy', 'category')) {
        wp_insert_term('Материалы', 'category', ['slug' => 'materialy']);
    }
    if (!term_exists('kejsy', 'category')) {
        wp_insert_term('Кейсы', 'category', ['slug' => 'kejsy']);
    }
}

function mb2_upsert_page($slug, $title, $content, $template = '', $parent = 0) {
    $path = $slug;
    if ($parent) {
        $parent_post = get_post($parent);
        if ($parent_post) {
            $path = $parent_post->post_name . '/' . $slug;
        }
    }
    $existing = get_page_by_path($path);
    if (!$existing) {
        $existing = get_page_by_path($slug);
    }

    if ($existing) {
        $id = (int) $existing->ID;
        wp_update_post([
            'ID'           => $id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_parent'  => $parent,
            'post_status'  => 'publish',
            'post_name'    => $slug,
        ]);
    } else {
        $id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $content,
            'post_parent'  => $parent,
        ]);
        if (is_wp_error($id)) {
            return 0;
        }
    }
    if ($template) {
        update_post_meta($id, '_wp_page_template', $template);
    }
    return (int) $id;
}

function mb2_ensure_core_pages() {
    mb2_upsert_page('cabinet', 'Личный кабинет', '', 'templates/cabinet.php');
    mb2_upsert_page(
        'privacy-policy',
        'Политика конфиденциальности',
        '<p>Мы обрабатываем контактные данные (имя, email, телефон, URL сайта) только для связи по заявке на SEO-услуги 5MB2 Digital. Данные не продаём третьим лицам.</p><p>По вопросам: <a href="mailto:hello@5mb2.ru">hello@5mb2.ru</a>.</p>'
    );
    mb2_upsert_page(
        'contacts',
        'Контакты',
        '<p>Агентство <strong>5MB2 Digital</strong> — SEO-продвижение сайтов по России.</p><p>Email: <a href="mailto:hello@5mb2.ru">hello@5mb2.ru</a><br>VK: <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">vk.com/5mb2online</a></p><p><a href="' . esc_url(home_url('/#contact')) . '">Оставить заявку</a> · <a href="' . esc_url(home_url('/cabinet/')) . '">Личный кабинет</a></p>'
    );
    mb2_upsert_page('spasibo', 'Спасибо за заявку', '', 'templates/thanks.php');
    mb2_upsert_page('materialy', 'Материалы', '', 'templates/materials.php');
    mb2_upsert_page('kejsy', 'Кейсы', '', 'templates/cases.php');
    mb2_upsert_page(
        'o-nas',
        'О агентстве',
        '<p><strong>5MB2 Digital</strong> — SEO-команда для бизнеса, которому нужны заявки из поиска, а не отчёты ради отчётов.</p><p>Работаем прозрачно: диагностика → стратегия → реализация → рост. Клиенты видят прогресс в личном кабинете.</p><p><a href="' . esc_url(home_url('/#contact')) . '">Обсудить проект</a></p>'
    );
}

function mb2_ensure_service_pages() {
    $hub_id = mb2_upsert_page(
        'services',
        'Услуги SEO',
        '<p>Выберите услугу — на странице есть описание, сроки и кнопка заявки.</p>',
        'templates/services.php'
    );
    if (!$hub_id) {
        return;
    }
    foreach (mb2_services_catalog() as $slug => $svc) {
        // Контент рисует templates/service.php из каталога — в записи только краткий абзац для SEO/поиска.
        $id = mb2_upsert_page($slug, $svc['title'], $svc['body'], 'templates/service.php', $hub_id);
        if ($id) {
            update_post_meta($id, '_mb2_service_slug', $slug);
        }
    }
}

function mb2_ensure_sample_posts() {
    if (get_option('mb2_sample_posts_v2')) {
        return;
    }

    $mat = get_term_by('slug', 'materialy', 'category');
    $case = get_term_by('slug', 'kejsy', 'category');
    $mat_id = $mat ? (int) $mat->term_id : 0;
    $case_id = $case ? (int) $case->term_id : 0;

    $posts = [
        [
            'title' => 'С чего начать SEO в 2026 году',
            'slug'  => 's-chego-nachat-seo-2026',
            'cat'   => $mat_id,
            'body'  => '<p>Сначала — цели бизнеса и аудит. Затем семантика и структура. Контент и ссылки работают только на чистом техническом фундаменте.</p><p>В 5MB2 мы начинаем с диагностики и плана на 90 дней — без обещаний «топ за неделю».</p>',
        ],
        [
            'title' => 'Чем SEO отличается от контекстной рекламы',
            'slug'  => 'seo-vs-kontekst',
            'cat'   => $mat_id,
            'body'  => '<p>Реклама даёт трафик сразу, пока есть бюджет. SEO накапливает актив: страницы продолжают приносить заявки месяцами.</p><p>Оптимально сочетать оба канала: реклама — на быстрый спрос, SEO — на устойчивость.</p>',
        ],
        [
            'title' => 'Local SEO: как получать заявки из своего города',
            'slug'  => 'local-seo-zayavki-iz-goroda',
            'cat'   => $mat_id,
            'body'  => '<p>Карточки на картах, единый NAP, региональные посадочные и отзывы — база локального продвижения.</p><p>Если вы работаете в одном городе или сети точек, Local SEO часто даёт заявки быстрее «общего» продвижения.</p>',
        ],
        [
            'title' => 'Кейс: рост органики интернет-магазина',
            'slug'  => 'kejs-rost-organiki-magazina',
            'cat'   => $case_id,
            'body'  => '<p><strong>Задача:</strong> увеличить органический трафик и заявки без роста бюджета на рекламу.</p><p><strong>Что сделали:</strong> технический аудит, пересборка категорий, контент под спрос, внутренняя перелинковка.</p><p><strong>Результат (ориентир):</strong> +120–180% к органике за 4–6 месяцев при выполнении рекомендаций. Точные цифры зависят от ниши.</p>',
        ],
        [
            'title' => 'Кейс: заявки для локальной услуги',
            'slug'  => 'kejs-local-usluga',
            'cat'   => $case_id,
            'body'  => '<p><strong>Задача:</strong> заявки из Яндекса и карт в одном городе.</p><p><strong>Что сделали:</strong> карточка организации, посадочные под районы, коммерческие блоки, отзывы.</p><p><strong>Результат (ориентир):</strong> стабильный поток обращений через 6–10 недель.</p>',
        ],
    ];

    foreach ($posts as $p) {
        if (get_page_by_path($p['slug'], OBJECT, 'post')) {
            continue;
        }
        $existing = get_posts([
            'name'        => $p['slug'],
            'post_type'   => 'post',
            'post_status' => 'any',
            'numberposts' => 1,
        ]);
        if ($existing) {
            continue;
        }
        $id = wp_insert_post([
            'post_title'   => $p['title'],
            'post_name'    => $p['slug'],
            'post_content' => $p['body'],
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);
        if ($id && !is_wp_error($id) && $p['cat']) {
            wp_set_post_categories($id, [$p['cat']]);
        }
    }

    update_option('mb2_sample_posts_v2', 1, false);
}

function mb2_ensure_menus() {
    $home = trailingslashit(home_url('/'));
    $primary = [
        ['title' => 'Услуги', 'url' => $home . 'services/'],
        ['title' => 'Как работаем', 'url' => $home . '#process'],
        ['title' => 'Кейсы', 'url' => $home . 'kejsy/'],
        ['title' => 'Материалы', 'url' => $home . 'materialy/'],
        ['title' => 'О нас', 'url' => $home . 'o-nas/'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
        ['title' => 'Кабинет', 'url' => $home . 'cabinet/'],
        ['title' => 'Заявка', 'url' => $home . '#contact'],
    ];
    $footer = [
        ['title' => 'Услуги', 'url' => $home . 'services/'],
        ['title' => 'Кейсы', 'url' => $home . 'kejsy/'],
        ['title' => 'Материалы', 'url' => $home . 'materialy/'],
        ['title' => 'Кабинет', 'url' => $home . 'cabinet/'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
        ['title' => 'Конфиденциальность', 'url' => $home . 'privacy-policy/'],
    ];
    mb2_assign_menu('primary', '5MB2 Главное', $primary);
    mb2_assign_menu('footer', '5MB2 Подвал', $footer);
}

function mb2_assign_menu($location, $menu_name, array $items) {
    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = [];
    }

    $menu = wp_get_nav_menu_object($menu_name);
    if (!$menu) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = (int) $menu->term_id;
        $existing = wp_get_nav_menu_items($menu_id);
        if (is_array($existing)) {
            foreach ($existing as $item) {
                wp_delete_post($item->ID, true);
            }
        }
    }
    if (is_wp_error($menu_id) || !$menu_id) {
        return;
    }

    $pos = 1;
    foreach ($items as $item) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'    => $item['title'],
            'menu-item-url'      => $item['url'],
            'menu-item-status'   => 'publish',
            'menu-item-type'     => 'custom',
            'menu-item-position' => $pos++,
        ]);
    }

    $locations[$location] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}
