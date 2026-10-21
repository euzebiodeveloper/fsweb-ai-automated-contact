<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Enqueues the widget assets and prints the chat markup in the footer. The
 * widget only ever talks to the plugin's own REST endpoints — provider keys
 * stay on the server.
 *
 * Appearance is merchant-driven: icon, colours, position, auto-open and an
 * optional custom CSS block are all resolved here and handed to the browser as
 * CSS variables.
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
	 * Settings resolved once per request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private $settings = array();

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
		$this->settings    = Fsweb_Ai_Automated_Contact_Settings::all();
	}

	/**
	 * Icon markup for one of the built-in avatars.
	 *
	 * @since  1.0.0
	 * @param  string $type Icon id.
	 * @return string       Inline SVG (empty for "initials" and "custom").
	 */
	public static function icon_svg( $type ) {
		switch ( $type ) {
			case 'whatsapp':
				return '<svg class="fsweb-aic-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.5 0-2.97-.4-4.26-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.24 8.24-8.24s8.24 3.7 8.24 8.24-3.7 8.24-8.17 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.7-.8-.23-.08-.4-.12-.56.13-.17.25-.65.8-.8.96-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.39.11-.51.11-.11.29-.29.43-.44.14-.15.19-.25.29-.42.1-.17.05-.31-.02-.44-.08-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.42h-.48c-.17 0-.44.06-.67.31-.23.25-.87.85-.87 2.08 0 1.23.89 2.41 1.02 2.58.12.17 1.75 2.67 4.23 3.74.59.25 1.05.4 1.41.51.58.18 1.11.16 1.53.1.47-.07 1.44-.59 1.64-1.16.2-.57.2-1.05.14-1.16-.06-.1-.23-.17-.48-.29z"/></svg>';
			case 'telegram':
				return '<svg class="fsweb-aic-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.9 4.3 19 19.2c-.2 1-.8 1.2-1.6.8l-4.4-3.3-2.1 2c-.2.3-.4.4-.9.4l.3-4.5 8.2-7.4c.4-.3-.1-.5-.6-.2L7.8 13.2 3.4 11.8c-1-.3-1-1 .2-1.4l17-6.6c.8-.3 1.5.2 1.3 1.5z"/></svg>';
			case 'robot':
				return '<svg class="fsweb-aic-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3.5" y="8" width="17" height="12.5" rx="3.5"/><path d="M12 4.2V8"/><circle cx="12" cy="3.2" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="13" r="1.15" fill="currentColor" stroke="none"/><circle cx="15" cy="13" r="1.15" fill="currentColor" stroke="none"/><path d="M9.5 17.2h5"/></svg>';
			case 'chat':
			default:
				return '<svg class="fsweb-aic-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.6c0 4.6-4 8.3-8.9 8.3-1 0-2-.16-2.9-.45L4.2 21l1.35-4.2A8 8 0 0 1 3.2 11.6c0-4.6 4-8.3 8.9-8.3S21 7 21 11.6Z"/><circle cx="8.6" cy="11.6" r="1.1" fill="currentColor" stroke="none"/><circle cx="12.1" cy="11.6" r="1.1" fill="currentColor" stroke="none"/><circle cx="15.6" cy="11.6" r="1.1" fill="currentColor" stroke="none"/></svg>';
		}
	}

	/**
	 * Avatar markup for the chat header.
	 *
	 * @since  1.0.0
	 * @param  array $settings Plugin settings.
	 * @return string
	 */
	public static function avatar_markup( array $settings ) {
		$type = $settings['avatar_type'];

		if ( 'initials' === $type ) {
			$letter = function_exists( 'mb_substr' )
				? mb_substr( $settings['agent_name'], 0, 1 )
				: substr( $settings['agent_name'], 0, 1 );

			$markup = '<span class="fsweb-aic-initial">' . esc_html( $letter ) . '</span>';
		} else {
			$markup = self::icon_svg( $type );
		}

		/**
		 * Filter the avatar markup shown in the chat header.
		 *
		 * @since 1.0.0
		 * @param string $markup   Avatar HTML.
		 * @param array  $settings Current settings.
		 */
		return (string) apply_filters( 'fsweb_aic_avatar_markup', $markup, $settings );
	}

	/**
	 * Whether the widget is enabled and allowed on the current request.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_enabled() {
		$settings = $this->settings;

		if ( 'yes' !== $settings['enabled'] ) {
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

		// Version the asset by its modification time: without that, browsers keep
		// serving the previous CSS/JS after every update.
		$stylesheet = plugin_dir_path( __FILE__ ) . 'css/fsweb-ai-automated-contact-public.css';

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/fsweb-ai-automated-contact-public.css',
			array(),
			file_exists( $stylesheet ) ? (string) filemtime( $stylesheet ) : $this->version,
			'all'
		);

		$settings = $this->settings;

		$css = sprintf(
			'.fsweb-aic-widget{--fsweb-aic-accent:%1$s;--fsweb-aic-title:%2$s;--fsweb-aic-chat-bg:%3$s;--fsweb-aic-chat-text:%4$s;--fsweb-aic-agent-bubble:%5$s;}',
			esc_html( $settings['accent_color'] ),
			esc_html( $settings['color_title'] ),
			esc_html( $settings['chat_background'] ),
			esc_html( $settings['chat_text'] ),
			esc_html( $settings['bubble_agent'] )
		);

		/**
		 * Filter the inline CSS printed with the widget.
		 *
		 * @since 1.0.0
		 * @param string $css      Inline CSS.
		 * @param array  $settings Current settings.
		 */
		$css = (string) apply_filters( 'fsweb_aic_inline_css', $css, $settings );

		if ( '' !== $css ) {
			wp_add_inline_style( $this->plugin_name, $css );
		}
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

		$script = plugin_dir_path( __FILE__ ) . 'js/fsweb-ai-automated-contact-public.js';

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/fsweb-ai-automated-contact-public.js',
			array(),
			file_exists( $script ) ? (string) filemtime( $script ) : $this->version,
			true
		);

		$settings = $this->settings;

		$data = array(
				'rest_url'     => esc_url_raw( rest_url( Fsweb_Ai_Automated_Contact_Rest::NAMESPACE_V1 ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'agent_name'   => $settings['agent_name'],
				'welcome'      => $settings['welcome_message'],
				'privacy'      => $settings['privacy_note'],
				'position'     => $settings['position'],
				'avatar_type'  => $settings['avatar_type'],
				'cart_url'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'i18n'         => array(
					'placeholder'  => __( 'Type your message…', 'fsweb-ai-automated-contact' ),
					'send'         => __( 'Send', 'fsweb-ai-automated-contact' ),
					'close'        => __( 'Close', 'fsweb-ai-automated-contact' ),
					'open'         => __( 'Open chat', 'fsweb-ai-automated-contact' ),
					'reset'        => __( 'New conversation', 'fsweb-ai-automated-contact' ),
					'typing'       => __( 'typing…', 'fsweb-ai-automated-contact' ),
					'error'        => __( 'I could not answer right now. Please try again in a moment.', 'fsweb-ai-automated-contact' ),
					'add_to_cart'  => __( 'Add to cart', 'fsweb-ai-automated-contact' ),
					/* translators: %s: product id. */
					'add_request'  => __( 'Please add product id %s to my cart', 'fsweb-ai-automated-contact' ),
					'go_checkout'  => __( 'Checkout', 'fsweb-ai-automated-contact' ),
					'view_product' => __( 'View product', 'fsweb-ai-automated-contact' ),
					'cart_updated' => __( 'Cart updated', 'fsweb-ai-automated-contact' ),
					'out_of_stock' => __( 'Out of stock', 'fsweb-ai-automated-contact' ),
			),
		);

		/**
		 * Filter the data handed to the widget script.
		 *
		 * @since 1.0.0
		 * @param array $data     Script data.
		 * @param array $settings Current settings.
		 */
		$data = (array) apply_filters( 'fsweb_aic_script_data', $data, $settings );

		wp_localize_script( $this->plugin_name, 'FSWEB_Aic_Widget', $data );
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

		$settings = $this->settings;

		require plugin_dir_path( __FILE__ ) . 'partials/fsweb-ai-automated-contact-widget.php';
	}
}
