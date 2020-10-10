=== Enable jQuery Migrate Helper ===
Contributors: wordpressdotorg, clorith, azaozz
Requires at least: 5.4
Tested up to: 5.5
Stable tag: 1.0.1
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

With the update to WordPress 5.5, a migration tool known as `jquery-migrate` will no longer be enabled by default. This may lead to unexpected behaviors in some themes or plugins who run older code.

This plugin serves as a temporary solution, enabling the migration script for your site to give your plugin and theme authors some more time to update, and test, their code.

== Frequently Asked Questions ==

= What does it mean that something is "deprecated" =
A script, a file, or some other piece of code is deprecated when its developers are in the process of replacing it with more modern code or removing it entirely.

= How do I find and use the browser console =
WordPress.org has an article about [using the browsers console log to diagnose JavaScript errors](https://wordpress.org/support/article/using-your-browser-to-diagnose-javascript-errors/).

== Installation ==

1. Upload to your plugins folder, usually `wp-content/plugins/`.
2. Activate the plugin on the plugin screen.
3. That's it! The plugin handles the rest automatically for you.

== Changelog ==

= v 1.1.0 =
* Added option to dismiss deprecation notices in backend
* Added logging of deprecation notices in the front end
* Added admin bar entry to show when deprecations occur
* Added view of logged deprecations
* Changed the time interval between showing the dashboard nag from 2 weeks to 1 week, as WordPress 5.6 comes closer.

= v 1.0.1 =
* Fix one of the admin notices being non-dismissible.

= v 1.0.0 =
* Initial release.
