<?php

namespace AiBuddy;

use AiBuddy\Chatbot\SessionStore;
use AiBuddy\Claude\Api as ClaudeAiApi;
use AiBuddy\Google\Api as GoogleAiApi;
use AiBuddy\OpenAi\Api as OpenAiApi;
use AiBuddy\OpenAi\AssistantApi;
use AiBuddy\OpenAi\ThreadApi;
use AiBuddy\OpenRouter\Api as OpenRouterApi;
use Parsedown;

class Container {

    /**
     * @var Closure[]
     */
    private array $classes;
    private Options $options;
    private static self $instance;

    public static function create(): Container {
        if ( empty( self::$instance ) ) {
            self::$instance = new self( new Options( get_option( AI_BUDDY_SLUG, [] ) ) );
        }

        return self::$instance;
    }

    public function __construct( Options $options ) {
        $this->options = $options;

        $this->classes = [
            AiContentGenerator::class          => function () {
                return new AiContentGenerator(
                    $this->get( OpenAiApi::class ),
                    $this->get( GoogleAiApi::class ),
                    $this->get( ClaudeAiApi::class ),
                    $this->get( OpenRouterApi::class ),
                );
            },
            OpenAiApi::class                   => function () {
                return new OpenAiApi( $this->get( Plugin::class ), $this->options->get( 'openai.apikey', '' ) );
            },
            GoogleAiApi::class                 => function () {
                return new GoogleAiApi( $this->get( Plugin::class ), $this->options->get( 'googleai.apikey', '' ) );
            },
            ClaudeAiApi::class                 => function () {
                return new ClaudeAiApi( $this->get( Plugin::class ), $this->options->get( 'claude.apikey', '' ) );
            },
            OpenRouterApi::class               => function () {
                return new OpenRouterApi( $this->get( Plugin::class ), $this->options->get( 'openrouter.apikey', '' ) );
            },
            Markdown::class                    => function () {
                return new Markdown( new Parsedown(), $this->options );
            },
            Notice::class                      => function () {
                return new Notice();
            },
            Rest::class                        => function () {
                return new Rest(
                    $this->get( Plugin::class ),
                    $this->get( \AiBuddy\AiContentGenerator::class ),
                    $this->get( OpenAiApi::class ),
                    $this->get( GoogleAiApi::class ),
                    $this->get( ClaudeAiApi::class ),
                    $this->get( OpenRouterApi::class ),
                    $this->get( \AiBuddy\Markdown::class ),
                    $this->get( Notice::class ),
                );
            },
            Chatbot\Rest::class                => function () {
                return new Chatbot\Rest(
                    $this->get( \AiBuddy\AiContentGenerator::class ),
                    $this->get( \AiBuddy\Markdown::class ),
                    $this->get( AssistantApi::class ),
                    $this->get( ThreadApi::class ),
                    $this->get( SessionStore::class ),
                    $this->options,
                );
            },
            Chatbot\Logs::class                => function () {
                return new Chatbot\Logs();
            },
            Hooks\AIBuddy_SEO_Details::class   => function () {
                return new Hooks\AIBuddy_SEO_Details(
                    $this->get( Plugin::class ),
                    $this->get( \AiBuddy\AiContentGenerator::class ),
                );
            },
            OpenAi\Rest::class                 => function () {
                return new OpenAi\Rest(
                    $this->get( OpenAiApi::class )
                );
            },
            AssistantApi::class                => function () {
                return new AssistantApi( $this->options->get( 'openai.apikey', '' ) );
            },
            ThreadApi::class                   => function () {
                return new ThreadApi( $this->options->get( 'openai.apikey', '' ) );
            },
            SessionStore::class => function () {
                return new SessionStore( $this->get( ThreadApi::class ), $this->get( Markdown::class ) );
            },
            Plugin::class                      => function () {
                return new Plugin( AI_BUDDY_SLUG, AI_BUDDY_FILE );
            },
            \AiBuddy\Chatbot\Assistants::class => function () {
                return new \AiBuddy\Chatbot\Assistants(
                    $this->get( AssistantApi::class )
                );
            },
        ];
    }

    public function get( $key ) {
        if ( ! isset( $this->classes[ $key ] ) ) {
            throw new \InvalidArgumentException( "No such key in container: " . esc_html($key) );
        }

        return $this->classes[$key]();
    }

}
