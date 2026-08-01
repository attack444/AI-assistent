<?php

namespace AiBuddy\OpenAi;

final class Usage {
	public int $completion_tokens;
	public int $prompt_tokens;
	public int $total_tokens;

	public function __construct( int $completion_tokens, int $prompt_tokens, int $total_tokens ) {
		$this->completion_tokens = $completion_tokens;
		$this->prompt_tokens     = $prompt_tokens;
		$this->total_tokens      = $total_tokens;
	}
}
