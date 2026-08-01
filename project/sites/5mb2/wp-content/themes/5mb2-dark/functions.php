<?php
/**
 * 5MB2 Dark — SEO agency theme (NVIDIA-inspired).
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MB2_THEME_VER', '1.0.0');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer'  => 'Подвал',
    ]);
});

add_action('wp_enqueue_scripts', function () {
    $uri = get_template_directory_uri();
    $dir = get_template_directory();
    wp_enqueue_style(
        'mb2-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Grotesk:wght@500;600;700&display=swap',
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
});

/** Create cabinet page once */
add_action('after_switch_theme', function () {
    if (!get_page_by_path('cabinet')) {
        $id = wp_insert_post([
            'post_title'   => 'Личный кабинет',
            'post_name'    => 'cabinet',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_wp_page_template', 'templates/cabinet.php');
        }
    }
    if (!get_page_by_path('uslugi')) {
        wp_insert_post([
            'post_title'  => 'Услуги',
            'post_name'   => 'uslugi',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ]);
    }
    if (!get_page_by_path('kejsy')) {
        wp_insert_post([
            'post_title'  => 'Кейсы',
            'post_name'   => 'kejsy',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ]);
    }
    // Front page = home blog? Prefer static front if none
    if (!get_option('page_on_front')) {
        // keep whatever is set; front-page.php still applies when blog is front
    }
});

/** AJAX: client register / login (subscribers) */
add_action('wp_ajax_nopriv_mb2_register', 'mb2_ajax_register');
add_action('wp_ajax_nopriv_mb2_login', 'mb2_ajax_login');
add_action('wp_ajax_mb2_logout', 'mb2_ajax_logout');
add_action('wp_ajax_nopriv_mb2_logout', 'mb2_ajax_logout');
add_action('wp_ajax_mb2_save_site', 'mb2_ajax_save_site');

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
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
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
        wp_send_json_error(['message' => 'Неверный email или пароль'], 401);
    }
    wp_send_json_success(['redirect' => home_url('/cabinet/')]);
}

function mb2_ajax_logout() {
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

add_action('wp_enqueue_scripts', function () {
    wp_localize_script('mb2-main', 'MB2', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mb2_auth'),
        'home'  => home_url('/'),
        'user'  => is_user_logged_in() ? [
            'name'  => wp_get_current_user()->display_name,
            'email' => wp_get_current_user()->user_email,
        ] : null,
    ]);
}, 20);

/** Soften widget for dark site */
add_filter('ai_helper_chat_title', fn () => '5MB2 · помощник');
add_filter('ai_helper_chat_greeting', fn () => 'Здравствуйте! Отвечу про SEO и услуги 5MB2 — без регистрации.');
add_filter('ai_helper_chat_chips', fn () => ['Что входит в SEO?', 'Сроки роста', 'Стоимость', 'Оставить заявку']);

function mb2_nav_fallback() {
    echo '<ul class="nav-list">';
    echo '<li><a href="' . esc_url(home_url('/#services')) . '">Услуги</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#process')) . '">Как работаем</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#cases')) . '">Кейсы</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#faq')) . '">FAQ</a></li>';
    echo '<li><a href="' . esc_url(home_url('/cabinet/')) . '">Кабинет</a></li>';
    echo '</ul>';
}
