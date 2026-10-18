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
				'AI Automated Contact: WooCommerce is not active. The assistant still answers store questions, but product search, product details, cart and checkout are unavailable until WooCommerce is activated.',
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
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
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

		add_submenu_page(
			'fsweb-ai-automated-contact',
			__( 'AI Contact — Tutorial', 'fsweb-ai-automated-contact' ),
			__( 'Tutorial', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact-tutorial',
			array( $this, 'render_tutorial_page' )
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
		?>
		<div class="wrap fsweb-aic-wrap">
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
										</div>
										<p class="description"><?php esc_html_e( 'The free edition ships the primary colour. The rest of the palette is part of the PRO add-on.', 'fsweb-ai-automated-contact' ); ?></p>
										<div class="fsweb-aic-preview" id="fsweb-aic-preview">
											<div class="fsweb-aic-preview__panel">
												<div class="fsweb-aic-preview__header">
													<span class="fsweb-aic-preview__avatar" id="fsweb-aic-preview-avatar"><?php echo wp_kses_post( Fsweb_Ai_Automated_Contact_Public::avatar_markup( $settings ) ); ?></span>
													<span class="fsweb-aic-preview__name"><?php echo esc_html( $settings['agent_name'] ); ?></span>
												</div>
												<div class="fsweb-aic-preview__body" id="fsweb-aic-preview-body">
													<span class="fsweb-aic-preview__bubble" id="fsweb-aic-preview-bubble"><?php esc_html_e( 'Hi! How can I help?', 'fsweb-ai-automated-contact' ); ?></span>
												</div>
											</div>
											<span class="fsweb-aic-preview__ball" id="fsweb-aic-preview-ball"><?php echo wp_kses_post( $preview_icon ); ?></span>
										</div>
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
								<p><?php esc_html_e( 'Pick OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq or an OpenAI-compatible endpoint. Paste the API key of the chosen provider. Keys stay in this site and are never exposed to visitors.', 'fsweb-ai-automated-contact' ); ?></p>
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
						<li><?php esc_html_e( 'Conversations stay in the visitor\'s browser and are not stored on the server.', 'fsweb-ai-automated-contact' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
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
