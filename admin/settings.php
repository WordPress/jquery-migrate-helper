<?php
/**
 * Admin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

$downgraded = get_option( '_jquery_migrate_downgrade_version', 'no' );
$show_deprecations = jQuery_Migrate_Helper::show_deprecated_scripts_notice();
?>

<h2>Settings</h2>

<form method="post" action="">
    <input type="hidden" name="jqmh-settings" value="true">
	<?php wp_nonce_field( 'jqmh-settings' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
                <label for="jquery-version">
				    <?php _e( 'jQuery Version', 'enable-jquery-migrate-helper' ); ?>
                </label>
			</th>
			<td>
				<select name="jquery-version" id="jquery-version">
                    <option value="" <?php echo ( 'no' === $downgraded ? 'selected="selected"' : '' ); ?>><?php _ex( 'Default from WordPress', 'jQuery version', 'enable-jquery-migrate-helper' ); ?></option>
                    <option value="1" <?php echo ( 'yes' === $downgraded ? 'selected="selected"' : '' ); ?>><?php _ex( 'Legacy 1.12.4-wp', 'jQuery version', 'enable-jquery-migrate-helper' ); ?></option>
                </select>
			</td>
		</tr>

        <tr>
            <th scope="row">
                <?php _e( 'Live deprecations', 'enable-jquery-migrate-helper' ); ?>
            </th>
            <td>
                <label>
                    <input name="live-deprecations" type="checkbox" <?php checked( $show_deprecations ); ?>>
                    <?php _e( 'Show deprecation notices, on each admin page, as they happen', 'enable-jquery-migrate-helper' ); ?>
                </label>
            </td>
        </tr>
	</table>

    <?php submit_button( __( 'Save settings', 'enable-jquery-migrate-helper' ) ); ?>
</form>