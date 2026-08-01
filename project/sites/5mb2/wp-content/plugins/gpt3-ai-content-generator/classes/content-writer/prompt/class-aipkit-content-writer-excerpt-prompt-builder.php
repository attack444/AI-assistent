<?php


namespace WPAICG\ContentWriter\Prompt;

use WPAICG\ContentWriter\AIPKit_Content_Writer_Prompts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the prompt for generating an SEO excerpt.
 */
class AIPKit_Content_Writer_Excerpt_Prompt_Builder
{
    /**
     * Builds the final user prompt for the excerpt generation AI call.
     *
     * @param string $final_title The final generated title of the article.
     * @param string $content_summary A summary of the main content.
     * @param string $keywords The relevant keywords for the article.
     * @param string|null $prompt_mode The selected prompt mode ('standard' or 'custom').
     * @param string|null $custom_excerpt_prompt The user-defined custom prompt, if any.
     * @return string The complete user prompt for the AI.
     */
    public static function build(string $final_title, string $content_summary, string $keywords, ?string $prompt_mode = 'standard', ?string $custom_excerpt_prompt = null): string
    {
        // If a custom prompt is provided, use it by replacing placeholders.
        if ($prompt_mode === 'custom' && !empty($custom_excerpt_prompt)) {
            $prompt_template = $custom_excerpt_prompt;
        } else {
            $prompt_template = AIPKit_Content_Writer_Prompts::get_default_excerpt_prompt();
        }

        $prompt = str_replace('{topic}', $final_title, $prompt_template);
        $prompt = str_replace('{content_summary}', $content_summary, $prompt);
        $prompt = str_replace('{keywords}', $keywords, $prompt);

        return $prompt;
    }
}
