<?php

namespace AiBuddy\OpenAi;

final class MessageQuery extends Query {
	public array $messages = array();

	public function __construct( array $messages, string $model = Model::GPT_35_TURBO ) {
		$this->set_model( $model );
		$this->set_messages( $messages );
	}

	public function set_messages( array $messages ): void {
		$this->messages = $messages;
	}

	public function to_request_body(): array {
		return array(
			'model'    => $this->model,
			'messages' => $this->messages,
		);
	}
}
