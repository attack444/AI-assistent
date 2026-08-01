<?php

namespace AiBuddy\OpenAi;

abstract class Query {
	public int $max_results = 1;
	public string $model;
	public array $messages = array();
	public string $prompt;
	/**
	 * Model name
	 */
	public function set_model( string $model ): void {
		$this->model = $model;
	}

	public function set_messages( array $messages ): void {
		$this->messages = $messages;
	}
	/**
	 * For content generation
	 */
	public function set_prompt( string $prompt ): void {
		$this->prompt = $prompt;
	}
	/**
	 * How many completions to generate for each prompt.
	 * Because this parameter generates many completions, it can quickly consume your token quota.
	 * Use carefully and ensure that you have reasonable settings for max_tokens and stop.
	 */
	public function set_max_results( int $max_results ): void {
		$this->max_results = $max_results;
	}

	abstract public function to_request_body(): array;
}
