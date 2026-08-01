<?php

namespace AiBuddy\OpenAi;

final class QueryFactory {
	public static function text( array $messages, array $params ): Query {
		$query = new TextQuery( $messages );
		self::apply_params( $query, $params );

		return $query;
	}

	public static function image( string $prompt, array $params ): Query {
		$query = new ImageQuery( $prompt );
		self::apply_params( $query, $params );

		return $query;
	}

	public static function message( array $message, array $params ): Query {
		$query = new MessageQuery( $message );
		self::apply_params( $query, $params );

		return $query;
	}

	private static function apply_params( Query $query, array $params ): void {
		foreach ( $params as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$method = 'set_' . $key;
			if ( method_exists( $query, $method ) ) {
				$query->$method( $value );
			}
		}
	}
}
