<?php

/**
 * WPFiles
 *
 * WPFiles is the best WordPress media plugin to organize media library into folders,
 * increase your website speed by compressing and lazy loading images, CDN support, advanced automatic watermarking and much more.
 *
 * @link              https://wpfiles.io
 * @since             1.0.0
 * @package           WPFiles
 *
 * @wordpress-plugin
 * Plugin Name:       WPFiles
 * Plugin URI:        http://wordpress.org/plugins/wpfiles/
 * Description:       A single powerful and easy-to-use plugin to take care of all media needs. Organize your media files into folders. Speed up your website by compressing and lazy loading images, CDN support, advanced automatic watermarking and much more!
 * Version:           1.1.1
 * Author:            Media Library Folders - RipeBits
 * Author URI:        https://wpfiles.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpfiles
 * Domain Path:       /languages/
 */

 /*
This plugin was originally developed by WPFiles Team (https://wpfiles.io).

Copyright 2023 WPFiles (https://wpfiles.io)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

if ( ! defined( 'WP_FILES_VERSION' ) ) {
	define('WP_FILES_VERSION', '1.1.1');
}

//Rest api routes prefix
if ( ! defined( 'WP_FILES_REST_API_PREFIX' ) ) {
	define('WP_FILES_REST_API_PREFIX', 'wpfiles/v1');
}

if ( ! defined( 'WP_FILES_BASENAME' ) ) {
	define( 'WP_FILES_BASENAME', plugin_basename( __FILE__ ) );
}

if (!defined('WP_FILES_UPLOAD_DIR')) {
	define('WP_FILES_UPLOAD_DIR', 'wpfiles-uploads');
}

if (!defined('WP_FILES_PLUGIN_URL')) {
	define('WP_FILES_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('WP_FILES_PLUGIN_DIR')) {
	define('WP_FILES_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if ( ! defined( 'WP_FILES_PLUGIN_FILE' ) ) {
	define( 'WP_FILES_PLUGIN_FILE', __FILE__ );
}

if (!defined('WP_FILES_PREFIX')) {
	define('WP_FILES_PREFIX', 'wpfiles-');
}

if (!defined('WP_FILES_PLUGIN_NAME')) {
	define('WP_FILES_PLUGIN_NAME', 'wpfiles');
}

if (!defined('WP_FILES_LOCALIZATION_PREFIX')) {
	define('WP_FILES_LOCALIZATION_PREFIX', 'wpfiles');
}

if (!defined('WP_FILES_CACHE_PREFIX')) {
	define('WP_FILES_CACHE_PREFIX', 'wpfiles');
}

if (!defined('WP_FILES_PRO_MAX_BYTES')) {
	define('WP_FILES_PRO_MAX_BYTES', 32000000);
}

if (!defined('WP_FILES_MAX_FREE_BYTES')) {
	define('WP_FILES_MAX_FREE_BYTES', 5000000);
}

if (!defined('WP_FILES_TIMEOUT')) {
	define('WP_FILES_TIMEOUT', 300);
}

if (!defined('WP_FILES_UA')) {
	define('WP_FILES_UA', 'WP WPFiles/' . WP_FILES_VERSION . '; ' . network_home_url());
}

if (!defined('WP_FILES_OPTIMIZE_API')) {
	define('WP_FILES_OPTIMIZE_API', 'https://optimize.wpfiles.io');
}

if (!defined('WP_FILES_API_URL')) {
	define('WP_FILES_API_URL', 'https://api.wpfiles.io');
}

if (!defined('WP_FILES_URL')) {
	define('WP_FILES_URL', 'https://wpfiles.io');
}

if (!defined('WP_FILES_GO_URL')) {
	define('WP_FILES_GO_URL', 'https://go.wpfiles.io');
}

if (!defined('WP_FILES_CDN_POPS')) {
	define('WP_FILES_CDN_POPS', '107');
}

if (!defined('WP_FILES_CDN_ENDPOINT')) {
	define('WP_FILES_CDN_ENDPOINT', 'https://cdn.wpfiles.io');
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpfiles-activator.php
 * @since 1.0.0
 */
if ( ! function_exists( 'wpfiles_activate' ) ) {
	function wpfiles_activate()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/class-wpfiles-activator.php';
		Wp_Files_Activator::activate();
	}
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpfiles-deactivator.php
 * @since 1.0.0
 */
if ( ! function_exists( 'wpfiles_deactivate' ) ) {
	function wpfiles_deactivate()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/class-wpfiles-deactivator.php';
		Wp_Files_Deactivator::deactivate();
	}
}

/**
 * The code that runs during plugin uninstallation.
 * This action is documented in includes/class-wpfiles-uninstall.php
 * @since 1.0.0
 */
if ( ! function_exists( 'wpfiles_uninstall' ) ) {
	function wpfiles_uninstall()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/class-wpfiles-uninstall.php';
		Wp_Files_Uninstall::uninstall();
	}
}

/**
 * If we are activating a version, while having another present and activated.
 * Leave in the Pro version, if it is available.
 * @since 1.0.0
 */

if ( WP_FILES_BASENAME !== plugin_basename( __FILE__ ) ) {

	$pro = false;

	if ( file_exists( WP_PLUGIN_DIR . '/wpfiles-pro/wpfiles.php' ) ) {
		$pro = true;
	}

	if ( is_plugin_active( 'wpfiles-pro/wpfiles.php' ) ) {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		return;

	} elseif ( $pro && is_plugin_active( WP_FILES_BASENAME ) ) {

		update_option( WP_FILES_PREFIX . 'free-to-pro-plugin-conversion-notice', 1 );

		deactivate_plugins( WP_FILES_BASENAME );

		// If Activating process of plugin
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}
		
		activate_plugin( plugin_basename( __FILE__ ) );
	}
	
}

register_activation_hook(__FILE__, 'wpfiles_activate');

register_deactivation_hook(__FILE__, 'wpfiles_deactivate');

register_uninstall_hook(__FILE__, 'wpfiles_uninstall');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 * @since 1.0.0
 */
require plugin_dir_path(__FILE__) . 'includes/class-wpfiles.php';

// Try and include our autoloader.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

/**
 * Begins execution of the plugin.
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 * @since    1.0.0
 */
if ( ! function_exists( 'wpfiles_start' ) ) {
	function wpfiles_start()
	{
		$plugin = new WPFiles();
		$plugin->run();
	}
}

wpfiles_start();
