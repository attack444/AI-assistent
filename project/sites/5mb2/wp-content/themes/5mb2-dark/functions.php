<?php
/**
 * 5MB2 Dark — SEO theme.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MB2_THEME_VER', '1.9.2');

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
    // Короче: кабинет и заявка — в кнопках справа, не в списке
    $items = [
        ['Услуги', home_url('/services/')],
        ['Инструменты', home_url('/instrumenty/')],
        ['Проекты', home_url('/kejsy/')],
        ['Материалы', home_url('/materialy/')],
        ['О нас', home_url('/o-nas/')],
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
add_action('wp_ajax_mb2_onboard_profile', 'mb2_ajax_onboard_profile');
add_action('wp_ajax_mb2_onboard_request', 'mb2_ajax_onboard_request');
add_action('wp_ajax_mb2_onboard_finish', 'mb2_ajax_onboard_finish');
add_action('wp_ajax_nopriv_mb2_lead', 'mb2_ajax_lead');
add_action('wp_ajax_mb2_lead', 'mb2_ajax_lead');

function mb2_get_onboarding($user_id) {
    $step = (string) get_user_meta($user_id, 'mb2_onboarding', true);
    if (!in_array($step, ['profile', 'request', 'done'], true)) {
        $site = (string) get_user_meta($user_id, 'mb2_site_url', true);
        $reqs = get_user_meta($user_id, 'mb2_requests', true);
        if (is_array($reqs) && $reqs && $site) {
            $step = 'done';
        } elseif ($site) {
            $step = 'request';
        } else {
            $step = 'profile';
        }
        update_user_meta($user_id, 'mb2_onboarding', $step);
    }
    return $step;
}

function mb2_ajax_register() {
    check_ajax_referer('mb2_auth', 'nonce');
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if (!is_email($email) || strlen($pass) < 8) {
        wp_send_json_error(['message' => 'Email и пароль от 8 символов'], 400);
    }
    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Такой email уже зарегистрирован. Войдите или укажите другой email.'], 400);
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
    mb2_set_checklist($user_id, mb2_default_checklist());
    update_user_meta($user_id, 'mb2_onboarding', 'profile');
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    wp_send_json_success(['redirect' => home_url('/cabinet/?welcome=1')]);
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
    $dest = home_url('/cabinet/');
    if (mb2_get_onboarding($user->ID) !== 'done') {
        $dest = home_url('/cabinet/?welcome=1');
    }
    wp_send_json_success(['redirect' => $dest]);
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
    if ($url && mb2_get_onboarding($uid) === 'profile') {
        update_user_meta($uid, 'mb2_onboarding', 'request');
    }
    wp_send_json_success(['ok' => true, 'onboarding' => mb2_get_onboarding($uid)]);
}

function mb2_ajax_onboard_profile() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $uid   = get_current_user_id();
    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $url   = esc_url_raw(wp_unslash($_POST['site_url'] ?? ''));
    if ($phone === '' || $url === '') {
        wp_send_json_error(['message' => 'Укажите телефон и URL сайта'], 400);
    }
    if ($name) {
        wp_update_user(['ID' => $uid, 'display_name' => $name, 'first_name' => $name]);
    }
    update_user_meta($uid, 'mb2_phone', $phone);
    update_user_meta($uid, 'mb2_site_url', $url);
    update_user_meta($uid, 'mb2_onboarding', 'request');
    wp_send_json_success([
        'ok'         => true,
        'onboarding' => 'request',
        'redirect'   => home_url('/cabinet/?welcome=1'),
    ]);
}

function mb2_store_client_request($uid, $msg, $service = '') {
    $list = get_user_meta($uid, 'mb2_requests', true);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, [
        'at'      => current_time('mysql'),
        'message' => $msg,
        'service' => $service,
        'status'  => 'new',
    ]);
    $list = array_slice($list, 0, 20);
    update_user_meta($uid, 'mb2_requests', $list);

    $user = get_userdata($uid);
    $site = get_user_meta($uid, 'mb2_site_url', true);
    $phone = get_user_meta($uid, 'mb2_phone', true);
    $admin = get_option('admin_email');
    if ($admin && $user) {
        wp_mail(
            $admin,
            'Заявка из кабинета 5MB2: ' . $user->user_email,
            "Клиент: {$user->display_name} <{$user->user_email}>\nТелефон: {$phone}\nСайт: {$site}\nУслуга: {$service}\n\n{$msg}\n"
        );
    }

    // В админский список лидов тоже
    $leads = get_option('mb2_leads', []);
    if (!is_array($leads)) {
        $leads = [];
    }
    array_unshift($leads, [
        'at'      => current_time('mysql'),
        'name'    => $user ? $user->display_name : '',
        'email'   => $user ? $user->user_email : '',
        'phone'   => $phone,
        'service' => $service,
        'site'    => $site,
        'message' => $msg,
    ]);
    update_option('mb2_leads', array_slice($leads, 0, 200), false);

    return $list;
}

function mb2_ajax_onboard_request() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $uid = get_current_user_id();
    $msg = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $service = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));
    if (strlen($msg) < 10) {
        wp_send_json_error(['message' => 'Опишите задачу чуть подробнее (от 10 символов)'], 400);
    }
    if (!get_user_meta($uid, 'mb2_site_url', true)) {
        wp_send_json_error(['message' => 'Сначала укажите сайт в шаге 1'], 400);
    }
    mb2_store_client_request($uid, $msg, $service);
    update_user_meta($uid, 'mb2_onboarding', 'done');
    // Первый пункт чеклиста — в работе
    $checks = mb2_get_checklist($uid);
    if ($checks && ($checks[0]['status'] ?? '') === 'todo') {
        $checks[0]['status'] = 'progress';
        mb2_set_checklist($uid, $checks);
    }
    wp_send_json_success([
        'ok'         => true,
        'onboarding' => 'done',
        'redirect'   => home_url('/cabinet/?welcome=1'),
    ]);
}

function mb2_ajax_onboard_finish() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    update_user_meta(get_current_user_id(), 'mb2_onboarding', 'done');
    wp_send_json_success(['redirect' => home_url('/cabinet/')]);
}

function mb2_ajax_save_request() {
    check_ajax_referer('mb2_auth', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Нужен вход'], 401);
    }
    $uid = get_current_user_id();
    $msg = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $service = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));
    if (strlen($msg) < 5) {
        wp_send_json_error(['message' => 'Опишите задачу чуть подробнее'], 400);
    }
    $list = mb2_store_client_request($uid, $msg, $service);
    if (mb2_get_onboarding($uid) !== 'done') {
        update_user_meta($uid, 'mb2_onboarding', 'done');
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

/**
 * Починка подписей вида u0422u0435… (JSON \uXXXX после stripslashes).
 */
function mb2_fix_unicode_mojibake($text) {
    $text = (string) $text;
    if ($text === '' || !preg_match('/(?<!\\\\)u04[0-9a-fA-F]{2}/', $text)) {
        return $text;
    }
    $jsonish = '"' . preg_replace('/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $text) . '"';
    $decoded = json_decode($jsonish);
    if (is_string($decoded) && $decoded !== '' && !preg_match('/(?<!\\\\)u04[0-9a-fA-F]{2}/', $decoded)) {
        return $decoded;
    }
    $fixed = preg_replace_callback('/(?<!\\\\)u([0-9a-fA-F]{4})/', static function ($m) {
        return html_entity_decode('&#x' . $m[1] . ';', ENT_QUOTES, 'UTF-8');
    }, $text);
    return is_string($fixed) && $fixed !== '' ? $fixed : $text;
}

function mb2_checklist_label_broken($label) {
    $label = (string) $label;
    if ($label === '') {
        return true;
    }
    // u04xx — кириллица в «сломанном» JSON; также отсекаем сырой JSON-мусор
    return (bool) preg_match('/(?<!\\\\)u04[0-9a-fA-F]{2}/', $label)
        || str_contains($label, '\\u04')
        || str_contains($label, 'u040');
}

function mb2_normalize_checklist($items) {
    $defaults = mb2_default_checklist();
    $by_key = [];
    foreach ($defaults as $d) {
        $by_key[$d['key']] = $d;
    }
    if (!is_array($items) || !$items) {
        return ['items' => $defaults, 'changed' => true];
    }
    $out = [];
    $changed = false;
    foreach ($items as $i => $c) {
        if (!is_array($c)) {
            $changed = true;
            continue;
        }
        $key = (string) ($c['key'] ?? ($defaults[$i]['key'] ?? ('item' . $i)));
        $status = (string) ($c['status'] ?? 'todo');
        if (!in_array($status, ['todo', 'progress', 'done'], true)) {
            $status = 'todo';
            $changed = true;
        }
        $original = (string) ($c['label'] ?? '');
        $label = mb2_fix_unicode_mojibake($original);
        if (mb2_checklist_label_broken($label) || $label === '') {
            $label = $by_key[$key]['label'] ?? ($defaults[$i]['label'] ?? 'Пункт работ');
            $changed = true;
        } elseif ($label !== $original) {
            $changed = true;
        }
        $out[] = [
            'key'    => $key,
            'label'  => $label,
            'status' => $status,
        ];
    }
    if (!$out) {
        return ['items' => $defaults, 'changed' => true];
    }
    foreach ($out as $j => $row) {
        if (mb2_checklist_label_broken($row['label'])) {
            $out[$j]['label'] = $by_key[$row['key']]['label'] ?? 'Пункт работ';
            $changed = true;
        }
    }
    return ['items' => $out, 'changed' => $changed];
}

function mb2_set_checklist($user_id, array $items) {
    $norm = mb2_normalize_checklist($items);
    $list = $norm['items'];
    // Массив в usermeta — без JSON \uXXXX и stripslashes
    update_user_meta((int) $user_id, 'mb2_checklist', $list);
    return $list;
}

function mb2_get_checklist($user_id) {
    $user_id = (int) $user_id;
    $raw = get_user_meta($user_id, 'mb2_checklist', true);
    $items = null;

    if (is_array($raw) && $raw) {
        $items = $raw;
    } elseif (is_string($raw) && $raw !== '') {
        // Старые записи: JSON, иногда с «съеденными» слэшами у \uXXXX
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $repaired = preg_replace('/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $raw);
            $decoded = is_string($repaired) ? json_decode($repaired, true) : null;
        }
        if (is_array($decoded)) {
            $items = $decoded;
        }
    }

    if ($items === null) {
        return mb2_set_checklist($user_id, mb2_default_checklist());
    }

    $norm = mb2_normalize_checklist($items);
    $list = $norm['items'];
    // Всегда мигрируем на массив + чиним битые подписи один раз
    if (!is_array($raw) || !empty($norm['changed'])) {
        mb2_set_checklist($user_id, $list);
    }
    return $list;
}

/** Фазы проекта в кабинете (как у клиентских порталов агентств). */
function mb2_project_phases() {
    return [
        'onboarding'  => 'Знакомство',
        'audit'       => 'Аудит',
        'foundation'  => 'База SEO',
        'growth'      => 'Рост',
        'reporting'   => 'Отчётный период',
        'paused'      => 'На паузе',
    ];
}

function mb2_infer_project_phase($user_id) {
    $onb = mb2_get_onboarding($user_id);
    if ($onb !== 'done') {
        return 'onboarding';
    }
    $checks = mb2_get_checklist($user_id);
    $done = 0;
    $progress = 0;
    $keys_done = [];
    foreach ($checks as $c) {
        $st = $c['status'] ?? 'todo';
        if ($st === 'done') {
            $done++;
            $keys_done[$c['key'] ?? ''] = true;
        } elseif ($st === 'progress') {
            $progress++;
        }
    }
    $total = max(count($checks), 1);
    if (!empty($keys_done['report']) || $done >= $total) {
        return 'reporting';
    }
    if ($done >= 3 || (!empty($keys_done['content']) || !empty($keys_done['links']))) {
        return 'growth';
    }
    if ($done >= 1 || $progress > 0) {
        $first_key = $checks[0]['key'] ?? 'audit';
        if ($first_key === 'audit' && empty($keys_done['audit']) && ($checks[0]['status'] ?? '') === 'progress') {
            return 'audit';
        }
        return $done < 2 ? 'audit' : 'foundation';
    }
    return 'audit';
}

function mb2_get_project_phase($user_id) {
    $phase = (string) get_user_meta($user_id, 'mb2_phase', true);
    $phases = mb2_project_phases();
    if ($phase !== '' && isset($phases[$phase])) {
        return $phase;
    }
    return mb2_infer_project_phase($user_id);
}

function mb2_get_project_kpis($user_id) {
    $raw = get_user_meta($user_id, 'mb2_kpis', true);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    return [
        'organic'  => sanitize_text_field((string) ($raw['organic'] ?? '')),
        'keywords' => sanitize_text_field((string) ($raw['keywords'] ?? '')),
        'leads'    => sanitize_text_field((string) ($raw['leads'] ?? '')),
    ];
}

/**
 * Тексты для «Обзора проекта»: сводка, следующий шаг, журнал.
 *
 * @return array{
 *   phase:string,phase_label:string,summary:string,next:array,log:array,progress:array,kpis:array
 * }
 */
function mb2_cabinet_overview_data($user_id) {
    $phases = mb2_project_phases();
    $phase = mb2_get_project_phase($user_id);
    $onb = mb2_get_onboarding($user_id);
    $site = (string) get_user_meta($user_id, 'mb2_site_url', true);
    $checks = mb2_get_checklist($user_id);
    $reqs = get_user_meta($user_id, 'mb2_requests', true);
    $reports = get_user_meta($user_id, 'mb2_reports', true);
    $summary = trim((string) get_user_meta($user_id, 'mb2_summary', true));
    $note = trim((string) get_user_meta($user_id, 'mb2_client_note', true));
    $next_override = trim((string) get_user_meta($user_id, 'mb2_next_action', true));
    $kpis = mb2_get_project_kpis($user_id);

    if (!is_array($reqs)) {
        $reqs = [];
    }
    if (!is_array($reports)) {
        $reports = [];
    }

    $done = 0;
    $in_progress = null;
    $next_check = null;
    $log_progress = [];
    $log_done = [];
    foreach ($checks as $c) {
        $st = $c['status'] ?? 'todo';
        $label = (string) ($c['label'] ?? '');
        if ($st === 'done') {
            $done++;
            $log_done[] = [
                'type'  => 'done',
                'title' => $label,
                'meta'  => 'Готово',
            ];
        } elseif ($st === 'progress') {
            if (!$in_progress) {
                $in_progress = $c;
            }
            $log_progress[] = [
                'type'  => 'progress',
                'title' => $label,
                'meta'  => 'В работе',
            ];
        } elseif (!$next_check) {
            $next_check = $c;
        }
    }
    $total = max(count($checks), 1);
    $pct = (int) round(($done / $total) * 100);

    $log_reqs = [];
    foreach (array_slice($reqs, 0, 3) as $r) {
        $log_reqs[] = [
            'type'  => 'request',
            'title' => wp_trim_words((string) ($r['message'] ?? 'Заявка'), 12, '…'),
            'meta'  => trim(((string) ($r['at'] ?? '')) . (!empty($r['service']) ? ' · ' . $r['service'] : '')),
        ];
    }
    // В работе → готовое → заявки
    $log = array_slice(array_merge($log_progress, array_reverse($log_done), $log_reqs), 0, 8);

    if ($summary === '') {
        if ($onb === 'profile') {
            $summary = 'Кабинет создан. Укажите сайт и телефон — без этого не начнём оценку проекта.';
        } elseif ($onb === 'request') {
            $summary = 'Данные проекта сохранены. Отправьте первую заявку — откроем чеклист и ответим в рабочие часы.';
        } elseif (!$site) {
            $summary = 'Профиль почти готов. Добавьте URL сайта во вкладке «Проект», чтобы мы привязали работы к площадке.';
        } elseif ($note !== '') {
            $summary = $note;
        } else {
            $host = wp_parse_url($site, PHP_URL_HOST) ?: $site;
            $summary = sprintf(
                'Работаем по проекту %s. Сейчас фаза «%s»: %d из %d пунктов чеклиста закрыто. Ниже — что в работе и что нужно от вас.',
                $host,
                $phases[$phase] ?? $phase,
                $done,
                $total
            );
        }
    }

    $next = [
        'eyebrow' => 'Следующий шаг',
        'title'   => '',
        'text'    => '',
        'cta'     => '',
        'tab'     => '',
        'done'    => false,
    ];
    if ($next_override !== '') {
        $next['eyebrow'] = 'От специалиста';
        $next['title'] = $next_override;
        $next['text'] = 'Если нужна уточняющая информация — напишите во вкладке «Заявка».';
        $next['cta'] = 'Написать заявку';
        $next['tab'] = 'request';
    } elseif ($onb === 'profile') {
        $next['title'] = 'Укажите сайт и телефон';
        $next['text'] = 'Форма настройки выше на этой странице. Без контакта и URL оценку не начнём.';
    } elseif ($onb === 'request') {
        $next['title'] = 'Отправьте первую заявку';
        $next['text'] = 'Коротко опишите задачу — откроем работу и ответим.';
    } elseif (!$site) {
        $next['title'] = 'Добавьте URL сайта';
        $next['text'] = 'Так мы привяжем аудит и отчёты к вашей площадке.';
        $next['cta'] = 'Открыть проект';
        $next['tab'] = 'project';
    } elseif ($in_progress) {
        $next['eyebrow'] = 'В работе у 5MB2';
        $next['title'] = (string) ($in_progress['label'] ?? 'Текущий этап');
        $next['text'] = 'Статус обновляем мы. Вопросы и материалы — во вкладке «Заявка».';
        $next['cta'] = 'Написать команде';
        $next['tab'] = 'request';
    } elseif ($next_check) {
        $next['eyebrow'] = 'В очереди';
        $next['title'] = (string) ($next_check['label'] ?? 'Следующий пункт');
        $next['text'] = 'Пункт ещё не стартовал. Обычно берём следующий после закрытия текущего.';
        $next['cta'] = 'Задать вопрос';
        $next['tab'] = 'request';
    } else {
        $next['eyebrow'] = 'Период закрыт';
        $next['title'] = 'Чеклист за период выполнен';
        $next['text'] = 'Ждите отчёт или опишите новую задачу — откроем следующий цикл.';
        $next['cta'] = $reports ? 'Смотреть отчёты' : 'Новая задача';
        $next['tab'] = $reports ? 'reports' : 'request';
        $next['done'] = true;
    }

    return [
        'phase'       => $phase,
        'phase_label' => $phases[$phase] ?? $phase,
        'summary'     => $summary,
        'note'        => $note,
        'next'        => $next,
        'log'         => $log,
        'progress'    => [
            'done'  => $done,
            'total' => $total,
            'pct'   => $pct,
        ],
        'kpis'        => $kpis,
        'latest_report' => $reports[0] ?? null,
        'site_host'   => $site ? (string) (wp_parse_url($site, PHP_URL_HOST) ?: $site) : '',
    ];
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
