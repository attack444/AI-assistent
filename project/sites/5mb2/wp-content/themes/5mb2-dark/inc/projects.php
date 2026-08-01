<?php
/**
 * Реальные проекты (не демо-кейсы) — контент из старой страницы /cases/.
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_vitra_project_html() {
    $u = content_url('uploads/2026/03');
    // файлы на VPS: Снимок-экрана-221.png … 227.png
    $shots = [];
    foreach ([221, 222, 223, 224, 225, 226, 227] as $n) {
        $shots[] = $u . '/Снимок-экрана-' . $n . '.png';
    }

    ob_start();
    ?>
<p><strong>Проект:</strong> масштабирование органического трафика для VitrA Russia (сантехника и керамика, имиджевый сайт производителя).</p>
<p><strong>Задача:</strong> уйти от продвижения только по бренду и вырастить видимость по небрендовым коммерческим запросам в приоритетном регионе (Москва), усилив технику, контент и локальные факторы.</p>

<h2>Этап 1. Семантическое ядро</h2>
<p>Отошли от идеи продвигаться только по запросу «сантехника Vitra». Собрано широкое ядро небрендовых запросов: «керамогранит под мрамор», «черный подвесной унитаз», «смесители для раковины». Акцент — на кластеры с высоким коммерческим потенциалом и доступной сложностью для сайта-производителя.</p>

<figure class="media-frame"><img src="<?php echo esc_url($shots[0]); ?>" alt="Скриншот работ по проекту VitrA" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 2. Прогнозирование позиций</h2>
<p>На основе конкурентов и текущей видимости построена модель роста: при снятии технических барьеров сайт способен занять ТОП-3 по существенной доле целевых запросов в горизонте полугода за счёт авторитета домена.</p>

<figure class="media-frame"><img src="<?php echo esc_url($shots[1]); ?>" alt="Прогноз и видимость" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 3. Техническая оптимизация</h2>
<p>Имиджевый сайт — сложный индекс. Вычистили мусорный индекс, настроили корректные 404, robots.txt и sitemap.xml, устранили дубли, связанные со спецификой CMS.</p>

<figure class="media-frame"><img src="<?php echo esc_url($shots[2]); ?>" alt="Техническая оптимизация" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 4. Мобильная версия</h2>
<p>В нише дизайна интерьеров мобильный трафик часто &gt;60%. Доработали каталог: фильтры на узких экранах, читаемость. Рост Mobile-Friendly.</p>

<figure class="media-frame"><img src="<?php echo esc_url($shots[3]); ?>" alt="Мобильная оптимизация" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 5. Скорость загрузки</h2>
<p>Тяжёлые фото коллекций замедляли сайт. Внедрены WebP, Lazy Load, сжатие ресурсов — вход в зелёную зону Core Web Vitals.</p>
<figure class="media-frame"><img src="<?php echo esc_url($shots[4]); ?>" alt="Скорость и Метрика" loading="lazy" width="1200" height="675" /></figure>
<figure class="media-frame"><img src="<?php echo esc_url($shots[5]); ?>" alt="Яндекс.Метрика" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 6. Региональная оптимизация</h2>
<p>Целевой регион — Москва. Оптимизирована страница «Где купить», микроразметка LocalBusiness для флагманских салонов, актуальные данные в Яндекс.Бизнесе и Google Maps.</p>

<h2>Этап 7–8. Контент и коммерческие факторы</h2>
<p>Шаблоны Title/Description для карточек с LSI, структурированные описания коллекций. Усилили доверие: гарантия, PDF по монтажу, 3D-модели для дизайнеров, заметные CTA «Где купить» / «Заказать звонок».</p>

<figure class="media-frame"><img src="<?php echo esc_url($shots[6]); ?>" alt="Контент и коммерция" loading="lazy" width="1200" height="675" /></figure>

<h2>Этап 9–11. Санкции, приоритеты, стратегия</h2>
<p>Проверен риск скрытых санкций (в т.ч. переспам). Составлен приоритет внедрения доработок и стратегия дальнейшего продвижения.</p>

<p><em>Это рабочий проект из портфолио 5MB2. Цифры и скриншоты — из реальной работы; детали по NDA с заказчиком не раскрываем.</em></p>
<p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Обсудить похожий проект</a></p>
    <?php
    return ob_get_clean();
}

function mb2_ensure_real_projects() {
    // Убрать демо-кейсы из сида 1.4
    foreach (['kejs-local-usluga', 'kejs-rost-organiki-magazina'] as $slug) {
        $found = get_posts([
            'name'        => $slug,
            'post_type'   => 'post',
            'post_status' => 'any',
            'numberposts' => 1,
        ]);
        if ($found) {
            wp_update_post(['ID' => $found[0]->ID, 'post_status' => 'draft']);
        }
    }

    if (!term_exists('kejsy', 'category')) {
        wp_insert_term('Проекты', 'category', ['slug' => 'kejsy']);
    } else {
        $term = get_term_by('slug', 'kejsy', 'category');
        if ($term && !is_wp_error($term)) {
            wp_update_term((int) $term->term_id, 'category', ['name' => 'Проекты']);
        }
    }
    $term = get_term_by('slug', 'kejsy', 'category');
    $cat_id = $term ? (int) $term->term_id : 0;

    $slug = 'vitra-russia-masshtabirovanie-trafika';
    $existing = get_posts([
        'name'        => $slug,
        'post_type'   => 'post',
        'post_status' => 'any',
        'numberposts' => 1,
    ]);

    $content = mb2_vitra_project_html();
    $postarr = [
        'post_title'   => 'VitrA Russia: масштабирование органического трафика',
        'post_name'    => $slug,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_excerpt' => 'Семантика, техника, скорость, Local SEO и коммерческие факторы для имиджевого сайта производителя.',
    ];

    if ($existing) {
        $postarr['ID'] = $existing[0]->ID;
        $id = wp_update_post($postarr);
    } else {
        $id = wp_insert_post($postarr);
    }
    if ($id && !is_wp_error($id) && $cat_id) {
        wp_set_post_categories($id, [$cat_id]);
    }

    // Старая Elementor-страница /cases/ → редирект на /kejsy/
    $old = get_page_by_path('cases');
    if ($old) {
        update_post_meta($old->ID, '_mb2_redirect_to', home_url('/kejsy/'));
    }
}

add_action('template_redirect', function () {
    if (!is_page()) {
        return;
    }
    $to = get_post_meta(get_queried_object_id(), '_mb2_redirect_to', true);
    if ($to) {
        wp_safe_redirect($to, 301);
        exit;
    }
});
