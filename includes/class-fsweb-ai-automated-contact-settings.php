<?php

/**
 * Settings schema, AI provider catalogue and option accessors.
 *
 * Single source of truth for every option the plugin reads or writes, so the
 * admin UI, the REST layer and the chat service never disagree about defaults
 * or about which providers/models are available.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * Settings and provider registry.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_Settings {

	/**
	 * Option name holding the whole settings array.
	 */
	const OPTION = 'fsweb_aic_settings';

	/**
	 * Provider catalogue.
	 *
	 * "api" is either "openai" (OpenAI-compatible /chat/completions) or
	 * "anthropic" (Messages API). Every entry carries a pre-selected model
	 * list; the merchant may always type a custom model instead.
	 *
	 * @since  1.0.0
	 * @return array<string,array<string,mixed>> Providers keyed by provider id.
	 */
	public static function providers() {
		$providers = array(
			'openai'    => array(
				'label'    => __( 'OpenAI (ChatGPT)', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.openai.com/v1',
				'keys_url' => 'https://platform.openai.com/api-keys',
				'models'   => array( 'gpt-5.4-mini', 'gpt-5.5', 'gpt-5.4', 'gpt-5.4-nano', 'gpt-5.2', 'gpt-5.1', 'gpt-5-mini', 'gpt-4.1-mini' ),
			),
			'anthropic' => array(
				'label'    => __( 'Anthropic (Claude)', 'fsweb-ai-automated-contact' ),
				'api'      => 'anthropic',
				'base_url' => 'https://api.anthropic.com/v1',
				'keys_url' => 'https://console.anthropic.com/settings/keys',
				'models'   => array( 'claude-sonnet-5', 'claude-haiku-4-5', 'claude-opus-5', 'claude-fable-5-1' ),
			),
			'deepseek'  => array(
				'label'    => __( 'DeepSeek', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.deepseek.com/v1',
				'keys_url' => 'https://platform.deepseek.com/api_keys',
				'models'   => array( 'deepseek-flash', 'deepseek-v4-pro' ),
			),
			'xai'       => array(
				'label'    => __( 'xAI (Grok)', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.x.ai/v1',
				'keys_url' => 'https://console.x.ai',
				'models'   => array( 'grok-4.6', 'grok-4.5', 'grok-4.3', 'grok-build-0.1' ),
			),
			'groq'      => array(
				'label'    => __( 'Groq', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.groq.com/openai/v1',
				'keys_url' => 'https://console.groq.com/keys',
				'models'   => array( 'openai/gpt-oss-120b', 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-20b', 'qwen/qwen3.8-27b', 'minimaxai/minimax-m2.7' ),
			),
			'custom'    => array(
				'label'    => __( 'Other (OpenAI-compatible)', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => '',
				'keys_url' => '',
				'models'   => array(),
			),
		);

		/**
		 * Filter the AI provider catalogue.
		 *
		 * @since 1.0.0
		 * @param array $providers Provider definitions.
		 */
		return (array) apply_filters( 'fsweb_aic_providers', $providers );
	}

	/**
	 * A single provider definition, or null when unknown.
	 *
	 * @since  1.0.0
	 * @param  string $id Provider id.
	 * @return array<string,mixed>|null
	 */
	public static function provider( $id ) {
		$providers = self::providers();
		$id        = sanitize_key( $id );

		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * Default context sent to the agent as business instructions.
	 *
	 * Ships pre-filled so a fresh install already has a well-behaved
	 * attendant instead of an empty prompt the merchant has to invent.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function default_context() {
		return __(
			"You are the virtual assistant for this store. Your job is to serve customers with politeness, clarity and focus, helping in three areas:\n" .
			"1. Store questions — delivery times, shipping, payment methods, returns and exchanges, opening hours.\n" .
			"2. Product search — find what the customer is looking for, show at most 5 options with name and price, and help them compare.\n" .
			"3. Cart and checkout — confirm the item and quantity before adding to the cart, then share the checkout link.\n" .
			"\n" .
			"How to behave:\n" .
			"- Greet, listen and answer exactly what was asked; do not change the subject.\n" .
			"- Rely only on this store's information and the available tools; never invent a product, price, deadline or condition.\n" .
			"- When you do not know something, say you will check and offer the store\'s human support.\n" .
			"- Never ask for passwords, card details or documents; never ask for sensitive data at all.\n" .
			"- Do not promise discounts, deadlines or conditions that are not written here.\n" .
			"- Keep a friendly, professional tone, with no slang and no emoji overload.\n" .
			"- Short, direct messages, like a WhatsApp conversation.\n" .
			"\n" .
			"Edit this text with your store's real information: delivery times and regions, shipping cost or free shipping, accepted payment methods, returns and exchange policy, opening hours and your human support channel.",
			'fsweb-ai-automated-contact'
		);
	}

	/**
	 * Default settings.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'           => 'no',
			'agent_name'        => __( 'Virtual Assistant', 'fsweb-ai-automated-contact' ),
			'welcome_message'   => __( 'Hi! I am the store\'s virtual assistant. I can answer questions and help you find a product. How can I help?', 'fsweb-ai-automated-contact' ),
			'provider'          => 'openai',
			'model'             => 'gpt-5.4-mini',
			'temperature'       => 0.3,
			'max_tokens'        => 800,
			'context'           => self::default_context(),
			'woo_enabled'       => 'yes',
			'product_limit'     => 5,
			'allow_add_to_cart' => 'yes',
			'position'          => 'right',
			'accent_color'      => '#2563eb',
			'privacy_note'      => __( 'Automated AI support. Do not send sensitive data.', 'fsweb-ai-automated-contact' ),
			'rate_limit'        => 30,
			'providers'         => array(
				'openai'    => array( 'api_key' => '' ),
				'anthropic' => array( 'api_key' => '' ),
				'deepseek'  => array( 'api_key' => '' ),
				'xai'       => array( 'api_key' => '' ),
				'groq'      => array( 'api_key' => '' ),
				'custom'    => array(
					'api_key'  => '',
					'base_url' => '',
					'model'    => '',
				),
			),
		);
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_merge( self::defaults(), $stored );

		// Nested provider credentials always keep every known key.
		$defaults_providers = self::defaults()['providers'];
		$stored_providers   = isset( $stored['providers'] ) && is_array( $stored['providers'] ) ? $stored['providers'] : array();
		$providers          = array();
		foreach ( $defaults_providers as $id => $credentials ) {
			$providers[ $id ] = array_merge( $credentials, isset( $stored_providers[ $id ] ) && is_array( $stored_providers[ $id ] ) ? $stored_providers[ $id ] : array() );
		}
		$settings['providers'] = $providers;

		return $settings;
	}

	/**
	 * A single setting value.
	 *
	 * @since  1.0.0
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Fallback when the key is absent.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Sanitize the whole settings array before it is stored.
	 *
	 * @since  1.0.0
	 * @param  mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		// Booleans stored as yes/no.
		foreach ( array( 'enabled', 'woo_enabled', 'allow_add_to_cart' ) as $flag ) {
			$output[ $flag ] = ( isset( $input[ $flag ] ) && 'yes' === $input[ $flag ] ) ? 'yes' : 'no';
		}

		$output['agent_name']      = isset( $input['agent_name'] ) ? sanitize_text_field( $input['agent_name'] ) : $defaults['agent_name'];
		$output['welcome_message'] = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : $defaults['welcome_message'];
		$output['privacy_note']    = isset( $input['privacy_note'] ) ? sanitize_textarea_field( $input['privacy_note'] ) : $defaults['privacy_note'];

		// The context is a free-form prompt: keep line breaks, strip tags.
		$output['context'] = isset( $input['context'] ) ? wp_kses_post( trim( (string) $input['context'] ) ) : '';

		// An empty context falls back to the shipped default so the agent
		// never runs without its role instructions.
		if ( '' === $output['context'] ) {
			$output['context'] = self::default_context();
		}

		$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : $defaults['provider'];
		$output['provider'] = self::provider( $provider ) ? $provider : $defaults['provider'];

		$output['model']   = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : $defaults['model'];
		$output['context'] = isset( $output['context'] ) ? $output['context'] : '';

		$temperature = isset( $input['temperature'] ) ? (float) $input['temperature'] : (float) $defaults['temperature'];
		$output['temperature'] = max( 0, min( 2, round( $temperature, 2 ) ) );

		$max_tokens = isset( $input['max_tokens'] ) ? absint( $input['max_tokens'] ) : (int) $defaults['max_tokens'];
		$output['max_tokens'] = max( 128, min( 8000, $max_tokens ) );

		$limit = isset( $input['product_limit'] ) ? absint( $input['product_limit'] ) : (int) $defaults['product_limit'];
		$output['product_limit'] = max( 1, min( 10, $limit ) );

		$rate_limit = isset( $input['rate_limit'] ) ? absint( $input['rate_limit'] ) : (int) $defaults['rate_limit'];
		$output['rate_limit'] = max( 1, min( 300, $rate_limit ) );

		$output['position'] = ( isset( $input['position'] ) && 'left' === $input['position'] ) ? 'left' : 'right';

		$color = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$output['accent_color'] = $color ? $color : $defaults['accent_color'];

		// Provider credentials.
		$output['providers'] = array();
		foreach ( self::defaults()['providers'] as $id => $credentials ) {
			$raw   = isset( $input['providers'][ $id ] ) && is_array( $input['providers'][ $id ] ) ? $input['providers'][ $id ] : array();
			$entry = $credentials;

			if ( isset( $raw['api_key'] ) ) {
				// Trim only — API keys are opaque and must not be mangled.
				$entry['api_key'] = trim( (string) $raw['api_key'] );
			}

			if ( 'custom' === $id ) {
				$base = isset( $raw['base_url'] ) ? esc_url_raw( trim( (string) $raw['base_url'] ) ) : '';
				// Only http(s) endpoints are accepted for the custom provider.
				$entry['base_url'] = $base && preg_match( '#^https?://#i', $base ) ? untrailingslashit( $base ) : '';
				$entry['model']    = isset( $raw['model'] ) ? sanitize_text_field( $raw['model'] ) : '';
			}

			$output['providers'][ $id ] = $entry;
		}

		return $output;
	}

	/**
	 * Resolved endpoint + key + model for the active configuration.
	 *
	 * @since  1.0.0
	 * @return array<string,string> Keys: provider, api, base_url, api_key, model.
	 */
	public static function active_endpoint() {
		$settings = self::all();
		$provider = $settings['provider'];
		$config   = self::provider( $provider );

		if ( ! $config ) {
			$provider = 'openai';
			$config   = self::provider( $provider );
		}

		$credentials = $settings['providers'][ $provider ];
		$base_url    = $config['base_url'];

		if ( 'custom' === $provider ) {
			$base_url = $credentials['base_url'];
		}

		$model = 'custom' === $provider && ! empty( $credentials['model'] )
			? $credentials['model']
			: $settings['model'];

		return array(
			'provider' => $provider,
			'api'      => $config['api'],
			'base_url' => untrailingslashit( $base_url ),
			'api_key'  => isset( $credentials['api_key'] ) ? $credentials['api_key'] : '',
			'model'    => $model,
		);
	}

	/**
	 * Whether the active configuration has everything it needs to run.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function is_configured() {
		$endpoint = self::active_endpoint();

		return ( ! empty( $endpoint['api_key'] ) && ! empty( $endpoint['model'] ) && ! empty( $endpoint['base_url'] ) );
	}
}
