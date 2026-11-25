<?php
/**
 * Data: the email log.
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One table. Recipients are stored shortened, bodies are never stored.
 */
class MailWarden_Store {

	/**
	 * Log table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mailwarden_log';
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email_id varchar(60) NOT NULL DEFAULT '',
			email_title varchar(120) NOT NULL DEFAULT '',
			recipient varchar(120) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'sent',
			error varchar(255) NOT NULL DEFAULT '',
			resent tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY status_created (status, created_at)
		) {$charset};"
		);
		update_option( 'mailwarden_db_version', MAILWARDEN_DB_VERSION, false );
	}

	/**
	 * Add a row.
	 *
	 * @param array $row Row.
	 * @return void
	 */
	public static function add( $row ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table, write.
		$wpdb->insert(
			self::table(),
			array(
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
				'order_id'    => absint( $row['order_id'] ),
				'email_id'    => substr( sanitize_key( $row['email_id'] ), 0, 60 ),
				'email_title' => substr( sanitize_text_field( $row['email_title'] ), 0, 120 ),
				'recipient'   => substr( sanitize_text_field( $row['recipient'] ), 0, 120 ),
				'subject'     => substr( sanitize_text_field( $row['subject'] ), 0, 255 ),
				'status'      => 'failed' === $row['status'] ? 'failed' : 'sent',
				'error'       => substr( sanitize_text_field( $row['error'] ), 0, 255 ),
				'resent'      => empty( $row['resent'] ) ? 0 : 1,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Emails for one order, newest first.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function for_order( $order_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d ORDER BY id DESC LIMIT 50', self::table(), $order_id ), ARRAY_A );
	}

	/**
	 * Recent emails, optionally only failures.
	 *
	 * @param int    $limit  Rows.
	 * @param string $status '' or failed.
	 * @return array
	 */
	public static function recent( $limit = 50, $status = '' ) {
		global $wpdb;
		if ( 'failed' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
			return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT %d', self::table(), 'failed', $limit ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', self::table(), $limit ), ARRAY_A );
	}

	/**
	 * Count by status in the last N seconds.
	 *
	 * @param string $status  sent|failed.
	 * @param int    $seconds Window.
	 * @return int
	 */
	public static function count_since( $status, $seconds ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND created_at >= %s', self::table(), $status, gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ) ) );
	}

	/**
	 * Newest failure.
	 *
	 * @return array|null
	 */
	public static function last_failure() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT 1', self::table(), 'failed' ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Delete old rows.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$days = (int) MailWarden_Settings::get( 'retention_days' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 5000', self::table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}
}
