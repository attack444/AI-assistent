<?php
/**
 * Наполнение: услуги, материалы, проекты, юр.страницы.
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
    if (function_exists('mb2_ensure_real_projects')) {
        mb2_ensure_real_projects();
    }
    mb2_ensure_menus();
}

function mb2_ensure_terms() {
    if (!term_exists('materialy', 'category')) {
        wp_insert_term('Материалы', 'category', ['slug' => 'materialy']);
    }
    if (!term_exists('kejsy', 'category')) {
        wp_insert_term('Проекты', 'category', ['slug' => 'kejsy']);
    } else {
        $term = get_term_by('slug', 'kejsy', 'category');
        if ($term && !is_wp_error($term)) {
            wp_update_term((int) $term->term_id, 'category', ['name' => 'Проекты']);
        }
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
    mb2_upsert_page('instrumenty', 'SEO-инструменты', '', 'templates/tools.php');
    mb2_upsert_page('privacy-policy', 'Политика конфиденциальности', mb2_privacy_html());
    mb2_upsert_page('oferta', 'Публичная оферта', mb2_oferta_html());
    mb2_upsert_page('contacts', 'Контакты', mb2_contacts_html());
    mb2_upsert_page('spasibo', 'Спасибо за заявку', '', 'templates/thanks.php');
    mb2_upsert_page('materialy', 'Материалы', '', 'templates/materials.php');
    mb2_upsert_page('kejsy', 'Проекты', '', 'templates/cases.php');
    $uploads = content_url('uploads/2026/03');
    mb2_upsert_page(
        'o-nas',
        'О проекте 5MB2',
        '<p><strong>5MB2 Digital</strong> — SEO для бизнеса в России: аудит, продвижение, Local SEO. Исполнитель — Вячеслав Сундуков.</p>'
        . '<p>Работаю как <strong>самозанятый</strong> (НПД, 422-ФЗ): прозрачная оферта, чек в «Мой налог», без НДС.</p>'
        . '<h2>Как работаю</h2>'
        . '<ul><li>системно: от диагностики к стратегии, а не к списку хаотичных правок;</li>'
        . '<li>с опорой на данные Метрики, Вебмастера, краулеров;</li>'
        . '<li>с прогрессом в <a href="' . esc_url(home_url('/cabinet/')) . '">личном кабинете</a>.</li></ul>'
        . '<h2>Обучение и материалы</h2>'
        . '<p>Есть учебные разборы и портфолио-материалы (аудит структуры, мета, индексация). '
        . 'Их публикуем как <strong>«учебный разбор»</strong> — это демонстрация метода, а не замена коммерческим кейсам. '
        . 'Галерею сертификатов на главную не выносим: доверие строится на процессе и результате, а не на бейджах курсов.</p>'
        . '<p>Полная презентация портфолио (PDF): '
        . '<a href="' . esc_url($uploads . '/Вячеслав-Сундуков.pdf') . '" target="_blank" rel="noopener">скачать</a>.</p>'
        . '<h2>Инструменты в работе</h2>'
        . '<p>Ahrefs, Screaming Frog, Яндекс.Метрика, Google Analytics / Search Console, таблицы для отчётов.</p>'
        . '<p><a href="' . esc_url(home_url('/#contact')) . '">Обсудить задачу</a> · '
        . '<a href="' . esc_url(home_url('/kejsy/')) . '">Проекты</a> · '
        . '<a href="' . esc_url(home_url('/oferta/')) . '">Оферта</a></p>'
    );
}

function mb2_ensure_service_pages() {
    $hub_id = mb2_upsert_page(
        'services',
        'Услуги SEO',
        '<p>Цены — ориентир по рынку РФ на 2026 год. Точный бюджет после короткого брифа. Работа по публичной оферте, статус — самозанятый (НПД).</p>',
        'templates/services.php'
    );
    if (!$hub_id) {
        return;
    }
    foreach (mb2_services_catalog() as $slug => $svc) {
        $id = mb2_upsert_page($slug, $svc['title'], $svc['body'], 'templates/service.php', $hub_id);
        if ($id) {
            update_post_meta($id, '_mb2_service_slug', $slug);
        }
    }
}

function mb2_ensure_sample_posts() {
    $mat = get_term_by('slug', 'materialy', 'category');
    $mat_id = $mat ? (int) $mat->term_id : 0;

    $posts = [
        [
            'title' => 'С чего начать SEO в 2026 году',
            'slug'  => 's-chego-nachat-seo-2026',
            'body'  => '<p>Сначала — цели бизнеса и аудит. Затем семантика и структура. Контент и ссылки работают только на чистом техническом фундаменте.</p><p>В 5MB2 начинаем с диагностики и плана на 90 дней — без обещаний «топ за неделю».</p>',
        ],
        [
            'title' => 'Чем SEO отличается от контекстной рекламы',
            'slug'  => 'seo-vs-kontekst',
            'body'  => '<p>Реклама даёт трафик сразу, пока есть бюджет. SEO накапливает актив: страницы продолжают приносить заявки месяцами.</p>',
        ],
        [
            'title' => 'Local SEO: как получать заявки из своего города',
            'slug'  => 'local-seo-zayavki-iz-goroda',
            'body'  => '<p>Карточки на картах, единый NAP, региональные посадочные и отзывы — база локального продвижения.</p>',
        ],
        [
            'title' => 'Сколько стоит SEO в России в 2026',
            'slug'  => 'skolko-stoit-seo-rossiya-2026',
            'body'  => '<p>По рынку 2026 у большинства компаний бюджет на SEO — <strong>50–150 тыс. ₽/мес</strong>. Локальный старт часто от 40–70 тыс. ₽/мес. Разовый аудит — ориентир 25–80 тыс. ₽.</p><p>На сайте 5MB2 указаны входные цены «от» для самозанятого специалиста. Итог — после брифа по нише и состоянию сайта.</p>',
        ],
        [
            'title' => 'Чеклист перед заказом SEO: 12 пунктов',
            'slug'  => 'cheklist-pered-zakazom-seo',
            'body'  => '<p>Перед аудитом или продвижением соберите: цели (заявки / трафик / бренд), регион, доступ к Метрике и Вебмастеру, список конкурентов, кто правит сайт, бюджет на 3 месяца.</p><p>Бесплатно проверьте базу в <a href="/instrumenty/">SEO-инструментах</a>, затем оставьте заявку — в кабинете появится рабочий чеклист.</p>',
        ],
        [
            'title' => 'Почему «гарантия топ-1» — красный флаг',
            'slug'  => 'pochemu-garantiya-top-1-krasnyj-flag',
            'body'  => '<p>Поисковики меняют выдачу. Честный подрядчик обещает процесс, прозрачные метрики и гипотезы — не место любой ценой.</p><p>В 5MB2 вы видите прогресс в кабинете: аудит → семантика → структура → контент → отчёт.</p>',
        ],
    ];

    foreach ($posts as $p) {
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
        if ($id && !is_wp_error($id) && $mat_id) {
            wp_set_post_categories($id, [$mat_id]);
        }
    }

    update_option('mb2_sample_posts_v4', 1, false);
}

function mb2_ensure_menus() {
    $home = trailingslashit(home_url('/'));
    $primary = [
        ['title' => 'Услуги', 'url' => $home . 'services/'],
        ['title' => 'Инструменты', 'url' => $home . 'instrumenty/'],
        ['title' => 'Проекты', 'url' => $home . 'kejsy/'],
        ['title' => 'Материалы', 'url' => $home . 'materialy/'],
        ['title' => 'О нас', 'url' => $home . 'o-nas/'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
        ['title' => 'Кабинет', 'url' => $home . 'cabinet/'],
        ['title' => 'Заявка', 'url' => $home . '#contact'],
    ];
    $footer = [
        ['title' => 'Услуги', 'url' => $home . 'services/'],
        ['title' => 'Инструменты', 'url' => $home . 'instrumenty/'],
        ['title' => 'Проекты', 'url' => $home . 'kejsy/'],
        ['title' => 'Материалы', 'url' => $home . 'materialy/'],
        ['title' => 'Оферта', 'url' => $home . 'oferta/'],
        ['title' => 'Конфиденциальность', 'url' => $home . 'privacy-policy/'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
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
