<?php
/**
 * Admin page header
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

$page = ( ! empty( $_GET['tab'] ) ? $_GET['tab'] : 'settings' );

?>

<h1>jQuery Migrate</h1>

<div class="notice notice-info">
    <p>
        jQuery is a framework that makes creating interactive elements on your website quick and easy for theme and plugin authors.
    </p>

    <p>
        Because of this versatility, WordPress has included a version of the jQuery library for a long time.
    </p>

    <p>
        Due to this inclusion, many plugins and themes have implemented features which are no longer maintained in jQuery, but the Migrate tool has put them back in, but only as a temporary solution.
    </p>

    <p>
        When WordPress removed this Migration tool, and when it upgraded the version of jQuery included, some themes and plugins stopped working, because their code was outdated.
    </p>
</div>

<nav class="nav-tab-wrapper" aria-label="Secondary menu">
    <a class="nav-tab <?php echo ( 'settings' === $page ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=jqmh' ) ); ?>"><?php _e( 'Settings', 'enable-jquery-migrate-helper' ); ?></a>
    <a class="nav-tab <?php echo ( 'logs' === $page ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=jqmh&tab=logs' ) ); ?>"><?php _e( 'Logged deprecations', 'enable-jquery-migrate-helper' ); ?></a>
</nav>

<?php
switch ( $page ) {
	case 'logs':
        include_once __DIR__ . '/logs.php';
        break;
	case 'settings':
	default:
        include_once __DIR__ . '/settings.php';
}