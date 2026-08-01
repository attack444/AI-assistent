<?php
/**
 * Plugin Name: AI Helper Chat Widget
 * Description: Всплывающий чат AI Helper на сайте (гость, без логина). Не даёт доступ к админке.
 * Version: 1.0.0
 * Author: AI Helper
 *
 * Установка: скопировать в wp-content/mu-plugins/ (must-use).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    // widget.js с витрины /sites/ai/
    $src = '/sites/ai/widget.js';
    wp_enqueue_script('ai-helper-chat-widget', $src, [], '1.0.0', true);
});

add_action('wp_footer', function () {
    $title = apply_filters('ai_helper_chat_title', 'Помощник сайта');
    $placeholder = apply_filters('ai_helper_chat_placeholder', 'Спросите про заказ или доставку…');
    $site = apply_filters('ai_helper_chat_site', '5mb2');
    $greeting = apply_filters('ai_helper_chat_greeting', 'Здравствуйте! Чем помочь по сайту?');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!window.AIHelperChat) return;
      AIHelperChat.mount({
        title: <?php echo wp_json_encode($title); ?>,
        placeholder: <?php echo wp_json_encode($placeholder); ?>,
        site: <?php echo wp_json_encode($site); ?>,
        greeting: <?php echo wp_json_encode($greeting); ?>,
        chips: ['Доставка', 'Как заказать?', 'Контакты'],
        apiBase: '/api'
      });
    });
    </script>
    <?php
}, 99);
