<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$chatbot_params = array(
	'id'                  => '',
	'mode'                => 'chat',
    'assistant'           => '',
	'context'             => 'Converse as if you were an AiBud WP Chat. Be friendly, creative.',
	'collect_emails_message' => 'Please enter your email address.',
	'chat_name'           => 'AiBud WP Chat',
	'start_sentence'      => 'Hello! I\'m AiBud WP Chat, ask me anything!',
	'text_send'           => 'Send',
	'text_clear'          => 'Clear',
	'text_placeholder'    => 'Type your message...',
	'style'               => 'chatgpt',
	'window'              => false,
	'icon'                => 'pro-aibuddy-chat-icon-1',
	'fullscreen'          => false,
	'casually_fine_tuned' => false,
	'prompt_ending'       => null,
	'completion_ending'   => null,
	'dark_mode'           => null,
	'model'               => \AiBuddy\OpenAi\Model::GPT_35_TURBO,
	'temperature'         => 0.8,
	'max_tokens'          => 1024,
	'max_results'         => 3,
);

return array(
	'extra_models'            => '',

	'languages'               => array(
		'en' => 'English',
		'es' => 'Spanish',
		'fr' => 'French',
		'de' => 'German',
		'it' => 'Italian',
		'pt' => 'Portuguese',
		'ru' => 'Russian',
		'ja' => 'Japanese',
		'zh' => 'Chinese',
	),

	'modules'                 => array(
		'chatbot'     => false,
		'excerpts'    => true,
		'titles'      => true,
		'images'      => true,
		'woocommerce' => true,
	),

    'chatbot' => array(
        'default_params'  => $chatbot_params,
        'formatting'      => true,
        'inject'          => false,
        'html'            => true,
        'params'          => $chatbot_params,
        'params_override' => false,
        'chat_styles'     => array(
            'spacing'           => '10',
            'font_size'         => '14',
            'border_radius'     => '10',
            'user_bubble_color' => '#2985F7',
            'bot_bubble_color'  => '#EAF2FE',
            'button_color'      => '#2985F7',
            'user_text_color'   => '#FFFFFF',
            'bot_text_color'    => '#1E2A36',
            'icon_background'   => '#2985F7',
        ),
    ),

	'generator'               => array(
		'titles'   => array(
			'prompt'  => 'Create a short SEO-friendly title for this article: %s. Must be between 40 and 60 characters, no URLs.',
			'results' => 5,
			'tokens'  => 64,
		),
		'excerpts' => array(
			'prompt'  => 'Create a SEO-friendly excerpt for this article %s. Must be between 120 and 160 characters, no URLs.',
			'results' => 5,
			'tokens'  => 160,
		),
		'images'   => array(
			'prompt'  => 'Please create an SEO-friendly image for the article %s. One image is sufficient.',
			'results' => 1,
			'tokens'  => 160,
		),
	),

	'openai'                  => array(
		'apikey'  => '',
		'pricing' => array(
			array(
				'model' => 'davinci',
				'price' => 0.02,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'curie',
				'price' => 0.002,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'babbage',
				'price' => 0.0005,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'ada',
				'price' => 0.0004,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'dall-e-2',
				'type'  => 'image',
				'unit'  => 1,
				'sizes' => array(
					array(
						'size'  => '1024x1024',
						'price' => 0.016, // actual
					),
					array(
						'size'  => '512x512',
						'price' => 0.018, // actual
					),
					array(
						'size'  => '256x256',
						'price' => 0.016, // actual
					),
				),
			),
			array(
				'model' => 'dall-e-3',
				'type'  => 'image',
				'unit'  => 1,
				'sizes' => array(
					array(
						'size'  => '1024x1024',
						'price' => 0.040, // default: standard quality
					),
					array(
						'size'  => '512x512',
						'price' => 0.030, // estimated (scaled down)
					),
					array(
						'size'  => '256x256',
						'price' => 0.020, // estimated (scaled down)
					),
				),
			),
			array(
				'model' => 'gpt-image-1',
				'type'  => 'image',
				'unit'  => 1,
				'sizes' => array(
					array(
						'size'  => '1024x1024',
						'price' => 0.042, // medium quality (best fit for "auto")
					),
					array(
						'size'  => '512x512',
						'price' => 0.032, // estimated (scaled down from 1024x1024 medium)
					),
					array(
						'size'  => '256x256',
						'price' => 0.022, // estimated (scaled down)
					),
				),
			),
			array(
				'model' => 'fn-davinci',
				'price' => 0.12,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'fn-curie',
				'price' => 0.012,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'fn-babbage',
				'price' => 0.0024,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
			array(
				'model' => 'fn-ada',
				'price' => 0.0016,
				'type'  => 'token',
				'unit'  => 1 / 1000,
			),
		),
	),
	'finetune_models'         => array(),
	'finetune_deleted_models' => array(),
);
