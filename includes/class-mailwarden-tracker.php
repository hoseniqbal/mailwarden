<?php
/**
 * Records every WooCommerce order email, resends, and raises the failure alert.
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email tracking.
 */
class MailWarden_Tracker {

	const ALERT = 'mailwarden_alert';

	/**
	 * Last wp_mail error message during this request.
	 *
	 * @var string
	 */
	private static $last_error = '';

	/**
	 * True while we resend, so the log row is marked.
	 *
	 * @var bool
	 */
	private static $resending = false;

	/**
	 * True while we send our own alert, so it is not logged as an order email.
	 *
	 * @var bool
	 */
	private static $alerting = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_mail_failed', array( __CLASS__, 'on_mail_failed' ) );
		add_action( 'woocommerce_email_sent', array( __CLASS__, 'on_email_sent' ), 10, 3 );
	}

	/**
	 * Remember why wp_mail failed.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	public static function on_mail_failed( $error ) {
		if ( $error instanceof WP_Error ) {
			self::$last_error = (string) $error->get_error_message();
		}
	}

	/**
	 * Shorten an address: jane.doe@gmail.com becomes ja***@gmail.com.
	 *
	 * @param string $email Address.
	 * @return string
	 */
	public static function mask( $email ) {
		$email = trim( (string) $email );
		$at    = strrpos( $email, '@' );
		if ( false === $at ) {
			return '';
		}
		$user = substr( $email, 0, $at );
		return substr( $user, 0, min( 2, strlen( $user ) ) ) . '***' . substr( $email, $at );
	}

	/**
	 * Order emails that can be resent from the order screen.
	 *
	 * @return array id => title
	 */
	public static function resendable() {
		$ids = array( 'new_order', 'cancelled_order', 'failed_order', 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice' );
		$out = array();
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( in_array( $email->id, $ids, true ) ) {
				$out[ $email->id ] = $email->get_title();
			}
		}
		return $out;
	}

	/**
	 * Log a WooCommerce email.
	 *
	 * @param bool     $result   Whether wp_mail accepted it.
	 * @param string   $email_id Email ID.
	 * @param WC_Email $email    Email object.
	 * @return void
	 */
	public static function on_email_sent( $result, $email_id, $email ) {
		if ( ! MailWarden_Settings::on( 'enabled' ) || self::$alerting || ! $email instanceof WC_Email ) {
			return;
		}
		$order = $email->object;
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$recipients = array_filter( array_map( array( __CLASS__, 'mask' ), explode( ',', (string) $email->get_recipient() ) ) );
		MailWarden_Store::add(
			array(
				'order_id'    => $order->get_id(),
				'email_id'    => (string) $email_id,
				'email_title' => $email->get_title(),
				'recipient'   => implode( ', ', $recipients ),
				'subject'     => $email->get_subject(),
				'status'      => $result ? 'sent' : 'failed',
				'error'       => $result ? '' : ( '' !== self::$last_error ? self::$last_error : __( 'WordPress could not hand the email to the mail server.', 'mailwarden' ) ),
				'resent'      => self::$resending,
			)
		);
		$error            = self::$last_error;
		self::$last_error = '';
		if ( $result ) {
			self::maybe_close_alert();
		} else {
			self::maybe_open_alert();
			/**
			 * An order email failed.
			 *
			 * @param int    $order_id Order ID.
			 * @param string $email_id WooCommerce email ID.
			 * @param string $error    Reason, if known.
			 */
			do_action( 'mailwarden_email_failed', $order->get_id(), (string) $email_id, $error );
		}
	}

	/**
	 * Resend one email for an order.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $email_id Email ID.
	 * @return string sent|failed|skipped|disabled|unknown
	 */
	public static function resend( $order, $email_id ) {
		if ( ! array_key_exists( $email_id, self::resendable() ) ) {
			return 'unknown';
		}
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email->id !== $email_id ) {
				continue;
			}
			if ( ! $email->is_enabled() && 'customer_invoice' !== $email_id ) {
				return 'disabled';
			}
			self::$resending = true;
			$before          = count( MailWarden_Store::for_order( $order->get_id() ) );
			// WooCommerce sends the new order email only once unless a resend is allowed.
			add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
			$email->trigger( $order->get_id(), $order );
			remove_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
			self::$resending = false;
			$rows            = MailWarden_Store::for_order( $order->get_id() );
			if ( count( $rows ) <= $before ) {
				return 'skipped';
			}
			if ( 'sent' === $rows[0]['status'] ) {
				/* translators: %s: email name */
				$order->add_order_note( sprintf( __( '%s email resent with MailWarden.', 'mailwarden' ), $email->get_title() ) );
				return 'sent';
			}
			return 'failed';
		}
		return 'unknown';
	}

	/**
	 * Open the failure alert when enough emails failed in the last hour.
	 *
	 * @return void
	 */
	private static function maybe_open_alert() {
		if ( ! MailWarden_Settings::on( 'alert' ) ) {
			return;
		}
		$state = get_option( self::ALERT, array() );
		if ( ! empty( $state['open'] ) ) {
			return;
		}
		$failed = MailWarden_Store::count_since( 'failed', HOUR_IN_SECONDS );
		if ( $failed < (int) MailWarden_Settings::get( 'alert_min' ) ) {
			return;
		}
		update_option(
			self::ALERT,
			array(
				'open'      => true,
				'since'     => time(),
				'dismissed' => false,
			),
			false
		);
		/**
		 * Order emails started failing.
		 *
		 * @param int $failed Failed emails in the last hour.
		 */
		do_action( 'mailwarden_failing', $failed );
		// Try to warn by email too. It may fail for the same reason, which is why the admin notice exists.
		self::$alerting = true;
		$site           = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		wp_mail(
			get_option( 'admin_email' ),
			/* translators: %s: site name */
			sprintf( __( 'Order emails are failing on %s', 'mailwarden' ), $site ),
			sprintf(
				/* translators: 1: number, 2: link */
				__( "%1\$s order emails failed in the last hour, so customers may not be getting their confirmations.\n\nSee which ones and resend them: %2\$s", 'mailwarden' ),
				$failed,
				admin_url( 'admin.php?page=mailwarden' )
			)
		);
		self::$alerting = false;
	}

	/**
	 * Close the alert after a successful send.
	 *
	 * @return void
	 */
	private static function maybe_close_alert() {
		$state = get_option( self::ALERT, array() );
		if ( ! empty( $state['open'] ) ) {
			update_option(
				self::ALERT,
				array(
					'open'     => false,
					'closed'   => time(),
					'lasted'   => time() - (int) $state['since'],
				),
				false
			);
		}
	}
}
