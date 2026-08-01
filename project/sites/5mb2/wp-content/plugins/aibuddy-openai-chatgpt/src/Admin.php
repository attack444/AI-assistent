<?php

namespace AiBuddy;

class Admin {
	private string $plugin_slug;

	public function __construct( $plugin_slug ) {
		$this->plugin_slug = $plugin_slug;

		if ( is_admin() ) {
			( new Compatability() )->check();

			if ( AuthGate::authorized() ) {
				//add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
			}
		}
	}

	public static function plugin_actions( $links, $plugin_info, $slug ): array {
        /* translators: %s is replaced with the plugin slug */
		return array_merge(	$links,	array('settings' => sprintf(__( '<a href="admin.php?page=%s_settings">Settings</a>', 'aibuddy-openai-chatgpt'), $slug) ) );
	}

	public function post_row_actions( $actions, $post ) {
		if ( 'post' === $post->post_type ) {
			$actions['ai_titles'] = sprintf(
				'<a class="%s-link-title" href="#" data-id="%s" data-title="%s">' .
				'<span class="dashicons dashicons-update"></span> Generate Titles</a>',
				$this->plugin_slug,
				$post->ID,
				$post->post_title
			);
		}

		return $actions;
	}
}
