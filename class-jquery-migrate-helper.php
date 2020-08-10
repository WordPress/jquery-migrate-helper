<?php
/**
 * Class jQuery_Migrate_Helper,
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

class jQuery_Migrate_Helper {

	private function __construct() {}

	public static function init_actions() {
		// To be able to replace the src, scripts should not be concatenated.
		if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
			define( 'CONCATENATE_SCRIPTS', false );
		}

		$GLOBALS['concatenate_scripts'] = false;

		add_action( 'wp_default_scripts', array( __CLASS__, 'replace_scripts' ), -1 );

		// We need our own script for displaying warnings to run as late as possible.
		// Print it separately after the footer scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_scripts' ), 100 );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'wp_ajax_jquery-migrate-dismiss-notice', array( __CLASS__, 'admin_notices_dismiss' ) );
	}

	// Pre-register scripts on 'wp_default_scripts' action, they won't be overwritten by $wp_scripts->add().
	private static function set_script( $scripts, $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
		$script = $scripts->query( $handle, 'registered' );

		if ( $script ) {
			// If already added
			$script->src  = $src;
			$script->deps = $deps;
			$script->ver  = $ver;
			$script->args = $in_footer;

			unset( $script->extra['group'] );

			if ( $in_footer ) {
				$script->add_data( 'group', 1 );
			}
		} else {
			// Add the script
			if ( $in_footer ) {
				$scripts->add( $handle, $src, $deps, $ver, 1 );
			} else {
				$scripts->add( $handle, $src, $deps, $ver );
			}
		}
	}

	/*
	 * Enqueue jQuery migrate, and force it to be the development version.
	 *
	 * This will ensure that console errors are generated, and we can surface these to the
	 * end user in a responsible manner so that they can update their plugins and theme,
	 * or make a decision to switch to other plugin/theme if no updates are available.
	 */
	public static function replace_scripts( $scripts ) {
		$assets_url = plugins_url( 'js/', __FILE__ );

		self::set_script( $scripts, 'jquery-migrate', $assets_url . 'jquery-migrate-1.4.1-wp.js', array(), '1.4.1-wp' );
		self::set_script( $scripts, 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '1.12.4-wp' );
	}

	/**
	 * Register the deprecation notice capture handler script.
	 */
	public static function register_scripts() {
		wp_register_script( 'jquery-migrate-deprecation-notices', plugins_url( 'js/deprecation-notice.js', __FILE__ ), array( 'jquery' ), false, true );
	}

	/**
	 * Output/print the deprecation notice script. Needs to be last in the footer.
	 */
	public static function print_scripts() {
		wp_print_scripts( 'jquery-migrate-deprecation-notices' );
	}

	/**
	 * HTML for jQuery Migrate deprecated notices.
	 *
	 * This notice is only displayed if there are JS migration deprecation notices
	 * for scripts loaded on the current screen.
	 *
	 * @since 1.0.0
	 */
	public static function deprecated_scripts_notice() {
		?>

		<div class="notice notice-error is-dismissible jquery-migrate-deprecation-notice hidden">
			<h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'jquery-migrate-helper' ); ?> &mdash; <?php _ex( 'Warnings encountered', 'jquery-migrate-helper' ); ?></h2>
			<p><?php _e( 'This page generated the following warnings:', 'jquery-migrate-helper' ); ?></p>

			<ol class="jquery-migrate-deprecation-list"></ol>

			<p><?php _e( 'Please make sure you\'re using the latest version of all of your plugins, and your theme. If you are, you might want to ask their developers for an update.', 'jquery-migrate-helper' ); ?></p>
            <p>
				<?php _e( 'A script, a file, or some other piece of code is <strong>deprecated</strong> when its developers are in the process of replacing it with more modern code or removing it entirely from its environment.', 'jquery-migrate-helper' ); ?>
                <br>
				<?php _e( 'If you get a warning, you should check the theme or plugin that generated it for an update—there will very likely be one you can install.', 'jquery-migrate-helper' ); ?>
            </p>
		</div>

		<?php
	}

	/**
	 * HTML for the Dashboard notice.
	 */
	public static function dashboard_notice() {
		// Show again in two seeks if the user has dismissed this notice.
		$is_dismissed = get_option( '_jquery_migrate_dismissed_notice', false );
		$recurrence   = 2 * WEEK_IN_SECONDS;

		// If the message has been dismissed, and it has been less than two weeks since it was seen,
		// then skip showing the admin notice for now.
		if ( false !== $is_dismissed && $is_dismissed > ( time() - $recurrence ) ) {
			return;
		}

		?>

		<div class="notice notice-warning is-dismissible jquery-migrate-dashboard-notice">
			<h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'jquery-migrate-helper' ); ?></h2>
			<p><?php _e( 'Right now you are using a helper JavaScript that your theme or one or more of your plugins installed to help your site run some old code, but it\'s possible you no longer need it.' , 'jquery-migrate-helper' ); ?></p>
			<p><?php _e( 'To check, please deactivate the jQuery Migrate Helper plugin and then browse the WordPress dashboard. If you see errors or warnings about deprecated scripts, you should reactivate jQuery Migrate Helper.', 'jquery-migrate-helper' ); ?></p>
			<p>
                <?php _e( 'A script, a file, or some other piece of code is <strong>deprecated</strong> when its developers are in the process of replacing it with more modern code or removing it entirely from its environment.', 'jquery-migrate-helper' ); ?>
                <br>
                <?php _e( 'If you get a warning, you should check the theme or plugin that generated it for an update—there will very likely be one you can install.', 'jquery-migrate-helper' ); ?>
            </p>
			<?php wp_nonce_field( 'jquery-migrate-notice', 'jquery-migrate-notice-nonce', false ); ?>
		</div>

		<?php
	}

	public static function admin_notices() {
		// Show only to admins.
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( get_current_screen()->id === 'dashboard' ) {
			self::dashboard_notice();
		}

		self::deprecated_scripts_notice();
	}

	public static function admin_notices_dismiss() {
		if ( empty( POST['dismiss-notice-nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( POST['dismiss-notice-nonce'], 'jquery-migrate-notice' ) ) {
			return;
		}

		update_option( '_jquery_migrate_dismissed_notice', time() );
	}
}
