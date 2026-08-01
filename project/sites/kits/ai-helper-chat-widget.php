<?php
/**
 * Plugin Name: AI Helper Chat Widget
 * Description: Всплывающий чат AI Helper на сайте (гость, без логина).
 * Version: 1.1.0
 * Author: AI Helper
 *
 * Кладётся в wp-content/mu-plugins/ вместе с ai-helper-widget.js
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    // Локальный JS рядом с mu-plugin — работает на домене 5mb2.ru
    // (путь /sites/ai/widget.js на vhost домена даёт 404)
    $js = __DIR__ . '/ai-helper-widget.js';
    if (!is_file($js)) {
        return;
    }
    $src = content_url('mu-plugins/ai-helper-widget.js');
    wp_enqueue_script(
        'ai-helper-chat-widget',
        $src,
        [],
        (string) filemtime($js),
        true
    );
});

add_action('wp_footer', function () {
    $title = apply_filters('ai_helper_chat_title', 'Помощник 5MB2');
    $placeholder = apply_filters('ai_helper_chat_placeholder', 'Спросите про SEO или услуги…');
    $site = apply_filters('ai_helper_chat_site', '5mb2');
    $greeting = apply_filters(
        'ai_helper_chat_greeting',
        'Здравствуйте! Компания 5MB2 Digital — SEO и продвижение. Чем помочь?'
    );
    $chips = apply_filters('ai_helper_chat_chips', [
        'SEO-продвижение',
        'Сколько стоит?',
        'Как заказать?',
        'Контакты',
    ]);
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!window.AIHelperChat) return;
      AIHelperChat.mount({
        title: <?php echo wp_json_encode($title); ?>,
        placeholder: <?php echo wp_json_encode($placeholder); ?>,
        site: <?php echo wp_json_encode($site); ?>,
        greeting: <?php echo wp_json_encode($greeting); ?>,
        chips: <?php echo wp_json_encode(array_values($chips)); ?>,
        fabLabel: 'Чат',
        apiBase: '/api'
      });
    });
    </script>
    <?php
}, 99);
