<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use function WPAICG\PostEnhancer\Ajax\Base\get_post_content_snippet_logic;
use function WPAICG\PostEnhancer\Ajax\Base\generate_suggestions_logic;

class AIPKit_PostEnhancer_Generate_Meta extends AIPKit_Post_Enhancer_Base_Ajax_Action {
    public function handle(): void {
        $permission_check = $this->check_permissions('aipkit_generate_meta_nonce');
        if (is_wp_error($permission_check)) { $this->send_error_response($permission_check); return; }

        $post = $this->get_post();
        if (is_wp_error($post)) { $this->send_error_response($post); return; }
        $feature_permission = $this->check_row_assistant_permissions($post);
        if (is_wp_error($feature_permission)) { $this->send_error_response($feature_permission); return; }

        $original_title = trim($post->post_title);
        $post_content_snippet = get_post_content_snippet_logic($post, 800);

        $prompt_template = 'Generate exactly 5 concise and SEO-friendly meta description suggestions (under 156 characters each) for a web page based on the provided information.' . "\n" .
                           'Return ONLY the 5 meta descriptions, each on a new line.' . "\n" .
                           'Do NOT include any introduction, explanation, numbering, markdown formatting (like **), or surrounding quotes.' . "\n\n" .
                           'Page title: "{title}"' . "\n" .
                           'Page content snippet: "{content}"';

        $prompt = str_replace(['{title}', '{content}'], [$original_title, $post_content_snippet], $prompt_template);
        $final_prompt = apply_filters('aipkit_post_enhancer_meta_prompt', $prompt, $post->ID);

        generate_suggestions_logic('meta', $post, $final_prompt);
    }
}
