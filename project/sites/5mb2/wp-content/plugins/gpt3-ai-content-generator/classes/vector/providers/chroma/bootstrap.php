<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Vector\Providers;

use WPAICG\Vector\AIPKit_Vector_Base_Provider_Strategy;
use WP_Error;

require_once __DIR__ . '/methods.php';

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chroma Vector Store Provider Strategy.
 */
class AIPKit_Vector_Chroma_Strategy extends AIPKit_Vector_Base_Provider_Strategy
{
    protected $api_key;
    protected $chroma_url;
    protected $tenant = 'default_tenant';
    protected $database = 'default_database';

    /**
     * @return bool|\WP_Error
     */
    public function connect(array $config)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\connect_logic($this, $config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function create_index_if_not_exists(string $index_name, array $index_config)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\create_index_if_not_exists_logic($this, $index_name, $index_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upsert_vectors(string $index_name, array $vectors_data)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\upsert_vectors_logic($this, $index_name, $vectors_data);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function query_vectors(string $index_name, array $query_vector_param, int $top_k, array $filter = [])
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\query_vectors_logic($this, $index_name, $query_vector_param, $top_k, $filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_vectors(string $index_name, array $vector_ids_or_filter)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\delete_vectors_logic($this, $index_name, $vector_ids_or_filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_index(string $index_name)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\delete_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_indexes(?int $limit = null, ?string $order = null, ?string $after = null, ?string $before = null)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\list_indexes_logic($this, $limit, $order, $after, $before);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function describe_index(string $index_name)
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\describe_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'user_data')
    {
        return \WPAICG\Vector\Providers\Chroma\Methods\upload_file_for_vector_store_logic($this, $file_path, $original_filename, $purpose);
    }

    public function get_api_key(): ?string
    {
        return $this->api_key;
    }

    public function get_chroma_url(): ?string
    {
        return $this->chroma_url;
    }

    public function get_tenant(): string
    {
        return $this->tenant ?: 'default_tenant';
    }

    public function get_database(): string
    {
        return $this->database ?: 'default_database';
    }

    public function get_is_connected_status(): bool
    {
        return $this->is_connected;
    }

    public function set_api_key(?string $key): void
    {
        $this->api_key = $key ? trim($key) : null;
    }

    public function set_chroma_url(?string $url): void
    {
        $url = $url ? rtrim(trim($url), '/') : null;
        if ($url && substr_compare($url, '/api/v2', -strlen('/api/v2')) === 0) {
            $url = (string) substr($url, 0, -7);
        }
        $this->chroma_url = $url;
    }

    public function set_tenant(?string $tenant): void
    {
        $tenant = $tenant ? trim($tenant) : '';
        $this->tenant = $tenant !== '' ? $tenant : 'default_tenant';
    }

    public function set_database(?string $database): void
    {
        $database = $database ? trim($database) : '';
        $this->database = $database !== '' ? $database : 'default_database';
    }

    public function set_is_connected_status(bool $status): void
    {
        $this->is_connected = $status;
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function decode_json_public_wrapper(string $json_string, string $context)
    {
        return parent::decode_json($json_string, $context);
    }

    public function parse_error_response_public_wrapper($response_body, int $status_code, string $context): string
    {
        return parent::parse_error_response($response_body, $status_code, $context);
    }
}
