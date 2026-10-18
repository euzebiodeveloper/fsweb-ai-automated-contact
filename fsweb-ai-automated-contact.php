<?php

/**
 * The plugin bootstrap file.
 *
 * @link              https://flowsyncweb.com
 * @since             1.0.0
 * @package           Fsweb_Ai_Automated_Contact
 *
 * @wordpress-plugin
 * Plugin Name:       AI Automated Contact
 * Plugin URI:        https://flowsyncweb.com/ai-automated-contact/
 * Description:       AI-powered virtual assistant for WooCommerce: answers questions, finds products and guides the customer to the cart and checkout.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
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
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_VERSION', '1.0.0' );

/**
 * Plugin slug. Used for the settings page and the text domain.
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_SLUG', 'fsweb-ai-automated-contact' );

/**
 * Plugin directory path and URL (with trailing slash).
 */
define( 'FSWEB_AI_AUTOMATED_CONTACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSWEB_AI_AUTOMATED_CONTACT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Landing page of the PRO version.
 *
 * The free plugin never contains premium code: advanced features live in a
 * separate add-on plugin, as required by the WordPress.org plugin guidelines.
 * Override in wp-config.php to point at another page.
 */
if ( ! defined( 'FSWEB_AI_AUTOMATED_CONTACT_PRO_URL' ) ) {
	define( 'FSWEB_AI_AUTOMATED_CONTACT_PRO_URL', 'https://flowsyncweb.com/produto/ai-automated-contact-pro/' );
}

/**
 * The code that runs during plugin activation.
 */
function fsweb_ai_automated_contact_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact-activator.php';
	Fsweb_Ai_Automated_Contact_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function fsweb_ai_automated_contact_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact-deactivator.php';
	Fsweb_Ai_Automated_Contact_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'fsweb_ai_automated_contact_activate' );
register_deactivation_hook( __FILE__, 'fsweb_ai_automated_contact_deactivate' );

/**
 * The core plugin class.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-fsweb-ai-automated-contact.php';

/**
 * Whether the plugin features are enabled.
 *
 * The free version has no license gate: it is fully functional with the
 * basic feature set. The PRO add-on can hook this filter if it needs a
 * different behaviour.
 *
 * @since 1.0.0
 * @return bool
 */
function fsweb_ai_automated_contact_license_is_active() {
	/**
	 * Filter whether the plugin features are enabled.
	 *
	 * @since 1.0.0
	 * @param bool $active Always true in the free version.
	 */
	return (bool) apply_filters( 'fsweb_ai_automated_contact_features_enabled', true );
}

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function fsweb_ai_automated_contact_run() {
	$plugin = new Fsweb_Ai_Automated_Contact();
	$plugin->run();
}
fsweb_ai_automated_contact_run();
