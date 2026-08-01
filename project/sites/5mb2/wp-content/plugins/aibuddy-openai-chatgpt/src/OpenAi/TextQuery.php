<?php

namespace AiBuddy\OpenAi;

class TextQuery extends Query {
	public int $max_tokens    = 16;
	public $stop              = null;
	public float $temperature = 0.8;

	public function __construct( array $messages = array(), int $max_tokens = 16, string $model = Model::GPT_35_TURBO ) {
		$this->set_max_tokens( $max_tokens );
		$this->set_model( $model );
		$this->set_messages( $messages );
	}

	/**
	 * The maximum number of tokens to generate in the completion.
	 * The token count of your prompt plus max_tokens cannot exceed the model's context length.
	 * Most models have a context length of 2048 tokens (except for the newest models, which support 4096).
	 */
	public function set_max_tokens( int $max_tokens ) {
		$this->max_tokens = $max_tokens;
	}

	/**
	 * Set the sampling temperature to use. Higher values means the model will take more risks.
	 * Try 0.9 for more creative applications, and 0 for ones with a well-defined answer.
	 */
	public function set_temperature( float $temperature ) {
		if ( $temperature > 1 ) {
			$temperature = 1.0;
		}
		if ( $temperature < 0 ) {
			$temperature = 0.0;
		}
		$this->temperature = $temperature;
	}

	/**
	 * Up to 4 sequences where the API will stop generating further tokens.
	 * The returned text will not contain the stop sequence.
	 */
	public function set_stop( $stop ): void {
		if ( ! empty( $stop ) ) {
			$this->stop = $stop;
		}
	}

	public function to_request_body(): array {
		return array(
			'model'       => $this->model,
			'messages'    => $this->messages,
			'stop'        => $this->stop,
			'n'           => $this->max_results,
			'max_tokens'  => $this->max_tokens,
			'temperature' => $this->temperature,
		);
	}
}
