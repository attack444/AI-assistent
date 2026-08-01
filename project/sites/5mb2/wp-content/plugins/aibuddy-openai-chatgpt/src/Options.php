<?php

namespace AiBuddy;

final class Options {
	private array $options;

	public function __construct( array $options ) {
		$this->options = $options;
	}

	public function get( $name, $default = null ) {
		return Arr::get( $this->options, $name, $default );
	}

	public function to_array(): array {
		return $this->options;
	}

	public function __get( $name ) {
		return $this->get( $name );
	}

	public function __set( $name, $value ) {
		$this->options[ $name ] = $value;
	}

	public function __isset( $name ) {
		return isset( $this->options[ $name ] );
	}
}
