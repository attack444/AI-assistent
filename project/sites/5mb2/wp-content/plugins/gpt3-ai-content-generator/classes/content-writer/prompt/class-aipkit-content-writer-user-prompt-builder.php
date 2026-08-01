<?php


namespace WPAICG\ContentWriter\Prompt;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Builds the user prompt for the Content Writer module.
 * UPDATED: Simplified to only use custom prompts, as Guided Mode has been removed.
 */
class AIPKit_Content_Writer_User_Prompt_Builder
{
    /**
     * Builds the main user prompt based on the custom prompt setting.
     *
     * @param array $settings User-defined settings from the Content Writer form.
     *                        Expected keys: 'custom_content_prompt'.
     * @return string The user prompt.
     */
    public static function build(array $settings): string
    {
        // Always use the custom content prompt. The caller will handle replacing placeholders.
        $prompt = trim($settings['custom_content_prompt'] ?? '');
        if ($prompt === '') {
            return '';
        }

        $output_rules = "Output rules:\n"
            . "- Return only the final article body.\n"
            . "- Do not include assistant commentary, chat-style follow-up questions, or offers to rewrite, convert, continue, or create another version.\n"
            . "- Do not include suggestions like \"If you want, I can...\" or alternative format options after the article.";

        return trim($prompt . "\n\n" . $output_rules);
    }
}
