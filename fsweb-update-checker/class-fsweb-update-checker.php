<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FSWEB Update Checker — Main Plugin Class
 *
 * Thin facade that wires dependencies and registers WordPress hooks.
 * All logic is delegated to focused classes following SOLID principles.
 *
 * Designed to be copied into any plugin — each plugin gets a unique
 * class name and namespace (derived from the plugin) so multiple
 * plugins can coexist without sharing class definitions.
 *
 * @version 1.0.0
 * @link    https://flowsyncweb.com
 * @license GPL-2.0+
 */

// Guard is for this plugin's own class — prevents a fatal on a duplicate require.
if ( ! class_exists( 'FSWEB_Update_Checker_AiAutomatedContact_1_0', false ) ) {

	/**
	 * FSWEB_Update_Checker_AiAutomatedContact_1_0 — plugin update checker for custom update servers.
	 *
	 * This plugin's own fork of the update-checker library (library version 1.0).
	 * To fork the library for another plugin, use a different class name and
	 * namespace (e.g. FSWEB_Update_Checker_OtherPlugin_1_0 and
	 * FSWEB\UpdateChecker\OtherPlugin\V1).
	 *
	 * Usage:
	 *   require_once plugin_dir_path( __FILE__ ) . 'fsweb-update-checker/index.php';
	 *   new FSWEB_Update_Checker_AiAutomatedContact_1_0( 'https://api.example.com/updates/plugin-slug.json', __FILE__ );
	 */
	class FSWEB_Update_Checker_AiAutomatedContact_1_0 {

		/**
		 * Plugin basename (e.g. 'my-plugin/my-plugin.php').
		 *
		 * @var string
		 */
		private $plugin_basename;

		/**
		 * Plugin slug.
		 *
		 * @var string
		 */
		private $slug;

		/**
		 * Hook suffix for namespacing hooks.
		 *
		 * @var string
		 */
		private $hook_suffix;

		/**
		 * Metadata repository.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\Metadata
		 */
		private $metadata;

		/**
		 * Update manager.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\Updates
		 */
		private $updates;

		/**
		 * Cron manager.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\Cron
		 */
		private $cron;

		/**
		 * Admin UI.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\AdminUi
		 */
		private $admin_ui;

		/**
		 * Rollback handler.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\Rollback
		 */
		private $rollback;

		/**
		 * Assets manager.
		 *
		 * @var \FSWEB\UpdateChecker\AiAutomatedContact\V1\Assets
		 */
		private $assets;

		/**
		 * License page URL — shown in admin notices.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		private $license_url = '';

		/**
		 * Option name for metadata cache.
		 *
		 * @var string
		 */
		private $option_name;

		/**
		 * Constructor.
		 *
		 * @param string $metadata_url  URL to the JSON metadata endpoint.
		 * @param string $plugin_file   Full path to the main plugin file (__FILE__).
		 * @param string $slug          Optional slug. Defaults to the plugin filename without .php.
		 * @param int    $check_period  How often to check (hours). 0 disables automatic checks. Default: 2.
		 */
		public function __construct( $metadata_url, $plugin_file, $slug = '', $check_period = 2 ) {
			$this->plugin_basename = plugin_basename( $plugin_file );
			$this->slug            = ! empty( $slug )
				? sanitize_title( $slug )
				: basename( $this->plugin_basename, '.php' );
			$this->hook_suffix     = $this->slug;
			$this->option_name     = 'fsweb_updates_' . $this->slug;

			// Resolve plugin directory URL for assets.
			$plugin_dir_url = plugin_dir_url( $plugin_file );
			$assets_url     = $plugin_dir_url . 'fsweb-update-checker/assets/';

			// ------------------------------------------------------------------//
			//  Wire dependencies
			// ------------------------------------------------------------------//
			$this->metadata = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Metadata(
				$metadata_url,
				$this->slug,
				$this->option_name
			);

			$this->updates = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Updates(
				$this->plugin_basename,
				$this->slug,
				$plugin_file,
				$this->metadata
			);

			$this->cron = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Cron(
				$check_period,
				$this->hook_suffix,
				array( $this, 'cron_check' )
			);

			$plugin_name = $this->updates->get_plugin_name();

			$this->admin_ui = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\AdminUi(
				$this->slug,
				$this->plugin_basename,
				$plugin_name,
				$assets_url,
				$this->metadata,
				$this->updates,
				'1.0.0'
			);

			$this->rollback = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Rollback(
				$this->slug,
				$this->plugin_basename,
				$this->metadata,
				$this->updates,
				$this->admin_ui
			);

			$nonce_action = $this->rollback->get_nonce_action();

			$this->assets = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Assets(
				$this->slug,
				$this->plugin_basename,
				$nonce_action,
				$assets_url,
				'1.0.0'
			);

			// ------------------------------------------------------------------//
			//  Slug uniqueness guard
			// ------------------------------------------------------------------//
			$slug_filter  = 'fsweb_slug_in_use_' . $this->slug;
			$slug_used_by = apply_filters( $slug_filter, false );

			if ( $slug_used_by ) {
				$message = sprintf(
					/* translators: %1$s is the slug, %2$s is the path of the plugin that already uses it. */
					__( 'Plugin slug "%1$s" is already in use by %2$s. Each plugin must have a unique slug.', 'fsweb-ai-automated-contact' ),
					$this->slug,
					$slug_used_by
				);
				wp_die(
					esc_html( $message ),
					esc_html__( 'Update Checker Conflict', 'fsweb-ai-automated-contact' ),
					array( 'response' => 500 )
				);
			}
			add_filter( $slug_filter, array( $this, 'get_plugin_basename' ) );

			// ------------------------------------------------------------------//
			//  Register WordPress hooks
			// ------------------------------------------------------------------//

			// Inject update data into the WordPress update transient.
			add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );

			// Intercept plugins_api() calls for plugin information.
			add_filter( 'plugins_api', array( $this, 'inject_plugin_info' ), 20, 3 );

			// Schedule cron for periodic checks.
			$this->cron->schedule();

			// Cleanup on plugin uninstall.
			add_action( 'uninstall_' . $this->plugin_basename, array( $this, 'cleanup' ) );

			// Clear our cache after a successful plugin update so stale
			// metadata doesn't linger.
			add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10, 2 );

			// Admin-only hooks.
			if ( is_admin() ) {
				// Auto-refresh the plugins page after a successful
				// update via update.php (the standard "update now" flow).
				add_action( 'admin_head-update.php', array( $this, 'auto_refresh_after_update' ) );

				// Plugin row links.
				add_filter(
					'plugin_row_meta',
					array( $this, 'add_plugin_row_links' ),
					10,
					2
				);

				// AJAX — previous versions thickbox.
				add_action(
					'wp_ajax_fsweb_previous_versions_' . $this->slug,
					array( $this, 'ajax_previous_versions' )
				);

				// AJAX — install selected version.
				add_action(
					'wp_ajax_fsweb_install_version_' . $this->slug,
					array( $this, 'ajax_install_version' )
				);

				// AJAX — manual update check.
				add_action(
					'wp_ajax_fsweb_check_updates_' . $this->slug,
					array( $this, 'ajax_check_for_updates' )
				);

				// Enqueue admin assets.
				add_action(
					'admin_enqueue_scripts',
					array( $this, 'enqueue_admin_assets' )
				);

				// License notice inside the plugin's update row (where WordPress
				// shows plugin update notifications). No top-of-admin notice.
				add_action(
					'after_plugin_row_' . $this->plugin_basename,
					array( $this, 'license_row_notice' ),
					10,
					3
				);
			}
		}

		// ------------------------------------------------------------------//
		//  Public API
		// ------------------------------------------------------------------//

		/**
		 * Set the license key so every update request includes it.
		 *
		 * Also stores the installed plugin version for the POST body.
		 * Call this before the first cron or transient check fires.
		 *
		 * @param string $license_key The license key.
		 * @return void
		 */
		public function set_license( $license_key ) {
			$this->metadata->set_license( $license_key );
			$this->metadata->set_plugin_version( $this->updates->get_installed_version() );
		}

		/**
		 * Set the license page URL shown in admin notices.
		 *
		 * @since 1.0.0
		 * @param string $url Full admin URL to the license settings page.
		 * @return void
		 */
		public function set_license_url( $url ) {
			$this->license_url = esc_url_raw( $url );
		}

		/**
		 * Get the last license error, if any.
		 *
		 * @return string 'invalid_key', 'forbidden', or empty string.
		 */
		public function get_license_error() {
			return $this->metadata->get_last_license_error();
		}

		/**
		 * Force an immediate update check.
		 *
		 * @return object|null Decoded JSON metadata, or null on failure.
		 */
		public function check_for_updates() {
			return $this->metadata->fetch_and_cache();
		}

		/**
		 * Reset every cache this update checker maintains.
		 *
		 * Deletes cached metadata, details, the last-check timestamp, the
		 * license/server error flags, the README transient and the WordPress
		 * update_plugins transient. Called when the license is activated so
		 * stale errors recorded under a previous (invalid/expired) key never
		 * keep blocking updates or the "Previous Versions" feature.
		 *
		 * @since 1.2.0
		 * @return void
		 */
		public function reset_cache() {
			$this->metadata->clear_cache();
			$this->metadata->clear_readme_cache();
			delete_site_transient( 'update_plugins' );
			wp_clean_plugins_cache();
		}

		/**
		 * Get the plugin basename (used by the slug uniqueness filter).
		 *
		 * @return string
		 */
		public function get_plugin_basename() {
			return $this->plugin_basename;
		}

		/**
		 * Remove all hooks and scheduled crons. Called on plugin uninstall.
		 *
		 * @return void
		 */
		public function cleanup() {
			$this->remove_hooks();
			$this->cron->clear();
			$this->metadata->clear_cache();
		}

		/**
		 * Clear our metadata cache after WordPress completes a plugin
		 * update, install, or delete. This prevents stale download URLs
		 * and version numbers from persisting after the plugin changes.
		 *
		 * @since 1.1.0
		 * @param \WP_Upgrader $upgrader   Upgrader instance.
		 * @param array        $hook_extra Extra hook data (type, plugin, etc.).
		 * @return void
		 */
		public function on_upgrader_complete( $upgrader, $hook_extra ) {
			if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
				return;
			}

			// Check if our plugin was affected.
			$plugins = array();
			if ( ! empty( $hook_extra['plugin'] ) ) {
				$plugins[] = $hook_extra['plugin'];
			} elseif ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
				$plugins = $hook_extra['plugins'];
			}

			if ( ! in_array( $this->plugin_basename, $plugins, true ) ) {
				return;
			}

			// Only clear cache on SUCCESS — not on failure.
			if ( isset( $upgrader->skin->result ) && is_wp_error( $upgrader->skin->result ) ) {
				return;
			}

			// Clear everything so the next transient check does a fresh fetch.
			$this->metadata->clear_cache();
			delete_site_transient( 'update_plugins' );
			wp_clean_plugins_cache();
		}

		/**
		 * Inject a small script on update.php that auto-redirects to
		 * plugins.php after a successful upgrade.
		 *
		 * Hooked to admin_head-update.php — runs only on the WordPress
		 * core update screen. When the page shows a success message
		 * (no errors), the user is redirected back to the plugins list
		 * after a short delay so they can see the updated status.
		 *
		 * @since 1.1.1
		 * @return void
		 */
		public function auto_refresh_after_update() {
			?>
			<script type="text/javascript">
			jQuery( function( $ ) {
				if ( $( '.updated, .notice-success, #message.updated, div.updated, .update-message.notice-success' ).length ) {
					setTimeout( function() {
						window.location.href = '<?php echo esc_js( admin_url( 'plugins.php' ) ); ?>';
					}, 1500 );
				}
			} );
			</script>
			<?php
		}

		// ------------------------------------------------------------------//
		//  WordPress Hook Callbacks (thin delegators)
		// ------------------------------------------------------------------//

		/**
		 * Inject update data into the site_transient_update_plugins.
		 *
		 * License errors (401/403) always block updates — the license is
		 * invalid or expired and the remote API won't serve downloads.
		 *
		 * Server errors (network issues, HTTP 5xx) only block updates when
		 * there is NO valid cached metadata to fall back on. A stale
		 * server_error from a previous transient failure must not prevent
		 * serving a cached update that was fetched successfully before
		 * the error occurred.
		 *
		 * @param \stdClass $transient The update_plugins transient value.
		 * @return \stdClass
		 */
		public function inject_update( $transient ) {
			// License errors are permanent — always block.
			$license_error = $this->metadata->get_last_license_error();
			if ( ! empty( $license_error ) ) {
				return $transient;
			}

			// Server errors are transient — only block if there's no
			// valid cached metadata to fall back on.
			$server_error = $this->metadata->get_last_server_error();
			if ( ! empty( $server_error ) ) {
				$cached = $this->metadata->get_cached();
				if ( ! $cached ) {
					return $transient;
				}
				// Cache is valid — clear the stale error so it doesn't
				// keep blocking future requests.
				$this->metadata->clear_errors();
			}

			return $this->updates->inject_into_transient( $transient );
		}

		/**
		 * Intercept plugins_api() calls for plugin information.
		 *
		 * Standard "View Details" uses local README.txt + plugin headers.
		 * When an update is available (remote version > installed), the
		 * "View version X.Y.Z details" link triggers a remote README fetch
		 * from the API so the user sees the changelog of the latest release.
		 *
		 * @param mixed  $result Default result.
		 * @param string $action API action.
		 * @param object $args   Request arguments.
		 * @return mixed Plugin info object or original result.
		 */
		public function inject_plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
				return $result;
			}

			if ( ! $this->updates->is_our_slug( $args->slug ) ) {
				return $result;
			}

			// Determine which link was clicked:
			// - "View version X.Y.Z details" (update notice) includes section=changelog
			// - "View Details" (standard plugin row link) does NOT include section
			$is_update_view = isset( $_REQUEST['section'] );

			if ( $is_update_view ) {
				// Update notice — fetch remote README when there's an update.
				$meta = $this->metadata->get_cached();
				if ( $meta && ! empty( $meta->version ) ) {
					$installed = $this->updates->get_installed_version();
					if ( $installed && version_compare( $meta->version, $installed, '>' ) ) {
						$readme = $this->metadata->fetch_readme();
						if ( null !== $readme ) {
							return $this->updates->build_remote_plugin_info( $readme );
						}

						// API unreachable — don't fall back to local README
						// (that would show the old version's content, which
						// is misleading when the user clicked "View version X.Y.Z").
						return new \WP_Error(
							'fsweb_readme_unavailable',
							__( 'Update information is temporarily unavailable. Please try again in a few moments.', 'fsweb-ai-automated-contact' )
						);
					}
				}
			}

			// Standard "View Details": local data. No API call, ever.
			return $this->updates->build_local_plugin_info();
		}

		/**
		 * Cron callback — triggered by wp_cron to check for updates.
		 *
		 * @return void
		 */
		public function cron_check() {
			$this->metadata->fetch_and_cache();
		}

		/**
		 * Add custom links to the plugin's second row.
		 *
		 * @param array  $links Existing meta links.
		 * @param string $file  Plugin basename.
		 * @return array
		 */
		public function add_plugin_row_links( $links, $file ) {
			return $this->admin_ui->add_row_links( $links, $file );
		}

		/**
		 * AJAX handler — renders the previous versions thickbox.
		 *
		 * @return void
		 */
		public function ajax_previous_versions() {
			$this->rollback->ajax_previous_versions();
		}

		/**
		 * AJAX handler — installs a selected version.
		 *
		 * @return void
		 */
		public function ajax_install_version() {
			$this->rollback->ajax_install_version();
		}

		/**
		 * AJAX handler — performs a manual update check.
		 *
		 * @return void
		 */
		public function ajax_check_for_updates() {
			$this->rollback->ajax_check_for_updates();
		}

		/**
		 * Enqueue admin CSS and JS assets.
		 *
		 * @param string $hook_suffix Current admin page hook suffix.
		 * @return void
		 */
		public function enqueue_admin_assets( $hook_suffix ) {
			$this->assets->enqueue( $hook_suffix );
		}

		/**
		 * License notice rendered inside the plugin's row on the Plugins
		 * screen — the same place WordPress shows plugin update notifications.
		 *
		 * A rejected credential (401 invalid key, 403 domain mismatch or
		 * expired key) always wins: when a key is configured the message
		 * reports it as invalid or expired, and the generic "server
		 * unreachable" warning is suppressed so it cannot mask the real cause.
		 * With no key configured the user is invited to activate a license.
		 *
		 * Nothing is printed at the top of the admin (no admin_notices).
		 *
		 * @param string $plugin_file Plugin file (unused).
		 * @param array  $plugin_data Plugin data (unused).
		 * @param string $status      Plugin status (unused).
		 * @return void
		 */
		public function license_row_notice( $plugin_file = '', $plugin_data = array(), $status = '' ) {
			$plugin_name   = $this->updates->get_plugin_name();
			$license_error = $this->metadata->get_last_license_error();
			$server_error  = $this->metadata->get_last_server_error();
			$has_key       = $this->metadata->has_license_key();

			$type    = 'error';
			$message = '';

			// A rejected credential is the priority — never mask it with the
			// generic "server unreachable" warning.
			if ( ! empty( $license_error ) ) {
				$message = $has_key
					? sprintf(
						/* translators: 1: plugin name, 2: opening link tag, 3: closing link tag */
						__(
							'%1$s: the license credential is invalid or expired. %2$sCheck your license%3$s to resume automatic updates.',
							'fsweb-ai-automated-contact'
						),
						'<strong>' . esc_html( $plugin_name ) . '</strong>',
						$this->license_url ? '<a href="' . esc_url( $this->license_url ) . '">' : '',
						$this->license_url ? '</a>' : ''
					)
					: sprintf(
						/* translators: 1: plugin name, 2: opening link tag, 3: closing link tag */
						__(
							'%1$s is not activated. %2$sPlease activate your license%3$s to receive automatic updates.',
							'fsweb-ai-automated-contact'
						),
						'<strong>' . esc_html( $plugin_name ) . '</strong>',
						$this->license_url ? '<a href="' . esc_url( $this->license_url ) . '">' : '',
						$this->license_url ? '</a>' : ''
					);
			} elseif ( ! empty( $server_error ) && ! $has_key ) {
				// Transient server failure — only actionable when there is no
				// key yet. With a key configured it must not replace the
				// credential message.
				$type    = 'warning';
				$message = '<strong>' . esc_html( $plugin_name ) . ':</strong> ' . esc_html( $server_error );
			}

			if ( '' === $message ) {
				return;
			}

			global $wp_list_table;
			$columns = ( is_object( $wp_list_table ) && method_exists( $wp_list_table, 'get_column_count' ) )
				? $wp_list_table->get_column_count()
				: 4;

			printf(
				'<tr class="plugin-update-tr fsweb-license-notice" id="%1$s-license-notice" data-slug="%1$s" data-plugin="%2$s"><td colspan="%3$d" class="plugin-update colspanchange"><div class="update-message notice inline notice-%4$s notice-alt"><p>%5$s</p></div></td></tr>',
				esc_attr( $this->slug ),
				esc_attr( $this->plugin_basename ),
				(int) $columns,
				esc_attr( $type ),
				wp_kses(
					$message,
					array(
						'strong' => array(),
						'a'      => array( 'href' => array() ),
					)
				)
			);
		}

		//  Cleanup helpers
		// ------------------------------------------------------------------//

		/**
		 * Remove all WordPress hooks registered by this instance.
		 *
		 * @return void
		 */
		private function remove_hooks() {
			remove_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
			remove_filter( 'plugins_api', array( $this, 'inject_plugin_info' ), 20 );
			remove_action( 'fsweb_check_updates_' . $this->hook_suffix, array( $this, 'cron_check' ) );
			remove_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_links' ), 10 );
			remove_action( 'wp_ajax_fsweb_previous_versions_' . $this->slug, array( $this, 'ajax_previous_versions' ) );
			remove_action( 'wp_ajax_fsweb_install_version_' . $this->slug, array( $this, 'ajax_install_version' ) );
			remove_action( 'wp_ajax_fsweb_check_updates_' . $this->slug, array( $this, 'ajax_check_for_updates' ) );
			remove_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			remove_action( 'after_plugin_row_' . $this->plugin_basename, array( $this, 'license_row_notice' ), 10 );
			remove_filter( 'cron_schedules', array( $this->cron, 'register_schedule' ) );
			remove_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10 );
		}
	}
}
