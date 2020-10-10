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

		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 100 );
		add_action( 'init', array( __CLASS__, 'maybe_show_admin_notices' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'wp_ajax_jquery-migrate-dismiss-notice', array( __CLASS__, 'admin_notices_dismiss' ) );
		add_action( 'wp_ajax_jquery-migrate-log-notice', array( __CLASS__, 'log_migrate_notice' ) );
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

		wp_localize_script(
			'jquery-migrate-deprecation-notices',
			'JQMH',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'report_nonce' => wp_create_nonce( 'jquery-migrate-report-deprecation' ),
				'backend'      => is_admin(),
                'plugin_slug'  => dirname( plugin_basename( __FILE__ ) ),
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
	    if ( ! current_user_can( 'manage_options' ) ) {
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

		<div class="notice notice-error is-dismissible jquery-migrate-dashboard-notice jquery-migrate-deprecation-notice <?php echo ( empty( $logs ) ? 'hidden' : '' ); ?>" data-notice-id="jquery-migrate-deprecation-list">
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

	public static function previous_deprecation_notices() {
		if ( ! isset( $_GET['show-jqmh-previous-notices'] ) ) {
			return;
		}

		$logs = get_option( 'jqmh_logs', array() );
        ?>
		<div class="notice notice-error is-dismissible jquery-migrate-dashboard-notice jquery-migrate-previous-deprecations" data-notice-id="jquery-migrate-previous-deprecations">
            <h2><?php _e( 'Previously logged deprecation notices', 'enable-jquery-migrate-helper' ); ?></h2>

            <p>
				<?php _e( 'The following are deprecations logged from the front-end of your site, or while the deprecation box was disabled.', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php _ex( 'Time', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                    <th><?php _ex( 'Notice', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                    <th><?php _ex( 'Page', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                </tr>
                </thead>

                <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr>
                        <td colspan="3">
                            <?php _e( 'No deprecations have been logged', 'enable-jquery-migrate-helper' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>

				<?php foreach ( $logs as $log ) : ?>

                    <tr>
                        <td><?php echo esc_html( $log['registered'] ); ?></td>
                        <td><?php echo esc_html( $log['notice'] ); ?></td>
                        <td><?php echo esc_html( $log['page'] ); ?></td>
                    </tr>

				<?php endforeach; ?>
                </tbody>

                <tfoot>
                <tr>
                    <th><?php _ex( 'Time', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                    <th><?php _ex( 'Notice', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                    <th><?php _ex( 'Page', 'Admin deprecation notices', 'enable-jquery-migrate-helper' ); ?></th>
                </tr>
                </tfoot>
            </table>

			<?php wp_nonce_field( 'jquery-migrate-previous-deprecations', 'jquery-migrate-previous-deprecations-nonce', false ); ?>

            <p></p>
        </div>

        <?php
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
			<?php wp_nonce_field( 'jquery-migrate-notice', 'jquery-migrate-notice-nonce', false ); ?>
		</div>

		<?php
	}

	public static function show_dashboard_notice() {
		// Show again in two weeks if the user has dismissed this notice.
		$is_dismissed = get_option( '_jquery_migrate_dismissed_notice', false );
		$recurrence   = 1 * WEEK_IN_SECONDS;

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

        <div class="notice notice-warning is-dismissible jquery-migrate-dashboard-notice" data-notice-id="jquery-migrate-no-deprecations-notice">
			<h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?></h2>

            <p>
                <?php _e( 'No deprecations have been logged on this site in a while, you may no longer need this plugins.', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <p>
                <?php _e( 'Please keep in mind that only notices on the public facing part of yoru site, or if you have disabled the display on the back end, will be logged and accounted for.', 'enable-jquery-migrate-helper' ); ?>
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

		if ( get_current_screen()->id === 'dashboard' ) {
			self::dashboard_notice();

			self::previous_deprecation_notices();

			self::plugin_obsolete_message();
		}

		self::deprecated_scripts_notice();
	}

	public static function log_migrate_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
	    if ( ! current_user_can( 'manage_options' ) ) {
	        return null;
	    }

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

		switch( $_POST['notice'] ) {
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
                        '<span class="count-wrapper" style="' . ( $deprecation_count > 0 ? '' : 'display:none;' ) . '">%s</span>',
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

		if ( ! self::show_deprecated_scripts_notice() ) {
			$wp_menu->add_node(
				array(
					'id'     => 'enable-jquery-migrate-helper-show-deprecations',
					'title'  => __( 'Display live deprecation notices', 'enable-jquery-migrate-helper' ),
					'parent' => 'enable-jquery-migrate-helper',
					'href'   => get_admin_url( null, '?show-jqmh-deprecations' ),
				)
			);
		}

		if ( ! self::show_dashboard_notice() ) {
			$wp_menu->add_node(
				array(
					'id'     => 'enable-jquery-migrate-helper-show-notices',
					'title'  => __( 'Display plugin information notice', 'enable-jquery-migrate-helper' ),
					'parent' => 'enable-jquery-migrate-helper',
					'href'   => get_admin_url( null, '?show-jqmh-notice' ),
				)
			);
		}

		$wp_menu->add_node(
            array(
                'id'     => 'enable-jquery-migrate-helper-show-previous-deprecations',
                'title'  => __( 'Show a list of logged deprecations', 'enable-jquery-migrate-helper' ),
                'parent' => 'enable-jquery-migrate-helper',
                'href'   => get_admin_url( null, '?show-jqmh-previous-notices' ),
            )
        );
	}

	public static function maybe_show_admin_notices() {
	    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
	        return;
	    }

	    if ( isset( $_GET['show-jqmh-deprecations'] ) ) {
		    delete_option( '_jquery_migrate_deprecations_dismissed_notice' );

		    add_action( 'admin_notices', function() {
			    ?>
                <div class="notice notice-success is-dismissible">
				    <?php _e( 'Live deprecation notices for jQuery Migrate have been enabled, they will show up in the admin interface when a notice is discovered.', 'enable-jquery-migrate-helper' ); ?>
                </div>
			    <?php
		    } );
	    }

	    if ( isset( $_GET['show-jqmh-notice'] ) ) {
		    delete_option( '_jquery_migrate_dismissed_notice' );
	    }
	}
}
