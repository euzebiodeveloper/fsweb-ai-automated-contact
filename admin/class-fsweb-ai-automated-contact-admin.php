<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Owns the settings screen, the license screen, the onboarding flow and the
 * license AJAX handlers. All rendering escapes on output; all input is
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
	 * License client.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Fsweb_Ai_Automated_Contact_License
	 */
	private $license;

	/**
	 * Hook suffix of the settings screen.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $settings_page_hook = '';

	/**
	 * Hook suffix of the license screen.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $license_page_hook = '';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string                               $plugin_name The name of this plugin.
	 * @param    string                               $version     The version of this plugin.
	 * @param    Fsweb_Ai_Automated_Contact_License   $license     License client.
	 */
	public function __construct( $plugin_name, $version, $license ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->license     = $license;
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

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/fsweb-ai-automated-contact-admin.css',
			array(),
			$this->version,
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

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/fsweb-ai-automated-contact-admin.js',
			array( 'jquery' ),
			$this->version,
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
				'license'  => array(
					'status'  => $this->license->get_status(),
					'expires' => $this->license->get_expires(),
				),
				'i18n'     => array(
					'confirm_deactivate' => __( 'Tem certeza que deseja desativar esta licença?', 'fsweb-ai-automated-contact' ),
					'activating'         => __( 'Ativando…', 'fsweb-ai-automated-contact' ),
					'deactivating'       => __( 'Desativando…', 'fsweb-ai-automated-contact' ),
					'validating'         => __( 'Verificando…', 'fsweb-ai-automated-contact' ),
					'testing'            => __( 'Testando…', 'fsweb-ai-automated-contact' ),
					'custom_model'       => __( 'Outro modelo (digitar)', 'fsweb-ai-automated-contact' ),
					'empty_key'          => __( 'Informe uma chave de licença.', 'fsweb-ai-automated-contact' ),
					'network_error'      => __( 'Erro de rede. Tente novamente.', 'fsweb-ai-automated-contact' ),
					'lifetime'           => __( 'Vitalícia', 'fsweb-ai-automated-contact' ),
					'status_active'      => __( 'Ativa', 'fsweb-ai-automated-contact' ),
					'status_inactive'    => __( 'Inativa', 'fsweb-ai-automated-contact' ),
					'status_expired'     => __( 'Expirada', 'fsweb-ai-automated-contact' ),
					'status_invalid'     => __( 'Inválida', 'fsweb-ai-automated-contact' ),
					'status_pending'     => __( 'Pendente', 'fsweb-ai-automated-contact' ),
				),
			)
		);
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
			|| ( $this->license_page_hook === $hook_suffix )
			|| $this->is_onboarding_page()
			|| $this->is_tutorial_page();
	}

	// ------------------------------------------------------------------//
	//  Menu
	// ------------------------------------------------------------------//

	/**
	 * Register the top-level menu, License, Tutorial and onboarding screens.
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

		$this->license_page_hook = add_submenu_page(
			'fsweb-ai-automated-contact',
			__( 'Licença', 'fsweb-ai-automated-contact' ),
			__( 'Licença', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact-license',
			array( $this, 'render_license_page' )
		);

		add_submenu_page(
			'fsweb-ai-automated-contact',
			__( 'AI Contact — Tutorial', 'fsweb-ai-automated-contact' ),
			__( 'Tutorial', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact-tutorial',
			array( $this, 'render_tutorial_page' )
		);

		// Hidden onboarding/setup page (reachable via URL / redirect).
		add_submenu_page(
			null,
			__( 'AI Contact — Setup', 'fsweb-ai-automated-contact' ),
			__( 'AI Contact — Setup', 'fsweb-ai-automated-contact' ),
			'manage_options',
			'fsweb-ai-automated-contact-onboarding',
			array( $this, 'render_onboarding_page' )
		);
	}

	/**
	 * Whether the current admin screen is the onboarding page.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_onboarding_page() {
		return isset( $_GET['page'] ) && 'fsweb-ai-automated-contact-onboarding' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Whether the current admin screen is the tutorial page.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_tutorial_page() {
		return isset( $_GET['page'] ) && 'fsweb-ai-automated-contact-tutorial' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Redirect to onboarding on first activation and while the license is
	 * not active.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_redirect_to_onboarding() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// "Close" action on the onboarding screen: dismiss it for good.
		if ( $this->is_onboarding_page() && isset( $_GET['dismiss'] ) ) {
			update_option( 'fsweb_aic_onboarding_dismissed', 'yes' );
			wp_safe_redirect( admin_url() );
			exit;
		}

		if ( $this->is_onboarding_page() ) {
			return;
		}

		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$status    = $this->license->get_status();
		$dismissed = get_option( 'fsweb_aic_onboarding_dismissed', 'no' );

		$on_plugin_page = in_array( $page, array( 'fsweb-ai-automated-contact', 'fsweb-ai-automated-contact-license' ), true );

		if ( 'active' !== $status && 'yes' !== $dismissed && $on_plugin_page ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fsweb-ai-automated-contact-onboarding' ) );
			exit;
		}
	}

	/**
	 * Add a body class on the onboarding screen so the WP admin chrome can be
	 * hidden for a full-screen setup experience.
	 *
	 * @since  1.0.0
	 * @param  string $classes Existing body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( $this->is_onboarding_page() ) {
			$classes .= ' fsweb-aic-onboarding-page';
		}

		return $classes;
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
	 * Render the onboarding / license activation screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_onboarding_page() {
		$license_key  = $this->license->get_key();
		$logo_url     = FSWEB_AI_AUTOMATED_CONTACT_URL . 'includes/assets/images/flowsyncweblogo.webp';
		$title_url    = FSWEB_AI_AUTOMATED_CONTACT_URL . 'includes/assets/images/flowsyncwebtitulo.webp';
		$tutorial_url = admin_url( 'admin.php?page=fsweb-ai-automated-contact-tutorial' );
		?>
		<div class="fsweb-aic-onboarding">
			<a class="fsweb-aic-onboarding__close" href="<?php echo esc_url( admin_url( 'admin.php?page=fsweb-ai-automated-contact-onboarding&dismiss=1' ) ); ?>" aria-label="<?php esc_attr_e( 'Fechar', 'fsweb-ai-automated-contact' ); ?>">&times;</a>

			<div class="fsweb-aic-onboarding__brand">
				<img class="fsweb-aic-onboarding__brand-img" src="<?php echo esc_url( $title_url ); ?>" alt="<?php esc_attr_e( 'AI Automated Contact', 'fsweb-ai-automated-contact' ); ?>">
			</div>

			<div class="fsweb-aic-onboarding__panel">
				<img class="fsweb-aic-onboarding__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Flow Sync Web', 'fsweb-ai-automated-contact' ); ?>">

				<h1 class="fsweb-aic-onboarding__title"><?php esc_html_e( 'Bem-vindo ao AI Automated Contact', 'fsweb-ai-automated-contact' ); ?></h1>

				<p class="fsweb-aic-onboarding__lead">
					<?php esc_html_e( 'Ative sua licença para liberar o atendente virtual com IA: ele conversa com seus clientes, tira dúvidas, encontra produtos, monta o carrinho e encaminha para a finalização da compra.', 'fsweb-ai-automated-contact' ); ?>
				</p>

				<div class="fsweb-aic-onboarding__license">
					<label class="fsweb-aic-onboarding__label" for="fsweb-aic-onboarding-license-key"><?php esc_html_e( 'Chave de licença', 'fsweb-ai-automated-contact' ); ?></label>

					<div class="fsweb-aic-onboarding__row">
						<input type="text" id="fsweb-aic-onboarding-license-key" class="fsweb-aic-onboarding__input"
							value="<?php echo esc_attr( $license_key ); ?>"
							placeholder="<?php esc_attr_e( 'ex.: FSWEB-XXXX-XXXX-XXXX', 'fsweb-ai-automated-contact' ); ?>">
						<button type="button" id="fsweb-aic-onboarding-activate" class="button button-primary fsweb-aic-onboarding__button">
							<?php esc_html_e( 'Ativar', 'fsweb-ai-automated-contact' ); ?>
						</button>
					</div>

					<p class="fsweb-aic-onboarding__hint">
						<?php esc_html_e( 'Informe sua chave para receber atualizações automáticas e liberar o atendente virtual neste domínio.', 'fsweb-ai-automated-contact' ); ?>
					</p>
				</div>

				<div id="fsweb-aic-onboarding-message" class="notice" style="display:none;"></div>

				<div class="fsweb-aic-onboarding__done" id="fsweb-aic-onboarding-done" style="display:none;">
					<p><?php esc_html_e( 'Licença ativa! Agora configure a IA da sua loja.', 'fsweb-ai-automated-contact' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fsweb-ai-automated-contact' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Continuar', 'fsweb-ai-automated-contact' ); ?></a>
					<a href="<?php echo esc_url( $tutorial_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Ver tutorial', 'fsweb-ai-automated-contact' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings screen (provider, context, WooCommerce, appearance).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		$settings  = Fsweb_Ai_Automated_Contact_Settings::all();
		$providers = Fsweb_Ai_Automated_Contact_Settings::providers();
		$endpoint  = Fsweb_Ai_Automated_Contact_Settings::active_endpoint();
		$option    = Fsweb_Ai_Automated_Contact_Settings::OPTION;
		$woo       = class_exists( 'WooCommerce' );
		?>
		<div class="wrap fsweb-aic-wrap">
			<hr class="wp-header-end">

			<div class="fsweb-aic-hero">
				<h1><?php esc_html_e( 'AI Contact', 'fsweb-ai-automated-contact' ); ?></h1>
				<p><?php esc_html_e( 'Configure o atendente virtual, escolha a IA, escreva o contexto da loja e ajuste o widget.', 'fsweb-ai-automated-contact' ); ?></p>
			</div>

			<div class="fsweb-aic-stats">
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Atendente', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo 'yes' === $settings['enabled'] ? 'is-on' : 'is-off'; ?>">
						<?php echo 'yes' === $settings['enabled'] ? esc_html__( 'Ativo no site', 'fsweb-ai-automated-contact' ) : esc_html__( 'Desativado', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'Inteligência Artificial', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo Fsweb_Ai_Automated_Contact_Settings::is_configured() ? 'is-on' : 'is-off'; ?>">
						<?php echo Fsweb_Ai_Automated_Contact_Settings::is_configured() ? esc_html__( 'Configurada', 'fsweb-ai-automated-contact' ) : esc_html__( 'Não configurada', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
				<div class="fsweb-aic-stat-card">
					<span class="fsweb-aic-stat-label"><?php esc_html_e( 'WooCommerce', 'fsweb-ai-automated-contact' ); ?></span>
					<span class="fsweb-aic-pill <?php echo $woo ? 'is-on' : 'is-off'; ?>">
						<?php echo $woo ? esc_html__( 'Ativo', 'fsweb-ai-automated-contact' ) : esc_html__( 'Ausente', 'fsweb-ai-automated-contact' ); ?>
					</span>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( 'fsweb_aic_settings_group' ); ?>

				<div class="fsweb-aic-grid">
					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2><?php esc_html_e( 'Atendente', 'fsweb-ai-automated-contact' ); ?></h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Ativar widget', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?>>
											<?php esc_html_e( 'Exibir o chat no site', 'fsweb-ai-automated-contact' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-agent-name"><?php esc_html_e( 'Nome do atendente', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><input type="text" class="regular-text" id="fsweb-aic-agent-name" name="<?php echo esc_attr( $option ); ?>[agent_name]" value="<?php echo esc_attr( $settings['agent_name'] ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-welcome"><?php esc_html_e( 'Mensagem de boas-vindas', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><textarea class="large-text" rows="3" id="fsweb-aic-welcome" name="<?php echo esc_attr( $option ); ?>[welcome_message]"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-privacy"><?php esc_html_e( 'Aviso de privacidade', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><textarea class="large-text" rows="2" id="fsweb-aic-privacy" name="<?php echo esc_attr( $option ); ?>[privacy_note]"><?php echo esc_textarea( $settings['privacy_note'] ); ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-rate"><?php esc_html_e( 'Limite de mensagens / 10 min', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<input type="number" min="1" max="300" id="fsweb-aic-rate" name="<?php echo esc_attr( $option ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $settings['rate_limit'] ); ?>">
										<p class="description"><?php esc_html_e( 'Proteção contra abuso por visitante.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2><?php esc_html_e( 'Inteligência Artificial', 'fsweb-ai-automated-contact' ); ?></h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fsweb-aic-provider"><?php esc_html_e( 'Provedor', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<select id="fsweb-aic-provider" name="<?php echo esc_attr( $option ); ?>[provider]" class="fsweb-aic-provider">
											<?php foreach ( $providers as $id => $provider ) : ?>
												<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['provider'], $id ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-model-select"><?php esc_html_e( 'Modelo', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td>
										<select id="fsweb-aic-model-select" class="fsweb-aic-model-select"></select>

										<div class="fsweb-aic-model-custom" id="fsweb-aic-model-custom" hidden>
											<label class="screen-reader-text" for="fsweb-aic-model"><?php esc_html_e( 'Nome do modelo', 'fsweb-ai-automated-contact' ); ?></label>
											<input type="text" class="regular-text" id="fsweb-aic-model"
												name="<?php echo esc_attr( $option ); ?>[model]"
												value="<?php echo esc_attr( $settings['model'] ); ?>"
												placeholder="<?php esc_attr_e( 'ex.: gpt-5.4-mini', 'fsweb-ai-automated-contact' ); ?>"
												autocomplete="off">
										</div>

										<p class="description"><?php esc_html_e( 'A lista traz os modelos atuais do provedor escolhido. Para usar um modelo específico ou recém-lançado, selecione "Outro modelo" e digite o nome.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Chave de API', 'fsweb-ai-automated-contact' ); ?></th>
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
															esc_html__( 'Chave da API — %s', 'fsweb-ai-automated-contact' ),
															esc_html( $provider['label'] )
														);
														?>
													</label>
													<input type="password" class="regular-text" id="fsweb-aic-key-<?php echo esc_attr( $id ); ?>"
														name="<?php echo esc_attr( $option ); ?>[providers][<?php echo esc_attr( $id ); ?>][api_key]"
														value="<?php echo esc_attr( $credentials['api_key'] ); ?>"
														autocomplete="new-password">
													<?php if ( ! empty( $provider['keys_url'] ) ) : ?>
														<a href="<?php echo esc_url( $provider['keys_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'obter chave', 'fsweb-ai-automated-contact' ); ?></a>
													<?php endif; ?>
												</p>
												<?php if ( 'custom' === $id ) : ?>
													<p class="fsweb-aic-field">
														<label for="fsweb-aic-base-url"><?php esc_html_e( 'URL base (compatível com OpenAI)', 'fsweb-ai-automated-contact' ); ?></label>
														<input type="url" class="regular-text" id="fsweb-aic-base-url"
															name="<?php echo esc_attr( $option ); ?>[providers][custom][base_url]"
															value="<?php echo esc_attr( $credentials['base_url'] ); ?>"
															placeholder="https://api.exemplo.com/v1">
													</p>
													<p class="fsweb-aic-field">
														<label for="fsweb-aic-custom-model"><?php esc_html_e( 'Modelo padrão deste provedor', 'fsweb-ai-automated-contact' ); ?></label>
														<input type="text" class="regular-text" id="fsweb-aic-custom-model"
															name="<?php echo esc_attr( $option ); ?>[providers][custom][model]"
															value="<?php echo esc_attr( $credentials['model'] ); ?>">
													</p>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e( 'O atendente usa um provedor por vez: só os campos do provedor selecionado acima aparecem aqui.', 'fsweb-ai-automated-contact' ); ?></p>
										<p class="description"><?php esc_html_e( 'As chaves ficam salvas apenas neste site e nunca são enviadas ao navegador do visitante.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Parâmetros', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label><?php esc_html_e( 'Temperatura', 'fsweb-ai-automated-contact' ); ?>
											<input type="number" step="0.1" min="0" max="2" name="<?php echo esc_attr( $option ); ?>[temperature]" value="<?php echo esc_attr( (string) $settings['temperature'] ); ?>" class="small-text">
										</label>
										&nbsp;&nbsp;
										<label><?php esc_html_e( 'Máx. tokens', 'fsweb-ai-automated-contact' ); ?>
											<input type="number" step="1" min="128" max="8000" name="<?php echo esc_attr( $option ); ?>[max_tokens]" value="<?php echo esc_attr( (string) $settings['max_tokens'] ); ?>" class="small-text">
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Teste de conexão', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<button type="button" class="button" id="fsweb-aic-test-provider"><?php esc_html_e( 'Testar agora', 'fsweb-ai-automated-contact' ); ?></button>
										<span class="spinner" id="fsweb-aic-test-spinner" style="float:none;margin:0 0 0 6px;"></span>
										<p class="fsweb-aic-test-result" id="fsweb-aic-test-result"></p>
										<p class="description"><?php esc_html_e( 'The test uses the values currently on screen — no need to save first.', 'fsweb-ai-automated-contact' ); ?></p>
										<?php if ( ! empty( $endpoint['model'] ) ) : ?>
											<p class="description"><?php echo esc_html( sprintf( /* translators: 1: provider id, 2: model. */ __( 'Configuração atual: %1$s / %2$s', 'fsweb-ai-automated-contact' ), $endpoint['provider'], $endpoint['model'] ) ); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card fsweb-aic-card--wide">
						<div class="fsweb-aic-card__header">
							<h2><?php esc_html_e( 'Contexto da loja (instruções para a IA)', 'fsweb-ai-automated-contact' ); ?></h2>
						</div>
						<div class="fsweb-aic-card__body">
							<p class="description"><?php esc_html_e( 'Escreva aqui tudo que a IA deve saber: produtos e categorias principais, prazos de entrega, formas de pagamento, trocas e devoluções, horário de atendimento, tom de voz. Este texto é enviado como contexto prioritário do atendente.', 'fsweb-ai-automated-contact' ); ?></p>
							<textarea class="large-text code" rows="14" id="fsweb-aic-context" name="<?php echo esc_attr( $option ); ?>[context]"><?php echo esc_textarea( $settings['context'] ); ?></textarea>
							<p>
								<button type="button" class="button" id="fsweb-aic-context-reset"><?php esc_html_e( 'Restaurar texto padrão', 'fsweb-ai-automated-contact' ); ?></button>
								<span class="description"><?php esc_html_e( 'Recarrega o texto original com as regras de atendimento.', 'fsweb-ai-automated-contact' ); ?></span>
							</p>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2><?php esc_html_e( 'WooCommerce', 'fsweb-ai-automated-contact' ); ?></h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Catálogo', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[woo_enabled]" value="yes" <?php checked( 'yes', $settings['woo_enabled'] ); ?>>
											<?php esc_html_e( 'Permitir busca de produtos e leitura da loja', 'fsweb-ai-automated-contact' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'O atendente consulta o catálogo da loja em tempo real para responder com nome, preço e disponibilidade.', 'fsweb-ai-automated-contact' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Carrinho', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[allow_add_to_cart]" value="yes" <?php checked( 'yes', $settings['allow_add_to_cart'] ); ?>>
											<?php esc_html_e( 'Permitir adicionar produtos ao carrinho', 'fsweb-ai-automated-contact' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fsweb-aic-limit"><?php esc_html_e( 'Produtos por busca', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><input type="number" min="1" max="10" id="fsweb-aic-limit" name="<?php echo esc_attr( $option ); ?>[product_limit]" value="<?php echo esc_attr( (string) $settings['product_limit'] ); ?>" class="small-text"></td>
								</tr>
							</table>
						</div>
					</div>

					<div class="fsweb-aic-card">
						<div class="fsweb-aic-card__header">
							<h2><?php esc_html_e( 'Aparência', 'fsweb-ai-automated-contact' ); ?></h2>
						</div>
						<div class="fsweb-aic-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fsweb-aic-color"><?php esc_html_e( 'Cor de destaque', 'fsweb-ai-automated-contact' ); ?></label></th>
									<td><input type="color" id="fsweb-aic-color" name="<?php echo esc_attr( $option ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Posição', 'fsweb-ai-automated-contact' ); ?></th>
									<td>
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[position]" value="right" <?php checked( 'right', $settings['position'] ); ?>> <?php esc_html_e( 'Direita', 'fsweb-ai-automated-contact' ); ?></label>
										&nbsp;&nbsp;
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[position]" value="left" <?php checked( 'left', $settings['position'] ); ?>> <?php esc_html_e( 'Esquerda', 'fsweb-ai-automated-contact' ); ?></label>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<?php submit_button( __( 'Salvar configurações', 'fsweb-ai-automated-contact' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the license management page.
	 *
	 * The UI lives here; activation/deactivation calls go to the FSWEB
	 * license API through the license client.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function render_license_page() {
		$license_key     = $this->license->get_key();
		$license_status  = $this->license->get_status();
		$license_expires = $this->license->get_expires();

		// Always sync with the license server on page load so the status
		// reflects reality — another site may have activated/deactivated
		// the same license key since the last local check.
		if ( ! empty( $license_key ) ) {
			$synced = $this->license->sync();

			if ( $synced ) {
				$license_status  = $synced['status'];
				$license_expires = $synced['expires'];
			}
		}
		?>
		<div class="wrap fsweb-aic-wrap fsweb-aic-license-wrap">
			<hr class="wp-header-end">
			<div class="fsweb-aic-hero">
				<h1><?php esc_html_e( 'AI Contact — Licença', 'fsweb-ai-automated-contact' ); ?></h1>
				<p><?php esc_html_e( 'Informe sua chave para receber atualizações automáticas e suporte.', 'fsweb-ai-automated-contact' ); ?></p>
			</div>

			<div class="fsweb-aic-license-card">
				<p class="description">
					<?php esc_html_e( 'A chave é vinculada a este domínio e libera as atualizações automáticas e o atendente virtual.', 'fsweb-ai-automated-contact' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fsweb-aic-license-key"><?php esc_html_e( 'Chave de licença', 'fsweb-ai-automated-contact' ); ?></label>
						</th>
						<td>
							<input type="text" id="fsweb-aic-license-key" name="fsweb_aic_license_key" class="regular-text"
								value="<?php echo esc_attr( $license_key ); ?>"
								placeholder="<?php esc_attr_e( 'ex.: FSWEB-XXXX-XXXX-XXXX', 'fsweb-ai-automated-contact' ); ?>"
								<?php echo 'active' === $license_status ? 'readonly' : ''; ?>>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'fsweb-ai-automated-contact' ); ?></th>
						<td>
							<span id="fsweb-aic-license-status" class="fsweb-aic-status fsweb-aic-status--<?php echo esc_attr( $license_status ); ?>">
								<?php
								switch ( $license_status ) {
									case 'active':
										esc_html_e( 'Ativa', 'fsweb-ai-automated-contact' );
										break;
									case 'expired':
										esc_html_e( 'Expirada', 'fsweb-ai-automated-contact' );
										break;
									case 'invalid':
										esc_html_e( 'Inválida', 'fsweb-ai-automated-contact' );
										break;
									case 'pending':
										esc_html_e( 'Pendente', 'fsweb-ai-automated-contact' );
										break;
									default:
										esc_html_e( 'Inativa', 'fsweb-ai-automated-contact' );
										break;
								}
								?>
							</span>
						</td>
					</tr>
					<tr id="fsweb-aic-expires-row" style="<?php echo 'active' === $license_status && ! empty( $license_expires ) ? '' : 'display:none;'; ?>">
						<th scope="row"><?php esc_html_e( 'Expira em', 'fsweb-ai-automated-contact' ); ?></th>
						<td><span id="fsweb-aic-license-expires"><?php echo ! empty( $license_expires ) ? esc_html( mysql2date( get_option( 'date_format' ), $license_expires ) ) : esc_html__( 'Vitalícia', 'fsweb-ai-automated-contact' ); ?></span></td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" id="fsweb-aic-activate-license" class="button button-primary" style="<?php echo 'active' === $license_status ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Ativar', 'fsweb-ai-automated-contact' ); ?></button>
					<button type="button" id="fsweb-aic-deactivate-license" class="button button-secondary" style="<?php echo 'active' === $license_status ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Desativar', 'fsweb-ai-automated-contact' ); ?></button>
					<?php $show_verify = in_array( $license_status, array( 'expired', 'pending', 'invalid' ), true ); ?>
					<button type="button" id="fsweb-aic-validate-license" class="button button-secondary" style="<?php echo $show_verify ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Verificar licença', 'fsweb-ai-automated-contact' ); ?></button>
					<span id="fsweb-aic-validate-spinner" class="spinner" style="display:none; float:none; margin-top:0;"></span>
				</p>
				<div id="fsweb-aic-license-message" class="notice" style="display:none;"></div>
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
		?>
		<div class="wrap fsweb-aic-wrap">
			<hr class="wp-header-end">
			<div class="fsweb-aic-hero">
				<h1><?php esc_html_e( 'AI Contact — Tutorial', 'fsweb-ai-automated-contact' ); ?></h1>
				<p><?php esc_html_e( 'Em cinco passos seu atendente virtual está no ar.', 'fsweb-ai-automated-contact' ); ?></p>
			</div>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2><?php esc_html_e( 'Passo a passo', 'fsweb-ai-automated-contact' ); ?></h2>
				</div>
				<div class="fsweb-aic-card__body">
					<ol class="fsweb-aic-steps">
						<li>
							<strong><?php esc_html_e( 'Escolha a IA', 'fsweb-ai-automated-contact' ); ?></strong>
							<p><?php esc_html_e( 'Selecione OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq ou um endpoint compatível com OpenAI. Cole a chave de API do provedor escolhido.', 'fsweb-ai-automated-contact' ); ?></p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Escolha o modelo', 'fsweb-ai-automated-contact' ); ?></strong>
							<p><?php esc_html_e( 'A lista já vem pré-selecionada por provedor. Você pode digitar qualquer modelo específico, inclusive lançamentos recentes.', 'fsweb-ai-automated-contact' ); ?></p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Escreva o contexto da loja', 'fsweb-ai-automated-contact' ); ?></strong>
							<p><?php esc_html_e( 'Quanto mais contexto, melhor o atendimento: prazos, frete, pagamento, trocas, tom de voz e informações que a IA não deve inventar.', 'fsweb-ai-automated-contact' ); ?></p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Ative o WooCommerce no chat', 'fsweb-ai-automated-contact' ); ?></strong>
							<p><?php esc_html_e( 'Com o catálogo liberado, a IA busca produtos, mostra preço e disponibilidade, adiciona ao carrinho (mediante confirmação) e envia o cliente ao checkout.', 'fsweb-ai-automated-contact' ); ?></p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Teste e ative o widget', 'fsweb-ai-automated-contact' ); ?></strong>
							<p><?php esc_html_e( 'Use o teste de conexão, salve as configurações e marque "Exibir o chat no site". Abra a loja como visitante para conferir a experiência.', 'fsweb-ai-automated-contact' ); ?></p>
						</li>
					</ol>
					<p><a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Ir para as configurações', 'fsweb-ai-automated-contact' ); ?></a></p>
				</div>
			</div>

			<div class="fsweb-aic-card fsweb-aic-card--wide">
				<div class="fsweb-aic-card__header">
					<h2><?php esc_html_e( 'O que o atendente faz pela sua loja', 'fsweb-ai-automated-contact' ); ?></h2>
				</div>
				<div class="fsweb-aic-card__body">
					<ul class="fsweb-aic-list">
						<li><?php esc_html_e( 'Consulta o catálogo em tempo real: nome, preço, disponibilidade, variações e link do produto.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Confirma com o cliente antes de colocar qualquer item no carrinho.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Responde dúvidas sobre frete, pagamento, prazos e trocas usando o contexto que você escreveu.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Fala no idioma do cliente e mantém o tom de voz que você definiu.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'Encaminha o cliente para o checkout assim que o carrinho está pronto.', 'fsweb-ai-automated-contact' ); ?></li>
						<li><?php esc_html_e( 'As conversas ficam no navegador do visitante e não são armazenadas no servidor.', 'fsweb-ai-automated-contact' ); ?></li>
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
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'fsweb-ai-automated-contact' ) ) );
		}
	}

	/**
	 * AJAX handler — activate a license key.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function ajax_activate_license() {
		$this->guard_ajax();

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$result      = $this->license->activate( $license_key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * AJAX handler — deactivate the stored license key.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function ajax_deactivate_license() {
		$this->guard_ajax();

		wp_send_json_success( $this->license->deactivate() );
	}

	/**
	 * AJAX handler — validate/sync the license status.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function ajax_validate_license() {
		$this->guard_ajax();

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$result      = $this->license->validate( $license_key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
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

		if ( 'custom' === $provider_id ) {
			$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['base_url'] ) ) ) : '';
		}

		if ( ! $base_url ) {
			return new WP_Error(
				'fsweb_aic_missing_base_url',
				__( 'Provide the base URL of the OpenAI-compatible endpoint.', 'fsweb-ai-automated-contact' )
			);
		}

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
