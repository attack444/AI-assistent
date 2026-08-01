<?php
/**
 * Проекты и разборы: реальные + учебные (явно помечены) + живой кейс 5mb2.
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_vitra_project_html() {
    $u = content_url('uploads/2026/03');
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

<p><em>Рабочий проект из портфолио 5MB2. Цифры и скриншоты — из реальной работы; детали по NDA с заказчиком не раскрываем.</em></p>
<p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Обсудить похожий проект</a></p>
    <?php
    return ob_get_clean();
}

function mb2_kchtz_razbor_html() {
    ob_start();
    ?>
<p><span class="plan-badge">Учебный разбор</span></p>
<p><strong>Сайт:</strong> kchtz.ru — крупное производственное предприятие на WordPress, сложная структура и много внутренних разделов.</p>
<p><strong>Задача разбора:</strong> провести технический SEO-аудит и найти точки роста — без обещания «мы уже вывели в топ».</p>

<h2>Проблемы на старте</h2>
<ul>
  <li>дубли страниц;</li>
  <li>некорректные мета-теги;</li>
  <li>частичная индексация;</li>
  <li>слабая структура заголовков.</li>
</ul>

<h2>Что сделано в рамках разбора</h2>
<ul>
  <li>анализ структуры URL и навигации;</li>
  <li>поиск дублей и проверка индексации (Search Console);</li>
  <li>аудит мета-тегов и заголовков;</li>
  <li>оценка скорости загрузки;</li>
  <li>отчёт с приоритетными рекомендациями.</li>
</ul>

<h2>Инструменты</h2>
<p>Screaming Frog, Google Search Console, PageSpeed Insights, Excel.</p>

<p><em>Это учебный разбор метода работы, не коммерческий кейс с договором. Нужен такой аудит для вашего сайта — <a href="<?php echo esc_url(home_url('/services/seo-audit/')); ?>">SEO-аудит</a>.</em></p>
    <?php
    return ob_get_clean();
}

function mb2_texturra_razbor_html() {
    ob_start();
    ?>
<p><span class="plan-badge">Учебный разбор</span></p>
<p><strong>Сайт:</strong> texturra.ru — услуги, CTR-ориентированный сайт с CPC-трафиком.</p>
<p><strong>Задача:</strong> комплексный SEO-аудит структуры и рекомендации по росту органики рядом с платным трафиком.</p>

<h2>Проблемы</h2>
<ul>
  <li>URL с длинными параметрами;</li>
  <li>слабая SEO-структура;</li>
  <li>частичная индексация;</li>
  <li>дубли контента;</li>
  <li>неоптимальные мета-теги.</li>
</ul>

<h2>Что проверено</h2>
<ul>
  <li>структура категорий и посадочных;</li>
  <li>мета-теги и конкуренты в нише;</li>
  <li>robots.txt / sitemap.xml;</li>
  <li>сформирован SEO-отчёт с точками роста (в т.ч. семантика).</li>
</ul>

<h2>Инструменты</h2>
<p>Screaming Frog, Ahrefs, Google Search Console, Excel.</p>

<p><em>Учебный разбор. Для коммерческого сопровождения — <a href="<?php echo esc_url(home_url('/services/prodvizhenie/')); ?>">SEO-продвижение</a> или заявка.</em></p>
    <?php
    return ob_get_clean();
}

function mb2_own_site_case_html() {
    $report = content_url('uploads/2026/03/Отчёт-по-сайту-5mb2.ru_.pdf');
    ob_start();
    ?>
<p><span class="plan-badge">Живой проект</span></p>
<p><strong>Сайт:</strong> <a href="https://5mb2.ru/">5mb2.ru</a> — собственный продукт 5MB2 Digital. Показываем процесс на себе, пока копилка клиентских кейсов растёт.</p>

<h2>Что уже сделано</h2>
<ul>
  <li>перенос на свой VPS, HTTPS, чистая тема без тяжёлого Elementor;</li>
  <li>каталог услуг с рыночными ценами «от», оферта самозанятого;</li>
  <li>личный кабинет с чеклистом SEO и заявками;</li>
  <li>бесплатные <a href="<?php echo esc_url(home_url('/instrumenty/')); ?>">SEO-инструменты</a> как точка входа;</li>
  <li>AI-виджет с ответами по услугам сайта;</li>
  <li>технический отчёт по сайту (PDF) — база для следующих итераций.</li>
</ul>

<p><a class="btn btn-ghost" href="<?php echo esc_url($report); ?>" target="_blank" rel="noopener">Открыть отчёт по 5mb2.ru</a></p>

<h2>Зачем это клиенту</h2>
<p>Вы видите не чужие красивые цифры, а рабочую воронку: заявка → кабинет → прозрачный прогресс. Первый коммерческий кейс с вашим сайтом можем разобрать так же честно.</p>
<p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Стать следующим проектом</a></p>
    <?php
    return ob_get_clean();
}

function mb2_upsert_project_post($slug, $title, $excerpt, $content, $cat_id, $kind = 'client') {
    $existing = get_posts([
        'name'        => $slug,
        'post_type'   => 'post',
        'post_status' => 'any',
        'numberposts' => 1,
    ]);
    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_excerpt' => $excerpt,
    ];
    if ($existing) {
        $postarr['ID'] = $existing[0]->ID;
        $id = wp_update_post($postarr);
    } else {
        $id = wp_insert_post($postarr);
    }
    if ($id && !is_wp_error($id) && $cat_id) {
        wp_set_post_categories($id, [$cat_id]);
        update_post_meta($id, '_mb2_project_kind', $kind);
    }
    return $id;
}

function mb2_ensure_real_projects() {
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

    // Старые RSS-посты (США / Bing) — в черновик: не бьют в позиционирование РФ
    foreach ([3675, 1276] as $old_id) {
        if (get_post($old_id)) {
            wp_update_post(['ID' => $old_id, 'post_status' => 'draft']);
        }
    }
    $rss_like = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'category_name'  => 'uncategorized',
        's'              => 'Bing',
    ]);
    foreach ($rss_like as $p) {
        wp_update_post(['ID' => $p->ID, 'post_status' => 'draft']);
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

    mb2_upsert_project_post(
        'vitra-russia-masshtabirovanie-trafika',
        'VitrA Russia: масштабирование органического трафика',
        'Семантика, техника, скорость, Local SEO и коммерческие факторы для имиджевого сайта производителя.',
        mb2_vitra_project_html(),
        $cat_id,
        'client'
    );

    mb2_upsert_project_post(
        'razbor-kchtz-tehnicheskij-audit',
        'Разбор: технический SEO-аудит производственного сайта (KCHTZ)',
        'Учебный разбор: дубли, мета, индексация, структура — метод аудита на примере WordPress-сайта завода.',
        mb2_kchtz_razbor_html(),
        $cat_id,
        'edu'
    );

    mb2_upsert_project_post(
        'razbor-texturra-seo-struktura',
        'Разбор: SEO-структура сервисного сайта (Texturra)',
        'Учебный разбор: параметры URL, дубли, мета и точки роста рядом с CPC-трафиком.',
        mb2_texturra_razbor_html(),
        $cat_id,
        'edu'
    );

    mb2_upsert_project_post(
        'zhivoj-proekt-5mb2',
        'Живой проект: запуск и SEO 5mb2.ru',
        'Собственный сайт агентства: инфраструктура, кабинет, инструменты, отчёт — процесс на виду.',
        mb2_own_site_case_html(),
        $cat_id,
        'own'
    );

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
