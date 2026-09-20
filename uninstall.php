<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * Removes every option the plugin created, the rate-limit transients and the
 * update-checker cron. Chat conversations are never persisted server-side, so
 * there is no visitor data to delete.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$fsweb_aic_options = array(
	'fsweb_aic_settings',
	'fsweb_aic_license_key',
	'fsweb_aic_license_status',
	'fsweb_aic_license_expires',
	'fsweb_aic_onboarding_dismissed',
	'fsweb_updates_fsweb-ai-automated-contact',
	'fsweb_updates_fsweb-ai-automated-contact_readme',
	'fsweb_updates_fsweb-ai-automated-contact_error',
	'fsweb_updates_fsweb-ai-automated-contact_server_error',
);

foreach ( $fsweb_aic_options as $fsweb_aic_option ) {
	delete_option( $fsweb_aic_option );
}

// Clear update-checker cron events.
wp_clear_scheduled_hook( 'fsweb_check_updates_fsweb-ai-automated-contact' );

// Remove rate-limit transients for the current site.
global $wpdb;

$fsweb_aic_like = $wpdb->esc_like( '_transient_fsweb_aic_rl_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$fsweb_aic_like
	)
);

$fsweb_aic_like_timeout = $wpdb->esc_like( '_transient_timeout_fsweb_aic_rl_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$fsweb_aic_like_timeout
	)
);
