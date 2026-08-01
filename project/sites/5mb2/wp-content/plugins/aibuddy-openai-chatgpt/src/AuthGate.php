<?php

namespace AiBuddy;

final class AuthGate {
	public static function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	public static function authorized() {
		return current_user_can( 'editor' ) || current_user_can( 'administrator' );
	}
}
