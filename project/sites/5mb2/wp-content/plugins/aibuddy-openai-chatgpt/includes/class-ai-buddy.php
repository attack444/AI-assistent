<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI_Buddy setup
 *
 * @package AI_Buddy
 * @since   1.0.0
 */

final class AI_Buddy {
	/**
	 * The single instance of the class.
	 *
	 * @var AI_Buddy
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Main AI_Buddy Instance.
	 *
	 * Ensures only one instance of AI_Buddy is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return AI_Buddy - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * AI_Buddy Constructor.
	 */
	public function __construct() {
		$this->includes();
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		/**
		 * Core classes.
		 */
		require AI_BUDDY_PATH . '/includes/class-ai-buddy-init.php';
	}
}
AI_Buddy::instance();
