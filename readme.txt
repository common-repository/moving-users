=== Moving Users ===
Contributors: Katsushi Kawamori
Donate link: https://shop.riverforest-wp.info/donate/
Tags: user, users, moving
Requires at least: 4.6
Requires PHP: 8.0
Tested up to: 6.6
Stable tag: 1.05
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Supports the transfer of Users between servers.

== Description ==

Supports the transfer of Users between servers.

= Export =
* Outputs the database as a JSON format file.
* Send the exported JSON file by e-mail.

= Import =
* It reads the exported JSON format file and outputs it to the database.

= Maintain the following =
* User ID
* Login name
* Password
* Email
* User's metadata

= Sibling plugin =
* [Moving Contents](https://wordpress.org/plugins/moving-contents/).
* [Moving Media Library](https://wordpress.org/plugins/moving-media-library/).

== Installation ==

1. Upload `moving-users` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

none

== Screenshots ==

1. Export
2. Import

== Changelog ==

= [1.05] 2024/05/26 =
* Fix - Fixed problem with import files not being copied.

= [1.04] 2024/03/05 =
* Fix - Changed file operations to WP_Filesystem.

= 1.03 =
Changed the way files are read/written.

= 1.02 =
Supported WordPress 6.4.
PHP 8.0 is now required.

= 1.01 =
Supported WordPress 6.1.

= 1.00 =
Initial release.
