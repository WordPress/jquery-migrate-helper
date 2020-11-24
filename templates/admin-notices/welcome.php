<?php
/**
 * Admin notice template for the plugins welcome message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
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