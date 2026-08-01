<?php
/**
 * 5MB2 Dark — SEO theme.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MB2_THEME_VER', '1.8.0');

require get_template_directory() . '/inc/services.php';
require get_template_directory() . '/inc/legal.php';
require get_template_directory() . '/inc/seo.php';
require get_template_directory() . '/inc/projects.php';
require get_template_directory() . '/inc/seed.php';
require get_template_directory() . '/inc/leads-admin.php';

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
    if (is_page_template('templates/tools.php') || is_page('instrumenty')) {
        $tjs = $dir . '/assets/js/tools.js';
        wp_enqueue_script('mb2-tools', $uri . '/assets/js/tools.js', ['mb2-main'], file_exists($tjs) ? (string) filemtime($tjs) : MB2_THEME_VER, true);
    }

    wp_localize_script('mb2-main', 'MB2', [
        'ajax'    => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('mb2_auth'),
        'home'    => home_url('/'),
        'thanks'  => home_url('/spasibo/'),
        'user'    => is_user_logged_in() ? [
            'name'  => wp_get_current_user()->display_name,
            'email' => wp_get_current_user()->user_email,
        ] : null,
    ]);
});

add_action('after_switch_theme', 'mb2_ensure_site_structure');
add_action('init', function () {
    if (get_option('mb2_structure_ver') === MB2_THEME_VER) {
        return;
    }
    mb2_ensure_site_structure();
    update_option('mb2_structure_ver', MB2_THEME_VER, false);
}, 20);

function mb2_nav_fallback() {
    echo '<ul class="nav-list">';
    $items = [
        ['Услуги', home_url('/services/')],
        ['Инструменты', home_url('/instrumenty/')],
        ['Проекты', home_url('/kejsy/')],
        ['Материалы', home_url('/materialy/')],
        ['О нас', home_url('/o-nas/')],
        ['Контакты', home_url('/contacts/')],
        ['Кабинет', home_url('/cabinet/')],
    ];
    foreach ($items as $item) {
        echo '<li><a href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a></li>';
    }
    echo '</ul>';
}

function mb2_footer_nav() {
    $locations = get_nav_menu_locations();
    $menu_id = isset($locations['footer']) ? (int) $locations['footer'] : 0;
    $items = $menu_id ? wp_get_nav_menu_items($menu_id) : false;
    if (!$items) {
        $items = [
            (object) ['title' => 'Услуги', 'url' => home_url('/services/')],
            (object) ['title' => 'Проекты', 'url' => home_url('/kejsy/')],
            (object) ['title' => 'Оферта', 'url' => home_url('/oferta/')],
            (object) ['title' => 'Контакты', 'url' => home_url('/contacts/')],
        ];
    }
    foreach ($items as $item) {
        echo '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
    }
}

/** Обновлять тексты оферты/политики при смене реквизитов */
add_action('update_option_mb2_legal', function () {
    if (function_exists('mb2_upsert_page')) {
        mb2_upsert_page('privacy-policy', 'Политика конфиденциальности', mb2_privacy_html());
        mb2_upsert_page('oferta', 'Публичная оферта', mb2_oferta_html());
        mb2_upsert_page('contacts', 'Контакты', mb2_contacts_html());
    }
});


/** Форма заявки (переиспользуется) */
function mb2_render_lead_form($selected_service = '') {
    $services = mb2_services_catalog();
    ?>
    <form class="lead-form" data-lead>
      <label>Имя
        <input type="text" name="name" required autocomplete="name" placeholder="Как к вам обращаться" />
      </label>
      <label>Email
        <input type="email" name="email" required autocomplete="email" placeholder="you@company.ru" />
      </label>
      <label>Телефон
        <input type="tel" name="phone" autocomplete="tel" placeholder="+7 …" />
      </label>
      <label>Услуга
        <select name="service">
          <option value="">Не выбрано — нужна консультация</option>
          <?php foreach ($services as $slug => $svc) : ?>
            <option value="<?php echo esc_attr($svc['title']); ?>" <?php selected($selected_service, $svc['title']); ?>>
              <?php echo esc_html($svc['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Сайт
        <input type="url" name="site" autocomplete="url" placeholder="https://example.ru" />
      </label>
      <label>Задача
        <textarea name="message" placeholder="Ниша, город, что хотите получить от SEO"></textarea>
      </label>
      <p class="form-note" hidden></p>
      <button class="btn btn-primary btn-lg" type="submit">Отправить заявку</button>
      <p class="muted tiny">Нажимая кнопку, вы соглашаетесь с <a class="text-link" href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">политикой конфиденциальности</a> и условиями <a class="text-link" href="<?php echo esc_url(home_url('/oferta/')); ?>">публичной оферты</a>.</p>
    </form>
    <?php
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
    $login = sanitize_text_field(wp_unslash($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $user  = wp_signon([
        'user_login'    => $login,
        'user_password' => $pass,
        'remember'      => true,
    ], is_ssl());
    if (is_wp_error($user) && is_email($login)) {
        $by = get_user_by('email', $login);
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
    update_user_meta(get_current_user_id(), 'mb2_site_url', esc_url_raw(wp_unslash($_POST['site_url'] ?? '')));
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
    $uid = get_current_user_id();
    $msg = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
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
        wp_mail($admin, 'Заявка из кабинета 5MB2: ' . $user->user_email, "Клиент: {$user->display_name} <{$user->user_email}>\n\n{$msg}\n");
    }
    wp_send_json_success(['ok' => true, 'requests' => $list]);
}

function mb2_ajax_lead() {
    check_ajax_referer('mb2_auth', 'nonce');
    $name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone   = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $service = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));
    $site    = esc_url_raw(wp_unslash($_POST['site'] ?? ''));
    $msg     = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    if (!$name || !is_email($email)) {
        wp_send_json_error(['message' => 'Укажите имя и корректный email'], 400);
    }
    if (strlen($msg) < 3) {
        $msg = $service ? ('Заявка: ' . $service) : 'Заявка на SEO-стратегию';
    }

    $leads = get_option('mb2_leads', []);
    if (!is_array($leads)) {
        $leads = [];
    }
    array_unshift($leads, [
        'at'      => current_time('mysql'),
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'service' => $service,
        'site'    => $site,
        'message' => $msg,
    ]);
    update_option('mb2_leads', array_slice($leads, 0, 200), false);

    $admin = get_option('admin_email');
    if ($admin) {
        wp_mail(
            $admin,
            'Заявка 5MB2: ' . ($service ?: $name),
            "Имя: {$name}\nEmail: {$email}\nТелефон: {$phone}\nУслуга: {$service}\nСайт: {$site}\n\n{$msg}\n"
        );
    }

    wp_send_json_success([
        'message'  => 'Заявка принята',
        'redirect' => home_url('/spasibo/'),
    ]);
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
    return 'Здравствуйте! Подскажу по услугам и ценам 5MB2, инструментам и заявке.';
});
add_filter('ai_helper_chat_chips', function () {
    return ['Услуги и цены', 'Local SEO', 'Инструменты', 'Оставить заявку'];
});
