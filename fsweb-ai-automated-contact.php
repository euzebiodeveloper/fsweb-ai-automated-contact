<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://flowsyncweb.com
 * @since             1.0.0
 * @package           Fsweb_Ai_Automated_Contact
 *
 * @wordpress-plugin
 * Plugin Name:       AI Automated Contact
 * Plugin URI:        https://flowsyncweb.com
 * Description:       AI-powered virtual assistant for WooCommerce: answers questions, finds products, builds the cart and takes the customer to checkout.
 * Version:           1.0.1
 * Author:            Flow Sync Web
 * Author URI:        https://flowsyncweb.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fsweb-ai-automated-contact
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_VERSION', '1.0.1' );

/**
 * Plugin slug. Used for the license API and the update checker.
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_SLUG', 'fsweb-ai-automated-contact' );

/**
 * Plugin directory path and URL (with trailing slash).
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSWEB_AI_AUTOMATED_CONTACT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Base URL for the license API (FSWEB license API).
 *
 * The client appends `/activate`, `/deactivate` and `/validate` to this
 * value; all three are served by the FSWEB license API (Laravel).
 *
 * Override in wp-config.php if needed:
 *   define( 'FSWEB_LICENSE_API_URL', 'https://yoursite.com/api/license' );
 */
if ( ! defined( 'FSWEB_LICENSE_API_URL' ) ) {
	define( 'FSWEB_LICENSE_API_URL', 'https://api.flowsyncweb.com/api/license' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-fsweb-ai-automated-contact-activator.php
 */
function activate_fsweb_ai_automated_contact() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact-activator.php';
	Fsweb_Ai_Automated_Contact_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-fsweb-ai-automated-contact-deactivator.php
 */
function deactivate_fsweb_ai_automated_contact() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact-deactivator.php';
	Fsweb_Ai_Automated_Contact_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_fsweb_ai_automated_contact' );
register_deactivation_hook( __FILE__, 'deactivate_fsweb_ai_automated_contact' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact.php';

/**
 * FSWEB Update Checker — automatic updates from your own server.
 *
 * Bundled per-plugin copy (own class name + namespace) so it can coexist
 * with the same library shipped by other FSWEB plugins.
 *
 * @see https://github.com/flowsyncweb/fsweb-update-checker
 */
require plugin_dir_path( __FILE__ ) . 'fsweb-update-checker/index.php';
$fsweb_ai_automated_contact_update_checker = new FSWEB_Update_Checker_AiAutomatedContact_1_0(
	'https://api.flowsyncweb.com/api/releases/' . FSWEB_AI_AUTOMATED_CONTACT_SLUG,
	__FILE__
);

// Set the license page URL so admin notices link to the correct page.
$fsweb_ai_automated_contact_update_checker->set_license_url(
	admin_url( 'admin.php?page=fsweb-ai-automated-contact-license' )
);

// Inject license key so the update-checker sends it with every request.
// Without a valid key the API returns 401 and updates are blocked.
$fsweb_ai_automated_contact_license_key = get_option( 'fsweb_aic_license_key', '' );
if ( ! empty( $fsweb_ai_automated_contact_license_key ) ) {
	$fsweb_ai_automated_contact_update_checker->set_license( $fsweb_ai_automated_contact_license_key );
}

/**
 * Reset the update checker caches after the license is activated.
 *
 * The update checker persists its metadata and error flags (license/server)
 * in wp_options. When a license is (re)activated, stale errors recorded under
 * the previous key must be wiped so they don't keep showing admin notices or
 * blocking the "Previous Versions" feature until the next successful fetch.
 *
 * @since 1.0.0
 * @return void
 */
function fsweb_ai_automated_contact_reset_update_checker_cache() {
	global $fsweb_ai_automated_contact_update_checker;

	if ( isset( $fsweb_ai_automated_contact_update_checker )
		&& is_object( $fsweb_ai_automated_contact_update_checker )
		&& method_exists( $fsweb_ai_automated_contact_update_checker, 'reset_cache' ) ) {
		$fsweb_ai_automated_contact_update_checker->reset_cache();
	}
}

/**
 * Whether the plugin license is currently active (valid).
 *
 * This is the single gate for everything the plugin loads on the front end
 * (chat widget, REST endpoints, WooCommerce tools). The admin screens for
 * license activation and onboarding stay available so the user can activate
 * the key.
 *
 * @since 1.0.0
 * @return bool True when the stored license status is "active".
 */
function fsweb_ai_automated_contact_license_is_active() {
	$active = ( 'active' === get_option( 'fsweb_aic_license_status', '' ) );

	/**
	 * Filter whether the plugin license is considered active.
	 *
	 * @since 1.0.0
	 * @param bool $active Whether the license is active.
	 */
	return (bool) apply_filters( 'fsweb_ai_automated_contact_license_is_active', $active );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_fsweb_ai_automated_contact() {

	$plugin = new Fsweb_Ai_Automated_Contact();
	$plugin->run();

}
run_fsweb_ai_automated_contact();
