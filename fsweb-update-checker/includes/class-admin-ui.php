<?php
/**
 * FSWEB Update Checker — Admin UI
 *
 * Responsible for rendering plugin row links and the "Previous Versions"
 * thickbox HTML. Does NOT inject inline <style> — CSS is served via
 * enqueued stylesheets.
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
 * Admin UI — plugin row links and thickbox rendering.
 */
class AdminUi {

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
	 * Plugin display name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * URL to the assets directory (with trailing slash).
	 *
	 * @var string
	 */
	private $assets_url;

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
	 * Plugin version (for cache busting).
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string   $slug            Plugin slug.
	 * @param string   $plugin_basename Plugin basename.
	 * @param string   $plugin_name     Plugin display name.
	 * @param string   $assets_url      URL to the assets/ directory.
	 * @param Metadata $metadata        Metadata repository.
	 * @param Updates  $updates         Update manager.
	 * @param string   $version         Plugin version for cache busting.
	 */
	public function __construct( $slug, $plugin_basename, $plugin_name, $assets_url, Metadata $metadata, Updates $updates, $version = '1.0.0' ) {
		$this->slug            = $slug;
		$this->plugin_basename = $plugin_basename;
		$this->plugin_name     = $plugin_name;
		$this->assets_url      = rtrim( $assets_url, '/\\' ) . '/';
		$this->metadata        = $metadata;
		$this->updates         = $updates;
		$this->version         = $version;
	}

	/**
	 * Add custom links to the plugin's second row (plugin_row_meta filter).
	 *
	 * @param array  $links Existing meta links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function add_row_links( $links, $file ) {
		if ( $file !== $this->plugin_basename ) {
			return $links;
		}

		// "View Details" — thickbox link.
		$view_url = add_query_arg(
			array(
				'tab'       => 'plugin-information',
				'plugin'    => dirname( $this->plugin_basename ),
				'TB_iframe' => 'true',
				'width'     => 772,
				'height'    => 550,
			),
			admin_url( 'plugin-install.php' )
		);
		$view_link = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
			esc_url( $view_url ),
			/* translators: %s: plugin name */
			esc_attr( sprintf( __( 'View details for %s', 'fsweb-ai-automated-contact' ), $this->plugin_name ) ),
			esc_html__( 'View Details', 'fsweb-ai-automated-contact' )
		);

		// Replace any existing "View Details" link.
		$has_view_details = false;
		foreach ( $links as $k => $v ) {
			if ( is_string( $v ) && false !== strpos( $v, 'open-plugin-details-modal' ) ) {
				$links[ $k ]      = $view_link;
				$has_view_details = true;
				break;
			}
		}
		if ( ! $has_view_details ) {
			$links['view-details'] = $view_link;
		}

		// "Check for Updates" — AJAX manual check (JS intercepts the click).
		$links['fsweb-check'] = sprintf(
			'<a href="#" class="fsweb-check-updates" data-action="%s" data-nonce="%s">%s</a>',
			esc_attr( 'fsweb_check_updates_' . $this->slug ),
			esc_attr( wp_create_nonce( 'fsweb_check_updates_' . $this->slug ) ),
			esc_html__( 'Check for Updates', 'fsweb-ai-automated-contact' )
		);

		// "Previous Versions" — thickbox modal.
		$prev_url = add_query_arg(
			array(
				'action'    => 'fsweb_previous_versions_' . $this->slug,
				'TB_iframe' => 'true',
				'width'     => 772,
				'height'    => 550,
			),
			admin_url( 'admin-ajax.php' )
		);
		$links['fsweb-versions'] = sprintf(
			'<a href="%s" class="thickbox" title="%s">%s</a>',
			esc_url( $prev_url ),
			esc_attr(
				sprintf(
					/* translators: %s: plugin name */
					__( '%s — Previous Versions', 'fsweb-ai-automated-contact' ),
					$this->plugin_name
				)
			),
			esc_html__( 'Previous Versions', 'fsweb-ai-automated-contact' )
		);

		return $links;
	}

	/**
	 * Render the "Previous Versions" thickbox HTML.
	 *
	 * @param array  $versions        List of version objects/arrays.
	 * @param string $current_version Currently installed version.
	 * @return void
	 */
	public function render_versions_modal( $versions, $current_version ) {
		?>
		<!DOCTYPE html>
		<html><head>
			<title>
				<?php echo esc_html( $this->plugin_name ); ?>
				&mdash;
				<?php esc_html_e( 'Previous Versions', 'fsweb-ai-automated-contact' ); ?>
			</title>
			<?php wp_admin_css( 'common' ); ?>
			<?php wp_admin_css( 'forms' ); ?>
			<?php $this->enqueue_thickbox_assets(); ?>
			<?php wp_print_styles( 'fsweb-update-checker-admin' ); ?>
		</head><body class="fsweb-rollback-body">
			<?php wp_print_scripts( 'fsweb-update-checker-admin' ); ?>
			<h2>
				<?php echo esc_html( $this->plugin_name ); ?>
				&mdash;
				<?php esc_html_e( 'Previous Versions', 'fsweb-ai-automated-contact' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'Select a version to install. The current version is highlighted in green.', 'fsweb-ai-automated-contact' ); ?>
			</p>

			<?php if ( empty( $versions ) ) : ?>
				<p><?php esc_html_e( 'No version history available.', 'fsweb-ai-automated-contact' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped rollback-table">
					<thead>
						<tr>
							<th width="50"><?php esc_html_e( 'Select', 'fsweb-ai-automated-contact' ); ?></th>
							<th><?php esc_html_e( 'Version', 'fsweb-ai-automated-contact' ); ?></th>
							<th><?php esc_html_e( 'Date', 'fsweb-ai-automated-contact' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $versions as $ver ) : ?>
							<?php
							$ver_obj    = is_object( $ver ) ? $ver : (object) $ver;
							$is_current = $current_version
								&& version_compare( $ver_obj->version, $current_version, '=' );
							?>
							<tr class="<?php echo $is_current ? 'current-version' : ''; ?>">
								<td>
									<input
										type="radio"
										name="fsweb_version"
										value="<?php echo esc_url( $ver_obj->download_url ); ?>"
										data-version="<?php echo esc_attr( $ver_obj->version ); ?>"
										<?php disabled( $is_current ); ?>>
								</td>
								<td>
									<strong><?php echo esc_html( $ver_obj->version ); ?></strong>
									<?php if ( $is_current ) : ?>
										<span class="current-label">
											&mdash; <?php esc_html_e( 'installed', 'fsweb-ai-automated-contact' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $this->format_date( $ver_obj->date ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="rollback-footer">
					<div>
						<button class="button button-primary" id="fsweb-install-version" disabled>
							<?php esc_html_e( 'Install Selected Version', 'fsweb-ai-automated-contact' ); ?>
						</button>
					</div>
					<div>
						<button class="button" onclick="self.parent.tb_remove();">
							<?php esc_html_e( 'Close', 'fsweb-ai-automated-contact' ); ?>
						</button>
					</div>
				</div>

				<div id="fsweb-install-result"></div>
			<?php endif; ?>
		</body></html>
		<?php
	}

	/**
	 * Render a "License Required" thickbox modal.
	 *
	 * Shown when the user attempts to access Previous Versions without
	 * a valid license key.
	 *
	 * @return void
	 */
	public function render_license_required_modal() {
		?>
		<!DOCTYPE html>
		<html><head>
			<title>
				<?php echo esc_html( $this->plugin_name ); ?>
				&mdash;
				<?php esc_html_e( 'License Required', 'fsweb-ai-automated-contact' ); ?>
			</title>
			<?php wp_admin_css( 'common' ); ?>
			<?php wp_admin_css( 'forms' ); ?>
			<?php $this->enqueue_thickbox_assets(); ?>
			<?php wp_print_styles( 'fsweb-update-checker-admin' ); ?>
		</head><body class="fsweb-rollback-body">
			<?php wp_print_scripts( 'fsweb-update-checker-admin' ); ?>
			<h2>
				<?php echo esc_html( $this->plugin_name ); ?>
				&mdash;
				<?php esc_html_e( 'License Required', 'fsweb-ai-automated-contact' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'A valid license key is required to access previous versions.', 'fsweb-ai-automated-contact' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Please activate your license on the plugin settings page to unlock this feature.', 'fsweb-ai-automated-contact' ); ?>
			</p>
			<div class="rollback-footer">
				<button class="button" onclick="self.parent.tb_remove();">
					<?php esc_html_e( 'Close', 'fsweb-ai-automated-contact' ); ?>
				</button>
			</div>
		</body></html>
		<?php
	}

	/**
	 * Enqueue CSS and JS assets for the thickbox iframe.
	 *
	 * Called inside render_versions_modal() and render_license_required_modal()
	 * so the thickbox popup has its own copy of the stylesheet and scripts.
	 * Without this, the "Install Selected Version" button stays disabled
	 * because the JS that handles radio selection never loads.
	 *
	 * @since 1.1.1
	 * @return void
	 */
	private function enqueue_thickbox_assets() {

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
				'nonce'          => wp_create_nonce( 'fsweb_rollback_' . $this->slug ),
				'checkAction'    => 'fsweb_check_updates_' . $this->slug,
				'checkNonce'     => wp_create_nonce( 'fsweb_check_updates_' . $this->slug ),
				'pluginBasename' => $this->plugin_basename,
				'i18n'           => array(
					'installBtn'    => __( 'Install Selected Version', 'fsweb-ai-automated-contact' ),
					'installing'    => __( 'Installing…', 'fsweb-ai-automated-contact' ),
					'downloading'   => __( 'Downloading and installing version', 'fsweb-ai-automated-contact' ),
					'networkError'  => __( 'Network error. Please try again.', 'fsweb-ai-automated-contact' ),
					'checking'      => __( 'Checking for updates…', 'fsweb-ai-automated-contact' ),
					'noLicense'     => __( 'A valid license key is required to check for updates.', 'fsweb-ai-automated-contact' ),
					'latestVersion' => __( 'You are running the latest version.', 'fsweb-ai-automated-contact' ),
				),
			)
		);
	}

	/**
	 * Format a date string for display.
	 *
	 * Converts ISO 8601 or other parseable dates to a human-readable format.
	 * Falls back to the original string if parsing fails.
	 *
	 * @param string $raw_date Raw date string (e.g. "2026-07-14T02:48:02Z").
	 * @return string
	 */
	private function format_date( $raw_date ) {
		if ( empty( $raw_date ) ) {
			return '&mdash;';
		}

		$timestamp = strtotime( $raw_date );

		if ( false === $timestamp ) {
			return esc_html( $raw_date );
		}

		return esc_html(
			wp_date(
				/* translators: date format for version release date */
				__( 'F j, Y', 'fsweb-ai-automated-contact' ),
				$timestamp
			)
		);
	}
}
