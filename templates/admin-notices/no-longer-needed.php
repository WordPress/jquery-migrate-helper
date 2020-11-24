<?php
/**
 * Admin notice template encouraging removal of the plugin when it may no longer be needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

?>
<div class="notice notice-warning is-dismissible jquery-migrate-dashboard-notice" data-notice-id="jquery-migrate-no-deprecations-notice">
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
				<?php _e( 'You are using a WordPress version prior to 5.5.1, this plugin also helps with a bug found in WordPress 5.5.0, you should update to version 5.5.1, or later, before deactivating the plugin.', 'enable-jquery-migrate-helper' ); ?>
			</strong>
		</p>

	<?php endif; ?>

	<?php wp_nonce_field( 'jquery-migrate-no-deprecations-notice', 'jquery-migrate-no-deprecations-notice-nonce', false ); ?>
</div>