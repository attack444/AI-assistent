<?php

namespace AiBuddy;

use AiBuddy\Chatbot\SessionStore;
use AiBuddy\Hooks\CommentHelper;

final class Plugin {
	public const REST_NAMESPACE = 'ai-buddy/v1';

	public string $basename;
	public string $entry_file;
	public string $root_dir;

	/**
	 * Used for prefixing options and hooks
	 */
	public string $slug;

	/**
	 * @var \AiBuddy\Options
	 */
	public Options $options;

	private Container $container;

	public function __construct( string $slug, string $entry_file ) {
		$this->basename   = plugin_basename( $entry_file );
		$this->entry_file = $entry_file;
		$this->root_dir   = dirname( $entry_file );
		$this->slug       = $slug;
		$this->options    = new Options( $this->get_options() );
		$this->container  = Container::create();
	}

	public function init(): void {
		if ( is_admin() ) {
			new Admin(
				$this->slug
			);
		} elseif ( $this->options->get( 'modules.chatbot', false ) && class_exists( Chatbot::class ) ) {
			new Chatbot(
				$this,
				self::REST_NAMESPACE,
				$this->options,
                $this->get(SessionStore::class),
				$this->slug
			);
		}

        if ( class_exists( CommentHelper::class ) ) {
            $commentHelper = new CommentHelper();
            $commentHelper->init($this->get( \AiBuddy\AiContentGenerator::class ));
        }
        $notice = new Notice();
        $notice->initNotice();
    }

	public function get_options(): array {
		return (array) get_option( $this->slug, array() );
	}

	public function update_options( array $options ): bool {
		$options = array_replace_recursive(
			$this->get_options(),
			$options
		);
		return update_option( $this->slug, $options, false );
	}

	public function update_option( string $option, $value ): bool {
		$options            = $this->get_options();
		$options[ $option ] = $value;

		return $this->update_options( $options );
	}

	/**
	 * @param string $option
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_option( string $option, $default = null ) {
		$options = $this->get_options();

		return $options[ $option ] ?? $default;
	}

	/**
	 * @template T
	 * @psalm-param class-string<T> $key
	 * @return T
	 */
	public function get( string $key ) {
		return $this->container->get( $key );
	}

	public function get_polished_post( $post ) {
		if ( is_object( $post ) ) {
			$post = (array) $post;
		}
		$content  = apply_filters( 'ai_buddy_pre_post_content', $post['post_content'], $post['ID'] );
		$content  = $this->polish_text( $content );
		$content  = apply_filters( 'ai_buddy_post_content', $content, $post['ID'] );
		$title    = $post['post_title'];
		$excerpt  = $post['post_excerpt'];
		$url      = get_permalink( $post['ID'] );
		$checksum = wp_hash( $content . $title . $url );
		return array(
			'postId'   => $post['ID'],
			'title'    => $title,
			'content'  => $content,
			'excerpt'  => $excerpt,
			'url'      => $url,
			'checksum' => $checksum,
		);
	}

	public function polish_text( string $raw_text ): string {
		$text = html_entity_decode( $raw_text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[\r\n]+/', "\n", $text );
		return $text . ' ';
	}

	public function remove_duplicate_sentences( string $text, $max_tokens = 512 ): string {
		$sentences        = preg_split( '/(?<=[.?!。．！？])+/u', $text );
		$hashes           = array();
		$unique_sentences = array();
		$length           = 0;
		foreach ( $sentences as $sentence ) {
			$sentence = preg_replace( '/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $sentence );
			$hash     = md5( $sentence );
			if ( ! in_array( $hash, $hashes ) ) {
					$tokens_count = apply_filters( 'ai_buddy_estimate_tokens', 0, $sentence );
				if ( $length + $tokens_count > $max_tokens ) {
					continue;
				}
				$hashes[]           = $hash;
				$unique_sentences[] = $sentence;
				$length            += $tokens_count;
			}
		}
		$polished_text = implode( ' ', $unique_sentences );
		$polished_text = preg_replace( '/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $polished_text );
		return $polished_text;
	}

}
