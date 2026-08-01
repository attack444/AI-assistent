<?php

namespace AiBuddy;

class Compatability {
	public function check(): void {
		if ( class_exists( 'LiteSpeed\Core' ) ) {
			$this->check_litespeed();
		}
	}

	/**
	 * Checks litespeed compatibility
	 */
	public function check_litespeed(): void {
		// disable caching if enabled
		// todo: add notice instead of silently disabling it
		if ( get_option( 'litespeed.conf.cache-rest' ) ) {
			update_option( 'litespeed.conf.cache-rest', 0 );
		}
	}
}
