<?php

namespace AiBuddy;

class Assistants {

	public static function add_meta_boxes( Plugin $plugin ): void {
		$plugin_info     = get_plugin_data( $plugin->entry_file );
		$general_setting = get_option( 'ai_buddy', array() );

		global $post;
		if ( null === $post || ( 'product' !== get_post_type( $post ) && 'post' !== get_post_type( $post ) && 'page' !== get_post_type( $post ) ) ) {
			return;
		}

		if ( isset($general_setting['modules']['woocommerce']) && $general_setting['modules']['woocommerce'] ) {
			add_meta_box(
				$plugin->slug . '-metadata',
				'AiBud WP',
				function () use ( $plugin, $plugin_info ) {
					require AI_BUDDY_PATH . '/includes/admin/meta-boxes/woocommerce.php';
				},
				'product',
				'side',
				'high'
			);
		}

		if ( (isset($general_setting['modules']['titles']) && $general_setting['modules']['titles']) || (isset($general_setting['modules']['excerpts']) && $general_setting['modules']['excerpts']) ) {
			add_meta_box(
				$plugin->slug . '-metadata',
				'AiBud WP',
				function () use ( $plugin, $plugin_info ) {
					require AI_BUDDY_PATH . '/includes/admin/meta-boxes/post.php';
				},
				'post',
				'side',
				'high'
			);
			add_meta_box(
				$plugin->slug . '-metadata',
				'AiBud WP',
				function () use ( $plugin, $plugin_info ) {
					require AI_BUDDY_PATH . '/includes/admin/meta-boxes/page.php';
				},
				'page',
				'side',
				'high'
			);
		}
	}
}
