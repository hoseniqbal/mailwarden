<?php
/**
 * Admin screens and the order screen box.
 *
 * @package MailWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview, Email log, Settings, order box and the failure notice.
 */
class MailWarden_Admin {

	const PAGE = 'mailwarden';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 62 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30 );
		add_action( 'admin_notices', array( __CLASS__, 'failure_notice' ) );
		add_action( 'admin_post_mailwarden_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_mailwarden_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_mailwarden_dismiss', array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'option_page_capability_mailwarden', array( __CLASS__, 'capability' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MAILWARDEN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability for saving settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAP;
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'overview' => __( 'Overview', 'mailwarden' ),
			'log'      => __( 'Email log', 'mailwarden' ),
			'settings' => __( 'Settings', 'mailwarden' ),
		);
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Plugins screen link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Dashboard', 'mailwarden' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.8 4.2v5.2c0 4.4 3 7.7 7.2 8.9 4.2-1.2 7.2-4.5 7.2-8.9V4.2L10 1.5ZM5.8 7h8.4c.4 0 .7.3.7.7v5c0 .4-.3.7-.7.7H5.8a.7.7 0 0 1-.7-.7v-5c0-.4.3-.7.7-.7Zm.6 1.3V12h7.2V8.3L10 10.7 6.4 8.3Z"/></svg>'
		);
		add_menu_page( __( 'MailWarden', 'mailwarden' ), __( 'MailWarden', 'mailwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ), $icon, 57 );
		add_submenu_page( self::PAGE, __( 'Overview', 'mailwarden' ), __( 'Overview', 'mailwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		$tabs = self::tabs();
		foreach ( array( 'log', 'settings' ) as $tab ) {
			add_submenu_page( self::PAGE, $tabs[ $tab ], $tabs[ $tab ], self::CAP, 'admin.php?page=' . self::PAGE . '&tab=' . $tab );
		}
	}

	/**
	 * Highlight the open tab in the menu.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu highlight.
		if ( self::PAGE !== $page ) {
			return $file;
		}
		$tab = self::current_tab();
		return 'overview' === $tab ? self::PAGE : 'admin.php?page=' . self::PAGE . '&tab=' . $tab;
	}

	/**
	 * Order screen ID (HPOS or classic).
	 *
	 * @return string
	 */
	private static function order_screen() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return wc_get_page_screen_id( 'shop-order' );
		}
		return 'shop_order';
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$order  = $screen && self::order_screen() === $screen->id;
		if ( false === strpos( (string) $hook, self::PAGE ) && ! $order ) {
			return;
		}
		$file = MAILWARDEN_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'mailwarden-admin', MAILWARDEN_URL . 'assets/css/admin.css', array(), MAILWARDEN_VERSION . '.' . (int) filemtime( $file ) );
	}

	/**
	 * Settings registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'mailwarden',
			MailWarden_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'MailWarden_Settings', 'sanitize' ),
				'default'           => MailWarden_Settings::defaults(),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
	}

	/* ------------------------------------------------------------------
	 * Order screen
	 * ---------------------------------------------------------------- */

	/**
	 * Register the order box.
	 *
	 * @return void
	 */
	public static function meta_box() {
		if ( ! MailWarden_Settings::on( 'order_box' ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		add_meta_box( 'mailwarden-order', __( 'Order emails', 'mailwarden' ), array( __CLASS__, 'render_box' ), self::order_screen(), 'normal', 'low' );
	}

	/**
	 * Resend link.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $email_id Email ID.
	 * @return string
	 */
	private static function resend_url( $order_id, $email_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mailwarden_resend',
					'order'  => (int) $order_id,
					'email'  => $email_id,
				),
				admin_url( 'admin-post.php' )
			),
			'mailwarden_resend_' . (int) $order_id
		);
	}

	/**
	 * Order box.
	 *
	 * @param WP_Post|WC_Order $object Post or order.
	 * @return void
	 */
	public static function render_box( $object ) {
		$order = $object instanceof WC_Order ? $object : wc_get_order( $object->ID );
		if ( ! $order ) {
			return;
		}
		$rows  = MailWarden_Store::for_order( $order->get_id() );
		$mails = MailWarden_Tracker::resendable();
		echo '<div class="mw-box">';
		if ( empty( $rows ) ) {
			echo '<p class="mw-empty">' . esc_html__( 'No emails recorded for this order yet. Emails sent before MailWarden was active are not listed.', 'mailwarden' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( $rows as $row ) {
				$failed = 'failed' === $row['status'];
				echo '<li><span class="mw-dot' . ( $failed ? ' is-failed' : '' ) . '"></span><div>';
				echo '<strong>' . esc_html( $row['email_title'] ) . '</strong>';
				echo '<span class="mw-meta">';
				/* translators: 1: time difference, 2: recipient */
				echo esc_html( sprintf( __( '%1$s ago to %2$s', 'mailwarden' ), human_time_diff( strtotime( $row['created_at'] . ' UTC' ) ), $row['recipient'] ) );
				if ( (int) $row['resent'] ) {
					echo esc_html( ' · ' . __( 'resent', 'mailwarden' ) );
				}
				echo '</span>';
				if ( $failed && '' !== $row['error'] ) {
					echo '<div class="mw-fail">' . esc_html( $row['error'] ) . '</div>';
				}
				echo '</div>';
				echo '<span class="mw-tag' . ( $failed ? ' is-failed' : '' ) . '">' . esc_html( $failed ? __( 'Failed', 'mailwarden' ) : __( 'Sent', 'mailwarden' ) ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
		if ( $mails ) {
			echo '<div class="mw-resend">';
			echo '<select id="mw-resend-email" aria-label="' . esc_attr__( 'Email to resend', 'mailwarden' ) . '">';
			foreach ( $mails as $id => $title ) {
				echo '<option value="' . esc_attr( self::resend_url( $order->get_id(), $id ) ) . '">' . esc_html( $title ) . '</option>';
			}
			echo '</select>';
			echo '<a class="button" href="' . esc_url( self::resend_url( $order->get_id(), (string) array_key_first( $mails ) ) ) . '" onclick="this.href=document.getElementById(\'mw-resend-email\').value;">' . esc_html__( 'Resend', 'mailwarden' ) . '</a>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Resend from the order box.
	 *
	 * @return void
	 */
	public static function handle_resend() {
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mailwarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mailwarden_resend_' . $order_id );
		$order  = wc_get_order( $order_id );
		$email  = isset( $_GET['email'] ) ? sanitize_key( wp_unslash( $_GET['email'] ) ) : '';
		$result = $order ? MailWarden_Tracker::resend( $order, $email ) : 'unknown';
		set_transient( 'mailwarden_notice_' . get_current_user_id(), $result, 60 );
		wp_safe_redirect( $order ? $order->get_edit_order_url() : self::url() );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Notices
	 * ---------------------------------------------------------------- */

	/**
	 * Failure alert on every admin screen, plus the resend result.
	 *
	 * @return void
	 */
	public static function failure_notice() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$key    = 'mailwarden_notice_' . get_current_user_id();
		$result = get_transient( $key );
		if ( $result ) {
			delete_transient( $key );
			$msgs = array(
				'sent'     => array( 'success', __( 'Email resent.', 'mailwarden' ) ),
				'failed'   => array( 'error', __( 'The email could not be sent. See the reason in the Order emails box.', 'mailwarden' ) ),
				'skipped'  => array( 'warning', __( 'WooCommerce did not send that email for this order, for example because it has no recipient or does not match the order status.', 'mailwarden' ) ),
				'disabled' => array( 'warning', __( 'That email is turned off in WooCommerce, Settings, Emails. Turn it on to resend it.', 'mailwarden' ) ),
				'unknown'  => array( 'error', __( 'That email cannot be resent.', 'mailwarden' ) ),
			);
			if ( isset( $msgs[ $result ] ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $msgs[ $result ][0] ), esc_html( $msgs[ $result ][1] ) );
			}
		}
		$state = get_option( MailWarden_Tracker::ALERT, array() );
		if ( empty( $state['open'] ) || ! empty( $state['dismissed'] ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=mailwarden_dismiss' ), 'mailwarden_dismiss' );
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a></p></div>',
			esc_html__( 'Order emails are failing.', 'mailwarden' ),
			esc_html__( 'Customers may not be getting their order confirmations. MailWarden shows which emails failed and why.', 'mailwarden' ),
			esc_url( self::url( 'log', array( 'status' => 'failed' ) ) ),
			esc_html__( 'See failed emails', 'mailwarden' ),
			esc_url( $dismiss ),
			esc_html__( 'Hide until the next failure', 'mailwarden' )
		);
	}

	/**
	 * Hide the failure notice.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mailwarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mailwarden_dismiss' );
		$state = get_option( MailWarden_Tracker::ALERT, array() );
		if ( is_array( $state ) ) {
			$state['open'] = false;
			update_option( MailWarden_Tracker::ALERT, $state, false );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url() );
		exit;
	}

	/**
	 * Send a test email.
	 *
	 * @return void
	 */
	public static function handle_test() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mailwarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mailwarden_test' );
		$to    = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$to    = is_email( $to ) ? $to : wp_get_current_user()->user_email;
		$error = '';
		$catch = static function ( $e ) use ( &$error ) {
			$error = $e instanceof WP_Error ? $e->get_error_message() : '';
		};
		add_action( 'wp_mail_failed', $catch );
		$from_name = get_option( 'woocommerce_email_from_name' );
		$ok        = wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( 'Test email from %s', 'mailwarden' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			__( 'This is a test from MailWarden. If you can read it, your store can send email to this address. Check whether it arrived in the inbox or in spam.', 'mailwarden' ),
			array( 'From: ' . ( $from_name ? $from_name : get_bloginfo( 'name' ) ) . ' <' . MailWarden_Setup::from_address() . '>' )
		);
		remove_action( 'wp_mail_failed', $catch );
		set_transient(
			'mailwarden_test_' . get_current_user_id(),
			array(
				'ok'    => $ok,
				'to'    => $to,
				'error' => $error,
			),
			60
		);
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Pages
	 * ---------------------------------------------------------------- */

	/**
	 * Logo.
	 *
	 * @return void
	 */
	private static function logo() {
		?>
		<svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path d="M16 2.5 4.5 6.8v8.4c0 7.1 4.9 12.4 11.5 14.3 6.6-1.9 11.5-7.2 11.5-14.3V6.8L16 2.5Z" fill="#12704c"/><rect x="9" y="11" width="14" height="10" rx="1.6" fill="none" stroke="#fff" stroke-width="2"/><path d="m9.8 12 6.2 4.6 6.2-4.6" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><circle cx="23.5" cy="21.5" r="4" fill="#7a5af5" stroke="#fff" stroke-width="1.5"/></svg>
		<?php
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab = self::current_tab();
		?>
		<div class="wrap mailwarden">
			<div class="ow-hero">
				<div class="mailwarden-header">
					<span class="ow-logo"><?php self::logo(); ?></span>
					<div class="ow-title">
						<h1><?php esc_html_e( 'MailWarden', 'mailwarden' ); ?></h1>
						<p class="ow-tagline"><?php esc_html_e( 'Know that every order email went out, and fix it when one did not.', 'mailwarden' ); ?></p>
					</div>
					<span class="mailwarden-version"><?php echo esc_html( 'v' . MAILWARDEN_VERSION ); ?></span>
				</div>
				<nav class="mailwarden-tabs" aria-label="<?php esc_attr_e( 'MailWarden sections', 'mailwarden' ); ?>">
					<?php foreach ( self::tabs() as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="wp-header-end">
			<?php
			if ( 'settings' === $tab ) {
				self::render_settings();
			} elseif ( 'log' === $tab ) {
				self::render_log();
			} else {
				self::render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$test = get_transient( 'mailwarden_test_' . get_current_user_id() );
		if ( is_array( $test ) ) {
			delete_transient( 'mailwarden_test_' . get_current_user_id() );
			if ( $test['ok'] ) {
				/* translators: %s: email address */
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( __( 'Test email handed to the mail server for %s. Check the inbox and the spam folder.', 'mailwarden' ), $test['to'] ) ) );
			} else {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%1$s %2$s</p></div>',
					esc_html__( 'The test email could not be sent.', 'mailwarden' ),
					esc_html( '' !== $test['error'] ? $test['error'] : __( 'WordPress gave no reason. Install an SMTP plugin.', 'mailwarden' ) )
				);
			}
		}
		$sent   = MailWarden_Store::count_since( 'sent', 7 * DAY_IN_SECONDS );
		$failed = MailWarden_Store::count_since( 'failed', 7 * DAY_IN_SECONDS );
		$rate   = ( $sent + $failed ) ? (int) floor( 100 * $sent / ( $sent + $failed ) ) : 100;
		$last   = MailWarden_Store::last_failure();
		$state  = ! MailWarden_Settings::on( 'enabled' ) ? 'is-off' : ( MailWarden_Store::count_since( 'failed', DAY_IN_SECONDS ) ? 'is-off' : ( $sent ? 'is-on' : 'is-learning' ) );
		$checks = MailWarden_Setup::checks();
		?>
		<section class="mailwarden-banner <?php echo esc_attr( $state ); ?>">
			<div>
				<?php if ( ! MailWarden_Settings::on( 'enabled' ) ) : ?>
					<h2><?php esc_html_e( 'Email tracking is off', 'mailwarden' ); ?></h2>
					<p><?php esc_html_e( 'Turn it on in Settings to record every order email.', 'mailwarden' ); ?></p>
				<?php elseif ( 'is-off' === $state ) : ?>
					<h2><?php esc_html_e( 'Some order emails failed today', 'mailwarden' ); ?></h2>
					<p><?php esc_html_e( 'Open the Email log to see why, then resend them from each order.', 'mailwarden' ); ?></p>
				<?php elseif ( 'is-on' === $state ) : ?>
					<h2><?php esc_html_e( 'Order emails are going out', 'mailwarden' ); ?></h2>
					<p><?php esc_html_e( 'Every WooCommerce order email is recorded with its result. You can see them on each order too.', 'mailwarden' ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Ready and recording', 'mailwarden' ); ?></h2>
					<p><?php esc_html_e( 'The next order email WooCommerce sends will show up here. Meanwhile, check your setup below.', 'mailwarden' ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="mailwarden-panel">
			<dl class="mailwarden-figures">
				<div><dt><?php esc_html_e( 'Sent, last 7 days', 'mailwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $sent ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Failed, last 7 days', 'mailwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $failed ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Success rate', 'mailwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $rate ) . '%' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Last failure', 'mailwarden' ); ?></dt><dd>
					<?php
					/* translators: %s: time difference */
					echo esc_html( $last ? sprintf( __( '%s ago', 'mailwarden' ), human_time_diff( strtotime( $last['created_at'] . ' UTC' ) ) ) : __( 'None', 'mailwarden' ) );
					?>
				</dd></div>
			</dl>
		</section>

		<section class="mailwarden-panel">
			<div class="mailwarden-panel-head">
				<h2><?php esc_html_e( 'Setup check', 'mailwarden' ); ?></h2>
				<p><?php esc_html_e( 'What decides whether order emails reach the inbox.', 'mailwarden' ); ?></p>
			</div>
			<ul class="mw-checks">
				<?php
				foreach ( $checks as $check ) :
					$map = array(
						'ok'   => array( 'is-on', 'is-ok', __( 'Good', 'mailwarden' ) ),
						'warn' => array( '', 'is-would', __( 'Check', 'mailwarden' ) ),
						'bad'  => array( 'is-off', 'is-blocked', __( 'Fix', 'mailwarden' ) ),
					);
					$m   = $map[ $check['state'] ];
					?>
					<li>
						<span class="mailwarden-dot <?php echo esc_attr( $m[0] ); ?>"></span>
						<div><strong><?php echo esc_html( $check['title'] ); ?></strong><p><?php echo esc_html( $check['text'] ); ?></p></div>
						<span class="mailwarden-tag <?php echo esc_attr( $m[1] ); ?>"><?php echo esc_html( $m[2] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<form class="mailwarden-actions mw-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mailwarden_test">
			<?php wp_nonce_field( 'mailwarden_test' ); ?>
			<input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" aria-label="<?php esc_attr_e( 'Send the test to', 'mailwarden' ); ?>">
			<?php submit_button( __( 'Send a test email', 'mailwarden' ), 'mailwarden-btn', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Email log.
	 *
	 * @return void
	 */
	private static function render_log() {
		$status = isset( $_GET['status'] ) && 'failed' === $_GET['status'] ? 'failed' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter.
		$rows   = MailWarden_Store::recent( 100, $status );
		?>
		<section class="mailwarden-panel">
			<div class="mailwarden-panel-head">
				<h2><?php echo esc_html( 'failed' === $status ? __( 'Failed order emails', 'mailwarden' ) : __( 'Order emails', 'mailwarden' ) ); ?></h2>
				<p>
					<a href="<?php echo esc_url( self::url( 'log' ) ); ?>"<?php echo '' === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'mailwarden' ); ?></a>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( self::url( 'log', array( 'status' => 'failed' ) ) ); ?>"<?php echo 'failed' === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Failed only', 'mailwarden' ); ?></a>
				</p>
			</div>
			<?php if ( empty( $rows ) ) : ?>
				<div class="mailwarden-empty">
					<strong><?php echo esc_html( 'failed' === $status ? __( 'No failed emails', 'mailwarden' ) : __( 'No order emails yet', 'mailwarden' ) ); ?></strong>
					<?php esc_html_e( 'Every email WooCommerce sends for an order is listed here with its result.', 'mailwarden' ); ?>
				</div>
			<?php else : ?>
				<table class="mailwarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'When', 'mailwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Order', 'mailwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'mailwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'To', 'mailwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'mailwarden' ); ?></th>
					</tr></thead>
					<tbody>
					<?php
					foreach ( $rows as $row ) :
						$order = wc_get_order( (int) $row['order_id'] );
						?>
						<tr>
							<td class="is-time">
								<?php
								/* translators: %s: time difference */
								echo esc_html( sprintf( __( '%s ago', 'mailwarden' ), human_time_diff( strtotime( $row['created_at'] . ' UTC' ) ) ) );
								?>
							</td>
							<td>
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php echo esc_html( '#' . $order->get_order_number() ); ?></a>
								<?php else : ?>
									<?php echo esc_html( '#' . (int) $row['order_id'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['email_title'] . ( (int) $row['resent'] ? ' (' . __( 'resent', 'mailwarden' ) . ')' : '' ) ); ?></td>
							<td><code><?php echo esc_html( $row['recipient'] ); ?></code></td>
							<td>
								<?php if ( 'failed' === $row['status'] ) : ?>
									<span class="mailwarden-tag is-blocked"><?php esc_html_e( 'Failed', 'mailwarden' ); ?></span>
									<span class="mw-err"><?php echo esc_html( $row['error'] ); ?></span>
								<?php else : ?>
									<span class="mailwarden-tag is-ok"><?php esc_html_e( 'Sent', 'mailwarden' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Switch.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return void
	 */
	private static function field_switch( $key, $label ) {
		?>
		<label class="mailwarden-switch">
			<input type="checkbox" name="<?php echo esc_attr( MailWarden_Settings::OPTION . '[' . $key . ']' ); ?>" value="yes" <?php checked( MailWarden_Settings::get( $key ), 'yes' ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * A sentence with a number input.
	 *
	 * @param string $format Sentence with %s.
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @return void
	 */
	private static function sentence( $format, $key, $label ) {
		$input = sprintf(
			'<input type="number" min="0" step="1" name="%1$s" value="%2$s" aria-label="%3$s">',
			esc_attr( MailWarden_Settings::OPTION . '[' . $key . ']' ),
			esc_attr( MailWarden_Settings::get( $key ) ),
			esc_attr( $label )
		);
		echo '<p>' . wp_kses(
			sprintf( esc_html( $format ), $input ),
			array(
				'input' => array(
					'type'       => true,
					'min'        => true,
					'step'       => true,
					'name'       => true,
					'value'      => true,
					'aria-label' => true,
				),
			)
		) . '</p>';
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	private static function render_settings() {
		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'mailwarden' ); ?>
			<section class="mailwarden-panel">
				<div class="mailwarden-panel-head"><h2><?php esc_html_e( 'Tracking', 'mailwarden' ); ?></h2></div>
				<div class="mailwarden-row">
					<div class="mailwarden-row-label"><?php esc_html_e( 'Order emails', 'mailwarden' ); ?><p><?php esc_html_e( 'Recipient (shortened), subject and result. Never the message itself.', 'mailwarden' ); ?></p></div>
					<div class="mailwarden-row-control">
						<?php self::field_switch( 'enabled', __( 'Record every WooCommerce order email', 'mailwarden' ) ); ?>
						<?php self::field_switch( 'order_box', __( 'Show the Order emails box on each order', 'mailwarden' ) ); ?>
					</div>
				</div>
				<div class="mailwarden-row">
					<div class="mailwarden-row-label"><?php esc_html_e( 'Failure warning', 'mailwarden' ); ?><p><?php esc_html_e( 'Shown at the top of every admin screen, and sent by email when possible.', 'mailwarden' ); ?></p></div>
					<div class="mailwarden-row-control">
						<?php self::field_switch( 'alert', __( 'Warn me when order emails start failing', 'mailwarden' ) ); ?>
						<?php
						/* translators: %s: number input */
						self::sentence( __( 'Warn after %s failed emails within an hour.', 'mailwarden' ), 'alert_min', __( 'Failed emails', 'mailwarden' ) );
						?>
					</div>
				</div>
			</section>
			<section class="mailwarden-panel">
				<div class="mailwarden-panel-head"><h2><?php esc_html_e( 'Data', 'mailwarden' ); ?></h2></div>
				<div class="mailwarden-row">
					<div class="mailwarden-row-label"><?php esc_html_e( 'Email log', 'mailwarden' ); ?></div>
					<div class="mailwarden-row-control">
						<?php
						/* translators: %s: number input */
						self::sentence( __( 'Keep the log for %s days.', 'mailwarden' ), 'retention_days', __( 'Days', 'mailwarden' ) );
						?>
					</div>
				</div>
				<div class="mailwarden-row">
					<div class="mailwarden-row-label"><?php esc_html_e( 'When you delete the plugin', 'mailwarden' ); ?></div>
					<div class="mailwarden-row-control"><?php self::field_switch( 'delete_data', __( 'Also delete its settings and log', 'mailwarden' ) ); ?></div>
				</div>
			</section>
			<div class="mailwarden-actions">
				<?php submit_button( __( 'Save settings', 'mailwarden' ), 'mailwarden-btn', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}
}
