<?php
/**
 * FSWEB Update Checker — Rollback Handler
 *
 * AJAX handlers for the version rollback feature:
 * - Previous Versions list (thickbox content)
 * - Install selected version (deactivate → delete → install → reactivate)
 * - Manual update check
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
 * Rollback Handler — AJAX endpoints for version rollback.
 */
class Rollback {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Nonce action identifier.
	 *
	 * @var string
	 */
	private $nonce_action;

	/**
	 * Metadata repository.
	 *
	 * @var Metadata
	 */
	private $metadata;

	/**
	 * Update manager.
	 *
	 * @var Updates
	 */
	private $updates;

	/**
	 * Admin UI.
	 *
	 * @var AdminUi
	 */
	private $admin_ui;

	/**
	 * Constructor.
	 *
	 * @param string   $slug            Plugin slug.
	 * @param string   $plugin_basename Plugin basename.
	 * @param Metadata $metadata        Metadata repository.
	 * @param Updates  $updates         Update manager.
	 * @param AdminUi  $admin_ui        Admin UI renderer.
	 */
	public function __construct( $slug, $plugin_basename, Metadata $metadata, Updates $updates, AdminUi $admin_ui ) {
		$this->slug            = $slug;
		$this->plugin_basename = $plugin_basename;
		$this->nonce_action    = 'fsweb_rollback_' . $slug;
		$this->metadata        = $metadata;
		$this->updates         = $updates;
		$this->admin_ui        = $admin_ui;
	}

	/**
	 * AJAX handler — renders the thickbox HTML with the version list.
	 *
	 * @return void
	 */
	public function ajax_previous_versions() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( -1 );
		}

		// Block access if no license key is set or license is invalid/expired.
		if ( ! $this->metadata->has_license() ) {
			$this->admin_ui->render_license_required_modal();
			wp_die();
		}

		$meta = $this->metadata->fetch_and_cache();
		if ( ! $meta ) {
			$meta = $this->metadata->get_cached();
		}

		// Double-check: if fetch returned error, don't show cached data.
		if ( ! $this->metadata->has_license() ) {
			$this->admin_ui->render_license_required_modal();
			wp_die();
		}

		$current_version = $this->updates->get_installed_version();
		$versions        = $this->build_versions_array( $meta );

		usort( $versions, array( $this, 'sort_versions_desc' ) );

		$this->admin_ui->render_versions_modal( $versions, $current_version );

		wp_die();
	}

	/**
	 * AJAX handler — installs the selected version via Plugin_Upgrader.
	 *
	 * @return void
	 */
	public function ajax_install_version() {
		check_ajax_referer( $this->nonce_action );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to install plugins.', 'fsweb-mercadopago' ),
			) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		if ( empty( $_POST['download_url'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'No download URL provided.', 'fsweb-mercadopago' ),
			) );
		}

		$download_url = esc_url_raw( wp_unslash( $_POST['download_url'] ) );
		$version      = isset( $_POST['version'] )
			? sanitize_text_field( wp_unslash( $_POST['version'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$was_active = is_plugin_active( $this->plugin_basename );

		$this->load_upgrade_classes();

		// 1. Deactivate if active.
		if ( $was_active ) {
			deactivate_plugins( $this->plugin_basename, true );
		}

		// 2. Delete current plugin files.
		$deleted = delete_plugins( array( $this->plugin_basename ) );
		if ( is_wp_error( $deleted ) ) {
			if ( $was_active ) {
				activate_plugin( $this->plugin_basename );
			}
			wp_send_json_error( array(
				'message' => $deleted->get_error_message(),
			) );
		}

		// 3. Install selected version.
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		$result   = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		if ( ! $result ) {
			wp_send_json_error( array(
				'message' => __( 'Installation failed. Check filesystem permissions.', 'fsweb-mercadopago' ),
			) );
		}

		// 4. Re-activate if it was active before.
		if ( $was_active ) {
			$activated = activate_plugin( $this->plugin_basename );
			if ( is_wp_error( $activated ) ) {
				wp_send_json_error( array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Version installed but could not re-activate: %s', 'fsweb-mercadopago' ),
						$activated->get_error_message()
					),
				) );
			}
		}

		wp_send_json_success( array(
			'message' => __( 'Version installed successfully!', 'fsweb-mercadopago' ),
			'version' => $version,
		) );
	}

	/**
	 * AJAX handler — performs a manual update check and returns the result.
	 *
	 * @return void
	 */
	public function ajax_check_for_updates() {
		check_ajax_referer( 'fsweb_check_updates_' . $this->slug );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( -1 );
		}

		// Block manual check if no license key is set or license is invalid/expired.
		if ( ! $this->metadata->has_license() ) {
			wp_send_json_error( array(
				'message' => __( 'A valid license key is required to check for updates.', 'fsweb-mercadopago' ),
			) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$force = isset( $_POST['force'] ) && ( '1' === $_POST['force'] || 'true' === $_POST['force'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Cache-first strategy: if not forced, serve cached metadata when
		// it's still fresh (within CACHE_TTL). This avoids an HTTP request
		// on every plugins.php page load. Manual clicks still force-refresh.
		$meta = null;
		if ( ! $force ) {
			$meta = $this->metadata->get_cached();
			if ( $meta ) {
				// Cache is fresh — clear any stale error flags from
				// previous transient failures so they don't block the
				// update injection on the next page load.
				$this->metadata->clear_errors();
			}
		}

		if ( ! $meta ) {
			$meta = $this->metadata->fetch_and_cache();

			if ( ! $meta ) {
				// Use the specific error from the API when available.
				$server_error  = $this->metadata->get_last_server_error();
				$license_error = $this->metadata->get_last_license_error();

				$error_message = $server_error ?: $license_error;

				// Translate known error codes to human-readable messages.
				if ( 'invalid_key' === $error_message || 'forbidden' === $error_message ) {
					$error_message = __( 'A valid license key is required to check for updates.', 'fsweb-mercadopago' );
				}

				wp_send_json_error( array(
					'message' => $error_message ?: __( 'Could not fetch update information.', 'fsweb-mercadopago' ),
				) );
			}
		}

		// Purge the update_plugins transient so WordPress picks up the fresh
		// metadata on the next transient check (no page reload required —
		// the JS injects the update row inline).
		delete_site_transient( 'update_plugins' );
		wp_clean_plugins_cache();

		$this->send_check_response( $meta );
	}

	/**
	 * Get the nonce action string.
	 *
	 * @return string
	 */
	public function get_nonce_action() {
		return $this->nonce_action;
	}

	// ------------------------------------------------------------------//
	//  Private helpers
	// ------------------------------------------------------------------//

	/**
	 * Build and send the JSON success response for an update check.
	 *
	 * Extracted so ajax_check_for_updates() can call it with either
	 * cached or freshly-fetched metadata.
	 *
	 * @param object $meta Decoded JSON metadata from the update API.
	 * @return void
	 */
	private function send_check_response( $meta ) {
		$current_version = $this->updates->get_installed_version();
		$has_update      = $current_version
			&& version_compare( $meta->version, $current_version, '>' );

		// Build the "View details" URL for the update notice.
		$details_url = add_query_arg(
			array(
				'tab'       => 'plugin-information',
				'plugin'    => dirname( $this->plugin_basename ),
				'section'   => 'changelog',
				'TB_iframe' => 'true',
				'width'     => 772,
				'height'    => 550,
			),
			admin_url( 'plugin-install.php' )
		);

		// Build the "Update now" URL.
		$update_url = add_query_arg(
			array(
				'action'   => 'upgrade-plugin',
				'plugin'   => $this->plugin_basename,
				'_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . $this->plugin_basename ),
			),
			admin_url( 'update.php' )
		);

		$plugin_name = $this->updates->get_plugin_name();

		wp_send_json_success( array(
			'has_update'      => $has_update,
			'slug'            => $this->slug,
			'latest_version'  => $meta->version,
			'current_version' => $current_version,
			'package_url'     => ! empty( $meta->download_url ) ? $meta->download_url : '',
			'plugin_name'     => $plugin_name,
			'plugin_basename' => $this->plugin_basename,
			'details_url'     => $details_url,
			'update_url'      => $update_url,
			'from_cache'      => true,
			'message'         => $has_update
				? sprintf(
					/* translators: %s: latest version number */
					__( 'Update available: version %s', 'fsweb-mercadopago' ),
					$meta->version
				)
				: __( 'You are running the latest version.', 'fsweb-mercadopago' ),
		) );

		wp_die();
	}

	/**
	 * Build a normalized versions array from metadata.
	 *
	 * @param object|null $meta Decoded JSON metadata.
	 * @return array
	 */
	private function build_versions_array( $meta ) {
		$has_license = $this->metadata->has_license();

		if ( $meta && ! empty( $meta->versions ) && is_array( $meta->versions ) ) {
			$versions = $meta->versions;
			// Strip download URLs if license is invalid (defense-in-depth).
			if ( ! $has_license ) {
				foreach ( $versions as &$v ) {
					if ( is_object( $v ) ) {
						$v->download_url = '';
					} elseif ( is_array( $v ) ) {
						$v['download_url'] = '';
					}
				}
			}
			return $versions;
		}

		if ( $meta && ! empty( $meta->version ) ) {
			return array(
				array(
					'version'      => $meta->version,
					'download_url' => $has_license && ! empty( $meta->download_url ) ? $meta->download_url : '',
					'date'         => ! empty( $meta->last_updated ) ? $meta->last_updated : '',
					'notes'        => '',
				),
			);
		}

		return array();
	}

	/**
	 * Sort versions in descending order.
	 *
	 * @param object|array $a First version.
	 * @param object|array $b Second version.
	 * @return int
	 */
	private function sort_versions_desc( $a, $b ) {
		return version_compare(
			is_object( $a ) ? $a->version : $a['version'],
			is_object( $b ) ? $b->version : $b['version'],
			'<'
		) ? 1 : -1;
	}

	/**
	 * Load WordPress upgrade classes if not already available.
	 *
	 * @return void
	 */
	private function load_upgrade_classes() {
		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
