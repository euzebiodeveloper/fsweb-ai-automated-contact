<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Enqueues the widget assets and prints the chat markup in the footer. The
 * widget only ever talks to the plugin's own REST endpoints — provider keys
 * stay on the server.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/public
 * @author     Flow Sync Web <euzebio.batista@flowsyncweb.com>
 */
class Fsweb_Ai_Automated_Contact_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version
	 */
	private $version;

	/**
	 * Whether the widget should be printed on this request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private $should_render = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name The name of the plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Whether the widget is enabled and allowed on the current request.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_enabled() {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( 'yes' !== $settings['enabled'] ) {
			return false;
		}

		if ( ! fsweb_ai_automated_contact_license_is_active() ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
			return false;
		}

		/**
		 * Filter whether the chat widget is printed on this request.
		 *
		 * @since 1.0.0
		 * @param bool $render Whether to render the widget.
		 */
		return (bool) apply_filters( 'fsweb_aic_render_widget', true );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->should_render = true;

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/fsweb-ai-automated-contact-public.css',
			array(),
			$this->version,
			'all'
		);

		$accent = Fsweb_Ai_Automated_Contact_Settings::get( 'accent_color', '#2563eb' );

		wp_add_inline_style(
			$this->plugin_name,
			sprintf(
				'.fsweb-aic-widget{--fsweb-aic-accent:%1$s;} .fsweb-aic-launcher{background:%1$s;} .fsweb-aic-bubble--user{background:%1$s;} .fsweb-aic-send{background:%1$s;} .fsweb-aic-cta{background:%1$s;}',
				esc_html( $accent )
			)
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_render ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/fsweb-ai-automated-contact-public.js',
			array(),
			$this->version,
			true
		);

		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		wp_localize_script(
			$this->plugin_name,
			'FSWEB_Aic_Widget',
			array(
				'rest_url'    => esc_url_raw( rest_url( Fsweb_Ai_Automated_Contact_Rest::NAMESPACE_V1 ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'agent_name'  => $settings['agent_name'],
				'welcome'     => $settings['welcome_message'],
				'privacy'     => $settings['privacy_note'],
				'position'    => $settings['position'],
				'cart_url'    => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'i18n'        => array(
					'placeholder' => __( 'Escreva sua mensagem…', 'fsweb-ai-automated-contact' ),
					'send'        => __( 'Enviar', 'fsweb-ai-automated-contact' ),
					'close'       => __( 'Fechar', 'fsweb-ai-automated-contact' ),
					'open'        => __( 'Abrir atendimento', 'fsweb-ai-automated-contact' ),
					'reset'       => __( 'Nova conversa', 'fsweb-ai-automated-contact' ),
					'typing'      => __( 'digitando…', 'fsweb-ai-automated-contact' ),
					'error'       => __( 'Não consegui responder agora. Tente novamente em instantes.', 'fsweb-ai-automated-contact' ),
					'add_to_cart' => __( 'Adicionar ao carrinho', 'fsweb-ai-automated-contact' ),
					'go_checkout' => __( 'Finalizar compra', 'fsweb-ai-automated-contact' ),
					'view_product' => __( 'Ver produto', 'fsweb-ai-automated-contact' ),
					'cart_updated' => __( 'Carrinho atualizado', 'fsweb-ai-automated-contact' ),
				),
			)
		);
	}

	/**
	 * Print the widget markup in the footer.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function render_widget() {
		if ( ! $this->should_render ) {
			return;
		}

		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		require plugin_dir_path( __FILE__ ) . 'partials/fsweb-ai-automated-contact-widget.php';
	}
}
