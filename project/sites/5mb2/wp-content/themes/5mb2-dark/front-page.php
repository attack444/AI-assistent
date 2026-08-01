<?php
/**
 * Homepage — SEO agency landing.
 */
get_header();
$img = get_template_directory_uri() . '/assets/img';
$services = mb2_services_catalog();
?>

<section class="hero">
  <div class="hero-bg" aria-hidden="true">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-orb"></div>
  </div>
  <div class="wrap hero-inner">
    <p class="brand-hero reveal">5MB2 <span>Digital</span></p>
    <h1 class="hero-title reveal">SEO, которое приводит клиентов</h1>
    <p class="hero-lead reveal">Продвижение сайтов по России: аудит, семантика, контент и техника — с понятными метриками роста.</p>
    <div class="hero-cta reveal">
      <a class="btn btn-primary btn-lg" href="#contact">Получить стратегию</a>
      <a class="btn btn-ghost btn-lg" href="<?php echo esc_url(home_url('/services/')); ?>">Смотреть услуги</a>
    </div>
  </div>
</section>

<section class="section section-alt" id="showcase">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Как выглядит работа</h2>
      <p>Аналитика, рост видимости, локальные заявки — в одном процессе.</p>
    </header>
    <div class="carousel reveal" data-carousel>
      <div class="carousel-track">
        <figure class="carousel-slide is-active">
          <img src="<?php echo esc_url($img . '/slide-1.jpg'); ?>" alt="Аналитика и рост трафика" width="1400" height="800" />
          <figcaption>Аналитика и точки роста</figcaption>
        </figure>
        <figure class="carousel-slide">
          <img src="<?php echo esc_url($img . '/slide-2.jpg'); ?>" alt="Дашборды и отчёты" width="1400" height="800" />
          <figcaption>Отчёты без «магии в презентациях»</figcaption>
        </figure>
        <figure class="carousel-slide">
          <img src="<?php echo esc_url($img . '/slide-3.jpg'); ?>" alt="Контент и стратегия" width="1400" height="800" />
          <figcaption>Стратегия и контент под спрос</figcaption>
        </figure>
      </div>
      <div class="carousel-nav">
        <button type="button" class="btn btn-ghost" data-carousel-prev aria-label="Назад">←</button>
        <div class="carousel-dots" data-carousel-dots></div>
        <button type="button" class="btn btn-ghost" data-carousel-next aria-label="Вперёд">→</button>
      </div>
    </div>
  </div>
</section>

<section class="section" id="services">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Услуги SEO</h2>
      <p>От аудита до ежемесячного роста — выберите услугу и оставьте заявку.</p>
    </header>
    <div class="service-cards">
      <?php foreach ($services as $slug => $svc) : ?>
        <a class="service-card reveal" href="<?php echo esc_url(mb2_service_url($slug)); ?>">
          <div class="service-card-media">
            <img src="<?php echo esc_url($svc['image']); ?>" alt="" width="640" height="400" loading="lazy" />
          </div>
          <div class="service-card-body">
            <h3><?php echo esc_html($svc['title']); ?></h3>
            <p><?php echo esc_html($svc['short']); ?></p>
            <span class="text-link">Подробнее →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-alt" id="process">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Как работаем</h2>
      <p>Путь клиента: заявка → диагностика → стратегия → кабинет и рост.</p>
    </header>
    <ol class="steps">
      <li class="reveal"><strong>Заявка</strong><span>Форма на сайте или кабинет</span></li>
      <li class="reveal"><strong>Диагностика</strong><span>Аудит и цели бизнеса</span></li>
      <li class="reveal"><strong>Стратегия</strong><span>План на 90 дней</span></li>
      <li class="reveal"><strong>Рост</strong><span>Работы и отчёты в кабинете</span></li>
    </ol>
  </div>
</section>

<section class="section" id="cases">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Результаты</h2>
      <p>Ориентиры. Реальные работы — в разделе <a class="text-link" href="<?php echo esc_url(home_url('/kejsy/')); ?>">Проекты</a>.</p>
    </header>
    <div class="stats">
      <div class="stat reveal"><strong data-count="180">0</strong><span>% средний рост органики*</span></div>
      <div class="stat reveal"><strong data-count="90">0</strong><span>дней до первых сдвигов</span></div>
      <div class="stat reveal"><strong data-count="24">0</strong><span>проекта в работе / год</span></div>
    </div>
    <p class="muted tiny reveal" style="margin-top:20px">*Ориентир по проектам с выполненными рекомендациями.</p>
  </div>
</section>

<section class="section section-alt" id="materials-teaser">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Материалы</h2>
      <p>Коротко про SEO — чтобы принимать решения спокойнее.</p>
    </header>
    <?php
    $mq = new WP_Query(['post_type' => 'post', 'posts_per_page' => 3, 'category_name' => 'materialy']);
    if (!$mq->have_posts()) {
        $mq = new WP_Query(['post_type' => 'post', 'posts_per_page' => 3]);
    }
    if ($mq->have_posts()) :
        ?>
      <div class="post-grid">
        <?php while ($mq->have_posts()) : $mq->the_post(); ?>
          <article class="post-card reveal">
            <span class="muted tiny"><?php echo esc_html(get_the_date()); ?></span>
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 22)); ?></p>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <p style="margin-top:24px"><a class="btn btn-ghost" href="<?php echo esc_url(home_url('/materialy/')); ?>">Все материалы</a></p>
    <?php endif; ?>
  </div>
</section>

<section class="section" id="faq">
  <div class="wrap narrow">
    <header class="section-head reveal">
      <h2>Частые вопросы</h2>
    </header>
    <div class="faq">
      <details class="reveal" open>
        <summary>Как заказать услугу?</summary>
        <p>Откройте нужную услугу → заполните форму «Заказать» → мы свяжемся. Либо оставьте заявку ниже или в кабинете.</p>
      </details>
      <details class="reveal">
        <summary>Сколько ждать результат?</summary>
        <p>Первые движения — обычно 1–3 месяца. Устойчивый рост — от 3–6 месяцев при регулярной работе.</p>
      </details>
      <details class="reveal">
        <summary>Нужен ли доступ к сайту?</summary>
        <p>Да — к админке или через вашего разработчика. Статус задач видно в кабинете.</p>
      </details>
    </div>
  </div>
</section>

<section class="section cta-band" id="contact">
  <div class="wrap cta-inner">
    <div class="reveal">
      <h2>Обсудим ваш рост</h2>
      <p class="muted">Выберите услугу или просто опишите задачу — вернёмся со следующими шагами.</p>
      <p style="margin-top:18px">
        <a class="text-link" href="mailto:hello@5mb2.ru">hello@5mb2.ru</a>
        ·
        <a class="text-link" href="https://vk.com/5mb2online" target="_blank" rel="noopener">VK</a>
        ·
        <a class="text-link" href="<?php echo esc_url(home_url('/cabinet/')); ?>">Кабинет</a>
      </p>
    </div>
    <div class="reveal">
      <?php mb2_render_lead_form(); ?>
    </div>
  </div>
</section>

<?php get_footer(); ?>
