<?php
/**
 * 5MB2 Dark — SEO theme. Минимально вмешивается в ядро WP / админку.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MB2_THEME_VER', '1.3.0');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer'  => 'Меню в подвале',
    ]);
});

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $uri = get_template_directory_uri();
    $dir = get_template_directory();

    wp_enqueue_style(
        'mb2-fonts',
        'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Unbounded:wght@400;500;600&display=swap',
        [],
        null
    );
    $css = $dir . '/assets/css/main.css';
    wp_enqueue_style('mb2-main', $uri . '/assets/css/main.css', ['mb2-fonts'], file_exists($css) ? (string) filemtime($css) : MB2_THEME_VER);
    $js = $dir . '/assets/js/main.js';
    wp_enqueue_script('mb2-main', $uri . '/assets/js/main.js', [], file_exists($js) ? (string) filemtime($js) : MB2_THEME_VER, true);

    if (is_page_template('templates/cabinet.php') || is_page('cabinet')) {
        $cjs = $dir . '/assets/js/cabinet.js';
        wp_enqueue_script('mb2-cabinet', $uri . '/assets/js/cabinet.js', ['mb2-main'], file_exists($cjs) ? (string) filemtime($cjs) : MB2_THEME_VER, true);
    }

    wp_localize_script('mb2-main', 'MB2', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mb2_auth'),
        'home'  => home_url('/'),
        'user'  => is_user_logged_in() ? [
            'name'  => wp_get_current_user()->display_name,
            'email' => wp_get_current_user()->user_email,
        ] : null,
    ]);
});

/** Кабинет, служебные страницы и меню */
add_action('after_switch_theme', 'mb2_ensure_site_structure');
add_action('init', function () {
    if (get_option('mb2_structure_ver') === MB2_THEME_VER) {
        return;
    }
    mb2_ensure_site_structure();
    update_option('mb2_structure_ver', MB2_THEME_VER, false);
}, 20);

function mb2_ensure_site_structure() {
    update_option('show_on_front', 'posts');
    delete_option('page_on_front');

    $pages = [
        'cabinet' => [
            'title'    => 'Личный кабинет',
            'template' => 'templates/cabinet.php',
            'content'  => '',
        ],
        'privacy-policy' => [
            'title'    => 'Политика конфиденциальности',
            'template' => '',
            'content'  => '<p>Мы обрабатываем контактные данные (имя, email, телефон, URL сайта) только для связи по заявке на SEO-услуги 5MB2 Digital. Данные не продаём третьим лицам. По вопросам: <a href="mailto:hello@5mb2.ru">hello@5mb2.ru</a>.</p>',
        ],
        'contacts' => [
            'title'    => 'Контакты',
            'template' => '',
            'content'  => '<p>Агентство <strong>5MB2 Digital</strong> — SEO-продвижение сайтов.</p><p>Email: <a href="mailto:hello@5mb2.ru">hello@5mb2.ru</a><br>VK: <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">vk.com/5mb2online</a></p><p><a href="' . esc_url(home_url('/#contact')) . '">Оставить заявку на сайте</a> или зайдите в <a href="' . esc_url(home_url('/cabinet/')) . '">личный кабинет</a>.</p>',
        ],
    ];

    foreach ($pages as $slug => $meta) {
        $existing = get_page_by_path($slug);
        if (!$existing) {
            $id = wp_insert_post([
                'post_title'   => $meta['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $meta['content'],
            ]);
            if ($id && !is_wp_error($id) && $meta['template']) {
                update_post_meta($id, '_wp_page_template', $meta['template']);
            }
        } elseif ($slug === 'cabinet') {
            update_post_meta($existing->ID, '_wp_page_template', 'templates/cabinet.php');
        }
    }

    mb2_ensure_menus();
}

function mb2_ensure_menus() {
    $home = home_url('/');
    $primary_items = [
        ['title' => 'Услуги', 'url' => $home . '#services'],
        ['title' => 'Как работаем', 'url' => $home . '#process'],
        ['title' => 'Результаты', 'url' => $home . '#cases'],
        ['title' => 'FAQ', 'url' => $home . '#faq'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
        ['title' => 'Кабинет', 'url' => $home . 'cabinet/'],
        ['title' => 'Заявка', 'url' => $home . '#contact'],
    ];
    $footer_items = [
        ['title' => 'Услуги', 'url' => $home . '#services'],
        ['title' => 'Процесс', 'url' => $home . '#process'],
        ['title' => 'Кабинет', 'url' => $home . 'cabinet/'],
        ['title' => 'Контакты', 'url' => $home . 'contacts/'],
        ['title' => 'Конфиденциальность', 'url' => $home . 'privacy-policy/'],
    ];

    mb2_assign_menu('primary', '5MB2 Главное', $primary_items);
    mb2_assign_menu('footer', '5MB2 Подвал', $footer_items);
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
            'menu-item-title'  => $item['title'],
            'menu-item-url'    => $item['url'],
            'menu-item-status' => 'publish',
            'menu-item-type'   => 'custom',
            'menu-item-position' => $pos++,
        ]);
    }

    $locations[$location] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

function mb2_nav_fallback() {
    echo '<ul class="nav-list">';
    $items = [
        ['Услуги', home_url('/#services')],
        ['Как работаем', home_url('/#process')],
        ['Результаты', home_url('/#cases')],
        ['FAQ', home_url('/#faq')],
        ['Контакты', home_url('/contacts/')],
        ['Кабинет', home_url('/cabinet/')],
    ];
    foreach ($items as $item) {
        echo '<li><a href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a></li>';
    }
    echo '</ul>';
}

/** Ссылки подвала без обёртки ul */
function mb2_footer_nav() {
    $locations = get_nav_menu_locations();
    $menu_id = isset($locations['footer']) ? (int) $locations['footer'] : 0;
    $items = $menu_id ? wp_get_nav_menu_items($menu_id) : false;
    if (!$items) {
        $items = [
            (object) ['title' => 'Услуги', 'url' => home_url('/#services')],
            (object) ['title' => 'Процесс', 'url' => home_url('/#process')],
            (object) ['title' => 'Кабинет', 'url' => home_url('/cabinet/')],
            (object) ['title' => 'Контакты', 'url' => home_url('/contacts/')],
        ];
    }
    foreach ($items as $item) {
        echo '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
    }
}

add_action('wp_ajax_nopriv_mb2_register', 'mb2_ajax_register');
add_action('wp_ajax_nopriv_mb2_login', 'mb2_ajax_login');
add_action('wp_ajax_mb2_logout', 'mb2_ajax_logout');
add_action('wp_ajax_nopriv_mb2_logout', 'mb2_ajax_logout');
add_action('wp_ajax_mb2_save_site', 'mb2_ajax_save_site');
add_action('wp_ajax_mb2_save_profile', 'mb2_ajax_save_profile');
add_action('wp_ajax_mb2_save_request', 'mb2_ajax_save_request');
add_action('wp_ajax_nopriv_mb2_lead', 'mb2_ajax_lead');
add_action('wp_ajax_mb2_lead', 'mb2_ajax_lead');

function mb2_ajax_register() {
    check_ajax_referer('mb2_auth', 'nonce');
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if (!is_email($email) || strlen($pass) < 8) {
        wp_send_json_error(['message' => 'Email и пароль от 8 символов'], 400);
    }
    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Такой email уже зарегистрирован'], 400);
    }
    $user_id = wp_create_user($email, $pass, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()], 400);
    }
    if ($name) {
        wp_update_user(['ID' => $user_id, 'display_name' => $name, 'first_name' => $name]);
    }
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    update_user_meta($user_id, 'mb2_plan', 'start');
    update_user_meta($user_id, 'mb2_site_url', '');
    update_user_meta($user_id, 'mb2_phone', '');
    update_user_meta($user_id, 'mb2_checklist', wp_json_encode(mb2_default_checklist()));
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    wp_send_json_success(['redirect' => home_url('/cabinet/')]);
}

function mb2_ajax_login() {
    check_ajax_referer('mb2_auth', 'nonce');
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $user  = wp_signon([
        'user_login'    => $email,
        'user_password' => $pass,
        'remember'      => true,
    ], is_ssl());
    if (is_wp_error($user)) {
        // логин может быть username, не email
        $by = get_user_by('email', $email);
        if ($by) {
            $user = wp_signon([
                'user_login'    => $by->user_login,
                'user_password' => $pass,
                'remember'      => true,
            ], is_ssl());
        }
    }
    if (is_wp_error($user)) {
        wp_send_json_error(['message' => 'Неверный email или пароль'], 401);
    }
    wp_send_json_success(['redirect' => home_url('/cabinet/')]);
}

function mb2_ajax_logout() {
    check_ajax_referer('mb2_auth', 'nonce');
    wp_logout();
    wp_send_json_success(['redirect' => home_url('/')]);
}

function mb2_ajax_save_site() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $url = esc_url_raw(wp_unslash($_POST['site_url'] ?? ''));
    update_user_meta(get_current_user_id(), 'mb2_site_url', $url);
    wp_send_json_success(['ok' => true]);
}

function mb2_ajax_save_profile() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $uid   = get_current_user_id();
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $url   = esc_url_raw(wp_unslash($_POST['site_url'] ?? ''));
    if ($name) {
        wp_update_user(['ID' => $uid, 'display_name' => $name, 'first_name' => $name]);
    }
    update_user_meta($uid, 'mb2_phone', $phone);
    update_user_meta($uid, 'mb2_site_url', $url);
    wp_send_json_success(['ok' => true]);
}

function mb2_ajax_save_request() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $uid  = get_current_user_id();
    $msg  = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    if (strlen($msg) < 5) {
        wp_send_json_error(['message' => 'Опишите задачу чуть подробнее'], 400);
    }
    $list = get_user_meta($uid, 'mb2_requests', true);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, [
        'at'      => current_time('mysql'),
        'message' => $msg,
        'status'  => 'new',
    ]);
    $list = array_slice($list, 0, 20);
    update_user_meta($uid, 'mb2_requests', $list);

    $user = wp_get_current_user();
    $admin = get_option('admin_email');
    if ($admin) {
        wp_mail(
            $admin,
            'Заявка из кабинета 5MB2: ' . $user->user_email,
            "Клиент: {$user->display_name} <{$user->user_email}>\n\n{$msg}\n"
        );
    }
    wp_send_json_success(['ok' => true, 'requests' => $list]);
}

function mb2_ajax_lead() {
    check_ajax_referer('mb2_auth', 'nonce');
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $site  = esc_url_raw(wp_unslash($_POST['site'] ?? ''));
    $msg   = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    if (!$name || !is_email($email)) {
        wp_send_json_error(['message' => 'Укажите имя и корректный email'], 400);
    }
    if (strlen($msg) < 5) {
        $msg = 'Заявка на SEO-стратегию';
    }

    $leads = get_option('mb2_leads', []);
    if (!is_array($leads)) {
        $leads = [];
    }
    array_unshift($leads, [
        'at'      => current_time('mysql'),
        'name'    => $name,
        'email'   => $email,
        'site'    => $site,
        'message' => $msg,
    ]);
    update_option('mb2_leads', array_slice($leads, 0, 100), false);

    $admin = get_option('admin_email');
    if ($admin) {
        wp_mail(
            $admin,
            'Заявка с сайта 5MB2: ' . $name,
            "Имя: {$name}\nEmail: {$email}\nСайт: {$site}\n\n{$msg}\n"
        );
    }
    wp_send_json_success(['message' => 'Заявка отправлена. Мы свяжемся с вами.']);
}

function mb2_default_checklist() {
    return [
        ['key' => 'audit', 'label' => 'Технический аудит', 'status' => 'todo'],
        ['key' => 'semantics', 'label' => 'Семантическое ядро', 'status' => 'todo'],
        ['key' => 'structure', 'label' => 'Структура и мета', 'status' => 'todo'],
        ['key' => 'content', 'label' => 'Контент-план', 'status' => 'todo'],
        ['key' => 'links', 'label' => 'Ссылочный профиль', 'status' => 'todo'],
        ['key' => 'report', 'label' => 'Ежемесячный отчёт', 'status' => 'todo'],
    ];
}

function mb2_get_checklist($user_id) {
    $raw = get_user_meta($user_id, 'mb2_checklist', true);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    if (is_array($raw) && $raw) {
        return $raw;
    }
    $def = mb2_default_checklist();
    update_user_meta($user_id, 'mb2_checklist', wp_json_encode($def));
    return $def;
}

add_filter('ai_helper_chat_title', function () {
    return '5MB2 · помощник';
});
add_filter('ai_helper_chat_greeting', function () {
    return 'Здравствуйте! Отвечу про SEO и услуги 5MB2 — без регистрации.';
});
add_filter('ai_helper_chat_chips', function () {
    return ['Что входит в SEO?', 'Сроки роста', 'Стоимость', 'Оставить заявку'];
});
