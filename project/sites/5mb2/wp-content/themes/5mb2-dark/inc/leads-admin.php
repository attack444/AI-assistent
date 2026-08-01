<?php
/**
 * Заявки в админке WP + хранение лидов.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_menu_page(
        'Заявки 5MB2',
        'Заявки 5MB2',
        'manage_options',
        'mb2-leads',
        'mb2_render_leads_admin',
        'dashicons-email-alt',
        26
    );
});

function mb2_render_leads_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['mb2_clear_leads']) && check_admin_referer('mb2_clear_leads')) {
        update_option('mb2_leads', [], false);
        echo '<div class="updated"><p>Список очищен.</p></div>';
    }
    $leads = get_option('mb2_leads', []);
    if (!is_array($leads)) {
        $leads = [];
    }
    echo '<div class="wrap"><h1>Заявки с сайта</h1>';
    echo '<p>Лиды с формы на главной и со страниц услуг. Также дублируются на email администратора (если почта настроена).</p>';
    echo '<form method="post" style="margin:12px 0">';
    wp_nonce_field('mb2_clear_leads');
    echo '<button class="button" name="mb2_clear_leads" value="1" onclick="return confirm(\'Очистить все заявки?\')">Очистить список</button>';
    echo '</form>';
    if (!$leads) {
        echo '<p>Пока нет заявок.</p></div>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Дата</th><th>Имя</th><th>Email</th><th>Телефон</th><th>Услуга</th><th>Сайт</th><th>Сообщение</th>';
    echo '</tr></thead><tbody>';
    foreach ($leads as $lead) {
        echo '<tr>';
        echo '<td>' . esc_html($lead['at'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['name'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['email'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['phone'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['service'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['site'] ?? '') . '</td>';
        echo '<td>' . esc_html($lead['message'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
