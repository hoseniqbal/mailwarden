<?php
/**
 * Bootstrap.
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class MailWarden_Plugin {

	const CRON = 'mailwarden_daily';

	/**
	 * Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'mailwarden_db_version' ) !== MAILWARDEN_DB_VERSION ) {
			MailWarden_Store::install();
		}
		add_action( self::CRON, array( 'MailWarden_Store', 'purge' ) );
		MailWarden_Tracker::init();
		if ( is_admin() ) {
			MailWarden_Admin::init();
		}
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		MailWarden_Store::install();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
