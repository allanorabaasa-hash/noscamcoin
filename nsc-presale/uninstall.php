<?php
/**
 * Removing the plugin deletes its tables and settings. The blockchain is untouched.
 *
 * @package nsc-presale
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'tx', 'holders', 'reports' ) as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nsc_' . $t ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
foreach ( array( 'nsc_settings', 'nsc_db_version', 'nsc_last_block', 'nsc_sync_status', 'nsc_stats_last' ) as $o ) {
	delete_option( $o );
}
delete_transient( 'nsc_stats' );
wp_clear_scheduled_hook( 'nsc_sync_event' );
