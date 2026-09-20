<?php
/**
 * FSWEB Update Checker — Assets Manager
 *
 * Enqueues external CSS and JS files for the admin screens.
 * No inline styles or scripts — everything is in real files under assets/.
 *
 * @package   FSWEB_Update_Checker
 * @version   1.0.0
 * @link      https://flowsyncweb.com
 * @license   GPL-2.0+
 */

namespace FSWEB\UpdateChecker\AiAutomatedContact\V1;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Assets Manager — enqueues admin CSS/JS files.
 */
class Assets {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin basename (e.g. 'my-plugin/my-plugin.php').
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Nonce action for AJAX.
	 *
	 * @var string
	 */
	private $nonce_action;

	/**
	 * URL to the assets directory (with trailing slash).
	 *
	 * @var string
	 */
	private $assets_url;

	/**
	 * Plugin version (for cache busting).
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $slug            Plugin slug.
	 * @param string $plugin_basename Plugin basename (e.g. 'my-plugin/my-plugin.php').
	 * @param string $nonce_action    Nonce action string.
	 * @param string $assets_url      URL to the assets/ directory.
	 * @param string $version         Plugin version (cache busting).
	 */
	public function __construct( $slug, $plugin_basename, $nonce_action, $assets_url, $version = '1.0.0' ) {
		$this->slug            = $slug;
		$this->plugin_basename = $plugin_basename;
		$this->nonce_action    = $nonce_action;
		$this->assets_url      = rtrim( $assets_url, '/\\' ) . '/';
		$this->version         = $version;
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * Hooked to `admin_enqueue_scripts`. Only loads on plugins.php
	 * and plugin-install.php screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'plugins.php', 'plugin-install.php' ), true ) ) {
			return;
		}

		if ( function_exists( 'add_thickbox' ) ) {
			add_thickbox();
		}

		wp_enqueue_style(
			'fsweb-update-checker-admin',
			$this->assets_url . 'css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'fsweb-update-checker-admin',
			$this->assets_url . 'js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'fsweb-update-checker-admin',
			'FSWEB_UpdateChecker_Rollback',
			array(
				'action'         => 'fsweb_install_version_' . $this->slug,
				'nonce'          => wp_create_nonce( $this->nonce_action ),
				'checkAction'    => 'fsweb_check_updates_' . $this->slug,
				'checkNonce'     => wp_create_nonce( 'fsweb_check_updates_' . $this->slug ),
				'pluginBasename' => $this->plugin_basename,
				'i18n'           => array(
					'installBtn'    => __( 'Install Selected Version', 'fsweb-mercadopago' ),
					'installing'    => __( 'Installing…', 'fsweb-mercadopago' ),
					'downloading'   => __( 'Downloading and installing version', 'fsweb-mercadopago' ),
					'networkError'  => __( 'Network error. Please try again.', 'fsweb-mercadopago' ),
					'checking'      => __( 'Checking for updates…', 'fsweb-mercadopago' ),
					'noLicense'     => __( 'A valid license key is required to check for updates.', 'fsweb-mercadopago' ),
					'latestVersion' => __( 'You are running the latest version.', 'fsweb-mercadopago' ),
				),
			)
		);
	}
}
