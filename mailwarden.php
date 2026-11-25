<?php
/**
 * Plugin Name:       MailWarden: Order Email Delivery Checker for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/mailwarden
 * Description:       See every email WooCommerce sent for an order, whether it worked, and resend it in one click. Warns you when order emails start failing.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossen
 * Author URI:        https://profiles.wordpress.org/iqbal511138/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mailwarden
 * WC requires at least: 7.6
 * WC tested up to:   11.3
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAILWARDEN_VERSION', '1.0.0' );
define( 'MAILWARDEN_DB_VERSION', '1' );
define( 'MAILWARDEN_FILE', __FILE__ );
define( 'MAILWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAILWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once MAILWARDEN_PATH . 'includes/class-mailwarden-settings.php';
require_once MAILWARDEN_PATH . 'includes/class-mailwarden-store.php';
require_once MAILWARDEN_PATH . 'includes/class-mailwarden-tracker.php';
require_once MAILWARDEN_PATH . 'includes/class-mailwarden-setup.php';
require_once MAILWARDEN_PATH . 'includes/class-mailwarden-admin.php';
require_once MAILWARDEN_PATH . 'includes/class-mailwarden-plugin.php';

register_activation_hook( __FILE__, array( 'MailWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MailWarden_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'MailWarden_Plugin', 'init' ) );
