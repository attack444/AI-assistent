<?php

namespace AiBuddy;

/**
 * Slice of Laravel's Arr class.
 */
final class Arr {

	public static function first( iterable $array, callable $callback = null, $default = null ) {
		if ( is_null( $callback ) ) {
			if ( empty( $array ) ) {
				return $default;
			}

			foreach ( $array as $item ) {
				return $item;
			}
		}

		foreach ( $array as $key => $value ) {
			if ( call_user_func( $callback, $value, $key ) ) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Get an item from an array using "dot" notation.
	 *
	 * @param  \ArrayAccess|array  $array
	 * @param  string|int|null  $key
	 * @param  mixed  $default
	 * @return mixed
	 */
	public static function get( $array, $key, $default = null ) {
		if ( ! self::accessible( $array ) ) {
			return $default;
		}

		if ( is_null( $key ) ) {
			return $array;
		}

		if ( self::exists( $array, $key ) ) {
			return $array[ $key ];
		}

		if ( strpos( $key, '.' ) === false ) {
			return $array[ $key ] ?? $default;
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( self::accessible( $array ) && self::exists( $array, $segment ) ) {
				$array = $array[ $segment ];
			} else {
				return $default;
			}
		}

		return $array;
	}


	/**
	 * Determine whether the given value is array accessible.
	 *
	 * @param  mixed  $value
	 * @return bool
	 */
	public static function accessible( $value ) {
		return is_array( $value ) || $value instanceof ArrayAccess;
	}

	/**
	 * Determine if the given key exists in the provided array.
	 *
	 * @param  \ArrayAccess|array  $array
	 * @param  string|int  $key
	 * @return bool
	 */
	public static function exists( $array, $key ) {
		if ( $array instanceof Enumerable ) {
			return $array->has( $key );
		}

		if ( $array instanceof ArrayAccess ) {
			return $array->offsetExists( $key );
		}

		if ( is_float( $key ) ) {
			$key = (string) $key;
		}

		return array_key_exists( $key, $array );
	}
}
