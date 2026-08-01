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
        'fio'     => '',
        'inn'     => '',
        'city'    => 'Россия',
        'email'   => 'hello@5mb2.ru',
        'phone'   => '',
        'brand'   => '5MB2 Digital',
        'npd'     => '1',
    ];
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
            'fio'   => sanitize_text_field(wp_unslash($_POST['fio'] ?? '')),
            'inn'   => preg_replace('/\D+/', '', (string) ($_POST['inn'] ?? '')),
            'city'  => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'brand' => sanitize_text_field(wp_unslash($_POST['brand'] ?? '5MB2 Digital')),
            'npd'   => !empty($_POST['npd']) ? '1' : '0',
        ];
        update_option('mb2_legal', $data, false);
        echo '<div class="updated"><p>Сохранено. Обновите страницы «Оферта» и «Контакты» на сайте (Ctrl+F5).</p></div>';
    }
    $o = mb2_legal();
    echo '<div class="wrap"><h1>Реквизиты самозанятого (НПД)</h1>';
    echo '<p>Для сайта и оферты по закону РФ (422-ФЗ о НПД, 152-ФЗ о персональных данных). Чеки клиентам выдавайте в приложении <strong>Мой налог</strong>.</p>';
    echo '<form method="post">';
    wp_nonce_field('mb2_legal_save');
    echo '<table class="form-table">';
    $fields = [
        'fio'   => 'ФИО полностью',
        'inn'   => 'ИНН (12 цифр)',
        'city'  => 'Город / регион',
        'email' => 'Email для связи и оферты',
        'phone' => 'Телефон',
        'brand' => 'Бренд на сайте',
    ];
    foreach ($fields as $k => $label) {
        echo '<tr><th><label for="' . esc_attr($k) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input class="regular-text" id="' . esc_attr($k) . '" name="' . esc_attr($k) . '" value="' . esc_attr($o[$k]) . '" /></td></tr>';
    }
    echo '<tr><th>Статус</th><td><label><input type="checkbox" name="npd" value="1" ' . checked($o['npd'], '1', false) . ' /> Самозанятый (НПД), без НДС</label></td></tr>';
    echo '</table>';
    echo '<p><button class="button button-primary" name="mb2_legal_save" value="1">Сохранить</button></p>';
    echo '</form>';
    echo '<p><a href="' . esc_url(home_url('/oferta/')) . '" target="_blank">Публичная оферта</a> · ';
    echo '<a href="' . esc_url(home_url('/privacy-policy/')) . '" target="_blank">Политика конфиденциальности</a></p>';
    echo '</div>';
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
    } else {
        $lines .= '<p><em>ИНН: заполните в WP → Настройки → 5MB2 реквизиты</em></p>';
    }
    $lines .= "<p>Email: <a href=\"mailto:{$email}\">{$email}</a></p>";
    if ($phone) {
        $lines .= "<p>Телефон: {$phone}</p>";
    }
    $lines .= '<p>VK: <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">vk.com/5mb2online</a></p>';
    $lines .= '<p><a href="' . esc_url(home_url('/#contact')) . '">Оставить заявку</a> · <a href="' . esc_url(home_url('/oferta/')) . '">Публичная оферта</a> · <a href="' . esc_url(home_url('/privacy-policy/')) . '">Конфиденциальность</a></p>';
    $lines .= '<p class="muted">Оплата услуг — по согласованию; чек НПД выдаётся через «Мой налог».</p>';
    return $lines;
}
