<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * Removes every option the plugin created, the rate-limit transients, the
 * update-checker cron and the conversation tables.
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
	'fsweb_aic_db_version',
);

foreach ( $fsweb_aic_options as $fsweb_aic_option ) {
	delete_option( $fsweb_aic_option );
}

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

// Conversation data is personal: drop the tables completely on uninstall.
$fsweb_aic_messages_table      = $wpdb->prefix . 'fsweb_aic_messages';
$fsweb_aic_conversations_table = $wpdb->prefix . 'fsweb_aic_conversations';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dropping the plugin's own tables on uninstall; the table name comes from $wpdb->prefix.
$wpdb->query( "DROP TABLE IF EXISTS {$fsweb_aic_messages_table}" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dropping the plugin's own tables on uninstall; the table name comes from $wpdb->prefix.
$wpdb->query( "DROP TABLE IF EXISTS {$fsweb_aic_conversations_table}" );
