<?php


namespace WPAICG\ContentWriter\Prompt;

use WPAICG\ContentWriter\AIPKit_Content_Writer_Prompts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the prompt for generating post tags.
 */
class AIPKit_Content_Writer_Tags_Prompt_Builder
{
    /**
     * Builds the final user prompt for the tags generation AI call.
     *
     * @param string $final_title The final generated title of the article.
     * @param string $content_summary A summary of the main content.
     * @param string $keywords The relevant keywords for the article.
     * @param string|null $prompt_mode The selected prompt mode ('standard' or 'custom').
     * @param string|null $custom_tags_prompt The user-defined custom prompt, if any.
     * @return string The complete user prompt for the AI.
     */
    public static function build(string $final_title, string $content_summary, string $keywords, ?string $prompt_mode = 'standard', ?string $custom_tags_prompt = null): string
    {
        if ($prompt_mode === 'custom' && !empty($custom_tags_prompt)) {
            $prompt_template = $custom_tags_prompt;
        } else {
            $prompt_template = AIPKit_Content_Writer_Prompts::get_default_tags_prompt();
        }

        $prompt = str_replace('{topic}', $final_title, $prompt_template);
        $prompt = str_replace('{content_summary}', $content_summary, $prompt);
        $prompt = str_replace('{keywords}', $keywords, $prompt);

        return $prompt;
    }
}
