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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_scripts' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_frontend' ), 100 );

		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( '_admin_menu', array( __CLASS__, 'append_tools_menu_counter' ) );
		add_action( '_user_admin_menu', array( __CLASS__, 'append_tools_menu_counter' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 100 );

		add_action( 'admin_init', array( __CLASS__, 'admin_settings' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'wp_ajax_jquery-migrate-dismiss-notice', array( __CLASS__, 'admin_notices_dismiss' ) );
		add_action( 'wp_ajax_jquery-migrate-log-notice', array( __CLASS__, 'log_migrate_notice' ) );
		add_action( 'wp_ajax_nopriv_jquery-migrate-log-notice', array( __CLASS__, 'log_migrate_notice' ) );

		add_action( 'wp_ajax_jquery-migrate-downgrade-version', array( __CLASS__, 'downgrade_jquery_version' ) );

		add_action( 'wp_head', array( __CLASS__, 'fatal_error_handler' ) );
		add_action( 'admin_head', array( __CLASS__, 'fatal_error_handler' ) );

		add_filter( 'site_status_tests', array( __CLASS__, 'site_health_check' ) );

		// Set up our scheduled weekly notification.
        if ( ! wp_next_scheduled( 'enable_jquery_migrate_helper_notification' ) && ! wp_installing() ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'enable_jquery_migrate_helper_notification' );
        }
        add_action( 'enable_jquery_migrate_helper_notification', array( __CLASS__, 'scheduled_event_handler' ) );
	}

	/**
	 * Run the scheduled event ot send an email summary to the site admin.
	 */
	public static function scheduled_event_handler() {
	    $recipient = get_bloginfo( 'admin_email' );
	    $title = __( 'Weekly jQuery Migrate Status Update', 'enable-jquery-migrate-helper' );

        ob_start();
        include_once __DIR__ . '/templates/email/weekly.php';
        $message = ob_get_clean();

	    add_filter( 'wp_mail_content_type', function() {
	        return 'text/html';
        } );

	    wp_mail( $recipient, $title, $message );
    }

	/**
	 * Appends the count of migration notices to the `Tools` menu item.
	 *
	 * This is done to indicate that a submenu item has actionable content.
	 *
	 * @global array $menu
	 */
	public static function append_tools_menu_counter() {
		global $menu;

		$count = self::logged_migration_notice_count();

		// Don't add extra maz<rkup if not needed.
		if ( $count < 1 ) {
		    return;
        }

		// Menu position 75 is the Tools menu.
		$menu[75][0] .= sprintf(
			' <span class="update-plugins jqmh-deprecations count-%1$d"><span class="plugin-count">%1$d</span></span>',
			self::logged_migration_notice_count()
		);
	}

	/**
	 * Add the jQuery Migrate plugin to the Tools sub-menu.
	 */
	public static function admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'jQuery Migrate', 'enable-jquery-migrate-helper' ),
			sprintf(
				'%1$s <span class="update-plugins jqmh-deprecations count-%2$d"><span class="plugin-count">%2$d</span></span>',
				__( 'jQuery Migrate', 'enable-jquery-migrate-helper' ),
				self::logged_migration_notice_count()
			),
			'manage_options',
			'jqmh',
			array( __CLASS__, 'admin_page' )
		);
	}

	/**
	 * Render the plugin tools page.
	 */
	public static function admin_page() {
		echo '<div class="wrap">';

		include_once __DIR__ . '/admin/header.php';

		echo '</div>';
	}

	/**
	 * Settings page save handler.
	 */
	public static function admin_settings() {
	    if ( ! isset( $_POST['jqmh-settings'] ) || ! current_user_can( 'manage_options' ) ) {
	        return;
        }

	    if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'jqmh-settings' ) ) {
	        return;
        }

	    $previous_downgrade = get_option( '_jquery_migrate_downgrade_version', 'no' );

	    if ( isset( $_POST['live-deprecations'] ) ) {
		    delete_option( '_jquery_migrate_deprecations_dismissed_notice' );
        } else {
		    update_option( '_jquery_migrate_deprecations_dismissed_notice', time() );
        }

	    if ( 'yes' === $_POST['jquery-version'] ) {
	        update_option( '_jquery_migrate_downgrade_version', 'yes' );
        } else {
		    update_option( '_jquery_migrate_downgrade_version', 'no' );

		    // Disable logging by default when enabling modern jQuery versions.
		    if ( 'yes' === $previous_downgrade ) {
			    update_option( '_jquery_migrate_modern_deprecations', 'no' );
			    update_option( '_jquery_migrate_deprecations_dismissed_notice', time() );
            }
        }

	    if ( isset( $_POST['public-deprecation-logging'] ) ) {
	        update_option( '_jquery_migrate_public_deprecation_logging', 'yes' );
        } else {
	        update_option( '_jquery_migrate_public_deprecation_logging', 'no' );
        }

	    if ( isset( $_POST['modern-deprecations'] ) ) {
	        update_option( '_jquery_migrate_modern_deprecations', 'yes' );
        } else {
	        update_option( '_jquery_migrate_modern_deprecations', 'no' );
        }
    }

	public static function site_health_check( $tests ) {
		$tests['direct']['enable-jquery-migrate-helper'] = array(
			'label' => __( 'WordPress jQuery Version', 'enable-jquery-migrate-helper' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);

		return $tests;
	}

	public static function site_health_test() {
		$result = array(
			'label'       => __( 'WordPress jQuery Version', 'enable-jquery-migrate-helper' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your site is using the latest jQuery version that comes with WordPress.', 'enable-jquery-migrate-helper' )
			),
			'actions'     => '',
			'test'        => 'enable-jquery-migrate-helper',
		);

		$downgrade_state = get_option( '_jquery_migrate_downgrade_version', 'no' );

		if ( 'no' !== $downgrade_state ) {
			$result['label']       = __( 'You are using a downgraded version of jQuery', 'enable-jquery-migrate-helper' );
			$result['status']      = 'critical';
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Your site is using an older version of jQuery, version 1.12.4-wp, likely due to problems experienced with your plugins or themes after an update. Please reach out to the authors of your plugins and themes to ensure they are compatible with WordPress 5.6 or later.', 'enable-jquery-migrate-helper' )
			);
			$result['actions']     = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_admin_url( null, '?jqmh-use-3' ) ),
				__( 'Switch back to jQuery 3', 'enable-jquery-migrate-helper' )
			);
		}

		return $result;
	}

	public static function downgrade_jquery_version() {
		/*
		 * Only allow the downgrade to be triggered automatically by site visitors if an admin hasn't
		 * changed the option them selves, at which point only an admin should be able to set the version.
		 */
        $has_auto_downgraded = get_option( '_jquery_migrate_has_auto_downgraded', 'no' );
		if ( 'no' !== $has_auto_downgraded ) {
			return;
		}

		// An array of functions that may trigger a jQuery Migrate downgrade.
		$deprecated = array(
			'andSelf',
            'browser',
            'live',
            'boxModel',
            'support.boxModel',
            'size',
            'swap',
            'clean',
            'sub',
		);

		preg_match( '/\)\.(?<function>.+?) is not a function/si', $_POST['msg'], $regex_match );
		$function = ( isset( $regex_match['function'] ) ? $regex_match['function'] : null );

		// If no function was detected, or it was not an acknowledged deprecated feature, do not downgrade.
		if ( null === $function || ! in_array( $function, $deprecated ) ) {
			return;
		}

		update_option( '_jquery_migrate_downgrade_version', 'yes' );
		update_option( '_jquery_migrate_has_auto_downgraded', 'yes' );

		wp_send_json_success( array( 'reload' => true ) );
	}

	/**
	 * Add a fatal error handler for uncaught errors.
	 *
	 * This will look for deprecated jQuery functions, and send an AJAX call letting the plugin
	 * know that it should serve future requests as a downgraded version of jQuery.
	 *
	 * Vanilla JavaScript is used here to remove all dependencies on libraries, even though they
	 * all look very pretty, this ensures that code can run no matter the circumstance.
	 */
	public static function fatal_error_handler() {
		// If an auto-downgraded has already been performed, do not output the error handler.
		if ( 'no' !== get_option( '_jquery_migrate_has_auto_downgraded', 'no' ) ) {
			return;
		}
		?>

        <script type="text/javascript">
			window.onerror = function( msg, url, line, col, error ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>' );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function () {
					var response,
                        reload = false;

					if ( 200 === xhr.status ) {
                        try {
                        	response = JSON.parse( xhr.response );

                        	reload = response.data.reload;
                        } catch ( e ) {
                        	reload = false;
                        }
                    }

					// Automatically reload the page if a deprecation caused an automatic downgrade, ensure visitors get the best possible experience.
					if ( reload ) {
						location.reload();
                    }
				};

				xhr.send( encodeURI( 'action=jquery-migrate-downgrade-version&msg=' + msg ) );

				// Suppress error alerts in older browsers
				return true;
			}
        </script>

		<?php
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

		if ( version_compare( $GLOBALS['wp_version'], '5.6-alpha', '<' ) || 'yes' === get_option( '_jquery_migrate_downgrade_version', 'no' ) ) {
			self::set_script( $scripts, 'jquery-migrate', $assets_url . 'jquery-migrate-1.4.1-wp.js', array(), '1.4.1-wp' );
			self::set_script( $scripts, 'jquery-core', $assets_url . 'jquery-1.12.4-wp.js', array(), '1.12.4-wp' );
			self::set_script( $scripts, 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '1.12.4-wp' );
		} else {
			if ( 'yes' === get_option( '_jquery_migrate_modern_deprecations', 'no' ) ) {
				self::set_script( $scripts, 'jquery-migrate', $assets_url . 'jquery-migrate-3.3.2-wp.js', array(), '3.3.2-wp' );
				self::set_script( $scripts, 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '3.5.1-wp' );
			}
		}
	}

	/**
	 * Register the deprecation notice capture handler script.
	 */
	public static function register_scripts() {
		wp_register_script( 'jquery-migrate-deprecation-notices', plugins_url( 'js/deprecation-notice.js', __FILE__ ), array( 'jquery', 'jquery-migrate' ), false, true );

		wp_localize_script(
			'jquery-migrate-deprecation-notices',
			'JQMH',
			array(
				'ajaxurl'              => admin_url( 'admin-ajax.php' ),
				'report_nonce'         => wp_create_nonce( 'jquery-migrate-report-deprecation' ),
				'backend'              => is_admin(),
				'plugin_slug'          => dirname( plugin_basename( __FILE__ ) ),
				'capture_deprecations' => ( 'yes' === get_option( '_jquery_migrate_modern_deprecations', 'no' ) ), // Deprecation logging must be explicitly enabled for jQuery 3.5
                'single_instance_log'  => ( 'no' === get_option( '_jquery_migrate_downgrade_version', 'no' ) ), // Only show one instance of deprecations in jQuery 3.5
			)
		);
	}

	/**
	 * Output/print the deprecation notice script. Needs to be last in the footer.
	 */
	public static function print_scripts() {
		wp_print_scripts( 'jquery-migrate-deprecation-notices' );
	}

	public static function enqueue_scripts_frontend() {
		// Only load the asset for users who can act on them.
		if ( ! current_user_can( 'manage_options' ) && 'no' === get_option( '_jquery_migrate_public_deprecation_logging', 'no' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-migrate-deprecation-notices' );
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
		// If the message has been dismissed, and it has been less than two weeks since it was seen,
		// then skip displaying the deprecation lister for now.
		if ( ! self::show_deprecated_scripts_notice() ) {
			return;
		}
		?>

        <div class="notice notice-error is-dismissible jquery-migrate-dashboard-notice jquery-migrate-deprecation-notice <?php echo( empty( $logs ) ? 'hidden' : '' ); ?>" data-notice-id="jquery-migrate-deprecation-list">
            <h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?> &mdash; <?php _ex( 'Warnings encountered', 'enable-jquery-migrate-helper' ); ?></h2>
            <p><?php _e( 'This page generated the following warnings:', 'enable-jquery-migrate-helper' ); ?></p>

            <ol class="jquery-migrate-deprecation-list"></ol>

            <p>
				<?php _e( 'Please make sure you are using the latest version of all of your plugins, and your theme.', 'enable-jquery-migrate-helper' ); ?>
				<?php _e( 'If you are, you may want to ask the developers of the code mentioned in the warnings for an update.', 'enable-jquery-migrate-helper' ); ?>
            </p>

			<?php wp_nonce_field( 'jquery-migrate-deprecation-list', 'jquery-migrate-deprecation-list-nonce', false ); ?>
        </div>

		<?php
	}

	public static function show_deprecated_scripts_notice() {
		return false === get_option( '_jquery_migrate_deprecations_dismissed_notice', false );
	}

	/**
	 * HTML for the Dashboard notice.
	 */
	public static function dashboard_notice() {
		if ( ! self::show_dashboard_notice() ) {
			return;
		}
		?>

        <div class="notice notice-warning is-dismissible jquery-migrate-dashboard-notice" data-notice-id="jquery-migrate-notice">
            <h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?></h2>
            <p>
				<?php _e( 'Right now you are using the Enable jQuery Migrate Helper plugin to enable support for old JavaScript code that uses deprecated functions in the jQuery JavaScript library.', 'enable-jquery-migrate-helper' ); ?>
                <br>
                <strong>
					<?php _e( 'Please note that this is a temporary solution which will only work between WordPress 5.5.0 and 5.6.0 is, and is not meant as a permanent fix for unsupported code.', 'enable-jquery-migrate-helper' ); ?>
                </strong>
            </p>

            <p>
				<?php _e( 'If you get warnings, you should check the theme or plugin that generated them for an update. There will very likely be one you can install.', 'enable-jquery-migrate-helper' ); ?>
				<?php _e( 'When you have updated your plugins and themes, and there are no more warnings, please deactivate Enable jQuery Migrate Helper.', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <p>
				<?php _e( '* A script, a file, or some other piece of code is deprecated when its developers are in the process of replacing it with more modern code or removing it entirely.', 'enable-jquery-migrate-helper' ); ?>
            </p>

	        <?php if ( 'no' !== get_option( '_jquery_migrate_downgrade_version', 'no' ) ) : ?>
                <p>
                    <strong>
				        <?php _e( 'This notice is permanent while using a legacy version of jQuery', 'enable-jquery-migrate-helper' ); ?>
                    </strong>
                </p>
	        <?php endif; ?>

            <p>
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=jqmh' ) ); ?>"><?php _e( 'Plugin settings', 'enable-jquery-migrate-helper' ); ?></a> | <a href="<?php echo esc_url( admin_url( 'tools.php?page=jqmh&tab=logs' ) ); ?>"><?php _e( 'Logged deprecations', 'enable-jquery-migrate-helper' ); ?></a>
            </p>

			<?php wp_nonce_field( 'jquery-migrate-notice', 'jquery-migrate-notice-nonce', false ); ?>
        </div>

		<?php
	}

	public static function show_dashboard_notice() {
		// Show again in two weeks if the user has dismissed this notice.
		$is_dismissed = get_option( '_jquery_migrate_dismissed_notice', false );
		$recurrence   = 1 * WEEK_IN_SECONDS;

		// Force show the admin notice if using a downgraded jQuery version.
		if ( 'no' !== get_option( '_jquery_migrate_downgrade_version', 'no' ) ) {
		    // Do not add this message to the plugins own admin page, it already has a permanent notice.
			if ( 'tools_page_jqmh' !== get_current_screen()->id) {
				return true;
			}
		}

		// Normally only show this warning on the dashboard page.
		if ( 'dashboard' !== get_current_screen()->id ) {
		    return false;
		}

		// If the message has been dismissed, and it has been less than two weeks since it was seen,
		// then skip showing the admin notice for now.
		if ( false !== $is_dismissed && $is_dismissed > ( time() - $recurrence ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display a dashboard notice if no deprecations have been logged in a while.
	 *
	 * This encourages users to remove the plugin when no longer needed, this will help gauge the jQuery
	 * migrations, and also remove the potential false-positive reports of jQuery issues caused by
	 * an unrelated core bug fixed in WordPress 5.5.1 that inflates the plugin numbers.
	 */
	public static function plugin_obsolete_message() {
		$last_log   = get_option( 'jqmh_last_log_time', null );
		$recurrence = 1 * WEEK_IN_SECONDS;

		/*
		 * If no log time is recorded, this is likely a recently updated plugin, so set the value to now,
		 * to give a buffer, and avoid showing the notice when we have no data telling if its needed or not.
		 */
		if ( null === $last_log || self::logged_migration_notice_count() > 0 ) {
			update_option( 'jqmh_last_log_time', time() );

			return;
		}

		if ( $last_log > ( time() - $recurrence ) ) {
			return;
		}
		?>

        <div class="notice notice-warning is-dismissible jquery-migrate-dashboard-notice"
             data-notice-id="jquery-migrate-no-deprecations-notice">
            <h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?></h2>

            <p>
				<?php _e( 'No deprecations have been logged on this site in a while, you may no longer need this plugin.', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <p>
				<?php _e( 'Please keep in mind that only notices on the public facing part of your site, or if you have disabled the display on the back end, will be logged and accounted for.', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <p>
				<?php _e( 'This means you should still check that things work as expected after the plugin is disabled, and if you know there have been warnings in the admin pages, you may still need to reach out to the plugin or theme authors affected.', 'enable-jquery-migrate-helper' ); ?>
            </p>

			<?php if ( is_wp_version_compatible( '5.5.1' ) ) : ?>

                <p>
                    <strong>
						<?php _e( 'You are using a WordPress version prior to 5.5.1, this plugin also helps with a bug found in WordPress 5.5.0, you should update to version 5.5.1, or later, before the plugin is deactivated.', 'enable-jquery-migarte-helper' ); ?>
                    </strong>
                </p>

			<?php endif; ?>

			<?php wp_nonce_field( 'jquery-migrate-no-deprecations-notice', 'jquery-migrate-no-deprecations-notice-nonce', false ); ?>
        </div>

		<?php
	}

	public static function admin_notices() {
		// Show only to admins.
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		self::dashboard_notice();

		if ( get_current_screen()->id === 'dashboard' ) {
			self::plugin_obsolete_message();
		}

		self::deprecated_scripts_notice();
	}

	public static function log_migrate_notice() {
		if ( ! current_user_can( 'manage_options' ) && 'no' === get_option( '_jquery_migrate_public_deprecation_logging', 'no' ) ) {
			status_header( 403 );
			die();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'jquery-migrate-report-deprecation' ) ) {
			status_header( 406, 'Invalid nonce' );
			die();
		}

		$required_fields = array(
			'notice',
		);

		foreach ( $required_fields as $required_field ) {
			if ( ! isset( $_POST[ $required_field ] ) ) {
				status_header( 400, 'Missing required fields' );
				die();
			}
		}

		$logs = get_option( 'jqmh_logs', array() );

		$deprecation_data = array(
			'notice' => wp_kses( $_POST['notice'], array() ),
		);

		/*
		 * Creating a hash of the deprecation data lets us ensure it is only reported once, to avoid
		 * filling the database with duplicates on busy sites.
		 */
		$deprecation_hash = md5( wp_json_encode( $deprecation_data ) );

		if ( ! isset( $logs[ $deprecation_hash ] ) ) {
			$logs[ $deprecation_hash ] = array_merge( array(
				'registered' => date_i18n( 'Y-m-d H:i:s' ),
				'page'       => ( isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '' ),
				'backend'    => isset( $_POST['backend'] ) && $_POST['backend'],
			), $deprecation_data );

			update_option( 'jqmh_logs', $logs );
			update_option( 'jqmh_last_log_time', time() );
		}

		wp_send_json_success();
	}

	/**
	 * Check if any errors have been logged to the database.
	 *
	 * @return int|null
	 */
	public static function logged_migration_notice_count() {
		$logs = get_option( 'jqmh_logs', array() );

		return count( $logs );
	}

	/**
	 * Handle ajax requests to dismiss a notice, and remember the dismissal.
	 *
	 * @return void
	 */
	public static function admin_notices_dismiss() {
		if ( empty( $_POST['dismiss-notice-nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['dismiss-notice-nonce'], $_POST['notice'] ) ) {
			return;
		}

		switch ( $_POST['notice'] ) {
			case 'jquery-migrate-deprecation-list':
				update_option( '_jquery_migrate_deprecations_dismissed_notice', time() );
				break;

			case 'jquery-migrate-previous-deprecations':
				delete_option( 'jqmh_logs' );
				break;

			case 'jquery-migrate-notice':
				update_option( '_jquery_migrate_dismissed_notice', time() );
				break;

			case 'jquery-migrate-no-deprecations-notice':
				update_option( 'jqmh_last_log_time', time() );
				break;
		}
	}

	/**
	 * Add this plugin to the admin bar as a menu item.
	 *
	 * This entry allows users to re-surface previously hidden notices from the plugin,
	 * and also allows for alerting of issues detected in the frontend, where injecting
	 * any notice isn't as elegant.
	 *
	 * @param $wp_menu
	 */
	public static function admin_bar_menu( $wp_menu ) {
		// Show only to those with the right capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$deprecation_count = self::logged_migration_notice_count();

		$wp_menu->add_menu(
			array(
				'id'    => 'enable-jquery-migrate-helper',
				'title' => sprintf(
				// translators: %s: Parenthesis with issue count.
					__( 'jQuery Migrate %s', 'enable-jquery-migrate-helper' ),
					sprintf(
						'<span class="count-wrapper jqmh-deprecations" style="' . ( $deprecation_count > 0 ? '' : 'display:none;' ) . '">%s</span>',
						sprintf(
						// translators: 1: The amount of existing issues. 2: Issues discovered on the currently loaded page.
							__( '(Previously known: %1$d Discovered on this page: %2$s)', 'enable-jquery-migrate-helper' ),
							$deprecation_count,
							sprintf(
								'<span class="count">%d</span>',
								0
							)
						)
					)
				),
				'href'  => '#',
			)
		);

		$wp_menu->add_node(
			array(
				'id'     => 'enable-jquery-migrate-helper-page',
                'title'  => _x( 'Settings Page', 'Admin bar menu link', 'enable-jquery-migrate-helper' ),
                'parent' => 'enable-jquery-migrate-helper',
                'href'   => get_admin_url( null, 'tools.php?page=jqmh' ),
			)
		);

		$wp_menu->add_node(
			array(
				'id'     => 'enable-jquery-migrate-helper-deprecation-logs',
				'title'  => _x( 'Logged deprecations', 'Admin bar menu link', 'enable-jquery-migrate-helper' ),
				'parent' => 'enable-jquery-migrate-helper',
				'href'   => get_admin_url( null, 'tools.php?page=jqmh&tab=logs' ),
			)
		);
	}
}
