<?php
/**
 * Setup check: is this store set up to deliver email?
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain checks with a clear next step for each.
 */
class MailWarden_Setup {

	/**
	 * Is a mail sending plugin active?
	 *
	 * @return string Plugin name or ''.
	 */
	public static function smtp_plugin() {
		$known = array(
			'WP Mail SMTP'   => defined( 'WPMS_PLUGIN_VER' ),
			'FluentSMTP'     => defined( 'FLUENTMAIL' ),
			'Post SMTP'      => defined( 'POST_SMTP_VER' ),
			'Easy WP SMTP'   => class_exists( 'EasyWPSMTP' ),
			'SMTP2GO'        => defined( 'SMTP2GO_PLUGIN_VERSION' ),
			'Brevo'          => class_exists( 'SIB_Manager' ),
			'Mailgun'        => class_exists( 'Mailgun' ),
			'SendGrid'       => class_exists( 'Sendgrid_Tools' ),
			'Site Mailer'    => defined( 'SITE_MAILER_VERSION' ),
			'Gmail SMTP'     => class_exists( 'GMAIL_SMTP' ),
		);
		foreach ( $known as $name => $active ) {
			if ( $active ) {
				return $name;
			}
		}
		return has_action( 'phpmailer_init' ) ? __( 'a mail plugin', 'mailwarden' ) : '';
	}

	/**
	 * Domain without www.
	 *
	 * @param string $host Host.
	 * @return string
	 */
	private static function bare( $host ) {
		return preg_replace( '/^www\./', '', strtolower( (string) $host ) );
	}

	/**
	 * The From address WooCommerce uses.
	 *
	 * @return string
	 */
	public static function from_address() {
		$from = get_option( 'woocommerce_email_from_address' );
		return is_email( $from ) ? $from : get_option( 'admin_email' );
	}

	/**
	 * TXT records, cached for 12 hours.
	 *
	 * @param string $domain Domain.
	 * @return array|null Null when DNS lookups are not possible here.
	 */
	private static function txt( $domain ) {
		$cache = get_transient( 'mailwarden_dns' );
		$cache = is_array( $cache ) ? $cache : array();
		if ( isset( $cache[ $domain ] ) ) {
			return $cache[ $domain ];
		}
		if ( ! function_exists( 'dns_get_record' ) ) {
			return null;
		}
		$records = @dns_get_record( $domain, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed lookup is reported to the user, not as a PHP warning.
		$txt     = array();
		foreach ( (array) $records as $record ) {
			if ( isset( $record['txt'] ) ) {
				$txt[] = (string) $record['txt'];
			}
		}
		$cache[ $domain ] = $txt;
		set_transient( 'mailwarden_dns', $cache, 12 * HOUR_IN_SECONDS );
		return $txt;
	}

	/**
	 * All checks.
	 *
	 * @return array[] Each: key, state ok|warn|bad, title, text.
	 */
	public static function checks() {
		$out = array();

		$failed = MailWarden_Store::count_since( 'failed', 7 * DAY_IN_SECONDS );
		$sent   = MailWarden_Store::count_since( 'sent', 7 * DAY_IN_SECONDS );
		$out[]  = array(
			'state' => $failed ? 'bad' : ( $sent ? 'ok' : 'warn' ),
			'title' => __( 'Recent order emails', 'mailwarden' ),
			'text'  => $failed
				/* translators: 1: failed, 2: total */
				? sprintf( __( '%1$s of %2$s order emails failed in the last 7 days. Open the Email log to see why and resend them.', 'mailwarden' ), number_format_i18n( $failed ), number_format_i18n( $failed + $sent ) )
				: ( $sent
					/* translators: %s: number */
					? sprintf( __( 'All %s order emails in the last 7 days were handed to the mail server.', 'mailwarden' ), number_format_i18n( $sent ) )
					: __( 'No order emails yet. Place a test order or press "Send a test email".', 'mailwarden' ) ),
		);

		$smtp  = self::smtp_plugin();
		$out[] = array(
			'state' => $smtp ? 'ok' : 'warn',
			'title' => __( 'Mail sending', 'mailwarden' ),
			'text'  => $smtp
				/* translators: %s: plugin name */
				? sprintf( __( 'Emails go out through %s.', 'mailwarden' ), $smtp )
				: __( 'Emails go out through your web server. Many of them land in spam. An SMTP plugin with a mail service (your host, Brevo, Mailgun, Gmail and so on) is far more reliable.', 'mailwarden' ),
		);

		$from        = self::from_address();
		$from_domain = self::bare( substr( strrchr( $from, '@' ), 1 ) );
		$site_domain = self::bare( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$free        = in_array( $from_domain, array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'aol.com', 'icloud.com', 'yandex.com', 'proton.me', 'protonmail.com' ), true );
		$matches     = $from_domain === $site_domain || ( '' !== $site_domain && str_ends_with( $site_domain, '.' . $from_domain ) );
		$out[]       = array(
			'state' => $free ? 'bad' : ( $matches ? 'ok' : 'warn' ),
			'title' => __( 'From address', 'mailwarden' ),
			'text'  => $free
				/* translators: %s: email address */
				? sprintf( __( 'Order emails are sent from %s. Free mail addresses fail checks at Gmail and Outlook when sent from your website. Use an address on your own domain in WooCommerce, Settings, Emails.', 'mailwarden' ), $from )
				: ( $matches
					/* translators: %s: email address */
					? sprintf( __( 'Order emails are sent from %s, which matches your website.', 'mailwarden' ), $from )
					/* translators: 1: email address, 2: domain */
					: sprintf( __( 'Order emails are sent from %1$s but your website is %2$s. Make sure your mail service is allowed to send for that domain.', 'mailwarden' ), $from, $site_domain ) ),
		);

		if ( ! $free && '' !== $from_domain ) {
			$txt   = self::txt( $from_domain );
			$spf   = null === $txt ? null : (bool) preg_grep( '/^v=spf1/i', $txt );
			$out[] = array(
				'state' => null === $spf ? 'warn' : ( $spf ? 'ok' : 'bad' ),
				'title' => __( 'SPF record', 'mailwarden' ),
				'text'  => null === $spf
					? __( 'Your server does not allow DNS lookups, so this could not be checked.', 'mailwarden' )
					: ( $spf
						/* translators: %s: domain */
						? sprintf( __( '%s has an SPF record. Check that it includes your mail service.', 'mailwarden' ), $from_domain )
						/* translators: %s: domain */
						: sprintf( __( '%s has no SPF record, so receivers cannot tell your emails are genuine. Your mail service shows the record to add at your domain provider.', 'mailwarden' ), $from_domain ) ),
			);
			$dmarc = self::txt( '_dmarc.' . $from_domain );
			$has   = null === $dmarc ? null : (bool) preg_grep( '/^v=DMARC1/i', $dmarc );
			$out[] = array(
				'state' => null === $has ? 'warn' : ( $has ? 'ok' : 'warn' ),
				'title' => __( 'DMARC record', 'mailwarden' ),
				'text'  => null === $has
					? __( 'Your server does not allow DNS lookups, so this could not be checked.', 'mailwarden' )
					: ( $has
						/* translators: %s: domain */
						? sprintf( __( '%s has a DMARC record.', 'mailwarden' ), $from_domain )
						/* translators: %s: domain */
						: sprintf( __( '%s has no DMARC record. Gmail and Yahoo expect one from senders. A simple "v=DMARC1; p=none" record is a safe start.', 'mailwarden' ), $from_domain ) ),
			);
		}
		return $out;
	}
}
