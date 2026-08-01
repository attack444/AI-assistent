<?php

namespace AiBuddy;

use AiBuddy\OpenAi\Api;
use AiBuddy\Google\Api as GoogleAi;
use AiBuddy\Claude\Api as Claude;
use AiBuddy\OpenRouter\Api as OpenRouter;
use AiBuddy\OpenAi\QueryFactory;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Class Rest
 *
 * @package AiBuddy
 */
class Rest {
	/**
	 * Ai Content Generator instance.
	 *
	 * @var AiContentGenerator
	 */
	private AiContentGenerator $ai;
	private Plugin $core;
	private Api $api;
    private GoogleAi $googleAi;
    private Claude $claude;
    private OpenRouter $openRouter;
	private Markdown $markdown;
	private Notice $notice;

	public function __construct( $core, AiContentGenerator $ai, Api $api, GoogleAi $googleAi, Claude $claude, OpenRouter $openRouter, Markdown $markdown, Notice $notice ) {
		$this->core     = $core;
		$this->ai       = $ai;
		$this->api      = $api;
        $this->googleAi = $googleAi;
        $this->claude   = $claude;
        $this->openRouter   = $openRouter;
		$this->markdown = $markdown;
		$this->notice = $notice;
		$notice->initAction();
	}

	public function register_rest_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => array( AuthGate::class, 'can_manage_options' ),
					'callback'            => array( $this, 'update_settings' ),
				),
				array(
					'methods'             => 'GET',
					'permission_callback' => array( AuthGate::class, 'can_manage_options' ),
					'callback'            => array( $this, 'get_settings' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/update_option',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'can_manage_options' ),
				'callback'            => array( $this, 'update_option' ),
			),
		);
		register_rest_route(
			$namespace,
			'/settings/templates',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'get_templates' ),
					'args'                => array(
						'category' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'update_templates' ),
					'args'                => array(
						'category'  => array(
							'required' => true,
							'type'     => 'string',
						),
						'templates' => array(
							'required' => true,
							'type'     => 'array',
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/ai/generator/completions',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_completions' ),
				'args'                => array(
					'messages' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/generator/message/completions',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_message_completions' ),
				'args'                => array(
					'messages' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/generator/images',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_images' ),
				'args'                => array(
					'prompt' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/analyzer/image',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'analyze_image' ),
				'args'                => array(
					'image_url' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
        register_rest_route(
			$namespace,
			'/ai/notice',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( Notice::class, 'notice_feedback_update' ),
			)
		);
        register_rest_route(
			$namespace,
			'/ai/tags',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_tags' ),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/generator/titles',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_titles' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/generator/excerpts',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_excerpts' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/ai/generator/post_images',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'generate_post_images' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/posts/update/excerpt',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'update_post' ),
				'args'                => array(
					'excerpt' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/posts',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'create_post' ),
				'args'                => array(
					'title'   => array(
						'required' => true,
						'type'     => 'string',
					),
					'excerpt' => array(
						'required' => true,
						'type'     => 'string',
					),
					'content' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/attachments',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'create_attachment' ),
				'args'                => array(
					'title'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'caption'     => array(
						'required' => true,
						'type'     => 'string',
					),
					'alt'         => array(
						'required' => true,
						'type'     => 'string',
					),
					'description' => array(
						'required' => true,
						'type'     => 'string',
					),
					'url'         => array(
						'required' => true,
						'type'     => 'string',
					),
					'filename'    => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/posts/(?P<post_id>[\d]+)/content',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_post_content' ),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/posts/post_count/',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'post_count' ),
			)
		);
		register_rest_route(
			$namespace,
			'/wp/posts/get-offset/content',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_range_post_content' ),
			)
		);
		register_rest_route(
			$namespace,
			'/models',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_models' ),
			)
		);
	}

	public function get_settings() {
		$options                    = $this->core->options->to_array();
		$options['openai']['usage'] = get_option( 'ai_buddy_openai_usage' );

		// Add ApiManager data for frontend
		$options['api_manager'] = [
			'available_keys' => ApiManager::getAvailableApiKeys(),
			'has_any_key' => ApiManager::hasAnyApiKey(),
			'default_model' => ApiManager::getDefaultModel(
				isset($options['fse']['model']) ? $options['fse']['model'] : null
			)
		];

		return new WP_REST_Response(
			array(
				'settings' => $options,
			),
			200
		);
	}

	public function update_settings( WP_REST_Request $request ) {
		try {
			// Extract data from the request.
			$options = $request->get_json_params();

			// Extract other modules from the $options array.
            $modules_titles = isset($options['modules']['titles']) ? $options['modules']['titles'] : null;
            $modules_excerpts = isset($options['modules']['excerpts']) ? $options['modules']['excerpts'] : null;
            $modules_images = isset($options['modules']['images']) ? $options['modules']['images'] : null;
            $modules_woocommerce = isset($options['modules']['woocommerce']) ? $options['modules']['woocommerce'] : null;

			// Get the plugin options from the WordPress database.
			$plugin_option = get_option( 'ai_buddy', array() );

			// Update the plugin options with the extracted values from $options.
			if ( isset($options['api_key_validation']) ) {
				$plugin_option['api_key_validation'] = $options['api_key_validation'];
			}
			if ( isset($options['modules']['titles']) ) {
				$plugin_option['modules']['titles'] = $modules_titles;
			}
			if ( isset($options['modules']['excerpts']) ) {
				$plugin_option['modules']['excerpts'] = $modules_excerpts;
			}
			if ( isset($options['modules']['images']) ) {
				$plugin_option['modules']['images'] = $modules_images;
			}
			if ( isset($options['modules']['woocommerce']) ) {
				$plugin_option['modules']['woocommerce'] = $modules_woocommerce;
			}

            try {
                if ( isset($options['openai']['apikey']) && !$this->api->check_api_key_validity($options['openai']['apikey']) ) {
                    $plugin_option['openai']['apikey'] = '';
                }

                if ( isset($options['googleai']['apikey']) && !$this->googleAi->check_api_key_validity($options['googleai']['apikey']) ) {
                    $plugin_option['googleai']['apikey'] = '';
                }

                if ( isset($options['claude']['apikey']) && !$this->claude->check_api_key_validity($options['claude']['apikey']) ) {
                    $plugin_option['claude']['apikey'] = '';
                }

                if ( isset($options['openrouter']['apikey']) && !$this->openRouter->check_api_key_validity($options['openrouter']['apikey']) ) {
                    $plugin_option['openrouter']['apikey'] = '';
                }
            } catch (Exception $e) {
                return new WP_REST_Response([
                    'message' => $e->getMessage()],
                    500);
            }

			// Update the plugin options in the WordPress database.
			$this->core->update_options( $options );

			return new WP_REST_Response(
				array(
					'settings' => $this->core->get_options(),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function update_option( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			$name   = $params['option_name'];
			$value  = $params['option_value'];

			$options = $this->core->update_option( $name, $value );
			$success = ! ! $options;
			$message = $success ? 'success' : 'Could not update option.';
			return new WP_REST_Response(
				array(
					'success' => $success,
					'message' => $message,
					'options' => $options,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}
	/**
	 * Generate completions
	 *
	 * @param WP_REST_Request $request accepts prompt.
	 * @Throws Exception on error.
	 * @return WP_REST_Response response.
	 */
	public function generate_completions( WP_REST_Request $request ) {
		try {
			$params   = $request->get_json_params();
			$response = $this->ai->exec( QueryFactory::text( $params['messages'], $params ) );

            if (isset($response->raw['error'])) {
                throw new Exception ( __( 'We are unable to locate an API Key for the selected AI Model. Please proceed to the settings to enter your API Key.', 'aibuddy-openai-chatgpt' ));
            }

            if (isset($params['source_page']) && $params['source_page'] === 'post_page') {
                $this->notice->increase_usage_count();
            }

            return new WP_REST_Response(
				array(
					'completions' => $response->results[0] ?? '',
					'usage'       => isset($response->raw['usage']) ?? '',
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
                array(
                    'message' => $e->getMessage(),
                ),
				500
			);
		}
	}

	/**
	 * Generate completions
	 *
	 * @param WP_REST_Request $request accepts prompt.
	 * @Throws Exception on error.
	 * @return WP_REST_Response response.
	 */
	public function generate_message_completions( WP_REST_Request $request ) {
		try {
			$params   = $request->get_json_params();
			$response = $this->ai->exec( QueryFactory::message( $params['messages'], $params ) );

			return new WP_REST_Response(
				array(
					'completions' => $response->results['content'] ?? '',
					'usage'       => $response->raw['usage'],
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}
	/**
	 * Generate images
	 *
	 * @param WP_REST_Request $request accepts image prompt.
	 * @Throws Exception on error.
	 * @return WP_REST_Response response.
	 */
	public function generate_images( WP_REST_Request $request ) {
		try {
			$params   = $request->get_json_params();
			$response = $this->ai->exec( QueryFactory::image( $params['prompt'], $params ) );

			return new WP_REST_Response(
				array(
					'images' => $response->results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Analyze image to generate metadata
	 *
	 * @param WP_REST_Request $request accepts image_url.
	 * @Throws Exception on error.
	 * @return WP_REST_Response response.
	 */
	public function analyze_image( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			$image_url = $params['image_url'] ?? null;
			
			if ( ! $image_url ) {
				return new WP_REST_Response( array( 'success' => false, 'message' => 'Image URL required' ), 400 );
			}
			
			$messages = array(
				array(
					'role' => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => 'Analyze this image and return metadata as JSON: {"title": "descriptive title", "caption": "brief caption", "description": "detailed description", "alt_text": "accessibility text", "filename": "suggested-name.jpg"}' ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => $image_url ) )
					)
				)
			);
			
			$response = $this->api->create_message_completions( array(
				'model' => 'gpt-4o',
				'messages' => $messages,
				'max_tokens' => 300
			) );
			
			if ( isset( $response['choices'][0]['message']['content'] ) ) {
				$content = $response['choices'][0]['message']['content'];
				
				// Extract JSON from markdown if wrapped
				if ( strpos( $content, '```' ) !== false ) {
					$content = preg_replace( '/```(?:json)?\s*(.*?)\s*```/s', '$1', $content );
				}
				
				$metadata = json_decode( trim( $content ), true );
				
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $metadata ) ) {
					return new WP_REST_Response( array( 'success' => true, 'data' => $metadata ), 200 );
				}
			}
			
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Failed to analyze image' ), 500 );
			
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $e->getMessage() ), 500 );
		}
	}

	public function generate_titles( WP_REST_Request $request ) {
		try {
			$text = $this->retrieve_post_content( (int) $request->get_param( 'post_id' ) );

			if ( null === $text ) {
				return $this->not_found_response();
			}

			$max_length   = 462;
			$request_text = '';
			if ( strlen( $text ) > 0 && strlen( $text ) <= $max_length ) {
				$request_text = $text;
			} elseif ( strlen( $text ) > $max_length ) {
				$request_text = substr( $text, 0, $max_length );
			}

			$prompt = sprintf( $this->core->options->get( 'generator.titles.prompt', '%s' ), $request_text );

			$response = $this->ai->exec(
				QueryFactory::text(
					$this->format_into_message( $prompt ),
					array(
						'max_results' => $this->core->options->get( 'generator.titles.results' ),
						'max_tokens'  => $this->core->options->get( 'generator.titles.tokens' ),
					)
				)
			);

			return new WP_REST_Response(
				array(
					'titles' => $response->results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function generate_excerpts( $request ): WP_REST_Response {
		try {
			$text = $this->retrieve_post_content( (int) $request->get_param( 'post_id' ) );

			if ( null === $text ) {
				return $this->not_found_response();
			}

			$max_length   = 462;
			$request_text = '';
			if ( strlen( $text ) > 0 && strlen( $text ) <= $max_length ) {
				$request_text = $text;
			} elseif ( strlen( $text ) > $max_length ) {
				$request_text = substr( $text, 0, $max_length );
			}
			$prompt   = sprintf( $this->core->options->get( 'generator.excerpts.prompt', '%s' ), $request_text );
			$response = $this->ai->exec(
				QueryFactory::text(
					(array) $this->format_into_message( $prompt ),
					array(
						'max_results' => $this->core->options->get( 'generator.excerpts.results' ),
						'max_tokens'  => $this->core->options->get( 'generator.excerpts.tokens' ),
					)
				)
			);

			return new WP_REST_Response(
				array(
					'excerpts' => $response->results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function generate_post_images( $request ): WP_REST_Response {
		try {
			$text = $this->retrieve_post_content( (int) $request->get_param( 'post_id' ) );

			if ( null === $text ) {
				return $this->not_found_response();
			}

			$max_length   = 462;
			$request_text = '';
			if ( strlen( $text ) > 0 && strlen( $text ) <= $max_length ) {
				$request_text = $text;
			} elseif ( strlen( $text ) > $max_length ) {
				$request_text = substr( $text, 0, $max_length );
			}

			$response = $this->ai->exec(
				QueryFactory::image(
					sprintf( $this->core->options->get( 'generator.images.prompt', '%s' ), $request_text ),
					array(
						'max_results' => $this->core->options->get( 'generator.images.results' ),
						'max_tokens'  => $this->core->options->get( 'generator.images.tokens' ),
					)
				)
			);

			return new WP_REST_Response(
				array(
					'images' => $response->results,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function update_post( WP_Rest_Request $request ) {
		try {
			$post_id = (int) $request->get_param( 'post_id' );

			if ( null === get_post( $post_id ) ) {
				return $this->not_found_response();
			}

			$post = get_post( $post_id );

			if ( $request->get_param( 'excerpt' ) ) {
				$post->post_excerpt = $request->get_param( 'excerpt' );
			}
			wp_update_post( $post );

			return new WP_REST_Response(
				array(
					'message' => __( 'Post updated', 'aibuddy-openai-chatgpt' ),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function create_post( WP_REST_Request $request ) {
		try {
			$post    = array(
				'post_title'   => $request->get_param( 'title' ),
				'post_excerpt' => $request->get_param( 'excerpt' ),
				'post_content' => $this->core->get( Markdown::class )->to_html( $request->get_param( 'content' ) ),
				'post_status'  => 'draft',
				'post_type'    => 'post',
			);
			$post_id = wp_insert_post( $post );
            
            do_action('ai_buddy_after_post_created', $post_id,$request);
            
            return new WP_REST_Response(
				array(
					'post_id'        => $post_id,
					'post_title'     => get_the_title( $post_id ),
					'post_edit_link' => get_edit_post_link( $post_id, '&' ),
					'post_permalink' => get_permalink( $post_id ),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function create_attachment( WP_REST_Request $request ) {
		try {
			// weird but it's WordPress way x_x
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$url = $request->get_param( 'url' );
			
			// Check if the URL is a base64 data URL (like from GPT image generation)
			if ( strpos( $url, 'data:image' ) === 0 ) {
				// Handle base64 data URL
				$attachment_id = $this->handle_base64_image(
					$url,
					sanitize_text_field( $request->get_param( 'title' ) ),
					sanitize_text_field( $request->get_param( 'filename' ) )
				);
			} else {
				// Handle regular URL
				$attachment_id = media_sideload_image(
					$url,
					0,
					sanitize_text_field( $request->get_param( 'title' ) ),
					'id'
				);
			}

			if ( is_wp_error( $attachment_id ) ) {
				return new WP_REST_Response(
					array(
						'message' => $attachment_id->get_error_message(),
					),
					500
				);
			}
			$alt        = sanitize_text_field( $request->get_param( 'alt' ) );
			$attachment = array(
				'ID'           => $attachment_id,
				'post_content' => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'post_excerpt' => sanitize_text_field( $request->get_param( 'caption' ) ),
				'post_status'  => 'inherit',
			);
			wp_update_post( $attachment );

			// the ony way to update alt text
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $alt ) );

			// strip possible path from filename
			$filename = basename( sanitize_text_field( $request->get_param( 'filename' ) ) );
			if ( ! empty( $filename ) ) {
				$file     = get_attached_file( $attachment_id, true );
				$dir      = dirname( $file );
				$new_file = $dir . '/' . $filename;

				if ( file_exists( $new_file ) ) {
					$new_file = $dir . '/' . uniqid() . '-' . $filename;
				}
                $this->move_file( $file, $new_file );
				update_attached_file( $attachment_id, $new_file );
			}

			return new WP_REST_Response(
				array(
					'attachment_id' => $attachment_id,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Handle base64 data URL by converting it to a temporary file and creating attachment
	 * 
	 * @param string $data_url Base64 data URL (like data:image/png;base64,...)
	 * @param string $title Title for the attachment
	 * @param string $filename Optional filename
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure
	 */
	private function handle_base64_image( $data_url, $title = '', $filename = '' ) {
		// Parse the data URL to extract mime type and base64 data
		if ( ! preg_match( '/^data:image\/([a-z]+);base64,(.+)$/i', $data_url, $matches ) ) {
			return new \WP_Error( 'invalid_data_url', 'Invalid base64 data URL format' );
		}

		$image_type = $matches[1]; // e.g., 'png', 'jpeg', 'gif'
		$base64_data = $matches[2];

		// Decode the base64 data
		$image_data = base64_decode( $base64_data );
		if ( $image_data === false ) {
			return new \WP_Error( 'base64_decode_failed', 'Failed to decode base64 data' );
		}

		// Generate filename if not provided
		if ( empty( $filename ) ) {
			$filename = 'generated-image-' . uniqid() . '.' . $image_type;
		} else {
			// Ensure the filename has the correct extension
			$filename = preg_replace( '/\.[^.]+$/', '', $filename ) . '.' . $image_type;
		}

		// Create temporary file
		$tmp_file = wp_tempnam( $filename );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'temp_file_failed', 'Failed to create temporary file' );
		}

		// Write image data to temporary file
		if ( file_put_contents( $tmp_file, $image_data ) === false ) {
			wp_delete_file( $tmp_file );
			return new \WP_Error( 'file_write_failed', 'Failed to write image data to temporary file' );
		}

		// Create file array for media_handle_sideload
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
			'error'    => 0,
			'size'     => filesize( $tmp_file ),
		);

		// Set mime type based on image type
		$mime_types = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
		$file_array['type'] = isset( $mime_types[ $image_type ] ) ? $mime_types[ $image_type ] : 'image/' . $image_type;

		// Use media_handle_sideload to create the attachment
		$attachment_id = media_handle_sideload( $file_array, 0, $title );

		// Clean up temporary file
		wp_delete_file( $tmp_file );

		return $attachment_id;
	}

	public function get_post_content( WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );

		if ( ! $post ) {
			return $this->not_found_response();
		}
		$content = apply_filters( 'the_content', $post->post_content );
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/[\r\n]+/', "\n", $content );

		return new WP_REST_Response(
			array(
				'content' => $content,
			),
			200
		);
	}

	public function post_count( WP_REST_Request $request ) {
		try {
			$params    = $request->get_query_params();
			$post_type = $params['post_type'];
			$count     = wp_count_posts( $post_type );
			return new WP_REST_Response(
				array(
					'success' => true,
					'count'   => $count,
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function get_range_post_content( WP_REST_Request $request ) {
		try {
			$params    = $request->get_query_params();
			$offset    = (int) $params['offset'];
			$post_id   = (int) $params['post_id'];
			$post_type = $params['post_type'];
			$post      = null;

			if ( ! empty( $post_id ) ) {
				$post = get_post( $post_id );
				if ( 'publish' !== $post->post_status && 'future' !== $post->post_status && 'draft' !== $post->post_status ) {
					$post = null;
				}
			} else {
				$posts = get_posts(
					array(
						'posts_per_page' => 1,
						'post_type'      => $post_type,
						'offset'         => $offset,
						'orderby'        => 'date',
						'order'          => 'ASC',
						'post_status'    => 'publish',
					)
				);
				$post  = count( $posts ) === 0 ? null : $posts[0];
			}
			if ( ! $post ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Post not found',
					),
					404
				);
			}
			$polished_post = $this->core->get_polished_post( $post );
			return new WP_REST_Response(
				array(
					'success' => true,
					'content' => $polished_post['content'],
					'excerpt' => $polished_post['excerpt'],
					'postId'  => $polished_post['postId'],
					'title'   => $polished_post['title'],
					'url'     => $polished_post['url'],
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function get_templates( WP_REST_Request $request ) {
		$category = (string) $request->get_param( 'category' );
		$groups   = $this->get_templates_option();

		$group = Arr::first(
			$groups,
			function ( $group ) use ( $category ) {
				return $group->category === $category;
			},
			new EmptyTemplatesGroup()
		);

		return new WP_REST_Response( array( 'templates' => $group->templates ), 200 );
	}

	public function update_templates( WP_REST_Request $request ) {
		$category = (string) $request->get_param( 'category' );
		$groups   = $this->get_templates_option();

		$group = Arr::first(
			$groups,
			function ( $group ) use ( $category ) {
				return $group->category === $category;
			}
		);

		if ( null === $group ) {
			$group    = new TemplatesGroup( $category, array() );
			$groups[] = $group;
		}

		$group->templates = (array) $request->get_param( 'templates' );

		$option_value = array_map(
			function ( $group ) {
				return $group->to_array();
			},
			$groups
		);

		update_option( $this->get_templates_option_key(), $option_value );

		return new WP_REST_Response( array( 'templates' => $group->templates ), 200 );
	}

	private function retrieve_post_content( $post_id ): ?string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$text = apply_filters( 'the_content', $post->post_content );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/^\h*\v+/m', '', $text );

		return html_entity_decode( $text );
	}

	private function format_into_message( $prompt ): array {
		return array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);
	}
	/**
	 * @return array<TemplatesGroup>
	 */
	private function get_templates_option(): array {
		$option = (array) get_option( $this->get_templates_option_key(), array() );

		return array_map(
			function ( $group ) {
				return new TemplatesGroup( $group['category'], $group['templates'] );
			},
			$option
		);
	}

	private function get_templates_option_key(): string {
		return $this->core->slug . '_templates';
	}

	private function not_found_response(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'message' => __( 'Not found', 'aibuddy-openai-chatgpt' ),
			),
			404
		);
	}

    public function generate_tags( WP_REST_Request $request ) {
        $tags = $request->get_json_params();
        $tag_ids = [];
        foreach ($tags as $tag) {
            $tag_id = wp_insert_term($tag, 'product_tag');
            if (!is_wp_error($tag_id)) {
                $tag_ids[] = $tag_id['term_id'];
            }
        }

        return rest_ensure_response($tag_ids);
    }

    /**
     * Move file
     *
     * @param string $file
     * @param string $new_file
     */
    private function move_file($file, $new_file) {
        require_once ( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
        require_once ( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

        $filesystem = new \WP_Filesystem_Direct(null);
        $filesystem->move($file, $new_file);
    }

    /**
     * Get models list
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function get_models( WP_REST_Request $request ) {
        try {
            $models = Models::get_models_list();
            
            // Transform the models into the format expected by the frontend
            $formatted_models = [];
            
            foreach ($models as $provider => $provider_models) {
                $options = [];
                foreach ($provider_models as $model_id => $model_name) {
                    $options[] = [
                        'value' => $model_id,
                        'label' => $model_name
                    ];
                }
                
                $formatted_models[] = [
                    'label' => $provider,
                    'options' => $options
                ];
            }
            
            return new WP_REST_Response([
                'models' => $formatted_models
            ], 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}