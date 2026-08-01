<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Processor\CommentReply;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the final prompt for generating a comment reply.
 *
 * @param string $prompt_template The prompt template from task config.
 * @param \WP_Comment $comment The original comment object.
 * @param \WP_Post $post The post the comment belongs to.
 * @return string The final, processed prompt.
 */
function build_reply_prompt_logic(string $prompt_template, \WP_Comment $comment, \WP_Post $post): string
{
    $placeholders = [
        '{comment_author}' => $comment->comment_author,
        '{comment_content}' => $comment->comment_content,
        '{post_title}' => $post->post_title,
    ];

    return str_replace(array_keys($placeholders), array_values($placeholders), $prompt_template);
}
