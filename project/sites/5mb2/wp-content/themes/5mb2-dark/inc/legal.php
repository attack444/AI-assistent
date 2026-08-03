<?php
/**
 * Реквизиты самозанятого (НПД) и юр.тексты по 422-ФЗ / 152-ФЗ.
 * Заполните в WP: Настройки → 5MB2 реквизиты.
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_legal_defaults() {
    return [
        'fio'          => 'Сундуков Вячеслав Алексеевич',
        'inn'          => '',
        'city'         => 'Россия',
        'email'        => 'hello@5mb2.ru',
        'phone'        => '',
        'brand'        => '5MB2 Digital',
        'npd'          => '1',
        // Банковские реквизиты (Сбер, накопительный счёт)
        'bank_account' => '40817810442000555115',
        'bank_name'    => 'ВОЛГО-ВЯТСКИЙ БАНК ПАО СБЕРБАНК',
        'bank_bik'     => '042202603',
        'bank_corr'    => '30101810900000000603',
        'bank_inn'     => '7707083893',
        'bank_kpp'     => '526002001',
        'bank_branch'  => '42/9042/00325',
        'bank_address' => 'г. Арзамас, ул. Мира, 7',
        'bank_type'    => 'Накопительный счёт',
    ];
}

/** Банковские реквизиты для страницы /rekvizity/ */
function mb2_bank_details() {
    $o = mb2_legal();
    $account = preg_replace('/\D+/', '', (string) ($o['bank_account'] ?? ''));
    $corr    = preg_replace('/\D+/', '', (string) ($o['bank_corr'] ?? ''));
    return [
        'payee'       => trim($o['fio'] ?? '') ?: 'Сундуков Вячеслав Алексеевич',
        'account'     => $account,
        'account_fmt' => mb2_format_account($account),
        'account_type'=> $o['bank_type'] ?: 'Накопительный счёт',
        'bank_name'   => $o['bank_name'] ?: 'ВОЛГО-ВЯТСКИЙ БАНК ПАО СБЕРБАНК',
        'bik'         => preg_replace('/\D+/', '', (string) ($o['bank_bik'] ?? '')),
        'corr'        => $corr,
        'corr_fmt'    => mb2_format_corr($corr),
        'bank_inn'    => preg_replace('/\D+/', '', (string) ($o['bank_inn'] ?? '')),
        'bank_kpp'    => preg_replace('/\D+/', '', (string) ($o['bank_kpp'] ?? '')),
        'branch'      => $o['bank_branch'] ?: '',
        'address'     => $o['bank_address'] ?: '',
        'payee_inn'   => preg_replace('/\D+/', '', (string) ($o['inn'] ?? '')),
        'email'       => $o['email'] ?: 'hello@5mb2.ru',
        'brand'       => $o['brand'] ?: '5MB2 Digital',
        'npd'         => ($o['npd'] ?? '1') === '1',
    ];
}

function mb2_format_account($digits) {
    $d = preg_replace('/\D+/', '', (string) $digits);
    if (strlen($d) !== 20) {
        return $d;
    }
    // 40817 810 4 4200 0555115
    return substr($d, 0, 5) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 1) . ' ' . substr($d, 9, 4) . ' ' . substr($d, 13);
}

function mb2_format_corr($digits) {
    $d = preg_replace('/\D+/', '', (string) $digits);
    if (strlen($d) !== 20) {
        return $d;
    }
    // 30101 810 9 0000 0000603
    return substr($d, 0, 5) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 1) . ' ' . substr($d, 9, 4) . ' ' . substr($d, 13);
}

function mb2_legal($key = null) {
    $opts = wp_parse_args(get_option('mb2_legal', []), mb2_legal_defaults());
    if ($key === null) {
        return $opts;
    }
    return $opts[$key] ?? '';
}

function mb2_legal_display_name() {
    $fio = trim(mb2_legal('fio'));
    return $fio !== '' ? $fio : mb2_legal('brand');
}

add_action('admin_menu', function () {
    add_options_page(
        '5MB2 реквизиты',
        '5MB2 реквизиты',
        'manage_options',
        'mb2-legal',
        'mb2_render_legal_admin'
    );
});

function mb2_render_legal_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['mb2_legal_save']) && check_admin_referer('mb2_legal_save')) {
        $data = [
            'fio'          => sanitize_text_field(wp_unslash($_POST['fio'] ?? '')),
            'inn'          => preg_replace('/\D+/', '', (string) ($_POST['inn'] ?? '')),
            'city'         => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'email'        => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone'        => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'brand'        => sanitize_text_field(wp_unslash($_POST['brand'] ?? '5MB2 Digital')),
            'npd'          => !empty($_POST['npd']) ? '1' : '0',
            'bank_account' => preg_replace('/\D+/', '', (string) ($_POST['bank_account'] ?? '')),
            'bank_name'    => sanitize_text_field(wp_unslash($_POST['bank_name'] ?? '')),
            'bank_bik'     => preg_replace('/\D+/', '', (string) ($_POST['bank_bik'] ?? '')),
            'bank_corr'    => preg_replace('/\D+/', '', (string) ($_POST['bank_corr'] ?? '')),
            'bank_inn'     => preg_replace('/\D+/', '', (string) ($_POST['bank_inn'] ?? '')),
            'bank_kpp'     => preg_replace('/\D+/', '', (string) ($_POST['bank_kpp'] ?? '')),
            'bank_branch'  => sanitize_text_field(wp_unslash($_POST['bank_branch'] ?? '')),
            'bank_address' => sanitize_text_field(wp_unslash($_POST['bank_address'] ?? '')),
            'bank_type'    => sanitize_text_field(wp_unslash($_POST['bank_type'] ?? '')),
        ];
        update_option('mb2_legal', $data, false);
        if (function_exists('mb2_upsert_page')) {
            mb2_upsert_page('rekvizity', 'Реквизиты', mb2_rekvizity_html(), '', 0, true);
        }
        echo '<div class="updated"><p>Сохранено. Страница «Реквизиты» обновлена.</p></div>';
    }
    $o = mb2_legal();
    echo '<div class="wrap"><h1>Реквизиты самозанятого (НПД)</h1>';
    echo '<p>Для сайта и оферты (422-ФЗ НПД, 152-ФЗ). Чеки — в <strong>Мой налог</strong>.</p>';
    echo '<form method="post">';
    wp_nonce_field('mb2_legal_save');
    echo '<table class="form-table">';
    $fields = [
        'fio'          => 'ФИО полностью (получатель)',
        'inn'          => 'ИНН получателя (12 цифр, если есть)',
        'city'         => 'Город / регион',
        'email'        => 'Email для связи и оферты',
        'phone'        => 'Телефон',
        'brand'        => 'Бренд на сайте',
        'bank_account' => 'Номер счёта (20 цифр)',
        'bank_type'    => 'Тип счёта',
        'bank_name'    => 'Наименование банка',
        'bank_bik'     => 'БИК',
        'bank_corr'    => 'Корр. счёт',
        'bank_inn'     => 'ИНН банка',
        'bank_kpp'     => 'КПП банка',
        'bank_branch'  => 'Код подразделения',
        'bank_address' => 'Адрес подразделения',
    ];
    foreach ($fields as $k => $label) {
        echo '<tr><th><label for="' . esc_attr($k) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input class="regular-text" id="' . esc_attr($k) . '" name="' . esc_attr($k) . '" value="' . esc_attr($o[$k] ?? '') . '" /></td></tr>';
    }
    echo '<tr><th>Статус</th><td><label><input type="checkbox" name="npd" value="1" ' . checked($o['npd'], '1', false) . ' /> Самозанятый (НПД), без НДС</label></td></tr>';
    echo '</table>';
    echo '<p><button class="button button-primary" name="mb2_legal_save" value="1">Сохранить</button></p>';
    echo '</form>';
    echo '<p><a href="' . esc_url(home_url('/rekvizity/')) . '" target="_blank">Страница реквизитов</a> · ';
    echo '<a href="' . esc_url(home_url('/oferta/')) . '" target="_blank">Оферта</a> · ';
    echo '<a href="' . esc_url(home_url('/privacy-policy/')) . '" target="_blank">Конфиденциальность</a></p>';
    echo '</div>';
}

function mb2_rekvizity_html() {
    $b = mb2_bank_details();
    $payee = esc_html($b['payee']);
    $brand = esc_html($b['brand']);
    $html  = "<p><strong>{$brand}</strong> — платёжные реквизиты для оплаты услуг.</p>";
    $html .= '<p>Получатель: ' . $payee . '<br>Статус: самозанятый (НПД), без НДС</p>';
    if ($b['payee_inn']) {
        $html .= '<p>ИНН получателя: ' . esc_html($b['payee_inn']) . '</p>';
    }
    $html .= '<p>Номер счёта: <strong>' . esc_html($b['account_fmt'] ?: $b['account']) . '</strong><br>';
    $html .= 'Тип счёта: ' . esc_html($b['account_type']) . '</p>';
    $html .= '<p>Банк: ' . esc_html($b['bank_name']) . '<br>';
    $html .= 'БИК: ' . esc_html($b['bik']) . '<br>';
    $html .= 'Корр. счёт: ' . esc_html($b['corr_fmt'] ?: $b['corr']) . '<br>';
    $html .= 'ИНН банка: ' . esc_html($b['bank_inn']) . '<br>';
    $html .= 'КПП банка: ' . esc_html($b['bank_kpp']) . '</p>';
    if ($b['branch'] || $b['address']) {
        $html .= '<p>Подразделение: ' . esc_html($b['branch']);
        if ($b['address']) {
            $html .= ' · ' . esc_html($b['address']);
        }
        $html .= '</p>';
    }
    $html .= '<p>Email: <a href="mailto:' . esc_attr($b['email']) . '">' . esc_html($b['email']) . '</a></p>';
    $html .= '<p class="muted">В назначении платежа укажите услугу и сайт. Чек НПД — через «Мой налог».</p>';
    return $html;
}

function mb2_privacy_html() {
    $name  = esc_html(mb2_legal_display_name());
    $email = esc_html(mb2_legal('email') ?: 'hello@5mb2.ru');
    $inn   = esc_html(mb2_legal('inn'));
    $city  = esc_html(mb2_legal('city'));
    $inn_line = $inn ? "<p>ИНН: {$inn}</p>" : '<p><em>ИНН будет указан после заполнения в Настройки → 5MB2 реквизиты.</em></p>';

    return <<<HTML
<p><strong>Политика обработки персональных данных</strong> (в соответствии с Федеральным законом № 152-ФЗ «О персональных данных»).</p>
<p><strong>Оператор:</strong> {$name}, применяющий специальный налоговый режим «Налог на профессиональный доход» (самозанятый), {$city}.</p>
{$inn_line}
<p><strong>Контакт:</strong> <a href="mailto:{$email}">{$email}</a></p>
<h2>1. Какие данные обрабатываем</h2>
<p>Имя, email, телефон, URL сайта, текст заявки — только если вы сами отправили их через формы на сайте или в личном кабинете.</p>
<h2>2. Цели</h2>
<p>Связь по заявке на SEO-услуги, подготовка коммерческого предложения, исполнение договора (оферты), ведение переписки и личного кабинета.</p>
<h2>3. Правовые основания</h2>
<p>Согласие субъекта персональных данных (п. 1 ч. 1 ст. 6 152-ФЗ) и необходимость исполнения договора / оферты.</p>
<h2>4. Передача третьим лицам</h2>
<p>Данные не продаём. Могут обрабатываться хостинг-провайдером и почтовым сервисом исключительно для работы сайта и доставки писем. По запросу госорганов — в случаях, предусмотренных законом РФ.</p>
<h2>5. Срок хранения</h2>
<p>Пока нужна связь по услуге, либо до отзыва согласия; далее — в сроки, необходимые для защиты прав (как правило, до 3 лет).</p>
<h2>6. Ваши права</h2>
<p>Вы можете запросить доступ, уточнение, блокирование или удаление данных, отозвать согласие — напишите на {$email}.</p>
<h2>7. Cookies и метрика</h2>
<p>Сайт может использовать cookies и счётчики (например, Яндекс.Метрика) для статистики. Отключить можно в настройках браузера.</p>
<h2>8. Согласие</h2>
<p>Отправляя заявку или регистрируясь в кабинете, вы подтверждаете согласие на обработку указанных данных на условиях этой политики.</p>
HTML;
}

function mb2_oferta_html() {
    $name  = esc_html(mb2_legal_display_name());
    $email = esc_html(mb2_legal('email') ?: 'hello@5mb2.ru');
    $phone = esc_html(mb2_legal('phone'));
    $inn   = esc_html(mb2_legal('inn'));
    $city  = esc_html(mb2_legal('city'));
    $brand = esc_html(mb2_legal('brand') ?: '5MB2 Digital');
    $phone_line = $phone ? "<p>Телефон: {$phone}</p>" : '';
    $inn_line = $inn ? "<p>ИНН: {$inn}</p>" : '<p><em>ИНН укажите в Настройки → 5MB2 реквизиты — он появится здесь автоматически.</em></p>';

    return <<<HTML
<p><strong>Публичная оферта</strong> на оказание информационных и консультационных услуг в сфере SEO (поискового продвижения сайтов).</p>
<p>В соответствии со ст. 437 ГК РФ настоящий документ является официальным предложением {$name} (бренд «{$brand}»), применяющего налог на профессиональный доход (самозанятый, 422-ФЗ), заключить договор на условиях ниже.</p>
<p><strong>Исполнитель:</strong> {$name}, {$city}</p>
{$inn_line}
<p>Email: <a href="mailto:{$email}">{$email}</a></p>
{$phone_line}
<h2>1. Предмет</h2>
<p>Исполнитель оказывает услуги: SEO-аудит, консультации, продвижение и сопутствующие работы по согласованному объёму. Конкретный перечень, сроки и стоимость фиксируются в заявке, переписке или счёте/акте.</p>
<h2>2. Акцепт оферты</h2>
<p>Акцептом считается оплата услуг и/или подтверждение заказа по email после согласования объёма. С этого момента договор считается заключённым.</p>
<h2>3. Стоимость и оплата</h2>
<p>Цены на сайте — ориентировочные. Итоговая сумма согласуется индивидуально. НДС не облагается (режим НПД). После оплаты Исполнитель формирует чек в приложении «Мой налог» и направляет Заказчику.</p>
<h2>4. Порядок оказания</h2>
<p>Работы выполняются дистанционно. Заказчик предоставляет доступы/материалы в разумный срок. Результат SEO зависит от ниши, конкуренции и внедрения рекомендаций; гарантия конкретных позиций в поиске не предоставляется.</p>
<h2>5. Ответственность</h2>
<p>Стороны отвечают по ГК РФ. Исполнитель не отвечает за действия хостинга, CMS, третьих подрядчиков Заказчика и изменения алгоритмов поисковых систем.</p>
<h2>6. Персональные данные</h2>
<p>Обработка данных — по <a href="/privacy-policy/">Политике конфиденциальности</a>.</p>
<h2>7. Срок и изменения</h2>
<p>Оферта действует бессрочно до отзыва. Актуальная редакция публикуется на этой странице. Споры — по месту нахождения Исполнителя / в соответствии с законодательством РФ.</p>
HTML;
}

function mb2_contacts_html() {
    // Контент-заглушка: реальный вид — page-contacts.php (реквизиты + форма).
    $name  = esc_html(mb2_legal_display_name());
    $email = esc_html(mb2_legal('email') ?: 'hello@5mb2.ru');
    $phone = esc_html(mb2_legal('phone'));
    $inn   = esc_html(mb2_legal('inn'));
    $city  = esc_html(mb2_legal('city'));
    $brand = esc_html(mb2_legal('brand') ?: '5MB2 Digital');

    $lines = "<p><strong>{$brand}</strong> — SEO-продвижение сайтов.</p>";
    $lines .= "<p>Исполнитель: {$name}<br>Статус: самозанятый (НПД), без НДС<br>Регион: {$city}</p>";
    if ($inn) {
        $lines .= "<p>ИНН: {$inn}</p>";
    }
    $lines .= "<p>Email: <a href=\"mailto:{$email}\">{$email}</a></p>";
    if ($phone) {
        $lines .= "<p>Телефон: {$phone}</p>";
    }
    $lines .= '<p>VK: <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">vk.com/5mb2online</a></p>';
    $lines .= '<p><a href="' . esc_url(home_url('/rekvizity/')) . '">Реквизиты</a> · <a href="' . esc_url(home_url('/#contact')) . '">Оставить заявку</a> · <a href="' . esc_url(home_url('/oferta/')) . '">Публичная оферта</a> · <a href="' . esc_url(home_url('/privacy-policy/')) . '">Конфиденциальность</a></p>';
    $lines .= '<p class="muted">AI-платформа: <a href="https://neobrain.site/contacts/">NeoBrain → контакты</a> · <a href="https://neobrain.site/rekvizity/">реквизиты</a>. Чек НПД — через «Мой налог».</p>';
    return $lines;
}
