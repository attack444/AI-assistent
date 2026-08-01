<?php

namespace WPAICG\SEO;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AIPKit_Base_SEO_Handler implements AIPKit_SEO_Handler_Interface
{
    protected const LOGIC_DIR = '';
    protected const LOGIC_NAMESPACE = '';

    public function update_meta_description(int $post_id, string $description): bool
    {
        return (bool) $this->call_logic('update-meta-description.php', 'update_meta_description_logic', [$post_id, $description], false);
    }

    public function update_focus_keyword(int $post_id, string $keyword): bool
    {
        return (bool) $this->call_logic('update-focus-keyword.php', 'update_focus_keyword_logic', [$post_id, $keyword], false);
    }

    public function get_focus_keyword(int $post_id): ?string
    {
        return $this->call_logic('get-focus-keyword.php', 'get_focus_keyword_logic', [$post_id], null);
    }

    /**
     * @param mixed $fallback
     * @return mixed
     */
    private function call_logic(string $file_name, string $function_name, array $args, $fallback)
    {
        $file_path = rtrim((string) static::LOGIC_DIR, '/\\') . '/' . $file_name;
        if (!file_exists($file_path)) {
            return $fallback;
        }

        require_once $file_path;
        $function = trim((string) static::LOGIC_NAMESPACE, '\\') . '\\' . $function_name;
        return function_exists($function) ? $function(...$args) : $fallback;
    }
}
