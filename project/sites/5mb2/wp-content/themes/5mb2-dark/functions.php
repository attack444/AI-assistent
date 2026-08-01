<?php
/**
 * 5MB2 Dark — SEO theme.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MB2_THEME_VER', '1.9.4');

require get_template_directory() . '/inc/services.php';
require get_template_directory() . '/inc/legal.php';
require get_template_directory() . '/inc/seo.php';
require get_template_directory() . '/inc/projects.php';
require get_template_directory() . '/inc/seed.php';
require get_template_directory() . '/inc/leads-admin.php';
require get_template_directory() . '/inc/feedback.php';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer'  => 'Меню в подвале',
    ]);
});

/** Клиентам (subscriber) не показываем чёрную полоску WP на сайте. */
add_filter('show_admin_bar', function ($show) {
    if (!is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options') || current_user_can('edit_posts')) {
        return $show;
    }
    return false;
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
            <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_service, $slug); selected($selected_service, $svc['title']); ?>>
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
    update_user_meta($user_id, 'mb2_site_url', '');
    update_user_meta($user_id, 'mb2_phone', '');
    mb2_apply_client_plan($user_id, 'start', true);
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
    $service_label = mb2_resolve_service_label($service);
    $list = get_user_meta($uid, 'mb2_requests', true);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, [
        'at'      => current_time('mysql'),
        'message' => $msg,
        'service' => $service_label,
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
            "Клиент: {$user->display_name} <{$user->user_email}>\nТелефон: {$phone}\nСайт: {$site}\nУслуга: {$service_label}\n\n{$msg}\n"
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
        'service' => $service_label,
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
    // Тариф и чеклист — по выбранной услуге (аудит ≠ ежемесячное SEO)
    $plan = mb2_service_to_plan($service);
    mb2_apply_client_plan($uid, $plan, true);
    $checks = mb2_get_checklist($uid);
    if ($checks && ($checks[0]['status'] ?? '') === 'todo') {
        $checks[0]['status'] = 'progress';
        mb2_set_checklist($uid, $checks, $plan);
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
    // Новая услуга может сменить сценарий (если ещё «Старт» или чеклист без прогресса)
    $plan = mb2_service_to_plan($service);
    $current = (string) (get_user_meta($uid, 'mb2_plan', true) ?: 'start');
    if ($plan !== 'start' && ($current === 'start' || !mb2_checklist_has_progress(mb2_get_checklist($uid)))) {
        mb2_apply_client_plan($uid, $plan, true);
        $checks = mb2_get_checklist($uid);
        if ($checks && ($checks[0]['status'] ?? '') === 'todo') {
            $checks[0]['status'] = 'progress';
            mb2_set_checklist($uid, $checks, $plan);
        }
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
    $service_label = mb2_resolve_service_label($service);
    if (strlen($msg) < 3) {
        $msg = $service_label ? ('Заявка: ' . $service_label) : 'Заявка на SEO-стратегию';
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
        'service' => $service_label,
        'site'    => $site,
        'message' => $msg,
    ]);
    update_option('mb2_leads', array_slice($leads, 0, 200), false);

    $admin = get_option('admin_email');
    if ($admin) {
        wp_mail(
            $admin,
            'Заявка 5MB2: ' . ($service_label ?: $name),
            "Имя: {$name}\nEmail: {$email}\nТелефон: {$phone}\nУслуга: {$service_label}\nСайт: {$site}\n\n{$msg}\n"
        );
    }

    wp_send_json_success([
        'message'  => 'Заявка принята',
        'redirect' => home_url('/spasibo/'),
    ]);
}

function mb2_plan_labels() {
    return [
        'start'   => 'Старт',
        'audit'   => 'SEO-аудит',
        'monthly' => 'SEO-продвижение',
        'local'   => 'Local SEO',
        'tech'    => 'Техническое SEO',
        'content' => 'Контент для SEO',
    ];
}

function mb2_resolve_service_label($service) {
    $service = trim((string) $service);
    if ($service === '') {
        return '';
    }
    $catalog = function_exists('mb2_services_catalog') ? mb2_services_catalog() : [];
    if (isset($catalog[$service]['title'])) {
        return (string) $catalog[$service]['title'];
    }
    return $service;
}

/** Услуга (slug или название) → тариф кабинета. */
function mb2_service_to_plan($service) {
    $raw = trim((string) $service);
    if ($raw === '') {
        return 'start';
    }
    $s = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    $slug_map = [
        'seo-audit'    => 'audit',
        'prodvizhenie' => 'monthly',
        'local-seo'    => 'local',
        'tech-seo'     => 'tech',
        'kontent'      => 'content',
        'otchetnost'   => 'monthly',
        'start'        => 'start',
        'audit'        => 'audit',
        'monthly'      => 'monthly',
        'local'        => 'local',
        'tech'         => 'tech',
        'content'      => 'content',
    ];
    if (isset($slug_map[$s])) {
        return $slug_map[$s];
    }
    if (isset($slug_map[$raw])) {
        return $slug_map[$raw];
    }
    if (function_exists('mb2_services_catalog')) {
        foreach (mb2_services_catalog() as $slug => $svc) {
            $title = function_exists('mb_strtolower')
                ? mb_strtolower((string) ($svc['title'] ?? ''), 'UTF-8')
                : strtolower((string) ($svc['title'] ?? ''));
            if ($title !== '' && $title === $s && isset($slug_map[$slug])) {
                return $slug_map[$slug];
            }
        }
    }
    if (str_contains($s, 'local')) {
        return 'local';
    }
    if (str_contains($s, 'аудит') || str_contains($s, 'audit')) {
        return 'audit';
    }
    if (str_contains($s, 'технич') || (str_contains($s, 'tech') && !str_contains($s, 'content'))) {
        return 'tech';
    }
    if (str_contains($s, 'контент') || str_contains($s, 'content')) {
        return 'content';
    }
    if (str_contains($s, 'продвиж') || str_contains($s, 'ежемесяч')) {
        return 'monthly';
    }
    return 'start';
}

function mb2_checklist_for_plan($plan) {
    $plan = (string) $plan;
    $templates = [
        'start' => [
            ['key' => 'profile', 'label' => 'Заполнить сайт и телефон', 'status' => 'todo'],
            ['key' => 'brief', 'label' => 'Отправить задачу (какая услуга нужна)', 'status' => 'todo'],
            ['key' => 'goals', 'label' => 'Согласовать цель и формат работы', 'status' => 'todo'],
            ['key' => 'access', 'label' => 'Передать доступы (сайт, Метрика, Вебмастер)', 'status' => 'todo'],
            ['key' => 'estimate', 'label' => 'Получить оценку и выбрать тариф', 'status' => 'todo'],
        ],
        'audit' => [
            ['key' => 'access', 'label' => 'Сбор доступов и аналитики', 'status' => 'todo'],
            ['key' => 'tech', 'label' => 'Технический аудит и индексация', 'status' => 'todo'],
            ['key' => 'speed', 'label' => 'Скорость и Core Web Vitals', 'status' => 'todo'],
            ['key' => 'competitors', 'label' => 'Сравнение с конкурентами', 'status' => 'todo'],
            ['key' => 'plan', 'label' => 'Приоритетный план правок', 'status' => 'todo'],
            ['key' => 'delivery', 'label' => 'Отчёт аудита и разбор', 'status' => 'todo'],
        ],
        'monthly' => [
            ['key' => 'audit', 'label' => 'Базовый технический аудит', 'status' => 'todo'],
            ['key' => 'semantics', 'label' => 'Семантическое ядро', 'status' => 'todo'],
            ['key' => 'structure', 'label' => 'Структура, мета и ТЗ', 'status' => 'todo'],
            ['key' => 'content', 'label' => 'Контент и внутренняя оптимизация', 'status' => 'todo'],
            ['key' => 'links', 'label' => 'Ссылочный профиль (по плану)', 'status' => 'todo'],
            ['key' => 'report', 'label' => 'Ежемесячный отчёт', 'status' => 'todo'],
        ],
        'local' => [
            ['key' => 'nap', 'label' => 'Аудит NAP и карточек организации', 'status' => 'todo'],
            ['key' => 'maps', 'label' => 'Яндекс/Google Карты и карточки', 'status' => 'todo'],
            ['key' => 'landings', 'label' => 'Региональные посадочные', 'status' => 'todo'],
            ['key' => 'reviews', 'label' => 'Отзывы и репутация', 'status' => 'todo'],
            ['key' => 'local_factors', 'label' => 'Локальные коммерческие факторы', 'status' => 'todo'],
            ['key' => 'report', 'label' => 'Отчёт по заявкам из города', 'status' => 'todo'],
        ],
        'tech' => [
            ['key' => 'access', 'label' => 'Доступы и карта текущего состояния', 'status' => 'todo'],
            ['key' => 'crawl', 'label' => 'Индексация, robots, карта сайта', 'status' => 'todo'],
            ['key' => 'cwv', 'label' => 'Скорость и Core Web Vitals', 'status' => 'todo'],
            ['key' => 'schema', 'label' => 'Микроразметка Schema', 'status' => 'todo'],
            ['key' => 'fixes', 'label' => 'Внедрение технических правок', 'status' => 'todo'],
            ['key' => 'delivery', 'label' => 'Отчёт и рекомендации', 'status' => 'todo'],
        ],
        'content' => [
            ['key' => 'brief', 'label' => 'Бриф: ниша, страницы, тон', 'status' => 'todo'],
            ['key' => 'semantics', 'label' => 'Семантика и интент под страницы', 'status' => 'todo'],
            ['key' => 'tz', 'label' => 'ТЗ на тексты', 'status' => 'todo'],
            ['key' => 'drafts', 'label' => 'Черновики и редактура', 'status' => 'todo'],
            ['key' => 'publish', 'label' => 'Публикация / передача текстов', 'status' => 'todo'],
            ['key' => 'delivery', 'label' => 'Итог и рекомендации', 'status' => 'todo'],
        ],
    ];
    return $templates[$plan] ?? $templates['start'];
}

/** @deprecated Используйте mb2_checklist_for_plan() */
function mb2_default_checklist() {
    return mb2_checklist_for_plan('start');
}

function mb2_checklist_title_for_plan($plan) {
    $titles = [
        'start'   => 'Чеклист знакомства',
        'audit'   => 'Чеклист SEO-аудита',
        'monthly' => 'Чеклист SEO-продвижения',
        'local'   => 'Чеклист Local SEO',
        'tech'    => 'Чеклист технического SEO',
        'content' => 'Чеклист контента',
    ];
    return $titles[$plan] ?? 'Чеклист работ';
}

function mb2_status_label($status) {
    $map = [
        'todo'     => 'В очереди',
        'progress' => 'Делаем сейчас',
        'done'     => 'Сделано',
    ];
    return $map[$status] ?? 'В очереди';
}

function mb2_checklist_has_progress(array $items) {
    foreach ($items as $c) {
        if (($c['status'] ?? 'todo') !== 'todo') {
            return true;
        }
    }
    return false;
}

function mb2_checklist_keys(array $items) {
    $keys = [];
    foreach ($items as $c) {
        $keys[] = (string) ($c['key'] ?? '');
    }
    return $keys;
}

/**
 * Назначить тариф и (при необходимости) чеклист сценария.
 */
function mb2_apply_client_plan($user_id, $plan, $reset_checklist = false) {
    $user_id = (int) $user_id;
    $labels = mb2_plan_labels();
    if (!isset($labels[$plan])) {
        $plan = 'start';
    }
    $prev_plan = (string) (get_user_meta($user_id, 'mb2_plan', true) ?: '');
    $tpl = (string) get_user_meta($user_id, 'mb2_checklist_plan', true);
    update_user_meta($user_id, 'mb2_plan', $plan);

    $need_reset = $reset_checklist || $tpl === '' || $tpl !== $plan || $prev_plan !== $plan;
    if ($need_reset) {
        $existing = get_user_meta($user_id, 'mb2_checklist', true);
        $items = is_array($existing) ? $existing : [];
        if ($reset_checklist || $tpl !== $plan || !mb2_checklist_has_progress($items)) {
            mb2_set_checklist($user_id, mb2_checklist_for_plan($plan), $plan);
        } else {
            update_user_meta($user_id, 'mb2_checklist_plan', $plan);
        }
    }
    return $plan;
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
    return (bool) preg_match('/(?<!\\\\)u04[0-9a-fA-F]{2}/', $label)
        || str_contains($label, '\\u04')
        || str_contains($label, 'u040');
}

function mb2_normalize_checklist($items, $plan = 'start') {
    $defaults = mb2_checklist_for_plan($plan);
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

function mb2_set_checklist($user_id, array $items, $plan = null) {
    $user_id = (int) $user_id;
    if ($plan === null) {
        $plan = (string) (get_user_meta($user_id, 'mb2_plan', true) ?: 'start');
    }
    $labels = mb2_plan_labels();
    if (!isset($labels[$plan])) {
        $plan = 'start';
    }
    $norm = mb2_normalize_checklist($items, $plan);
    $list = $norm['items'];
    update_user_meta($user_id, 'mb2_checklist', $list);
    update_user_meta($user_id, 'mb2_checklist_plan', $plan);
    return $list;
}

function mb2_sync_plan_from_requests($user_id) {
    $user_id = (int) $user_id;
    $plan = (string) (get_user_meta($user_id, 'mb2_plan', true) ?: 'start');
    $labels = mb2_plan_labels();
    if (!isset($labels[$plan])) {
        $plan = 'start';
    }
    if ($plan !== 'start') {
        return $plan;
    }
    $reqs = get_user_meta($user_id, 'mb2_requests', true);
    if (!is_array($reqs) || !$reqs) {
        return $plan;
    }
    $svc = (string) ($reqs[0]['service'] ?? '');
    $inferred = mb2_service_to_plan($svc);
    if ($inferred !== 'start') {
        mb2_apply_client_plan($user_id, $inferred, true);
        return $inferred;
    }
    return $plan;
}

function mb2_get_checklist($user_id) {
    $user_id = (int) $user_id;
    $plan = mb2_sync_plan_from_requests($user_id);
    $labels = mb2_plan_labels();
    if (!isset($labels[$plan])) {
        $plan = 'start';
        update_user_meta($user_id, 'mb2_plan', $plan);
    }
    $tpl = (string) get_user_meta($user_id, 'mb2_checklist_plan', true);
    $raw = get_user_meta($user_id, 'mb2_checklist', true);
    $items = null;

    if (is_array($raw) && $raw) {
        $items = $raw;
    } elseif (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $repaired = preg_replace('/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $raw);
            $decoded = is_string($repaired) ? json_decode($repaired, true) : null;
        }
        if (is_array($decoded)) {
            $items = $decoded;
        }
    }

    $expected = mb2_checklist_for_plan($plan);
    if ($items === null) {
        return mb2_set_checklist($user_id, $expected, $plan);
    }

    $norm = mb2_normalize_checklist($items, $plan);
    $list = $norm['items'];
    $keys_mismatch = mb2_checklist_keys($list) !== mb2_checklist_keys($expected);
    $wrong_tpl = ($tpl === '' || $tpl !== $plan);

    if ($wrong_tpl && $keys_mismatch && !mb2_checklist_has_progress($list)) {
        return mb2_set_checklist($user_id, $expected, $plan);
    }
    if ($wrong_tpl && $keys_mismatch && in_array($plan, ['audit', 'local', 'tech', 'content', 'start'], true)) {
        return mb2_set_checklist($user_id, $expected, $plan);
    }

    if (!is_array($raw) || !empty($norm['changed']) || $tpl !== $plan) {
        mb2_set_checklist($user_id, $list, $plan);
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
    $plan = (string) (get_user_meta($user_id, 'mb2_plan', true) ?: 'start');
    if ($plan === 'start') {
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
    if (!empty($keys_done['report']) || !empty($keys_done['delivery']) || $done >= $total) {
        return 'reporting';
    }
    if (in_array($plan, ['audit', 'tech', 'content'], true)) {
        return ($done >= 1 || $progress > 0) ? 'audit' : 'audit';
    }
    if ($plan === 'local') {
        return $done >= 3 ? 'growth' : 'foundation';
    }
    // monthly
    if ($done >= 3 || (!empty($keys_done['content']) || !empty($keys_done['links']))) {
        return 'growth';
    }
    if ($done >= 1 || $progress > 0) {
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
    $plan = (string) (get_user_meta($user_id, 'mb2_plan', true) ?: 'start');
    $plan_labels = mb2_plan_labels();
    $plan_label = $plan_labels[$plan] ?? $plan;
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
                'meta'  => mb2_status_label('done'),
            ];
        } elseif ($st === 'progress') {
            if (!$in_progress) {
                $in_progress = $c;
            }
            $log_progress[] = [
                'type'  => 'progress',
                'title' => $label,
                'meta'  => mb2_status_label('progress'),
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
            $summary = 'Кабинет создан. Укажите сайт и телефон — без этого не начнём оценку.';
        } elseif ($onb === 'request') {
            $summary = 'Данные сохранены. Выберите услугу и опишите задачу — откроем нужный чеклист (аудит, продвижение или Local SEO).';
        } elseif (!$site) {
            $summary = 'Добавьте URL сайта во вкладке «Проект», чтобы привязать работы к площадке.';
        } elseif ($note !== '') {
            $summary = $note;
        } else {
            $host = wp_parse_url($site, PHP_URL_HOST) ?: $site;
            if ($plan === 'start') {
                $summary = sprintf(
                    'Проект %s: пока этап знакомства. Выберите услугу в заявке — чеклист станет под ваш сценарий (не общий список на все услуги).',
                    $host
                );
            } elseif ($plan === 'audit') {
                $summary = sprintf(
                    'Идёт SEO-аудит для %s: %d из %d шагов. Это разовая работа с отчётом и планом правок — без ежемесячного контент-плана.',
                    $host,
                    $done,
                    $total
                );
            } elseif ($plan === 'local') {
                $summary = sprintf(
                    'Local SEO для %s: %d из %d шагов — карты, карточки, региональные страницы и заявки из города.',
                    $host,
                    $done,
                    $total
                );
            } elseif ($plan === 'tech') {
                $summary = sprintf(
                    'Техническое SEO для %s: %d из %d шагов — индексация, скорость, разметка и правки.',
                    $host,
                    $done,
                    $total
                );
            } elseif ($plan === 'content') {
                $summary = sprintf(
                    'Контент для SEO по %s: %d из %d шагов — от брифа и ТЗ до готовых текстов.',
                    $host,
                    $done,
                    $total
                );
            } else {
                $summary = sprintf(
                    'SEO-продвижение %s (%s): %d из %d пунктов закрыто. Ниже — что делаем сейчас и что в очереди.',
                    $host,
                    $plan_label,
                    $done,
                    $total
                );
            }
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
        $next['eyebrow'] = 'Сейчас делаем';
        $next['title'] = (string) ($in_progress['label'] ?? 'Текущий этап');
        $next['text'] = 'Это наш текущий шаг по тарифу «' . $plan_label . '». Вопросы и доступы — во вкладке «Заявка».';
        $next['cta'] = 'Написать команде';
        $next['tab'] = 'request';
    } elseif ($next_check) {
        $next['eyebrow'] = 'Следом в очереди';
        $next['title'] = (string) ($next_check['label'] ?? 'Следующий пункт');
        $next['text'] = 'Ещё не начали — возьмём после текущего шага. От вас пока ничего не нужно, если не просили доступы.';
        $next['cta'] = 'Задать вопрос';
        $next['tab'] = 'request';
    } else {
        $next['eyebrow'] = in_array($plan, ['audit', 'tech', 'content'], true) ? 'Работа завершена' : 'Период закрыт';
        $next['title'] = in_array($plan, ['audit', 'tech', 'content'], true)
            ? 'Все шаги по услуге выполнены'
            : 'Чеклист за период выполнен';
        $next['text'] = in_array($plan, ['audit', 'tech', 'content'], true)
            ? 'Смотрите отчёт или напишите, если нужна следующая услуга (продвижение, контент, Local SEO).'
            : 'Ждите отчёт или опишите новую задачу — откроем следующий цикл.';
        $next['cta'] = $reports ? 'Смотреть отчёты' : 'Новая задача';
        $next['tab'] = $reports ? 'reports' : 'request';
        $next['done'] = true;
    }

    $expect = 'SEO — накопительный канал: заметные сдвиги обычно через 2–4 месяца после базы.';
    if ($plan === 'audit') {
        $expect = 'Аудит — разовая услуга на 5–10 рабочих дней: доступы → проверка → план правок → отчёт. Без семантики и контент-плана «в долгую».';
    } elseif ($plan === 'local') {
        $expect = 'Local SEO часто даёт заявки быстрее общего продвижения: карты и карточки — в приоритете первых недель.';
    } elseif ($plan === 'tech') {
        $expect = 'Техническое SEO чинит фундамент (индекс, скорость, разметка). Срок зависит от CMS и объёма URL.';
    } elseif ($plan === 'content') {
        $expect = 'Контент: от брифа до готовых страниц. Срок обычно от нескольких дней на страницу.';
    } elseif ($plan === 'start') {
        $expect = 'Сначала выберите услугу в заявке — тогда здесь появится чеклист именно под неё, а не общий список.';
    }

    return [
        'phase'       => $phase,
        'phase_label' => $phases[$phase] ?? $phase,
        'plan'        => $plan,
        'plan_label'  => $plan_label,
        'checklist_title' => mb2_checklist_title_for_plan($plan),
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
        'expect'      => $expect,
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
