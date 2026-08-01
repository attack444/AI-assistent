<?php

namespace AiBuddy\OpenAi;

final class PriceCalculator {
	private $pricing;

	public function __construct( array $pricing ) {
		$this->pricing = $pricing;
	}

	public function calculate( Response $response ) {
		$query = $response->query;

		if ( $query instanceof TextQuery ) {
			$model = $query->model;
			// Finetuned models
			if ( preg_match( '/^([a-zA-Z]{0,32}):/', $model, $matches ) ) {
				$model = 'fn-' . $matches[1];
			} elseif ( preg_match( '/^text-(\w+)-\d+/', $model, $matches ) ) {
				// Standard models
				$model = $matches[1];
			}
			if ( empty( $model ) ) {
				return null;
			}
			return $this->total( $model, $response->raw['usage']['total_tokens'] );
		}

		if ( $query instanceof ImageQuery ) {
			return $this->total( $query->model, count( $response->results ), $query->size );
		}

		throw new \RuntimeException( 'Unknown query type.' );
	}


	private function total( $model, $units, $option = null ) {

		$pricing = \AiBuddy\Arr::first(
			$this->pricing,
			function( $pricing ) use ( $model ) {
				return $pricing['model'] === $model;
			}
		);

		if ( null === $pricing ) {
			return null;
		}

		if ( 'image' === $pricing['type'] ) {
			if ( ! $option ) {

				return null;
			}
			foreach ( $pricing['sizes'] as $type ) {
				if ( $type['size'] === $option ) {
					return $type['price'] * $units;
				}
			}
		}

		return $pricing['price'] * $pricing['unit'] * $units;
	}
}
