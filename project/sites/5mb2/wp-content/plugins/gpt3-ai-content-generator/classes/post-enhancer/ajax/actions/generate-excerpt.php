<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use function WPAICG\PostEnhancer\Ajax\Base\get_post_content_snippet_logic;
use function WPAICG\PostEnhancer\Ajax\Base\generate_suggestions_logic;

class AIPKit_PostEnhancer_Generate_Excerpt extends AIPKit_Post_Enhancer_Base_Ajax_Action {
    public function handle(): void {
        $permission_check = $this->check_permissions('aipkit_generate_excerpt_nonce');
        if (is_wp_error($permission_check)) { $this->send_error_response($permission_check); return; }

        $post = $this->get_post();
        if (is_wp_error($post)) { $this->send_error_response($post); return; }
        $feature_permission = $this->check_row_assistant_permissions($post);
        if (is_wp_error($feature_permission)) { $this->send_error_response($feature_permission); return; }

        $original_title = trim($post->post_title);
        $post_content_snippet = get_post_content_snippet_logic($post, 800);

        $prompt_template = 'Generate exactly 5 short, compelling excerpt suggestions (about 1-2 sentences each) for a blog post based on the following information.' . "\n" .
                           'Return ONLY the 5 excerpts, each on a new line.' . "\n" .
                           'Do NOT include any introduction, explanation, numbering, or markdown formatting (like **).' . "\n\n" .
                           'Post title: "{title}"' . "\n" .
                           'Post content snippet: "{content}"';

        $prompt = str_replace(['{title}', '{content}'], [$original_title, $post_content_snippet], $prompt_template);
        $final_prompt = apply_filters('aipkit_post_enhancer_excerpt_prompt', $prompt, $post->ID);

        generate_suggestions_logic('excerpt', $post, $final_prompt);
    }
}
