<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Content_Writer_Fetch_Posts_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    public function handle()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $post_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $media_filter = isset($_POST['media_filter']) ? sanitize_key(wp_unslash($_POST['media_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;

        if ($paged < 1) {
            $paged = 1;
        }
        $allowed_per_page = [10, 25, 50, 100, 200, 500, 1000];
        if (!in_array($per_page, $allowed_per_page, true)) {
            $per_page = 10;
        }

        $post_types = get_post_types(['public' => true], 'objects');
        $allowed_types = array_keys($post_types);
        $allowed_non_attachment = array_diff($allowed_types, ['attachment']);

        $allowed_statuses = ['publish', 'draft', 'pending', 'future', 'private', 'inherit', 'any'];

        $query_post_type = $allowed_non_attachment;
        if (empty($query_post_type)) {
            $query_post_type = 'any';
        }
        if ($post_type === 'attachment') {
            $query_post_type = 'attachment';
        } elseif ($post_type && in_array($post_type, $allowed_non_attachment, true)) {
            $query_post_type = $post_type;
        }

        $query_status = 'any';
        if ($post_status && in_array($post_status, $allowed_statuses, true)) {
            $query_status = $post_status;
        } elseif ($query_post_type === 'attachment') {
            $query_status = 'inherit';
        }

        if ($media_filter === 'unattached') {
            $media_filter = 'detached';
        }

        $query_args = [
            'post_type' => $query_post_type,
            'post_status' => $query_status,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            's' => $search,
            'no_found_rows' => false,
        ];

        if ($query_post_type === 'attachment' && $media_filter) {
            if ($media_filter === 'image') {
                $query_args['post_mime_type'] = 'image';
            } elseif ($media_filter === 'detached') {
                $query_args['post_parent'] = 0;
            } elseif ($media_filter === 'mine') {
                $query_args['author'] = get_current_user_id();
            }
        }

        $query = new \WP_Query($query_args);

        $posts = [];
        foreach ($query->posts as $post) {
            $title = get_the_title($post);
            if ($title === '') {
                $title = __('(Untitled)', 'gpt3-ai-content-generator');
            }

            $type_object = get_post_type_object($post->post_type);
            $status_object = get_post_status_object($post->post_status);
            $alt_text = '';
            $caption = '';
            $description = '';
            $thumb_url = '';
            $file_name = '';

            if ($post->post_type === 'attachment') {
                $alt_text = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
                $caption = $post->post_excerpt;
                $description = $post->post_content;
                $alt_text = trim(wp_strip_all_tags((string) $alt_text));
                $caption = trim(wp_strip_all_tags((string) $caption));
                $description = trim(wp_strip_all_tags((string) $description));
                $thumb_url = (string) wp_get_attachment_image_url($post->ID, 'thumbnail');
                $file_path = get_attached_file($post->ID);
                if ($file_path) {
                    $file_name = wp_basename($file_path);
                } else {
                    $guid_path = wp_parse_url((string) $post->guid, PHP_URL_PATH);
                    if (!empty($guid_path)) {
                        $file_name = wp_basename($guid_path);
                    }
                }
            }

            $posts[] = [
                'id' => (int) $post->ID,
                'title' => $title,
                'type' => $post->post_type,
                'type_label' => $type_object ? $type_object->label : $post->post_type,
                'status' => $post->post_status,
                'status_label' => $status_object ? $status_object->label : $post->post_status,
                'edit_link' => (string) get_edit_post_link($post->ID, ''),
                'alt_text' => $alt_text,
                'caption' => $caption,
                'description' => $description,
                'thumb_url' => $thumb_url,
                'file_name' => $file_name,
            ];
        }

        wp_send_json_success([
            'posts' => $posts,
            'pagination' => [
                'page' => $paged,
                'per_page' => $per_page,
                'total' => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
            ],
        ]);
    }
}
