<?php

namespace AiBuddy;

use Parsedown;

class Markdown {

	private Parsedown $parsedown;
    private Options $options;

    public function __construct( Parsedown $parsedown, Options $options) {
		$this->parsedown = $parsedown;
        $this->options = $options;
	}

	public function to_html( string $text ): string {
		if($this->should_format()) {
            $text = $this->cleanup_sources($text);
            return $this->parsedown->text( $text );
        }

        return $text;
	}

    public function should_format(): bool {
        return $this->options->get( 'chatbot.formatting', false );
    }

    public function cleanup_sources( string $text ): string {
        $re = '/【.*?】/mu';

        return preg_replace( $re, '', $text );
    }
}
