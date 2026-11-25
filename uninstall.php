<?php
/**
 * Uninstall: remove data only when the owner asked for it.
 *
 * @package MailWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mailwarden_settings = get_option( 'mailwarden_settings', array() );
if ( ! is_array( $mailwarden_settings ) || empty( $mailwarden_settings['delete_data'] ) || 'yes' !== $mailwarden_settings['delete_data'] ) {
	return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'mailwarden_log' ) );
foreach ( array( 'mailwarden_settings', 'mailwarden_db_version', 'mailwarden_alert' ) as $mailwarden_option ) {
	delete_option( $mailwarden_option );
}
delete_transient( 'mailwarden_dns' );
wp_clear_scheduled_hook( 'mailwarden_daily' );
