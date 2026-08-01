<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Processor\CommentReply;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inserts the AI-generated reply as a new comment.
 *
 * @param string $reply_content The AI-generated text for the reply.
 * @param \WP_Comment $original_comment The original comment object to reply to.
 * @param array $task_config The configuration of the task.
 * @return int|WP_Error The new comment ID or a WP_Error on failure.
 */
function insert_comment_reply_logic(string $reply_content, \WP_Comment $original_comment, array $task_config)
{
    // Check if the site owner should be the author of the reply
    $reply_author = get_user_by('id', $original_comment->post_author);
    $user_id = $reply_author ? $reply_author->ID : 1; // Default to admin if post author not found
    $user_data = get_userdata($user_id);

    $comment_data = [
        'comment_post_ID'      => $original_comment->comment_post_ID,
        'comment_author'       => $user_data->display_name,
        'comment_author_email' => $user_data->user_email,
        'comment_author_url'   => $user_data->user_url,
        'comment_content'      => $reply_content,
        'comment_parent'       => $original_comment->comment_ID,
        'user_id'              => $user_id,
        'comment_date'         => current_time('mysql'),
        'comment_date_gmt'     => current_time('mysql', 1),
        'comment_approved'     => ($task_config['reply_action'] ?? 'approve') === 'approve' ? 1 : 0,
    ];

    $new_comment_id = wp_insert_comment($comment_data);

    if (is_wp_error($new_comment_id)) {
        return $new_comment_id;
    }
    if ($new_comment_id === 0) {
        return new WP_Error('comment_insert_failed', 'Could not insert comment into the database.');
    }

    // Add meta to identify this as an automated reply from a specific task
    add_comment_meta($new_comment_id, '_aipkit_automated_reply', $task_config['task_id'] ?? 0, true);

    return $new_comment_id;
}
