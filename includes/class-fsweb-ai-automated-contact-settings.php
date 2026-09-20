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
				'models'   => array( 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4o', 'o4-mini' ),
			),
			'anthropic' => array(
				'label'    => __( 'Anthropic (Claude)', 'fsweb-ai-automated-contact' ),
				'api'      => 'anthropic',
				'base_url' => 'https://api.anthropic.com/v1',
				'keys_url' => 'https://console.anthropic.com/settings/keys',
				'models'   => array( 'claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-1', 'claude-3-7-sonnet-latest' ),
			),
			'deepseek'  => array(
				'label'    => __( 'DeepSeek', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.deepseek.com/v1',
				'keys_url' => 'https://platform.deepseek.com/api_keys',
				'models'   => array( 'deepseek-chat', 'deepseek-reasoner' ),
			),
			'xai'       => array(
				'label'    => __( 'xAI (Grok)', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.x.ai/v1',
				'keys_url' => 'https://console.x.ai',
				'models'   => array( 'grok-4', 'grok-3', 'grok-3-mini' ),
			),
			'groq'      => array(
				'label'    => __( 'Groq', 'fsweb-ai-automated-contact' ),
				'api'      => 'openai',
				'base_url' => 'https://api.groq.com/openai/v1',
				'keys_url' => 'https://console.groq.com/keys',
				'models'   => array( 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-120b', 'qwen/qwen3-32b' ),
			),
			'custom'    => array(
				'label'    => __( 'Outro (compatível com OpenAI)', 'fsweb-ai-automated-contact' ),
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
			"Você é o atendente virtual desta loja. Seu papel é atender com educação, clareza e objetividade, ajudando em três frentes:\n" .
			"1. Dúvidas sobre a loja — prazos de entrega, frete, formas de pagamento, trocas e devoluções, horário de atendimento.\n" .
			"2. Busca de produtos — encontre o que o cliente procura, apresente no máximo 5 opções com nome e preço e ajude a comparar.\n" .
			"3. Carrinho e finalização — confirme item e quantidade antes de adicionar ao carrinho e envie o link para finalizar a compra.\n" .
			"\n" .
			"Como agir no atendimento:\n" .
			"- Cumprimente, escute e responda exatamente o que foi perguntado; não fuja do assunto.\n" .
			"- Baseie-se apenas nas informações desta loja e nas ferramentas disponíveis; não invente produto, preço, prazo ou condição.\n" .
			"- Quando não souber algo, diga que vai verificar e ofereça o atendimento humano da loja.\n" .
			"- Nunca peça senha, dados de cartão ou documentos; nunca peça dados sensíveis em geral.\n" .
			"- Não prometa descontos, prazos ou condições que não estejam escritos aqui.\n" .
			"- Mantenha o tom cordial e profissional, sem gírias e sem excesso de emojis.\n" .
			"- Mensagens curtas e diretas, como numa conversa de WhatsApp.\n" .
			"\n" .
			"Ajuste este texto com as informações reais da sua loja: prazos e regiões de entrega, valor do frete ou frete grátis, formas de pagamento aceitas, política de trocas e devoluções, horário de atendimento e canal de atendimento humano.",
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
			'agent_name'        => __( 'Atendente Virtual', 'fsweb-ai-automated-contact' ),
			'welcome_message'   => __( 'Olá! Sou o atendente virtual da loja. Posso tirar dúvidas e ajudar você a encontrar um produto. Como posso ajudar?', 'fsweb-ai-automated-contact' ),
			'provider'          => 'openai',
			'model'             => 'gpt-4.1-mini',
			'temperature'       => 0.3,
			'max_tokens'        => 800,
			'context'           => self::default_context(),
			'woo_enabled'       => 'yes',
			'product_limit'     => 5,
			'allow_add_to_cart' => 'yes',
			'position'          => 'right',
			'accent_color'      => '#2563eb',
			'privacy_note'      => __( 'Atendimento automatizado por IA. Não envie dados sensíveis.', 'fsweb-ai-automated-contact' ),
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
