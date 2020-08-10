<?php
/*
Plugin Name: Enable jQuery Migrate Helper
Plugin URI: https://wordpress.org/plugins/enable-jquery-migrate-helper
Description: Enable support for old and outdated plugins and themes during a jQuery update transitional phase.
Version: 1.0.0
Requires at least: 5.4
Tested up to: 5.5
Requires PHP: 5.6
Author: The WordPress Team
Author URI: https://wordpress.org
Contributors: wordpressdotorg, clorith, azaozz
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: jquery-migrate-helper
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

if ( version_compare( $GLOBALS['wp_version'], '5.6-alpha', '<' ) && ! class_exists( 'jQuery_Migrate_Helper' ) ) {
	include_once __DIR__ . '/class-jquery-migrate-helper.php';
	add_action( 'plugins_loaded', array( 'jQuery_Migrate_Helper', 'init_actions' ) );
}
