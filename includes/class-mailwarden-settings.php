<?php
/**
 * Settings.
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All settings in one option.
 */
class MailWarden_Settings {

	const OPTION = 'mailwarden_settings';

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'        => 'yes',
			'order_box'      => 'yes',
			'alert'          => 'yes',
			'alert_min'      => 3,
			'retention_days' => 60,
			'delete_data'    => 'no',
		);
	}

	/**
	 * All values.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Yes/no check.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $in Raw.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( array( 'enabled', 'order_box', 'alert', 'delete_data' ) as $key ) {
			$out[ $key ] = ( isset( $in[ $key ] ) && 'yes' === $in[ $key ] ) ? 'yes' : 'no';
		}
		$ints = array(
			'alert_min'      => array( 1, 100 ),
			'retention_days' => array( 7, 730 ),
		);
		foreach ( $ints as $key => $range ) {
			$val         = isset( $in[ $key ] ) ? absint( $in[ $key ] ) : $d[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $val ) );
		}
		self::$cache = null;
		return $out;
	}
}
