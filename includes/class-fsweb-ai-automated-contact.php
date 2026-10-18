<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * The core plugin class.
 *
 * Wires internationalization, admin hooks, REST routes and the public chat
 * widget. The free version has no license gate: the basic feature set is
 * always available. The PRO add-on extends it through the plugin's filters.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 * @author     Flow Sync Web <euzebio.batista@flowsyncweb.com>
 */
class Fsweb_Ai_Automated_Contact {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Fsweb_Ai_Automated_Contact_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'FSWEB_AI_AUTOMATED_CONTACT_VERSION' ) ) {
			$this->version = FSWEB_AI_AUTOMATED_CONTACT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'fsweb-ai-automated-contact';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_rest_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-i18n.php';

		/**
		 * Settings schema and AI provider catalogue.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-settings.php';

		/**
		 * License client for the PRO add-on (the screen lives in this plugin).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-license.php';

		/**
		 * Provider-agnostic chat client.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-ai-client.php';

		/**
		 * WooCommerce tool set (read-only + add to cart).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-woo-tools.php';

		/**
		 * Chat orchestration and rate limiting.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-chat.php';

		/**
		 * REST endpoints for the widget.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-fsweb-ai-automated-contact-rest.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-fsweb-ai-automated-contact-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-fsweb-ai-automated-contact-public.php';

		$this->loader = new Fsweb_Ai_Automated_Contact_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Fsweb_Ai_Automated_Contact_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Fsweb_Ai_Automated_Contact_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'maybe_woocommerce_notice' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

		// Provider connectivity test.
		$this->loader->add_action( 'wp_ajax_fsweb_aic_test_provider', $plugin_admin, 'ajax_test_provider' );

		// License screen actions (activate, deactivate, recheck).
		$this->loader->add_action( 'admin_post_fsweb_aic_license', $plugin_admin, 'handle_license_action' );
	}

	/**
	 * Register the REST routes for the widget.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_rest_hooks() {

		$rest = new Fsweb_Ai_Automated_Contact_Rest();

		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the site.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Fsweb_Ai_Automated_Contact_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'render_widget' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Fsweb_Ai_Automated_Contact_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
