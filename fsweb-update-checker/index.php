<?php
/**
 * FSWEB Update Checker — Entry Point
 *
 * Drop this entire folder into your plugin and require this file.
 *
 * Each plugin that bundles this library gets its own class name and
 * namespace derived from the plugin (FSWEB_Update_Checker_AiAutomatedContact_1_0
 * and FSWEB\UpdateChecker\AiAutomatedContact\V1), so multiple plugins can be
 * active at the same time without sharing class definitions.
 *
 * @version 1.0.0
 * @link    https://flowsyncweb.com
 * @license GPL-2.0+
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// ------------------------------------------------------------------//
//  Same-plugin dedup — if this plugin's update-checker class already
//  exists (e.g. a duplicate require), we must not redeclare the
//  internal namespaced classes (would cause a fatal).
// ------------------------------------------------------------------//
if ( class_exists( 'FSWEB_Update_Checker_AiAutomatedContact_1_0', false ) ) {
	return;
}

// ------------------------------------------------------------------//
//  Autoloader — maps FSWEB\UpdateChecker\AiAutomatedContact\V1 namespace to includes/
// ------------------------------------------------------------------//
require_once __DIR__ . '/includes/class-auto-loader.php';

$fsweb_autoloader = new \FSWEB\UpdateChecker\AiAutomatedContact\V1\Autoloader( __DIR__ . '/includes' );
$fsweb_autoloader->register();

// ------------------------------------------------------------------//
//  Main library class
// ------------------------------------------------------------------//
require_once __DIR__ . '/class-fsweb-update-checker.php';
