<?php
/*
Plugin Name: Enable jQuery Migrate Helper
Plugin URI: https://wordpress.org/plugins/enable-jquery-migrate-helper
Description: Enable the jQuery Migrate helper feature during a transitional phase
Version: 0.1.0
Author: Clorith
Author URI: https://www.clorith.net
License: GPLv2
Text Domain: enable-jquery-migrate-helper
*/

class jQuery_Migrate_Helper {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ), 1 );

		// We need our own script for displaying warnings to run as late as possible on the site.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_footer' ), PHP_INT_MAX );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_jquery-migrate-dismiss-notice', array( $this, 'admin_notices_dismiss' ) );
	}

	/**
	 * Enqueue our script assets.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function enqueue() {
		/*
		 * Enqueue jQuery migrate, and force it to be the development version.
		 *
		 * This will ensure that console errors are generated, and we can surface these to the
		 * end user in a responsible manner so that they can reach out to authors of themes
		 * or plugins and get their code updated.
		 */
		wp_deregister_script( 'jquery-migrate' );
		wp_enqueue_script( 'jquery-migrate', plugins_url( 'js/jquery-migrate.js', __FILE__ ), array( 'jquery' ), '1.4.1-ejqmh', false );
	}

	/**
	 * Enqueue the deprecation notice capture handler.
	 */
	public function enqueue_footer() {
		wp_enqueue_script( 'jquery-migrate-deprecation-notices', plugins_url( 'js/deprecation-notice.js', __FILE__ ), array( 'jquery' ), false, true );
	}

	/**
	 * Display admin notices.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function admin_notices() {
		// Do not show notices for regular editors or authors.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/*
		 * The first notice is only displayed if there are JS migration deprecation notices.
		 */
		?>

		<div class="notice notice-error" id="jquery-migrate-deprecation-notice" style="display:none;">
			<h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?> &mdash; <?php _ex( 'Warnings encountered', 'enable-jquery-migrate-helper' ); ?></h2>

			<p>
				<?php _e( 'A deprecated function means it will be removed in an upcoming update, and should be replaced with more modern code.', 'enable-jquery-migrate-helper' ); ?>
			</p>

			<p>
				<?php _e( 'The following notices were encountered on this page:', 'enable-jquery-migrate-helper' ); ?>
			</p>

			<ol id="jquery-migrate-deprecation-list"></ol>


            <p>
                <?php _e( 'Please make sure you are using the latest version for any of the plugins or themes listed above. If you are, you should consider reaching out to their respective developers for an update.' ); ?>
            </p>
		</div>

		<?php

		$message_dismissed = get_option( '_jquery_migrate_dismissed_notice', false);

		$recurrence = 2 * WEEK_IN_SECONDS;

		/*
		 * If the message has been dismissed, and it has been less than two weeks since it was seen,
		 * then skip showing the admin notice for now.
		 */
		if ( false !== $message_dismissed && $message_dismissed > ( time() - $recurrence ) ) {
			return;
		}

		?>

		<div class="notice notice-warning is-dismissible" id="jquery-migrate-notice">
			<h2><?php _ex( 'jQuery Migrate Helper', 'Admin notice header', 'enable-jquery-migrate-helper' ); ?></h2>
			<p>
				<?php _e( 'You are currently using a migration helper for the jQuery script, please check if your theme and plugins still rely on this.' ,'enable-jquery-migrate-helper' ); ?>
			</p>

            <p>
                <?php _e( 'While browsing in the backend, you will be shown a notice if any warnings are detected. ', 'enable-jquery-migrate-helper' ); ?>
            </p>

            <p>
				<?php
				printf(
				// translators: %s: `Console Log` link
					__( 'To find out more about what plugin or theme is causing issues for the public part of yoru site, please consult your %s.', 'enable-jquery-migrate-helper' ),
					sprintf(
						'<a href="%s">%s</a>',
						_x( 'https://wordpress.org/support/article/using-your-browser-to-diagnose-javascript-errors/#step-3-diagnosis', 'URL to article about debugging JavaScript', 'enable-jquery-migrate-helper' ),
						__( 'Console Log', 'enable-jquery-migrate-helper' )
					)
				);
				?>
            </p>
		</div>
		<script type="text/javascript">
			jQuery( 'body' ).on( 'click', '.notice-dismiss', function() {
				jQuery.post(
					'<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
					{
						action: 'jquery-migrate-dismiss-notice'
					}
				);
			} );
		</script>

		<?php
	}

	public function admin_notices_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_option( '_jquery_migrate_dismissed_notice', time() );
	}
}

new jQuery_Migrate_Helper();
