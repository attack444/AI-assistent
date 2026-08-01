<?php

namespace AiBuddy;

use AiBuddy\OpenAi\Api;
use AiBuddy\Google\Api as GoogleAi;
use AiBuddy\Claude\Api as Claude;
use AiBuddy\OpenRouter\Api as OpenRouter;
use AiBuddy\OpenAi\ImageQuery;
use AiBuddy\OpenAi\Query;
use AiBuddy\OpenAi\Response;
use AiBuddy\OpenAi\TextQuery;
use AiBuddy\OpenAi\MessageQuery;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class AiContentGenerator {
	private Api $api;
	
	private GoogleAi $googleAi;
	private Claude $claude;

    private OpenRouter $openRouter;

	public function __construct( Api $api, GoogleAi $googleAi, Claude $claude, OpenRouter $openRouter ) {
		$this->api = $api;
		$this->googleAi = $googleAi;
		$this->claude = $claude;
		$this->openRouter = $openRouter;
	}

	/**
	 * @return Response
	 */
	public function exec( Query $query ) {
		try {
			if ( $query instanceof TextQuery ) {
			    $models_list = new Models();
                $models = $models_list->get_models_list();
                
                if (array_key_exists($query->model, $models['OpenAI'])) {
                    $data = $this->api->create_completions($query->to_request_body());
                    $response = new Response(
                        $query,
                        !is_string($data) ? [$data['choices'][0]['message']['content']] : [$data],
                        !is_string($data) ? $data : [$data],
                    );
                } elseif (array_key_exists($query->model, $models['Google'])) {
                    $data = $this->googleAi->create_completions($query->to_request_body(), $query->model);

                    $response = new Response(
                        $query,
                        !is_string($data) ? [$data['candidates'][0]['content']['parts'][0]['text']] : [$data],
                        !is_string($data) ? $data : [$data],
                    );

                    return $response;
                } elseif (array_key_exists($query->model, $models['Claude'])) {
                    $data = $this->claude->create_completions($query->to_request_body());

                    $response = new Response(
                        $query,
                        !is_string($data) ? [$data['content'][0]['text']] : [$data],
                        !is_string($data) ? $data : [$data],
                    );

                    return $response;
                } else {
                    $data = $this->openRouter->create_completions($query->to_request_body());
                    
                    $response = new Response(
                        $query,
                        !is_string($data) ? [$data['choices'][0]['message']['content']] : [$data],
                        !is_string($data) ? $data : [$data],
                    );
                    
                    return $response;
                }
            } elseif ($query instanceof ImageQuery ) {
				$data = $this->api->create_images( $query->to_request_body() );
				$image_urls = [];
				
				if (!is_string($data)) {
					if ($query->model === 'gpt-image-1') {
						// Handle base64 responses for gpt-image-1
						foreach ($data['data'] as $image_data) {
							if (isset($image_data['b64_json'])) {
								$image_urls[] = 'data:image/png;base64,' . $image_data['b64_json'];
							}
						}
					} else {
						// Handle URL responses for DALL-E models
						$image_urls = array_column($data['data'], 'url');
					}
				} else {
					$image_urls = [$data];
				}

				$response = new Response(
					$query,
					$image_urls,
					is_string($data) ? [$data] : $data
				);
			} elseif ( $query instanceof MessageQuery ) {
				$data     = $this->api->create_message_completions( $query->to_request_body() );
				$response = new Response(
					$query,
					$data['choices'][0]['message'],
					$data
				);
			} else {
				throw new InvalidArgumentException( 'Unknown query type' );
			}
			
			UsageLogger::log( $response );
			return $response;
		} catch ( Exception $e ) {
			throw new RuntimeException( esc_html($e->getMessage()) );
		}
	}
}
