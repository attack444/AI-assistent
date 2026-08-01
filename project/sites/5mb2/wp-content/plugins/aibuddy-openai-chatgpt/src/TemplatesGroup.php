<?php

namespace AiBuddy;

final class TemplatesGroup {
	public string $category;
	public array $templates;

	public function __construct( string $category, array $templates ) {
		$this->category  = $category;
		$this->templates = $templates;
	}

	public function to_array(): array {
		return array(
			'category'  => $this->category,
			'templates' => $this->templates,
		);
	}
}
