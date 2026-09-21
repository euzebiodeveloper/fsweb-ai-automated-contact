<?php

/**
 * REST endpoints consumed by the chat widget.
 *
 * Namespace: fsweb-aic/v1
 *
 * - GET  /config   Public, cache-safe widget configuration.
 * - POST /chat     One user message in, one agent reply out.
 * - POST /reset    Clears nothing server-side; exists for explicit UX resets.
 *
 * Every route is nonce-protected (wp_rest) and rate limited. Provider API keys
 * never leave the server: the browser only ever talks to these endpoints.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * REST controller.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_Rest {

	/**
	 * REST namespace.
	 */
	const NAMESPACE_V1 = 'fsweb-aic/v1';

	/**
	 * Chat service.
	 *
	 * @var Fsweb_Ai_Automated_Contact_Chat
	 */
	private $chat;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Fsweb_Ai_Automated_Contact_Chat|null $chat Optional chat service.
	 */
	public function __construct( $chat = null ) {
		$this->chat = $chat ? $chat : new Fsweb_Ai_Automated_Contact_Chat();
	}

	/**
	 * Register the routes.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_chat' ),
				'permission_callback' => array( $this, 'can_chat' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'history' => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_reset' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);
	}

	/**
	 * Whether the plugin is allowed to serve chat traffic.
	 *
	 * @since  1.0.0
	 * @return bool|\WP_Error
	 */
	public function can_chat() {
		if ( ! fsweb_ai_automated_contact_license_is_active() ) {
			return new WP_Error(
				'fsweb_aic_license_inactive',
				__( 'The virtual assistant is not active in this store.', 'fsweb-ai-automated-contact' ),
				array( 'status' => 403 )
			);
		}

		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( 'yes' !== $settings['enabled'] ) {
			return new WP_Error(
				'fsweb_aic_disabled',
				__( 'The virtual assistant is disabled.', 'fsweb-ai-automated-contact' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /config — public widget configuration.
	 *
	 * @since  1.0.0
	 * @return WP_REST_Response
	 */
	public function get_config() {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		return new WP_REST_Response(
			array(
				'enabled'    => ( 'yes' === $settings['enabled'] && fsweb_ai_automated_contact_license_is_active() ),
				'agent_name' => $settings['agent_name'],
				'welcome'    => $settings['welcome_message'],
				'privacy'    => $settings['privacy_note'],
				'position'   => $settings['position'],
				'color'      => $settings['accent_color'],
				'shop'       => array(
					'name'         => get_bloginfo( 'name' ),
					'cart_url'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
					'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				),
			),
			200
		);
	}

	/**
	 * POST /chat — one turn of the conversation.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function post_chat( WP_REST_Request $request ) {
		$allowed = $this->chat->check_rate_limit();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$message = (string) $request->get_param( 'message' );
		$history = (array) $request->get_param( 'history' );

		$result = $this->chat->respond( $message, $history );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /reset — acknowledges a client-side conversation reset.
	 *
	 * @since  1.0.0
	 * @return WP_REST_Response
	 */
	public function post_reset() {
		return new WP_REST_Response( array( 'reset' => true ), 200 );
	}
}
