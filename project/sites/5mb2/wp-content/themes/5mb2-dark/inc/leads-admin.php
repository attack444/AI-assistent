<?php
/**
 * Заявки + клиенты (чеклист / отчёты / обзор) в админке WP.
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
    add_submenu_page(
        'mb2-leads',
        'Обратная связь',
        'Обратная связь',
        'manage_options',
        'mb2-feedback',
        'mb2_render_feedback_admin'
    );
});

function mb2_render_feedback_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['mb2_clear_feedback']) && check_admin_referer('mb2_clear_feedback')) {
        update_option('mb2_feedback', [], false);
        echo '<div class="updated"><p>Список очищен.</p></div>';
    }
    $items = get_option('mb2_feedback', []);
    if (!is_array($items)) {
        $items = [];
    }
    $types = function_exists('mb2_feedback_types') ? mb2_feedback_types() : [];
    echo '<div class="wrap"><h1>Обратная связь</h1>';
    echo '<p>Идеи, ошибки и «мне нужно…» с сайта. Письма также уходят на email администратора.</p>';
    echo '<form method="post" style="margin:12px 0">';
    wp_nonce_field('mb2_clear_feedback');
    echo '<button class="button" name="mb2_clear_feedback" value="1" onclick="return confirm(\'Очистить?\')">Очистить список</button>';
    echo '</form>';
    if (!$items) {
        echo '<p>Пока пусто — виджет «Идея / ошибка» на сайте.</p></div>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Дата</th><th>Тип</th><th>Email</th><th>Страница</th><th>Сообщение</th>';
    echo '</tr></thead><tbody>';
    foreach ($items as $row) {
        $t = $row['type'] ?? '';
        echo '<tr>';
        echo '<td>' . esc_html($row['at'] ?? '') . '</td>';
        echo '<td>' . esc_html($types[$t] ?? $t) . '</td>';
        echo '<td>' . esc_html($row['email'] ?? '') . '</td>';
        echo '<td>' . esc_html($row['page'] ?? '') . '</td>';
        echo '<td>' . esc_html($row['message'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

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
            $labels = mb2_plan_labels();
            if (!isset($labels[$plan])) {
                $plan = 'start';
            }
            $reset_checks = !empty($_POST['reset_checklist']);
            mb2_apply_client_plan($uid, $plan, $reset_checks);

            update_user_meta($uid, 'mb2_client_note', sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
            update_user_meta($uid, 'mb2_summary', sanitize_textarea_field(wp_unslash($_POST['summary'] ?? '')));
            update_user_meta($uid, 'mb2_next_action', sanitize_text_field(wp_unslash($_POST['next_action'] ?? '')));

            $phase = sanitize_text_field(wp_unslash($_POST['phase'] ?? ''));
            $phases = mb2_project_phases();
            if ($phase === 'auto' || $phase === '') {
                delete_user_meta($uid, 'mb2_phase');
            } elseif (isset($phases[$phase])) {
                update_user_meta($uid, 'mb2_phase', $phase);
            }

            update_user_meta($uid, 'mb2_kpis', [
                'organic'  => sanitize_text_field(wp_unslash($_POST['kpi_organic'] ?? '')),
                'keywords' => sanitize_text_field(wp_unslash($_POST['kpi_keywords'] ?? '')),
                'leads'    => sanitize_text_field(wp_unslash($_POST['kpi_leads'] ?? '')),
            ]);

            if (!$reset_checks) {
                $checks = mb2_get_checklist($uid);
                foreach ($checks as $i => $c) {
                    $key = $c['key'] ?? ('i' . $i);
                    $st = sanitize_text_field(wp_unslash($_POST['check_' . $key] ?? 'todo'));
                    if (!in_array($st, ['todo', 'progress', 'done'], true)) {
                        $st = 'todo';
                    }
                    $checks[$i]['status'] = $st;
                }
                mb2_set_checklist($uid, $checks, $plan);
            }

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
            echo '<div class="updated"><p>Клиент обновлён. Кабинет на сайте покажет новый прогресс и обзор.</p></div>';
        }
    }

    $users = get_users([
        'meta_key' => 'mb2_plan',
        'number'   => 200,
        'orderby'  => 'registered',
        'order'    => 'DESC',
    ]);

    echo '<div class="wrap"><h1>Клиенты SEO</h1>';
    echo '<p>Внутрянка: тариф, фаза проекта, сводка для «Обзора», чеклист, KPI и отчёты. Клиент видит это в <code>/cabinet/</code>.</p>';

    if (!$users) {
        echo '<p>Пока нет зарегистрированных клиентов (meta <code>mb2_plan</code>). Пусть зарегистрируются в кабинете или создайте пользователя вручную и поставьте meta.</p></div>';
        return;
    }

    $edit_id = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
    $phases = mb2_project_phases();

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Клиент</th><th>Email</th><th>Тариф</th><th>Фаза</th><th>Сайт</th><th>Прогресс</th><th></th>';
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
        $phase = mb2_get_project_phase($u->ID);
        echo '<tr>';
        echo '<td>' . esc_html($u->display_name) . '</td>';
        echo '<td>' . esc_html($u->user_email) . '</td>';
        echo '<td>' . esc_html($plan) . '</td>';
        echo '<td>' . esc_html($phases[$phase] ?? $phase) . '</td>';
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
            $summary = get_user_meta($edit_id, 'mb2_summary', true) ?: '';
            $next_action = get_user_meta($edit_id, 'mb2_next_action', true) ?: '';
            $saved_phase = (string) get_user_meta($edit_id, 'mb2_phase', true);
            $inferred = mb2_infer_project_phase($edit_id);
            $kpis = mb2_get_project_kpis($edit_id);

            echo '<hr><h2>Редактировать: ' . esc_html($u->display_name) . ' &lt;' . esc_html($u->user_email) . '&gt;</h2>';
            echo '<form method="post" style="max-width:720px">';
            wp_nonce_field('mb2_save_client');
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $edit_id) . '" />';

            echo '<h3>Обзор проекта (видно клиенту)</h3>';
            echo '<p><label>Тариф (сценарий чеклиста)<br><select name="plan">';
            foreach (mb2_plan_labels() as $k => $lab) {
                echo '<option value="' . esc_attr($k) . '"' . selected($plan, $k, false) . '>' . esc_html($lab) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label><input type="checkbox" name="reset_checklist" value="1" /> Сбросить чеклист под выбранный тариф</label><br>';
            echo '<span class="description">Включите, если клиент взял аудит, а в кабинете ещё «семантика / контент-план». Статусы обнулятся.</span></p>';

            echo '<p><label>Фаза проекта<br><select name="phase">';
            echo '<option value="auto"' . selected($saved_phase, '', false) . '>Авто (сейчас: ' . esc_html($phases[$inferred] ?? $inferred) . ')</option>';
            foreach ($phases as $k => $lab) {
                echo '<option value="' . esc_attr($k) . '"' . selected($saved_phase, $k, false) . '>' . esc_html($lab) . '</option>';
            }
            echo '</select></label></p>';

            echo '<p><label>Сводка для обзора (30 секунд, простым языком)<br>';
            echo '<textarea name="summary" rows="3" class="large-text" placeholder="Что сделали, что меняется, что дальше. Пусто = текст соберётся сам.">' . esc_textarea($summary) . '</textarea></label></p>';

            echo '<p><label>Короткий комментарий специалисту → клиенту<br>';
            echo '<textarea name="note" rows="2" class="large-text" placeholder="Если сводка пуста — покажем этот текст.">' . esc_textarea($note) . '</textarea></label></p>';

            echo '<p><label>Следующий шаг (переопределение)<br>';
            echo '<input type="text" name="next_action" class="large-text" value="' . esc_attr($next_action) . '" placeholder="Напр.: пришлите доступы к Метрике" /></label></p>';

            echo '<h3>KPI в обзоре (опционально)</h3>';
            echo '<p class="description">Если пусто — клиент видит прогресс чеклиста, сайт и число обращений. Заполняйте цифры вручную из Search Console / отчёта.</p>';
            echo '<p><label>Органика / трафик<br><input type="text" name="kpi_organic" class="regular-text" value="' . esc_attr($kpis['organic']) . '" placeholder="+18% к прошлому месяцу" /></label></p>';
            echo '<p><label>Запросы в топе<br><input type="text" name="kpi_keywords" class="regular-text" value="' . esc_attr($kpis['keywords']) . '" placeholder="12 в топ-10" /></label></p>';
            echo '<p><label>Лиды / заявки с сайта<br><input type="text" name="kpi_leads" class="regular-text" value="' . esc_attr($kpis['leads']) . '" placeholder="4 заявки за месяц" /></label></p>';

            echo '<h3>Чеклист</h3>';
            foreach ($checks as $c) {
                $key = $c['key'] ?? '';
                $st = $c['status'] ?? 'todo';
                echo '<p><strong>' . esc_html($c['label'] ?? $key) . '</strong><br>';
                echo '<select name="check_' . esc_attr($key) . '">';
                foreach (['todo' => 'В очереди', 'progress' => 'Делаем сейчас', 'done' => 'Сделано'] as $sk => $sl) {
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
