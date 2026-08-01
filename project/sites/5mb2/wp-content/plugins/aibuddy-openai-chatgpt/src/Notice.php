<?php

namespace AiBuddy;

use Exception;
use WP_REST_Request;
use WP_REST_Response;

class Notice
{

    public function initNotice()
    {
        add_action('admin_notices', [$this, 'show_admin_notice']);
        add_action('wp_ajax_ai_buddy_feedback_given', [$this, 'notice_feedback_update']);
    }

    public function initAction()
    {
        add_action('ai_buddy_after_post_created', [$this, 'increase_usage_count']);
    }

    public function increase_usage_count()
    {
        $plugin_option = get_option('ai_buddy', ['usage_count' => 0]);
        $plugin_option['usage_count'] = isset($plugin_option['usage_count']) ? $plugin_option['usage_count'] + 1 : 1;
        update_option('ai_buddy', $plugin_option);
    }

    public function show_admin_notice()
    {
        $plugin_option = get_option('ai_buddy', ['usage_count' => 0]);
        $plugin_option['feedback_given'] = $plugin_option['feedback_given'] ?? 'no';
        $plugin_option['usage_count'] = $plugin_option['usage_count'] ?? '0';

        if ($plugin_option['usage_count'] > 20 &&
            $plugin_option['feedback_given'] === 'no' &&
            get_transient('ai_buddy_notice_ask_me_later') === false) {
            include AI_BUDDY_PATH . '/includes/admin/notice.php';
        }
    }

    public static function notice_feedback_update(WP_REST_Request $request)
    {

        try {
            $choice = $request->get_param('choice');

            if ($choice === 'never') {
                $ai_buddy_options = get_option('ai_buddy', []);
                $ai_buddy_options['feedback_given'] = 'yes';
                update_option('ai_buddy', $ai_buddy_options);
            } elseif ($choice === 'later') {
                set_transient('ai_buddy_notice_ask_me_later', true, WEEK_IN_SECONDS);
            }

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'choice' => $choice,
                ),
                200);

        } catch (Exception $e) {
            return new WP_REST_Response(
                array(
                    'message' => $e->getMessage(),
                ),
                200
            );
        }
    }
}


