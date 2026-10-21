<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Owns the settings screen, the PRO upgrade page, the tutorial and the
 * provider connection test. All rendering escapes on output; all input is
 * sanitized by the settings sanitize callback or by the AJAX handlers.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/admin
 * @author     Flow Sync Web <euzebio.batista@flowsyncweb.com>
 */
class Fsweb_Ai_Automated_Contact_Admin {

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
	 * Hook suffix of the settings screen.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $settings_page_hook = '';

	/**
	 * Hook suffix of the conversations screen.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $conversations_page_hook = '';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name The name of this plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Whether the PRO add-on is active on this site.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_pro_active() {
		return defined( 'FSWEB_AIC_PRO_VERSION' ) || class_exists( 'Fsweb_Aic_Pro_Settings' );
	}

	/**
	 * Warn the merchant when WooCommerce is inactive.
	 *
	 * The chat keeps answering store questions without WooCommerce; only the
	 * catalogue tools (search, product details, cart and checkout) stay off.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || false === strpos( (string) $screen->id, 'fsweb-ai-automated-contact' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'FSWEB AI Automated Contact: WooCommerce is not active. The assistant still answers store questions, but product search, product details, cart and checkout are unavailable until WooCommerce is activated.',
				'fsweb-ai-automated-contact'
			)
		);
	}

	// ------------------------------------------------------------------//
	//  Assets
	// ------------------------------------------------------------------//

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( ! $this->is_plugin_style_screen( $hook_suffix ) ) {
			return;
		}

		// Version by modification time so the admin never runs on cached assets.
		$stylesheet = plugin_dir_path( __FILE__ ) . 'css/fsweb-ai-automated-contact-admin.css';

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/fsweb-ai-automated-contact-admin.css',
			array(),
			file_exists( $stylesheet ) ? (string) filemtime( $stylesheet ) : $this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		// Needed by the avatar picker on the settings screen.
		wp_enqueue_media();

		$script = plugin_dir_path( __FILE__ ) . 'js/fsweb-ai-automated-contact-admin.js';

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/fsweb-ai-automated-contact-admin.js',
			array( 'jquery' ),
			file_exists( $script ) ? (string) filemtime( $script ) : $this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'FSWEB_Aic_Admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fsweb_aic_admin_action' ),
				'providers' => $this->providers_for_js(),
				'default_context' => Fsweb_Ai_Automated_Contact_Settings::default_context(),
				'avatar_icons'    => array(
					'chat'     => Fsweb_Ai_Automated_Contact_Public::icon_svg( 'chat' ),
					'initials' => '<span class="fsweb-aic-initial">' . esc_html( $this->agent_initial() ) . '</span>',
				),
				'i18n'     => array(
					'activating'         => __( 'Activating…', 'fsweb-ai-automated-contact' ),
					'deactivating'       => __( 'Deactivating…', 'fsweb-ai-automated-contact' ),
					'testing'            => __( 'Testing…', 'fsweb-ai-automated-contact' ),
					'custom_model'       => __( 'Other model (type it)', 'fsweb-ai-automated-contact' ),
					/* translators: 1: AI provider name, 2: model name. */
					'current_setup'      => __( 'Current setup: %1$s / %2$s', 'fsweb-ai-automated-contact' ),
					'network_error'      => __( 'Network error. Please try again.', 'fsweb-ai-automated-contact' ),
					'test_now'           => __( 'Test now', 'fsweb-ai-automated-contact' ),
				),
			)
		);
	}

	/**
	 * First letter of the assistant name, for the preview avatar.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function agent_initial() {
		$name = (string) Fsweb_Ai_Automated_Contact_Settings::get( 'agent_name', '' );

		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
	}

	/**
	 * Provider catalogue shaped for the admin script.
	 *
	 * @since  1.0.0
	 * @return array<string,array<string,mixed>>
	 */
	private function providers_for_js() {
		$out = array();

		foreach ( Fsweb_Ai_Automated_Contact_Settings::providers() as $id => $provider ) {
			$out[ $id ] = array(
				'label'  => $provider['label'],
				'models' => array_values( $provider['models'] ),
			);
		}

		return $out;
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_screen( $hook_suffix ) {
		return ( $this->settings_page_hook === $hook_suffix )
			|| ( $this->conversations_page_hook === $hook_suffix )
			|| $this->is_tutorial_page();
	}

	// ------------------------------------------------------------------//
	//  Menu
	// ------------------------------------------------------------------//

	/**
	 * Register the top-level menu, PRO and Tutorial screens.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		$this->settings_page_hook = add_menu_page(
			__( 'AI Contact', 'fsweb-ai-automated-contact' ),
			__( 'AI Contact', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);

		$this->conversations_page_hook = add_submenu_page(
			'fsweb-ai-automated-contact',
			__( 'AI Contact — Conversations', 'fsweb-ai-automated-contact' ),
			__( 'Conversations', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact-conversations',
			array( $this, 'render_conversations_page' )
		);
	}

	/**
	 * Whether the current admin screen is the tutorial page.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_tutorial_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		return isset( $_GET['page'] ) && 'fsweb-ai-automated-contact-tutorial' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Whether the shared admin stylesheet belongs on this screen.
	 *
	 * The PRO add-on lives under the same menu and uses the same hero, cards
	 * and form controls, so its screen needs this stylesheet too.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_style_screen( $hook_suffix ) {
		return $this->is_plugin_screen( $hook_suffix )
			|| false !== strpos( (string) $hook_suffix, 'fsweb-ai-automated-contact' );
	}


	// ------------------------------------------------------------------//
	//  Settings API
	// ------------------------------------------------------------------//

	/**
	 * Register the settings option and its sanitize callback.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'fsweb_aic_settings_group',
			Fsweb_Ai_Automated_Contact_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Fsweb_Ai_Automated_Contact_Settings', 'sanitize' ),
				'default'           => Fsweb_Ai_Automated_Contact_Settings::defaults(),
			)
		);
	}

	// ------------------------------------------------------------------//
	//  Screens
	// ------------------------------------------------------------------//

	/**
	 * Render the settings screen (provider, context, WooCommerce, appearance).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		$settings     = Fsweb_Ai_Automated_Contact_Settings::all();
		$providers    = Fsweb_Ai_Automated_Contact_Settings::providers();
		$endpoint     = Fsweb_Ai_Automated_Contact_Settings::active_endpoint();
		$option       = Fsweb_Ai_Automated_Contact_Settings::OPTION;
		$woo          = class_exists( 'WooCommerce' );
		$tutorial_url = admin_url( 'admin.php?page=fsweb-ai-automated-contact-tutorial' );
		$pro_url      = FSWEB_AI_AUTOMATED_CONTACT_PRO_URL;

		$avatar_type   = isset( $settings['avatar_type'] ) ? $settings['avatar_type'] : 'chat';
		$avatar_letter = function_exists( 'mb_substr' ) ? mb_substr( (string) $settings['agent_name'], 0, 1 ) : substr( (string) $settings['agent_name'], 0, 1 );
		$preview_icon  = ( 'initials' === $avatar_type )
			? '<span class="fsweb-aic-initial">' . esc_html( $avatar_letter ) . '</span>'
			: Fsweb_Ai_Automated_Contact_Public::icon_svg( $avatar_type );

		/** This filter is documented in public/partials/fsweb-ai-automated-contact-widget.php */
		$preview_icon = (string) apply_filters( 'fsweb_aic_launcher_icon', $preview_icon, $settings );

		$totals            = Fsweb_Ai_Automated_Contact_Conversations::totals();
		$conversations_url = admin_url( 'admin.php?page=fsweb-ai-automated-contact-conversations' );
		?>
		<div class="wrap fsweb-aic-wrap fsweb-aic-wrap--has-preview">
			<hr class="wp-header-end">

			<div class="fsweb-aic-hero fsweb-aic-hero--split">
					<div>
						<h1><?php esc_html_e( 'AI Contact', 'fsweb-ai-automated-contact' ); ?></h1>
						<p><?php esc_html_e( 'Configure the virtual assistant, pick the AI, write the store context and tune the widget.', 'fsweb-ai-automated-contact' ); ?></p>
					</div>
				<div class="fsweb-aic-hero__actions">
					<a class="button" href="<?php echo esc_url( $tutorial_url ); ?>">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Tutorial', 'fsweb-ai-automated-contact' ); ?>
					</a>
				</div>
			</div>

			<div class="fsweb-aic-stats">
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Assistant', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo 'yes' === $settings['enabled'] ? 'is-on' : 'is-off'; ?>">
						<?php echo 'yes' === $settings['enabled'] ? esc_html__( 'Live on the site', 'fsweb-ai-automated-contact' ) : esc_html__( 'Disabled', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Artificial Intelligence', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo Fsweb_Ai_Automated_Contact_Settings::is_configured() ? 'is-on' : 'is-off'; ?>">
						<?php echo Fsweb_Ai_Automated_Contact_Settings::is_configured() ? esc_html__( 'Configured', 'fsweb-ai-automated-contact' ) : esc_html__( 'Not configured', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'WooCommerce', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo $woo ? 'is-on' : 'is-off'; ?>">
						<?php echo $woo ? esc_html__( 'Active', 'fsweb-ai-automated-contact' ) : esc_html__( 'Missing', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Spent on AI', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( Fsweb_Ai_Automated_Contact_Conversations::format_cost( $totals['cost'] ) ); ?></span>
					<a href="<?php echo esc_url( $conversations_url ); ?>" class="description">
						<?php
						printf(
							/* translators: %s: number of stored conversations. */
							esc_html__( '%s conversations', 'fsweb-ai-automated-contact' ),
							esc_html( number_format_i18n( $totals['conversations'] ) )
						);
						?>
					</a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( 'fsweb_aic_settings_group' ); ?>

				<div class="fsweb-aic-grid fsweb-aic-grid--cards">
					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-format-chat"></span>
								<?php esc_html_e( 'Assistant', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Enable widget', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?>>
											<?php esc_html_e( 'Show the chat on the site', 'fsweb-ai-automated-contact' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Debug log', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[debug_log]" value="yes" <?php checked( 'yes', $settings['debug_log'] ); ?>>
											<?php esc_html_e( 'Record cart and provider diagnostics', 'fsweb-ai-automated-contact' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'The entries show up in WooCommerce → Status → Logs, under the source "fsweb-ai-automated-contact". Leave it off on a live store.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-lightbulb"></span>
								<?php esc_html_e( 'Artificial Intelligence', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<div class="notice notice-info inline">
								<p><strong><?php esc_html_e( 'The provider is yours.', 'fsweb-ai-automated-contact' ); ?></strong> <?php esc_html_e( 'This plugin does not include AI credits, resell tokens or share a key. Create the account with the provider, generate the API key and pay the provider directly. Keys are stored only on this site and are never sent to the visitor\'s browser.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fsweb-aic-provider"><?php esc_html_e( 'Provider', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<select id="fsweb-aic-provider" name="<?php echo esc_attr( $option ); ?>[provider]" class="fsweb-aic-provider">
											<?php foreach ( $providers as $id => $provider ) : ?>
												<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['provider'], $id ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-model-select"><?php esc_html_e( 'Model', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<select id="fsweb-aic-model-select" class="fsweb-aic-model-select"></select>

										<div class="fsweb-aic-model-custom" id="fsweb-aic-model-custom" hidden>
											<label class="screen-reader-text" for="fsweb-aic-model"><?php esc_html_e( 'Model name', 'fsweb-ai-automated-contact' ); ?></label>
											<input type="text" class="regular-text" id="fsweb-aic-model"
												name="<?php echo esc_attr( $option ); ?>[model]"
												value="<?php echo esc_attr( $settings['model'] ); ?>"
												placeholder="<?php esc_attr_e( 'e.g. gpt-5.4-mini', 'fsweb-ai-automated-contact' ); ?>"
												autocomplete="off">
										</div>

										<p class="description"><?php esc_html_e( 'The list shows the current models of the selected provider. To use a specific or brand-new model, choose "Other model" and type its name.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'API key', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<?php foreach ( $providers as $id => $provider ) : ?>
											<?php
											$credentials = $settings['providers'][ $id ];
											$is_current  = ( $settings['provider'] === $id );
											?>
											<div class="fsweb-aic-credentials" data-provider="<?php echo esc_attr( $id ); ?>"<?php echo $is_current ? '' : ' hidden'; ?>>
												<p class="fsweb-aic-field">
													<label for="fsweb-aic-key-<?php echo esc_attr( $id ); ?>">
														<?php
														printf(
															/* translators: %s: provider name. */
															esc_html__( 'API key — %s', 'fsweb-ai-automated-contact' ),
															esc_html( $provider['label'] )
														);
														?>
													</label>
													<input type="password" class="regular-text" id="fsweb-aic-key-<?php echo esc_attr( $id ); ?>"
														name="<?php echo esc_attr( $option ); ?>[providers][<?php echo esc_attr( $id ); ?>][api_key]"
														value="<?php echo esc_attr( $credentials['api_key'] ); ?>"
														autocomplete="new-password">
													<?php if ( ! empty( $provider['keys_url'] ) ) : ?>
														<a href="<?php echo esc_url( $provider['keys_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'get key', 'fsweb-ai-automated-contact' ); ?></a>
													<?php endif; ?>
												</p>
												<?php if ( 'custom' === $id ) : ?>
													<p class="fsweb-aic-field">
														<label for="fsweb-aic-base-url"><?php esc_html_e( 'Base URL (OpenAI-compatible)', 'fsweb-ai-automated-contact' ); ?></label>
														<input type="url" class="regular-text" id="fsweb-aic-base-url"
															name="<?php echo esc_attr( $option ); ?>[providers][custom][base_url]"
															value="<?php echo esc_attr( $credentials['base_url'] ); ?>"
															placeholder="https://api.exemplo.com/v1">
													</p>
													<p class="fsweb-aic-field">
														<label for="fsweb-aic-custom-model"><?php esc_html_e( 'Default model for this provider', 'fsweb-ai-automated-contact' ); ?></label>
														<input type="text" class="regular-text" id="fsweb-aic-custom-model"
															name="<?php echo esc_attr( $option ); ?>[providers][custom][model]"
															value="<?php echo esc_attr( $credentials['model'] ); ?>">
													</p>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e( 'The assistant uses one provider at a time: only the fields of the provider selected above show here.', 'fsweb-ai-automated-contact' ); ?></p>
										<p class="description"><?php esc_html_e( 'Keys are stored only on this site and are never sent to the visitor\'s browser.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Connection test', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<button type="button" class="button" id="fsweb-aic-test-provider">
											<span class="dashicons dashicons-update"></span>
											<?php esc_html_e( 'Test now', 'fsweb-ai-automated-contact' ); ?>
										</button>
										<span class="spinner" id="fsweb-aic-test-spinner" style="float:none;margin:0 0 0 6px;"></span>
										<p class="fsweb-aic-test-result" id="fsweb-aic-test-result"></p>
										<p class="description"><?php esc_html_e( 'The test uses the values currently on screen — no need to save first.', 'fsweb-ai-automated-contact' ); ?></p>
										<p class="description" id="fsweb-aic-current-setup"><?php echo esc_html( sprintf( /* translators: 1: provider id, 2: model. */ __( 'Current setup: %1$s / %2$s', 'fsweb-ai-automated-contact' ), $endpoint['provider'], $endpoint['model'] ) ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card fsweb-aic-card--wide">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'Store context (instructions for the AI)', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<p class="description"><?php esc_html_e( 'Write here everything the AI should know: main products and categories, delivery times, payment methods, returns and exchanges, opening hours, tone of voice. This text is sent as the assistant\'s priority context.', 'fsweb-ai-automated-contact' ); ?></p>
							<textarea class="large-text code" rows="14" id="fsweb-aic-context" name="<?php echo esc_attr( $option ); ?>[context]"><?php echo esc_textarea( $settings['context'] ); ?></textarea>
							<p class="fsweb-aic-context-actions">
								<button type="button" class="button" id="fsweb-aic-context-reset">
									<span class="dashicons dashicons-undo"></span>
									<?php esc_html_e( 'Restore default text', 'fsweb-ai-automated-contact' ); ?>
								</button>
							</p>
							<p class="description"><?php esc_html_e( 'Reloads the original text with the service rules.', 'fsweb-ai-automated-contact' ); ?></p>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'WooCommerce', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Catalogue', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[woo_enabled]" value="yes" <?php checked( 'yes', $settings['woo_enabled'] ); ?>>
											<?php esc_html_e( 'Allow product search and store reading', 'fsweb-ai-automated-contact' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'The assistant checks the store catalogue in real time to answer with name, price and availability.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-limit"><?php esc_html_e( 'Products per search', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><input type="number" min="1" max="10" id="fsweb-aic-limit" name="<?php echo esc_attr( $option ); ?>[product_limit]" value="<?php echo esc_attr( (string) $settings['product_limit'] ); ?>" class="small-text"></td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-chart-area"></span>
								<?php esc_html_e( 'Usage and spending', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<p class="description"><?php esc_html_e( 'Each conversation is stored on this site with its messages, token counts and estimated cost. The estimate uses the prices below; the provider bills you directly, so copy the current price from your provider account.', 'fsweb-ai-automated-contact' ); ?></p>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Price per 1M tokens', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label for="fsweb-aic-price-input"><?php esc_html_e( 'Input (US$)', 'fsweb-ai-automated-contact' ); ?></label>
										<input type="number" min="0" step="0.000001" id="fsweb-aic-price-input" name="<?php echo esc_attr( $option ); ?>[price_input]" value="<?php echo esc_attr( (string) $settings['price_input'] ); ?>" class="small-text">
										&nbsp;&nbsp;
										<label for="fsweb-aic-price-output"><?php esc_html_e( 'Output (US$)', 'fsweb-ai-automated-contact' ); ?></label>
										<input type="number" min="0" step="0.000001" id="fsweb-aic-price-output" name="<?php echo esc_attr( $option ); ?>[price_output]" value="<?php echo esc_attr( (string) $settings['price_output'] ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'Prices apply to the model currently selected above. Example: 0.15 for US$ 0.15 per million input tokens.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-cost-limit"><?php esc_html_e( 'Limit per conversation (US$)', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="number" min="0" step="0.01" id="fsweb-aic-cost-limit" name="<?php echo esc_attr( $option ); ?>[conversation_cost_limit]" value="<?php echo esc_attr( (string) $settings['conversation_cost_limit'] ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'When the estimated cost of a conversation reaches this amount, the assistant stops answering in that conversation. Use 0 for no limit.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
							</table>
							<p>
								<a class="button" href="<?php echo esc_url( $conversations_url ); ?>">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e( 'View conversations and spending', 'fsweb-ai-automated-contact' ); ?>
								</a>
							</p>
						</div>
					</div>

					<?php if ( ! defined( 'FSWEB_AIC_PRO_VERSION' ) ) : ?>
					<div class="fsweb-aic-card fsweb-aic-card--wide">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Assistant text and limits', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fsweb-aic-agent-name"><?php esc_html_e( 'Assistant name', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="text" class="regular-text" id="fsweb-aic-agent-name" name="<?php echo esc_attr( $option ); ?>[agent_name]" value="<?php echo esc_attr( $settings['agent_name'] ); ?>">
										<p class="description"><?php esc_html_e( 'Shown in the chat header and on the floating button when the initial avatar is selected.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-welcome"><?php esc_html_e( 'Welcome message', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><textarea class="large-text" rows="3" id="fsweb-aic-welcome" name="<?php echo esc_attr( $option ); ?>[welcome_message]"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-privacy"><?php esc_html_e( 'Privacy note', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="text" class="large-text" id="fsweb-aic-privacy" name="<?php echo esc_attr( $option ); ?>[privacy_note]" value="<?php echo esc_attr( $settings['privacy_note'] ); ?>">
										<p class="description"><?php esc_html_e( 'Short notice printed under the chat input, e.g. a warning about automated answers.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-temperature"><?php esc_html_e( 'Temperature', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="number" class="small-text" step="0.1" min="0" max="2" id="fsweb-aic-temperature" name="<?php echo esc_attr( $option ); ?>[temperature]" value="<?php echo esc_attr( (string) $settings['temperature'] ); ?>">
										<p class="description"><?php esc_html_e( 'Lower values keep the assistant factual; higher values make it more creative. 0.3 works well for support.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-max-tokens"><?php esc_html_e( 'Maximum tokens per reply', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="number" class="small-text" min="64" max="32000" step="1" id="fsweb-aic-max-tokens" name="<?php echo esc_attr( $option ); ?>[max_tokens]" value="<?php echo esc_attr( (string) $settings['max_tokens'] ); ?>">
										<p class="description"><?php esc_html_e( 'Caps the size of each answer and therefore the cost of each request.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-rate-limit"><?php esc_html_e( 'Messages per visitor', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="number" class="small-text" min="1" max="300" step="1" id="fsweb-aic-rate-limit" name="<?php echo esc_attr( $option ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $settings['rate_limit'] ); ?>">
										<p class="description"><?php esc_html_e( 'Maximum messages each visitor can send every 10 minutes. Use a low value to protect your API budget.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<?php endif; ?>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2>
								<span class="dashicons dashicons-art"></span>
								<?php esc_html_e( 'Appearance', 'fsweb-ai-automated-contact' ); ?>
							</h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fsweb-aic-avatar"><?php esc_html_e( 'Chat icon', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<select id="fsweb-aic-avatar" name="<?php echo esc_attr( $option ); ?>[avatar_type]">
											<option value="chat" <?php selected( 'chat', $settings['avatar_type'] ); ?>><?php esc_html_e( 'Chat bubble', 'fsweb-ai-automated-contact' ); ?></option>
											<option value="initials" <?php selected( 'initials', $settings['avatar_type'] ); ?>><?php esc_html_e( 'Initial letter', 'fsweb-ai-automated-contact' ); ?></option>
										</select>

										<p class="description"><?php esc_html_e( 'Shown on the floating button and in the chat header.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Colour', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<div class="fsweb-aic-colors">
											<label><span><?php esc_html_e( 'Primary', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-primary" name="<?php echo esc_attr( $option ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></label>
											<label><span><?php esc_html_e( 'Header text', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-title" name="<?php echo esc_attr( $option ); ?>[color_title]" value="<?php echo esc_attr( $settings['color_title'] ); ?>"></label>
											<label><span><?php esc_html_e( 'Icon', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-icon" name="<?php echo esc_attr( $option ); ?>[color_icon]" value="<?php echo esc_attr( $settings['color_icon'] ); ?>"></label>
											<label><span><?php esc_html_e( 'Chat background', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-chat-bg" name="<?php echo esc_attr( $option ); ?>[chat_background]" value="<?php echo esc_attr( $settings['chat_background'] ); ?>"></label>
											<label><span><?php esc_html_e( 'Chat text', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-chat-text" name="<?php echo esc_attr( $option ); ?>[chat_text]" value="<?php echo esc_attr( $settings['chat_text'] ); ?>"></label>
											<label><span><?php esc_html_e( 'Assistant bubble', 'fsweb-ai-automated-contact' ); ?></span>
												<input type="color" id="fsweb-aic-color-bubble" name="<?php echo esc_attr( $option ); ?>[bubble_agent]" value="<?php echo esc_attr( $settings['bubble_agent'] ); ?>"></label>
										</div>
										<p class="description"><?php esc_html_e( 'The palette is applied to the widget through CSS variables.', 'fsweb-ai-automated-contact' ); ?></p>
																			</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Position', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[position]" value="right" <?php checked( 'right', $settings['position'] ); ?>> <?php esc_html_e( 'Right', 'fsweb-ai-automated-contact' ); ?></label>
										&nbsp;&nbsp;
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[position]" value="left" <?php checked( 'left', $settings['position'] ); ?>> <?php esc_html_e( 'Left', 'fsweb-ai-automated-contact' ); ?></label>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<?php if ( ! defined( 'FSWEB_AIC_PRO_VERSION' ) ) : ?>
					<div class="fsweb-aic-card fsweb-aic-card--wide fsweb-aic-card--pro">
						<div class="fsweb-aic-card__body">
							<div class="fsweb-aic-pro-card">
								<span class="fsweb-aic-pro-card__icon dashicons dashicons-star-filled"></span>
								<div class="fsweb-aic-pro-card__text">
									<h2>
										<?php esc_html_e( 'AI Contact PRO', 'fsweb-ai-automated-contact' ); ?>
										<span class="fsweb-aic-pro-tag"><?php esc_html_e( 'PRO', 'fsweb-ai-automated-contact' ); ?></span>
									</h2>
									<p><?php esc_html_e( 'The free edition covers the whole conversation: every AI provider, catalogue search, cart and checkout. When you need more control over the experience, the PRO add-on takes over.', 'fsweb-ai-automated-contact' ); ?></p>
									<ul class="fsweb-aic-list">
										<li><?php esc_html_e( 'Assistant name, privacy notice and the full colour palette', 'fsweb-ai-automated-contact' ); ?></li>
										<li><?php esc_html_e( 'Custom message limit per visitor', 'fsweb-ai-automated-contact' ); ?></li>
										<li><?php esc_html_e( 'Custom avatar image and editable welcome message', 'fsweb-ai-automated-contact' ); ?></li>
										<li><?php esc_html_e( 'Auto-open with delay and custom CSS', 'fsweb-ai-automated-contact' ); ?></li>
										<li><?php esc_html_e( 'License activation and automatic updates', 'fsweb-ai-automated-contact' ); ?></li>
									</ul>
									<p>
										<a class="button button-primary" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener noreferrer">
											<span class="dashicons dashicons-external"></span>
											<?php esc_html_e( 'See the PRO version', 'fsweb-ai-automated-contact' ); ?>
										</a>
									</p>
								</div>
							</div>
						</div>
					</div>
					<?php endif; ?>

					<?php
					/**
					 * Print extra settings cards inside the free settings grid.
					 *
					 * The PRO add-on hooks here to render its own fields in the
					 * same form, keeping a single settings screen.
					 *
					 * @since 1.0.0
					 * @param array $settings Current settings.
					 */
					do_action( 'fsweb_aic_admin_settings_sections', $settings );
					?>
				</div>

				<p class="submit">
					<button type="submit" name="submit" id="submit" class="button button-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Save settings', 'fsweb-ai-automated-contact' ); ?>
					</button>
				</p>
			</form>

			<div class="fsweb-aic-preview-bar">
				<button type="button" class="button button-primary" id="fsweb-aic-preview-open">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Preview', 'fsweb-ai-automated-contact' ); ?>
				</button>
			</div>

			<div class="fsweb-aic-preview-modal" id="fsweb-aic-preview-modal" hidden>
				<div class="fsweb-aic-preview-modal__backdrop" data-fsweb-aic-close></div>
				<div class="fsweb-aic-preview-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fsweb-aic-preview-title">
					<div class="fsweb-aic-preview-modal__head">
						<h2 id="fsweb-aic-preview-title"><?php esc_html_e( 'Widget preview', 'fsweb-ai-automated-contact' ); ?></h2>
						<button type="button" class="button-link fsweb-aic-preview-modal__close" data-fsweb-aic-close aria-label="<?php esc_attr_e( 'Close preview', 'fsweb-ai-automated-contact' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="fsweb-aic-preview-stage" id="fsweb-aic-preview-stage">
						<div class="fsweb-aic-preview" id="fsweb-aic-preview">
							<div class="fsweb-aic-preview__panel">
								<div class="fsweb-aic-preview__header">
									<span class="fsweb-aic-preview__avatar" id="fsweb-aic-preview-avatar"><?php echo wp_kses_post( Fsweb_Ai_Automated_Contact_Public::avatar_markup( $settings ) ); ?></span>
									<span class="fsweb-aic-preview__name" id="fsweb-aic-preview-name"><?php echo esc_html( $settings['agent_name'] ); ?></span>
								</div>
								<div class="fsweb-aic-preview__body" id="fsweb-aic-preview-body">
									<span class="fsweb-aic-preview__bubble" id="fsweb-aic-preview-bubble"><?php echo esc_html( $settings['welcome_message'] ); ?></span>
									<span class="fsweb-aic-preview__bubble fsweb-aic-preview__bubble--user"><?php esc_html_e( 'Do you ship to my city?', 'fsweb-ai-automated-contact' ); ?></span>
									<span class="fsweb-aic-preview__bubble"><?php esc_html_e( 'Yes! Shipping is calculated at checkout.', 'fsweb-ai-automated-contact' ); ?></span>
								</div>
								<div class="fsweb-aic-preview__footer">
									<span class="fsweb-aic-preview__input"><?php esc_html_e( 'Write a message…', 'fsweb-ai-automated-contact' ); ?></span>
									<span class="fsweb-aic-preview__send dashicons dashicons-arrow-right-alt2"></span>
								</div>
							</div>
							<span class="fsweb-aic-preview__ball" id="fsweb-aic-preview-ball"><?php echo wp_kses_post( $preview_icon ); ?></span>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'This is how the widget looks on the storefront. Change the options above and open the preview again to see the result.', 'fsweb-ai-automated-contact' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the tutorial page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_tutorial_page() {
		$settings_url = admin_url( 'admin.php?page=fsweb-ai-automated-contact' );
		$pro_url      = FSWEB_AI_AUTOMATED_CONTACT_PRO_URL;
		?>
		<div class="wrap fsweb-aic-wrap">
			<hr class="wp-header-end">
			<div class="fsweb-aic-hero fsweb-aic-hero--split">
					<div>
						<h1><?php esc_html_e( 'AI Contact — Tutorial', 'fsweb-ai-automated-contact' ); ?></h1>
						<p><?php esc_html_e( 'Your virtual assistant is live in five steps.', 'fsweb-ai-automated-contact' ); ?></p>
					</div>
				<div class="fsweb-aic-hero__actions">
					<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Go to settings', 'fsweb-ai-automated-contact' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $pro_url ); ?>">
						<span class="dashicons dashicons-star-filled"></span>
						<?php esc_html_e( 'Go PRO', 'fsweb-ai-automated-contact' ); ?>
					</a>
				</div>
			</div>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2>
						<span class="dashicons dashicons-editor-ol"></span>
						<?php esc_html_e( 'Step by step', 'fsweb-ai-automated-contact' ); ?>
					</h2>
				</div>
				<div class="fsweb-aic-card__body">
					<ol class="fsweb-aic-steps">
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Pick the AI', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'Pick OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq or an OpenAI-compatible endpoint. The provider account is yours: create it, generate the API key and pay the provider directly — the plugin does not include AI credits or share a key. The key stays on this site and is never exposed to visitors.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Pick the model', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'The list is pre-filled per provider. You can type any specific model, including recent releases.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Write the store context', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'The more context, the better the service: delivery times, shipping, payment, returns, tone of voice and the details the AI must not invent.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Enable WooCommerce in the chat', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'With catalogue access enabled, the AI searches products, shows price and availability, adds to the cart (after confirmation) and sends the customer to checkout.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Style the widget', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'Choose the icon, the colours and the side of the screen where the launcher sits.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
						<li class="fsweb-aic-step">
							<div class="fsweb-aic-step__content">
								<h3><?php esc_html_e( 'Test and enable the widget', 'fsweb-ai-automated-contact' ); ?></h3>
								<p><?php esc_html_e( 'Use the connection test, save the settings and tick "Show the chat on the site". Open the store as a visitor to check the experience.', 'fsweb-ai-automated-contact' ); ?></p>
							</div>
						</li>
					</ol>
					<p><a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Go to settings', 'fsweb-ai-automated-contact' ); ?>
					</a></p>
				</div>
			</div>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2>
						<span class="dashicons dashicons-format-chat"></span>
						<?php esc_html_e( 'What the assistant does for your store', 'fsweb-ai-automated-contact' ); ?>
					</h2>
				</div>
				<div class="fsweb-aic-card__body">
					<ul class="fsweb-aic-list">
						<li><?php esc_html_e( 'Checks the catalogue in real time: name, price, availability, variations and product link.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Confirms with the customer before putting any item in the cart.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Answers questions about shipping, payment, deadlines and returns using the context you wrote.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Speaks the customer\'s language and keeps the tone of voice you defined.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Sends the customer to checkout as soon as the cart is ready.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Conversations are stored on this site with messages, token counts and the estimated cost, so you can review spending and delete them at any time.', 'fsweb-ai-automated-contact' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------//
	//  Conversations
	// ------------------------------------------------------------------//

	/**
	 * Render the conversation list or a single conversation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_conversations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
		$conversation_id = isset( $_GET['conversation'] ) ? absint( wp_unslash( $_GET['conversation'] ) ) : 0;
		$deleted         = isset( $_GET['deleted'] ) && '1' === sanitize_key( wp_unslash( $_GET['deleted'] ) );
		$page            = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $conversation_id ) {
			$this->render_conversation_detail( $conversation_id );
			return;
		}

		$list              = Fsweb_Ai_Automated_Contact_Conversations::paginate( array( 'page' => $page, 'per_page' => 20 ) );
		$totals            = Fsweb_Ai_Automated_Contact_Conversations::totals();
		$settings_url      = admin_url( 'admin.php?page=fsweb-ai-automated-contact' );
		$conversations_url = admin_url( 'admin.php?page=fsweb-ai-automated-contact-conversations' );
		?>
		<div class="wrap fsweb-aic-wrap">
			<hr class="wp-header-end">

			<div class="fsweb-aic-hero fsweb-aic-hero--split">
					<div>
						<h1><?php esc_html_e( 'Conversations', 'fsweb-ai-automated-contact' ); ?></h1>
						<p><?php esc_html_e( 'Every conversation handled by the assistant, with token usage and the estimated amount spent. Conversations are stored on this site; delete the ones you no longer need.', 'fsweb-ai-automated-contact' ); ?></p>
					</div>
				<div class="fsweb-aic-hero__actions">
					<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Settings', 'fsweb-ai-automated-contact' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $deleted ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Conversation deleted.', 'fsweb-ai-automated-contact' ); ?></p></div>
			<?php endif; ?>

			<div class="fsweb-aic-stats">
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Conversations', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( number_format_i18n( $totals['conversations'] ) ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Messages', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( number_format_i18n( $totals['messages'] ) ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Tokens', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( number_format_i18n( $totals['tokens'] ) ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Total spent', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( Fsweb_Ai_Automated_Contact_Conversations::format_cost( $totals['cost'] ) ); ?></span>
				</div>
			</div>

			<?php if ( ! Fsweb_Ai_Automated_Contact_Conversations::has_pricing() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Token prices are not filled in, so the spent column shows zero. Open Settings → Usage and spending and enter the input and output price per one million tokens from your provider.', 'fsweb-ai-automated-contact' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2>
						<span class="dashicons dashicons-format-chat"></span>
						<?php esc_html_e( 'Stored conversations', 'fsweb-ai-automated-contact' ); ?>
					</h2>
				</div>
				<div class="fsweb-aic-card__body">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Last activity', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Conversation', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Visitor', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Provider / model', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Messages', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Tokens', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Spent', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'fsweb-ai-automated-contact' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $list['rows'] ) ) : ?>
								<tr>
									<td colspan="9"><?php esc_html_e( 'No conversation stored yet. They appear here as soon as a visitor talks to the assistant.', 'fsweb-ai-automated-contact' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $list['rows'] as $conversation ) : ?>
									<?php
									$detail_url = add_query_arg(
										array(
											'page'         => 'fsweb-ai-automated-contact-conversations',
											'conversation' => (int) $conversation->id,
										),
										admin_url( 'admin.php' )
									);
									$delete_url = wp_nonce_url(
										add_query_arg(
											array(
												'action'       => 'fsweb_aic_delete_conversation',
												'conversation' => (int) $conversation->id,
											),
											admin_url( 'admin-post.php' )
										),
										'fsweb_aic_delete_conversation_' . (int) $conversation->id
									);
									?>
									<tr>
										<td><?php echo esc_html( $this->format_datetime( $conversation->updated_at ) ); ?></td>
										<td>
											<a href="<?php echo esc_url( $detail_url ); ?>"><code><?php echo esc_html( substr( $conversation->conversation_key, 0, 8 ) ); ?></code></a>
											<br><span class="description"><?php echo esc_html( $this->format_datetime( $conversation->started_at ) ); ?></span>
										</td>
										<td><?php echo esc_html( $this->conversation_visitor( $conversation ) ); ?></td>
										<td><?php echo esc_html( $conversation->provider . ' / ' . $conversation->model ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $conversation->message_count ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $conversation->total_tokens ) ); ?></td>
										<td><strong><?php echo esc_html( Fsweb_Ai_Automated_Contact_Conversations::format_cost( $conversation->cost ) ); ?></strong></td>
										<td>
											<span class="fsweb-aic-pill <?php echo 'limited' === $conversation->status ? 'is-off' : 'is-on'; ?>">
												<?php echo esc_html( $this->conversation_status_label( $conversation->status ) ); ?>
											</span>
										</td>
										<td>
											<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'fsweb-ai-automated-contact' ); ?></a>
											<a class="button button-small button-link-delete fsweb-aic-delete-conversation" href="<?php echo esc_url( $delete_url ); ?>" data-confirm="<?php esc_attr_e( 'Delete this conversation and all of its messages?', 'fsweb-ai-automated-contact' ); ?>"><?php esc_html_e( 'Delete', 'fsweb-ai-automated-contact' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<?php if ( $list['pages'] > 1 ) : ?>
						<div class="tablenav">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg( 'paged', '%#%', $conversations_url ),
											'format'    => '',
											'current'   => $list['page'],
											'total'     => $list['pages'],
											'prev_text' => '&laquo;',
											'next_text' => '&raquo;',
										)
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one conversation with its transcript.
	 *
	 * @since 1.0.0
	 * @param int $conversation_id Conversation id.
	 * @return void
	 */
	private function render_conversation_detail( $conversation_id ) {
		$conversation = Fsweb_Ai_Automated_Contact_Conversations::get( $conversation_id );
		$list_url     = admin_url( 'admin.php?page=fsweb-ai-automated-contact-conversations' );

		if ( ! $conversation ) {
			?>
			<div class="wrap fsweb-aic-wrap">
				<hr class="wp-header-end">
				<h1><?php esc_html_e( 'Conversation', 'fsweb-ai-automated-contact' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'Conversation not found.', 'fsweb-ai-automated-contact' ); ?></p></div>
				<p><a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Back to conversations', 'fsweb-ai-automated-contact' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$messages   = Fsweb_Ai_Automated_Contact_Conversations::get_messages( (int) $conversation->id );
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'       => 'fsweb_aic_delete_conversation',
					'conversation' => (int) $conversation->id,
				),
				admin_url( 'admin-post.php' )
			),
			'fsweb_aic_delete_conversation_' . (int) $conversation->id
		);
		?>
		<div class="wrap fsweb-aic-wrap">
			<hr class="wp-header-end">

			<div class="fsweb-aic-hero fsweb-aic-hero--split">
					<div>
						<h1><?php esc_html_e( 'Conversation', 'fsweb-ai-automated-contact' ); ?></h1>
						<p><code><?php echo esc_html( $conversation->conversation_key ); ?></code></p>
					</div>
				<div class="fsweb-aic-hero__actions">
					<a class="button" href="<?php echo esc_url( $list_url ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Back', 'fsweb-ai-automated-contact' ); ?>
					</a>
					<a class="button button-link-delete fsweb-aic-delete-conversation" href="<?php echo esc_url( $delete_url ); ?>" data-confirm="<?php esc_attr_e( 'Delete this conversation and all of its messages?', 'fsweb-ai-automated-contact' ); ?>">
						<?php esc_html_e( 'Delete', 'fsweb-ai-automated-contact' ); ?>
					</a>
				</div>
			</div>

			<div class="fsweb-aic-stats">
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Provider / model', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( $conversation->provider . ' / ' . $conversation->model ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Tokens', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( number_format_i18n( (int) $conversation->total_tokens ) ); ?></span>
					<span class="description"><?php echo esc_html( sprintf( /* translators: 1: prompt tokens, 2: completion tokens. */ __( '%1$s in / %2$s out', 'fsweb-ai-automated-contact' ), number_format_i18n( (int) $conversation->prompt_tokens ), number_format_i18n( (int) $conversation->completion_tokens ) ) ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Spent', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( Fsweb_Ai_Automated_Contact_Conversations::format_cost( $conversation->cost ) ); ?></span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Visitor', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-stat-value"><?php echo esc_html( $this->conversation_visitor( $conversation ) ); ?></span>
					<span class="description"><?php echo esc_html( $this->format_datetime( $conversation->started_at ) ); ?></span>
				</div>
			</div>

			<?php if ( ! Fsweb_Ai_Automated_Contact_Conversations::has_pricing() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Token prices are not filled in, so the spent value is zero. Enter the provider prices in Settings → Usage and spending.', 'fsweb-ai-automated-contact' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2>
						<span class="dashicons dashicons-media-text"></span>
						<?php esc_html_e( 'Transcript', 'fsweb-ai-automated-contact' ); ?>
					</h2>
				</div>
				<div class="fsweb-aic-card__body">
					<table class="wp-list-table widefat fixed striped fsweb-aic-transcript">
						<thead>
							<tr>
								<th scope="col" class="fsweb-aic-transcript__col-when"><?php esc_html_e( 'When', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col" class="fsweb-aic-transcript__col-role"><?php esc_html_e( 'Role', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Message', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col" class="fsweb-aic-transcript__col-tokens"><?php esc_html_e( 'Tokens', 'fsweb-ai-automated-contact' ); ?></th>
								<th scope="col" class="fsweb-aic-transcript__col-cost"><?php esc_html_e( 'Cost', 'fsweb-ai-automated-contact' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $messages ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No message stored for this conversation.', 'fsweb-ai-automated-contact' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $messages as $message ) : ?>
									<tr>
										<td><?php echo esc_html( $this->format_datetime( $message->created_at ) ); ?></td>
										<td><strong><?php echo esc_html( 'user' === $message->role ? __( 'Visitor', 'fsweb-ai-automated-contact' ) : __( 'Assistant', 'fsweb-ai-automated-contact' ) ); ?></strong></td>
										<td><?php echo nl2br( esc_html( $message->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $message->total_tokens ) ); ?></td>
										<td><?php echo esc_html( Fsweb_Ai_Automated_Contact_Conversations::format_cost( $message->cost ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Local date/time for a UTC database value.
	 *
	 * @since  1.0.0
	 * @param  string $gmt MySQL UTC datetime.
	 * @return string
	 */
	private function format_datetime( $gmt ) {
		if ( empty( $gmt ) ) {
			return '—';
		}

		$timestamp = strtotime( $gmt . ' UTC' );

		if ( ! $timestamp ) {
			return '—';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Visitor label for a conversation.
	 *
	 * @since  1.0.0
	 * @param  object $conversation Conversation row.
	 * @return string
	 */
	private function conversation_visitor( $conversation ) {
		if ( ! empty( $conversation->user_id ) ) {
			$user = get_userdata( (int) $conversation->user_id );

			if ( $user ) {
				return $user->display_name;
			}

			return sprintf(
				/* translators: %d: user id. */
				__( 'User #%d', 'fsweb-ai-automated-contact' ),
				(int) $conversation->user_id
			);
		}

		return __( 'Guest', 'fsweb-ai-automated-contact' );
	}

	/**
	 * Status label for a conversation.
	 *
	 * @since  1.0.0
	 * @param  string $status Stored status.
	 * @return string
	 */
	private function conversation_status_label( $status ) {
		if ( 'limited' === $status ) {
			return __( 'Limit reached', 'fsweb-ai-automated-contact' );
		}

		return __( 'Open', 'fsweb-ai-automated-contact' );
	}

	/**
	 * Delete one conversation from the admin screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_conversation() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to delete conversations.', 'fsweb-ai-automated-contact' ) );
		}

		$conversation_id = isset( $_POST['conversation'] ) ? absint( wp_unslash( $_POST['conversation'] ) ) : 0;

		check_admin_referer( 'fsweb_aic_delete_conversation_' . $conversation_id );

		Fsweb_Ai_Automated_Contact_Conversations::delete( $conversation_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fsweb-ai-automated-contact-conversations',
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ------------------------------------------------------------------//
	//  AJAX handlers
	// ------------------------------------------------------------------//

	/**
	 * Verify the AJAX nonce and the capability, or die with a JSON error.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function guard_ajax() {
		check_ajax_referer( 'fsweb_aic_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fsweb-ai-automated-contact' ) ) );
		}
	}

			/**
	 * Build the endpoint to test, preferring the values currently on screen.
	 *
	 * The test button works on unsaved input, so the merchant can try a key
	 * before committing it to the database.
	 *
	 * @since  1.0.0
	 * @return array<string,string>|WP_Error
	 */
	private function endpoint_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in guard_ajax().
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API keys are opaque and trimmed only.
		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$config      = Fsweb_Ai_Automated_Contact_Settings::provider( $provider_id );

		if ( ! $config ) {
			return new WP_Error(
				'fsweb_aic_unknown_provider',
				__( 'Select an AI provider first.', 'fsweb-ai-automated-contact' )
			);
		}

		// Keys are opaque: strip whitespace only, never mangle the value.
		$api_key = isset( $_POST['api_key'] ) ? trim( preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['api_key'] ) ) ) : '';
		$model   = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

		if ( '' === $api_key ) {
			$saved   = Fsweb_Ai_Automated_Contact_Settings::all();
			$api_key = isset( $saved['providers'][ $provider_id ]['api_key'] ) ? $saved['providers'][ $provider_id ]['api_key'] : '';
		}

		if ( '' === $api_key ) {
			return new WP_Error(
				'fsweb_aic_missing_key',
				__( 'Paste the API key of the selected provider to run the test.', 'fsweb-ai-automated-contact' )
			);
		}

		if ( '' === $model ) {
			$model = Fsweb_Ai_Automated_Contact_Settings::get( 'model', '' );
		}

		$base_url = $config['base_url'];

		if ( ! $base_url ) {
			return new WP_Error(
				'fsweb_aic_missing_base_url',
				__( 'This provider has no API endpoint configured.', 'fsweb-ai-automated-contact' )
			);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return array(
			'provider' => $provider_id,
			'api'      => $config['api'],
			'base_url' => untrailingslashit( $base_url ),
			'api_key'  => $api_key,
			'model'    => $model,
		);
	}

	/**
	 * AJAX handler — send a tiny probe to the provider configured on screen.
	 *
	 * Works with the values currently typed in the form, so the merchant can
	 * validate a key without saving first.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function ajax_test_provider() {
		$this->guard_ajax();

		$endpoint = $this->endpoint_from_request();

		if ( is_wp_error( $endpoint ) ) {
			wp_send_json_error( array( 'message' => $endpoint->get_error_message() ) );
		}

		$client = new Fsweb_Ai_Automated_Contact_Ai_Client( $endpoint );

		$result = $client->complete(
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with the single word: ok',
				),
			),
			array(),
			'You are a connection test. Answer with the minimum possible.'
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: 1: provider id, 2: model name, 3: answer from the model. */
					__( 'Success with %1$s / %2$s. Model answered: %3$s', 'fsweb-ai-automated-contact' ),
					$endpoint['provider'],
					$endpoint['model'],
					trim( wp_strip_all_tags( (string) $result['content'] ) )
				),
				'provider' => $endpoint['provider'],
				'model'    => $endpoint['model'],
			)
		);
	}
}
