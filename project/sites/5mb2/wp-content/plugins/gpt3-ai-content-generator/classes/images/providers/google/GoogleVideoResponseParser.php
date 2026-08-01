<?php

namespace WPAICG\Images\Providers\Google;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles parsing responses from Google Video Generation models (Veo 3).
 */
class GoogleVideoResponseParser {

    const VEO_TOKENS_PER_VIDEO = 3000; // Define hardcoded token cost for Veo video generation
    const MAX_POLL_ATTEMPTS = 60; // Maximum polling attempts (10 minutes at 10-second intervals)
    const POLL_INTERVAL = 10; // Polling interval in seconds

    /**
     * Parses the initial response from Google Video Generation API - returns operation info for async polling.
     *
     * @param array  $decoded_response The decoded JSON response body from initial request.
     * @param string $model_id The model ID used for the request.
     * @param array  $api_params API connection parameters for polling.
     * @return array|WP_Error Array with operation info for async polling or completed video data.
     */
    public static function parse(array $decoded_response, string $model_id, array $api_params) {
        
        // Extract operation name from initial response
        $operation_name = $decoded_response['name'] ?? null;
        
        if (empty($operation_name)) {
            return new WP_Error('no_operation_name', __('No operation name found in Google Video API response.', 'gpt3-ai-content-generator'));
        }
        
        return [
            'status' => 'processing',
            'operation_name' => $operation_name,
            'model_id' => $model_id,
            'api_params' => $api_params,
            'message' => __('Video generation started. Please wait...', 'gpt3-ai-content-generator')
        ];
    }

    /**
     * Check the status of a video generation operation.
     *
     * @param string $operation_name The operation name to check.
     * @param string $model_id The model ID used for the request.
     * @param array  $api_params API connection parameters.
     * @param string $prompt The original prompt used for generation (optional, for completed operations).
     * @param int|null $user_id The WordPress user ID (optional, for completed operations).
     * @return array|WP_Error Status info or completed video data.
     */
    public static function check_operation_status(string $operation_name, string $model_id, array $api_params, string $prompt = '', ?int $user_id = null) {
        
        // Poll the operation status
        $poll_result = self::poll_operation($operation_name, $api_params);
        
        if (is_wp_error($poll_result)) {
            return $poll_result;
        }

        // Check if operation is done
        if (isset($poll_result['done']) && $poll_result['done'] === true) {
            
            // Operation completed, extract video data
            if (isset($poll_result['response']['generateVideoResponse']['generatedSamples'])) {
                $samples = $poll_result['response']['generateVideoResponse']['generatedSamples'];
                                
                $videos = [];
                if (is_array($samples) && !empty($samples)) {
                    foreach ($samples as $index => $sample) {
                        if (isset($sample['video']['uri'])) {
                            
                            // Download the video file and get the URL - now includes prompt and user info
                            $video_url = self::download_video($sample['video']['uri'], $api_params, $prompt, $user_id, $model_id);
                            if (!is_wp_error($video_url)) {
                                $videos[] = ['url' => $video_url, 'type' => 'video'];
                            } else {
                                return $video_url;
                            }
                        }
                    }
                } else {
                    return new WP_Error('no_video_samples', __('No video samples found in API response.', 'gpt3-ai-content-generator'));
                }
                
                // Calculate usage for video generation
                $usage = null;
                $num_videos_generated = count($videos);
                if ($num_videos_generated > 0) {
                    $total_tokens_for_video = $num_videos_generated * self::VEO_TOKENS_PER_VIDEO;
                    $usage = [
                        'input_tokens'  => 0,
                        'output_tokens' => $total_tokens_for_video,
                        'total_tokens'  => $total_tokens_for_video,
                        'provider_raw'  => [
                            'source' => 'hardcoded_veo_cost',
                            'videos_generated' => $num_videos_generated,
                            'cost_per_video_tokens' => self::VEO_TOKENS_PER_VIDEO,
                        ],
                    ];
                }
                                return ['videos' => $videos, 'usage' => $usage, 'status' => 'completed'];
                
            } else {
                return new WP_Error('no_video_in_response', __('No video data found in completed Google Video API response.', 'gpt3-ai-content-generator'));
            }
        }

        // Check for error in response
        if (isset($poll_result['error'])) {
            $error_message = $poll_result['error']['message'] ?? __('Unknown error occurred during video generation.', 'gpt3-ai-content-generator');
            return new WP_Error('google_video_generation_error', $error_message);
        }

        // Operation still in progress
        return [
            'status' => 'processing',
            'operation_name' => $operation_name,
            'message' => __('Video generation in progress...', 'gpt3-ai-content-generator')
        ];
    }

    /**
     * Polls the operation status.
     *
     * @param string $operation_name The operation name to poll.
     * @param array  $api_params API connection parameters.
     * @return array|WP_Error The polling response or WP_Error.
     */
    private static function poll_operation(string $operation_name, array $api_params) {
                
        $poll_url = GoogleVideoUrlBuilder::build($operation_name, $api_params, 'poll');
        
        if (is_wp_error($poll_url)) {
            return $poll_url;
        }

        $response = wp_remote_get($poll_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('poll_http_error', __('HTTP error during Google Video API polling.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $decoded_response = json_decode($body, true);
        
        if ($status_code !== 200 || empty($decoded_response)) {
            /* translators: %d: HTTP status code */
            return new WP_Error('poll_api_error', sprintf(__('Google Video API polling error (%d).', 'gpt3-ai-content-generator'), $status_code));
        }

        return $decoded_response;
    }

    /**
     * Downloads the video from the provided URI.
     *
     * @param string $video_uri The URI of the video to download.
     * @param array  $api_params API connection parameters.
     * @param string $prompt The original prompt used for generation.
     * @param int|null $user_id The WordPress user ID to associate with the attachment.
     * @param string $model_id The model ID used for generation.
     * @return string|WP_Error The local video URL or WP_Error on failure.
     */
    private static function download_video(string $video_uri, array $api_params, string $prompt = '', ?int $user_id = null, string $model_id = 'veo-3.0-generate-preview') {
    
        
        $api_key = $api_params['api_key'] ?? '';
        
        // Download the video file
        $response = wp_remote_get($video_uri, [
            'timeout' => 120, // Longer timeout for video download
            'headers' => [
                'x-goog-api-key' => $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('video_download_error', __('Failed to download video from Google API.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            /* translators: %d: HTTP status code */
            return new WP_Error('video_download_http_error', sprintf(__('Video download failed with status %d.', 'gpt3-ai-content-generator'), $status_code));
        }

        $video_data = wp_remote_retrieve_body($response);
        $video_size = strlen($video_data);
                
        if (empty($video_data)) {
            return new WP_Error('empty_video_data', __('Downloaded video data is empty.', 'gpt3-ai-content-generator'));
        }

        // Check available memory before proceeding
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $available_memory = $memory_limit - $memory_usage;
                
        if ($video_size > $available_memory * 0.8) { // Use only 80% of available memory as safety buffer
            return new WP_Error('insufficient_memory', __('Insufficient memory to process video file.', 'gpt3-ai-content-generator'));
        }

        // Save video to WordPress uploads directory
        $upload_dir = wp_upload_dir();
        
        // Check for upload directory errors
        if (!empty($upload_dir['error'])) {
            return new WP_Error('upload_dir_error', __('Upload directory error: ', 'gpt3-ai-content-generator') . $upload_dir['error']);
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $filesystem_ready = WP_Filesystem();
        global $wp_filesystem;
        if (!$filesystem_ready || !$wp_filesystem) {
            return new WP_Error('filesystem_init_failed', __('Could not initialize filesystem.', 'gpt3-ai-content-generator'));
        }
        
        // Check if upload directory is writable
        if (!$wp_filesystem->is_writable($upload_dir['path'])) {
            return new WP_Error('upload_dir_not_writable', __('Upload directory is not writable.', 'gpt3-ai-content-generator'));
        }
        
        $filename = 'veo3_video_' . time() . '_' . wp_generate_password(8, false) . '.mp4';
        $file_path = trailingslashit($upload_dir['path']) . $filename;
        
        // Write video data to file
        $result = $wp_filesystem->put_contents($file_path, $video_data, FS_CHMOD_FILE);
                
        if ($result === false) {
            return new WP_Error('video_save_error', __('Failed to save video file to uploads directory.', 'gpt3-ai-content-generator'));
        }

        // Create attachment in media library
        $attachment_title = !empty($prompt) ? substr($prompt, 0, 100) : 'Veo 3 Generated Video';
        $attachment = [
            'guid'           => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'video/mp4',
            'post_title'     => $attachment_title,
            'post_content'   => $prompt ?: '',
            'post_status'    => 'inherit'
        ];
        
        // Set author if user ID is provided
        if ($user_id && $user_id > 0) {
            $attachment['post_author'] = $user_id;
        }
            
        $attachment_id = wp_insert_attachment($attachment, $file_path);
            
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata for the attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        try {
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            
            $metadata_result = wp_update_attachment_metadata($attachment_id, $attachment_data);
        } catch (Exception $e) {
        }

        // Add custom meta to identify this as an AI-generated video
        $meta1 = add_post_meta($attachment_id, '_aipkit_generated_video', '1');
        $meta2 = add_post_meta($attachment_id, '_aipkit_video_model', $model_id);
        $meta3 = add_post_meta($attachment_id, '_aipkit_video_provider', 'Google');
        
        // Add prompt and size metadata (similar to images)
        $meta4 = $meta5 = $meta6 = true; // Initialize for logging
        if (!empty($prompt)) {
            $meta4 = add_post_meta($attachment_id, '_aipkit_video_prompt', $prompt);
        }
        
        // Add size information if available from metadata
        if (isset($attachment_data['width']) && isset($attachment_data['height'])) {
            $size_string = $attachment_data['width'] . 'x' . $attachment_data['height'];
            $meta5 = add_post_meta($attachment_id, '_aipkit_video_size', $size_string);
        }
        
        // Add duration if available
        if (isset($attachment_data['length'])) {
            $meta6 = add_post_meta($attachment_id, '_aipkit_video_duration', $attachment_data['length']);
        }

        $final_url = wp_get_attachment_url($attachment_id);
        
        return $final_url;
    }

    /**
     * Parses error response from Google Video API.
     */
    public static function parse_error(string $response_body, int $status_code): string {
        $decoded = json_decode($response_body, true);
        
        if (isset($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }
        
        /* translators: %d: HTTP status code */
        return sprintf(__('Google Video API returned status %d', 'gpt3-ai-content-generator'), $status_code);
    }
} 
