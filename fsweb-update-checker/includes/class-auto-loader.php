<?php
/**
 * FSWEB Update Checker — PSR-4-like Autoloader
 *
 * Maps the FSWEB\UpdateChecker\AiAutomatedContact\V1 namespace to the includes/ directory.
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
 * Simple autoloader for the V1 namespace.
 */
final class Autoloader {

	/**
	 * Base directory for the namespace.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Absolute path to the includes/ directory.
	 */
	public function __construct( $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
	}

	/**
	 * Register this autoloader with SPL.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'load_class' ) );
	}

	/**
	 * Unregister this autoloader.
	 *
	 * @return void
	 */
	public function unregister() {
		spl_autoload_unregister( array( $this, 'load_class' ) );
	}

	/**
	 * Load the class file for a given fully-qualified class name.
	 *
	 * @param string $fqcn Fully-qualified class name.
	 * @return void
	 */
	public function load_class( $fqcn ) {
		$prefix = 'FSWEB\\UpdateChecker\\AiAutomatedContact\\V1\\';
		if ( 0 !== strpos( $fqcn, $prefix ) ) {
			return;
		}

		$relative_class = substr( $fqcn, strlen( $prefix ) );
		// Convert camelCase to kebab-case, then underscores to hyphens.
		$relative_class = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $relative_class );
		$relative_class = strtolower( str_replace( '_', '-', $relative_class ) );
		$parts          = explode( '\\', $relative_class );
		$class_name     = array_pop( $parts );
		$sub_dir        = ! empty( $parts ) ? implode( '/', $parts ) . '/' : '';

		$file = $this->base_dir . $sub_dir . 'class-' . $class_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
