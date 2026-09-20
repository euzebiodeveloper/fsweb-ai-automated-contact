<?php

/**
 * Provider-agnostic chat client with tool (function) calling support.
 *
 * Two wire formats are implemented:
 *
 * - "openai":     the /chat/completions shape used by OpenAI, DeepSeek,
 *                 xAI (Grok), Groq and any OpenAI-compatible endpoint.
 * - "anthropic":  the Anthropic Messages API.
 *
 * The chat service speaks one neutral message/tool format; this class is the
 * only place that knows how each provider encodes it. Adding a provider means
 * adding an entry to the settings catalogue, not touching the agent loop.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * Chat completions client.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_Ai_Client {

	/**
	 * Resolved endpoint configuration.
	 *
	 * @var array<string,string>
	 */
	private $endpoint;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array<string,string>|null $endpoint Optional endpoint override.
	 */
	public function __construct( $endpoint = null ) {
		$this->endpoint = is_array( $endpoint ) ? $endpoint : Fsweb_Ai_Automated_Contact_Settings::active_endpoint();
	}

	/**
	 * Whether the client has everything needed to make a request.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_ready() {
		return ( ! empty( $this->endpoint['api_key'] ) && ! empty( $this->endpoint['model'] ) && ! empty( $this->endpoint['base_url'] ) );
	}

	/**
	 * Run one completion round.
	 *
	 * @since  1.0.0
	 * @param  array  $messages   Neutral message list.
	 * @param  array  $tools      Neutral tool definitions.
	 * @param  string $system     System instruction (kept out of $messages).
	 * @return array{content:string,tool_calls:array}|\WP_Error
	 */
	public function complete( array $messages, array $tools = array(), $system = '' ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error(
				'fsweb_aic_not_configured',
				__( 'Nenhum provedor de IA configurado. Informe a chave da API nas configurações.', 'fsweb-ai-automated-contact' )
			);
		}

		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( 'anthropic' === $this->endpoint['api'] ) {
			$response = $this->request_anthropic( $messages, $tools, $system, $settings );
		} else {
			$response = $this->request_openai( $messages, $tools, $system, $settings );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * OpenAI-compatible request.
	 *
	 * @since  1.0.0
	 * @param  array $messages Neutral messages.
	 * @param  array $tools    Neutral tools.
	 * @param  string $system  System instruction.
	 * @param  array $settings Plugin settings.
	 * @return array|\WP_Error
	 */
	private function request_openai( array $messages, array $tools, $system, array $settings ) {
		$payload = array(
			'model'       => $this->endpoint['model'],
			'temperature' => (float) $settings['temperature'],
			'max_tokens'  => (int) $settings['max_tokens'],
			'messages'    => $this->openai_messages( $messages, $system ),
		);

		if ( ! empty( $tools ) ) {
			$payload['tools']       = $this->openai_tools( $tools );
			$payload['tool_choice'] = 'auto';
		}

		$response = wp_remote_post(
			$this->endpoint['base_url'] . '/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->endpoint['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$decoded = $this->decode( $response );

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$message = $decoded['choices'][0]['message'] ?? array();

		return array(
			'content'    => isset( $message['content'] ) ? (string) $message['content'] : '',
			'tool_calls' => $this->parse_openai_tool_calls( $message ),
		);
	}

	/**
	 * Anthropic Messages API request.
	 *
	 * @since  1.0.0
	 * @param  array $messages Neutral messages.
	 * @param  array $tools    Neutral tools.
	 * @param  string $system  System instruction.
	 * @param  array $settings Plugin settings.
	 * @return array|\WP_Error
	 */
	private function request_anthropic( array $messages, array $tools, $system, array $settings ) {
		$payload = array(
			'model'       => $this->endpoint['model'],
			'max_tokens'  => (int) $settings['max_tokens'],
			'temperature' => (float) $settings['temperature'],
			'messages'    => $this->anthropic_messages( $messages ),
		);

		if ( '' !== trim( (string) $system ) ) {
			$payload['system'] = $system;
		}

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $this->anthropic_tools( $tools );
		}

		$response = wp_remote_post(
			$this->endpoint['base_url'] . '/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $this->endpoint['api_key'],
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$decoded = $this->decode( $response );

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$content    = '';
		$tool_calls = array();

		foreach ( (array) ( $decoded['content'] ?? array() ) as $block ) {
			$type = isset( $block['type'] ) ? $block['type'] : '';

			if ( 'text' === $type ) {
				$content .= (string) ( $block['text'] ?? '' );
			} elseif ( 'tool_use' === $type ) {
				$tool_calls[] = array(
					'id'        => (string) ( $block['id'] ?? '' ),
					'name'      => (string) ( $block['name'] ?? '' ),
					'arguments' => isset( $block['input'] ) && is_array( $block['input'] ) ? $block['input'] : array(),
				);
			}
		}

		return array(
			'content'    => $content,
			'tool_calls' => $tool_calls,
		);
	}

	/**
	 * Decode an HTTP response into an array or a WP_Error.
	 *
	 * @since  1.0.0
	 * @param  array|\WP_Error $response Raw HTTP response.
	 * @return array|\WP_Error
	 */
	private function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'fsweb_aic_http_error',
				sprintf(
					/* translators: %s: transport error message. */
					__( 'Falha ao contatar o provedor de IA: %s', 'fsweb-ai-automated-contact' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'fsweb_aic_invalid_response',
				__( 'O provedor de IA retornou uma resposta inválida.', 'fsweb-ai-automated-contact' )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : '';

			if ( '' === $message && isset( $body['message'] ) && is_string( $body['message'] ) ) {
				$message = $body['message'];
			}

			return new WP_Error(
				'fsweb_aic_provider_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: provider message. */
					__( 'Provedor de IA retornou erro %1$d: %2$s', 'fsweb-ai-automated-contact' ),
					$code,
					$message ? $message : __( 'sem detalhes', 'fsweb-ai-automated-contact' )
				)
			);
		}

		return $body;
	}

	/**
	 * Convert neutral messages to the OpenAI shape.
	 *
	 * @since  1.0.0
	 * @param  array  $messages Neutral messages.
	 * @param  string $system   System instruction.
	 * @return array
	 */
	private function openai_messages( array $messages, $system ) {
		$out = array();

		if ( '' !== trim( (string) $system ) ) {
			$out[] = array(
				'role'    => 'system',
				'content' => $system,
			);
		}

		foreach ( $messages as $message ) {
			$role = isset( $message['role'] ) ? $message['role'] : 'user';

			if ( 'tool' === $role ) {
				$out[] = array(
					'role'         => 'tool',
					'tool_call_id' => isset( $message['tool_call_id'] ) ? $message['tool_call_id'] : '',
					'content'      => isset( $message['content'] ) ? (string) $message['content'] : '',
				);
				continue;
			}

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$calls = array();
				foreach ( (array) $message['tool_calls'] as $call ) {
					$calls[] = array(
						'id'       => isset( $call['id'] ) ? $call['id'] : '',
						'type'     => 'function',
						'function' => array(
							'name'      => isset( $call['name'] ) ? $call['name'] : '',
							'arguments' => wp_json_encode( isset( $call['arguments'] ) ? $call['arguments'] : array() ),
						),
					);
				}

				$out[] = array(
					'role'       => 'assistant',
					'content'    => isset( $message['content'] ) ? (string) $message['content'] : null,
					'tool_calls' => $calls,
				);
				continue;
			}

			$out[] = array(
				'role'    => in_array( $role, array( 'user', 'assistant' ), true ) ? $role : 'user',
				'content' => isset( $message['content'] ) ? (string) $message['content'] : '',
			);
		}

		return $out;
	}

	/**
	 * Convert neutral messages to the Anthropic shape.
	 *
	 * @since  1.0.0
	 * @param  array $messages Neutral messages.
	 * @return array
	 */
	private function anthropic_messages( array $messages ) {
		$out = array();

		foreach ( $messages as $message ) {
			$role = isset( $message['role'] ) ? $message['role'] : 'user';

			if ( 'tool' === $role ) {
				$out[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => isset( $message['tool_call_id'] ) ? $message['tool_call_id'] : '',
							'content'     => isset( $message['content'] ) ? (string) $message['content'] : '',
						),
					),
				);
				continue;
			}

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$blocks = array();

				if ( ! empty( $message['content'] ) ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => (string) $message['content'],
					);
				}

				foreach ( (array) $message['tool_calls'] as $call ) {
					$blocks[] = array(
						'type'  => 'tool_use',
						'id'    => isset( $call['id'] ) ? $call['id'] : '',
						'name'  => isset( $call['name'] ) ? $call['name'] : '',
						'input' => isset( $call['arguments'] ) && is_array( $call['arguments'] ) ? $call['arguments'] : array(),
					);
				}

				$out[] = array(
					'role'    => 'assistant',
					'content' => $blocks,
				);
				continue;
			}

			$out[] = array(
				'role'    => in_array( $role, array( 'user', 'assistant' ), true ) ? $role : 'user',
				'content' => isset( $message['content'] ) ? (string) $message['content'] : '',
			);
		}

		return $out;
	}

	/**
	 * Convert neutral tools to the OpenAI function shape.
	 *
	 * @since  1.0.0
	 * @param  array $tools Neutral tools.
	 * @return array
	 */
	private function openai_tools( array $tools ) {
		$out = array();

		foreach ( $tools as $tool ) {
			$out[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'],
					'description' => isset( $tool['description'] ) ? $tool['description'] : '',
					'parameters'  => isset( $tool['parameters'] ) ? $tool['parameters'] : array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			);
		}

		return $out;
	}

	/**
	 * Convert neutral tools to the Anthropic shape.
	 *
	 * @since  1.0.0
	 * @param  array $tools Neutral tools.
	 * @return array
	 */
	private function anthropic_tools( array $tools ) {
		$out = array();

		foreach ( $tools as $tool ) {
			$out[] = array(
				'name'         => $tool['name'],
				'description'  => isset( $tool['description'] ) ? $tool['description'] : '',
				'input_schema' => isset( $tool['parameters'] ) ? $tool['parameters'] : array(
					'type'       => 'object',
					'properties' => array(),
				),
			);
		}

		return $out;
	}

	/**
	 * Normalize OpenAI tool calls into the neutral format.
	 *
	 * @since  1.0.0
	 * @param  array $message Assistant message from the provider.
	 * @return array
	 */
	private function parse_openai_tool_calls( array $message ) {
		$calls = array();

		foreach ( (array) ( $message['tool_calls'] ?? array() ) as $call ) {
			$function = isset( $call['function'] ) ? $call['function'] : array();
			$args     = isset( $function['arguments'] ) ? json_decode( (string) $function['arguments'], true ) : array();

			$calls[] = array(
				'id'        => isset( $call['id'] ) ? (string) $call['id'] : '',
				'name'      => isset( $function['name'] ) ? (string) $function['name'] : '',
				'arguments' => is_array( $args ) ? $args : array(),
			);
		}

		return $calls;
	}
}
