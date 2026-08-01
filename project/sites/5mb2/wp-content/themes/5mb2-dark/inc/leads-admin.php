<?php
/**
 * Заявки + клиенты (чеклист / отчёты) в админке WP.
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
    add_submenu_page(
        'mb2-leads',
        'Клиенты SEO',
        'Клиенты SEO',
        'manage_options',
        'mb2-clients',
        'mb2_render_clients_admin'
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
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=mb2-clients')) . '">Открыть клиентов → чеклист / отчёты</a></p>';
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

function mb2_render_clients_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['mb2_save_client']) && check_admin_referer('mb2_save_client')) {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid && get_userdata($uid)) {
            $plan = sanitize_text_field(wp_unslash($_POST['plan'] ?? 'start'));
            update_user_meta($uid, 'mb2_plan', $plan);
            update_user_meta($uid, 'mb2_client_note', sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));

            $checks = mb2_get_checklist($uid);
            foreach ($checks as $i => $c) {
                $key = $c['key'] ?? ('i' . $i);
                $st = sanitize_text_field(wp_unslash($_POST['check_' . $key] ?? 'todo'));
                if (!in_array($st, ['todo', 'progress', 'done'], true)) {
                    $st = 'todo';
                }
                $checks[$i]['status'] = $st;
            }
            update_user_meta($uid, 'mb2_checklist', wp_json_encode($checks));

            $title = sanitize_text_field(wp_unslash($_POST['report_title'] ?? ''));
            $url = esc_url_raw(wp_unslash($_POST['report_url'] ?? ''));
            $rnote = sanitize_text_field(wp_unslash($_POST['report_note'] ?? ''));
            if ($title !== '') {
                $reports = get_user_meta($uid, 'mb2_reports', true);
                if (!is_array($reports)) {
                    $reports = [];
                }
                array_unshift($reports, [
                    'title' => $title,
                    'url'   => $url,
                    'note'  => $rnote,
                    'at'    => wp_date('Y-m-d H:i'),
                ]);
                update_user_meta($uid, 'mb2_reports', array_slice($reports, 0, 40));
            }
            echo '<div class="updated"><p>Клиент обновлён. Кабинет на сайте покажет новый прогресс.</p></div>';
        }
    }

    $users = get_users([
        'meta_key' => 'mb2_plan',
        'number'   => 200,
        'orderby'  => 'registered',
        'order'    => 'DESC',
    ]);

    echo '<div class="wrap"><h1>Клиенты SEO</h1>';
    echo '<p>Внутрянка: после заявки найдите клиента → выставьте тариф и статусы чеклиста → добавьте ссылку на отчёт. Клиент видит это в <code>/cabinet/</code>.</p>';

    if (!$users) {
        echo '<p>Пока нет зарегистрированных клиентов (meta <code>mb2_plan</code>). Пусть зарегистрируются в кабинете или создайте пользователя вручную и поставьте meta.</p></div>';
        return;
    }

    $edit_id = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Клиент</th><th>Email</th><th>Тариф</th><th>Сайт</th><th>Прогресс</th><th></th>';
    echo '</tr></thead><tbody>';
    foreach ($users as $u) {
        $checks = mb2_get_checklist($u->ID);
        $done = 0;
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'done') {
                $done++;
            }
        }
        $total = max(count($checks), 1);
        $site = get_user_meta($u->ID, 'mb2_site_url', true);
        $plan = get_user_meta($u->ID, 'mb2_plan', true) ?: 'start';
        echo '<tr>';
        echo '<td>' . esc_html($u->display_name) . '</td>';
        echo '<td>' . esc_html($u->user_email) . '</td>';
        echo '<td>' . esc_html($plan) . '</td>';
        echo '<td>' . esc_html($site ?: '—') . '</td>';
        echo '<td>' . esc_html($done . '/' . $total) . '</td>';
        echo '<td><a href="' . esc_url(admin_url('admin.php?page=mb2-clients&uid=' . $u->ID)) . '">Редактировать</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    if ($edit_id) {
        $u = get_userdata($edit_id);
        if ($u) {
            $checks = mb2_get_checklist($edit_id);
            $plan = get_user_meta($edit_id, 'mb2_plan', true) ?: 'start';
            $note = get_user_meta($edit_id, 'mb2_client_note', true) ?: '';
            echo '<hr><h2>Редактировать: ' . esc_html($u->display_name) . ' &lt;' . esc_html($u->user_email) . '&gt;</h2>';
            echo '<form method="post" style="max-width:720px">';
            wp_nonce_field('mb2_save_client');
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $edit_id) . '" />';
            echo '<p><label>Тариф<br><select name="plan">';
            foreach (['start' => 'Старт', 'audit' => 'Аудит', 'monthly' => 'Ежемесячное SEO', 'local' => 'Local SEO'] as $k => $lab) {
                echo '<option value="' . esc_attr($k) . '"' . selected($plan, $k, false) . '>' . esc_html($lab) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label>Комментарий клиенту (видно в кабинете)<br>';
            echo '<textarea name="note" rows="3" class="large-text">' . esc_textarea($note) . '</textarea></label></p>';
            echo '<h3>Чеклист</h3>';
            foreach ($checks as $c) {
                $key = $c['key'] ?? '';
                $st = $c['status'] ?? 'todo';
                echo '<p><strong>' . esc_html($c['label'] ?? $key) . '</strong><br>';
                echo '<select name="check_' . esc_attr($key) . '">';
                foreach (['todo' => 'Ожидает', 'progress' => 'В работе', 'done' => 'Готово'] as $sk => $sl) {
                    echo '<option value="' . esc_attr($sk) . '"' . selected($st, $sk, false) . '>' . esc_html($sl) . '</option>';
                }
                echo '</select></p>';
            }
            echo '<h3>Добавить отчёт (опционально)</h3>';
            echo '<p><label>Заголовок<br><input type="text" name="report_title" class="regular-text" placeholder="Отчёт за март" /></label></p>';
            echo '<p><label>Ссылка (Google Doc / PDF)<br><input type="url" name="report_url" class="regular-text" placeholder="https://" /></label></p>';
            echo '<p><label>Короткий комментарий<br><input type="text" name="report_note" class="regular-text" /></label></p>';
            echo '<p><button class="button button-primary" name="mb2_save_client" value="1">Сохранить</button></p>';
            echo '</form>';
        }
    }

    echo '</div>';
}
