<?php

namespace AiBuddy\OpenAi;

use AiBuddy\AuthGate;
use DateTimeImmutable;
use Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Rest {

	private $api;

	public function __construct( Api $api ) {
		$this->api = $api;
	}

	public function register_rest_routes( string $namespace ) {
		register_rest_route(
			$namespace,
			'/openai/files',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'get_files' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'upload_file' ),
					'args'                => array(
						'data'     => array(
							'required' => true,
							'type'     => 'string',
						),
						'filename' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/files/(?P<file_id>.+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'get_file_content' ),
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'delete_file' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'openai/finetunes/(?P<finetune_id>.+)',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'cancel_finetune' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/finetunes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'get_finetunes' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( AuthGate::class, 'authorized' ),
					'callback'            => array( $this, 'create_finetune' ),
					'args'                => array(
						'file_id'     => array(
							'required' => true,
							'type'     => 'string',
						),
						'model'       => array(
							'required' => true,
							'type'     => 'string',
						),
						'suffix'      => array(
							'required' => true,
							'type'     => 'string',
						),
						'hyperparams' => array(
							'required' => false,
							'type'     => 'array',
						),
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/deleted_finetunes',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_deleted_finetunes' ),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/models',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_models' ),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/models/(?P<model_id>.+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_model' ),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/models/(?P<model_id>.+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'delete_finetune' ),
			)
		);
		register_rest_route(
			$namespace,
			'/openai/incidents',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( AuthGate::class, 'authorized' ),
				'callback'            => array( $this, 'get_incidents' ),
			)
		);
	}

	public function get_files(): WP_REST_Response {
		try {
			// If no API keys are available at all, return a helpful error
			if (!\AiBuddy\ApiManager::hasAnyApiKey()) {
				return new WP_REST_Response(
					array(
						'message' => 'No API keys are configured. Please add at least one API key in the plugin settings.',
					),
					400
				);
			}
			
			// Check if OpenAI API key exists - even if OpenAI key is missing
			if (!(\AiBuddy\ApiManager::hasProviderApiKey(\AiBuddy\ApiManager::PROVIDER_OPENAI))) {
				// If any other API key exists, return an empty files array instead of error
				if (\AiBuddy\ApiManager::hasAnyApiKey()) {
					return new WP_REST_Response(
						array(
							'files' => [],
						),
						200
					);
				}
			}
			
			$files = $this->api->get_files();

			return new WP_REST_Response(
				array(
					'files' => $files,
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

	public function get_finetunes(): WP_REST_Response {
		try {
			$finetunes = $this->api->get_finetunes();

			return new WP_REST_Response(
				array(
					'finetunes' => $finetunes,
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

	public function upload_file( WP_REST_Request $request ) {
		try {
			$file = $this->api->upload_file(
				new UploadFile(
					sanitize_text_field( $request->get_param( 'filename' ) ),
					$request->get_param( 'data' )
				),
				'fine-tune'
			);

			return new WP_REST_Response(
				array(
					'file' => $file,
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

	public function delete_file( WP_REST_Request $request ) {
		try {
			$this->api->delete_file( $request->get_param( 'file_id' ) );

			return new WP_REST_Response( array(), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function cancel_finetune( WP_REST_Request $request ) {
		try {
			$this->api->cancel_finetune( $request->get_param( 'fine_tune_id' ) );

			return new WP_REST_Response( array(), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function get_models( WP_REST_Request $request ) {
		try {
			$models = $this->api->get_models();

			return new WP_REST_Response(
				array(
					'models' => $models,
				)
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

	public function get_model( WP_REST_Request $request ) {
		try {
			$this->api->get_model( $request->get_param( 'model_id' ) );

			return new WP_REST_Response( array(), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function get_deleted_finetunes( WP_REST_Request $request ) {
		try {
			$deleted_finetunes = $this->api->get_deleted_finetunes();

			return new WP_REST_Response(
				array(
					'deleted_finetunes' => $deleted_finetunes,
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

	public function delete_finetune( WP_REST_Request $request ) {
		try {
			$this->api->delete_finetune( $request->get_param( 'model_id' ) );

			return new WP_REST_Response( array(), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	public function get_file_content( WP_REST_Request $request ) {
		try {
			$data = $this->api->get_file_content( $request->get_param( 'file_id' ) );

			return new WP_REST_Response(
				array(
					'file' => $data,
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

	public function create_finetune( WP_REST_Request $request ) {
		try {
			$finetune = $this->api->create_finetune(
				$request->get_param( 'file_id' ),
				$request->get_param( 'model' ),
				$request->get_param( 'suffix' ),
				$request->get_param( 'hyperparams' ),
			);

			return new WP_REST_Response(
				array(
					'finetune' => $finetune,
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

	public function get_incidents(): WP_REST_Response {
		try {
			$cache_key  = 'ai_buddy_openai_incidents';
			$cache_time = 10 * MINUTE_IN_SECONDS;
			$incidents  = get_transient( $cache_key );

			if ( ! $incidents ) {
				$incidents = $this->api->get_incidents( new DateTimeImmutable( '-1 week' ) );
				set_transient( $cache_key, $incidents, $cache_time );
			}

			return new WP_REST_Response(
				array(
					'incidents' => $incidents,
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

}
