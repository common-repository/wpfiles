<?php

/**
 * Fired during plugin deactivation
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */

/**
 * Fired during plugin deactivation.
 * This class defines all code necessary to run during the plugin's deactivation.
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */
class Wp_Files_Deactivator {

	/**
     * Folder table
     * @since 1.0.0
     * @var $folder_table
    */
    private static $folder_table = 'wpf';

	/**
	 * Fired during plugin deactivation.
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {

		global $wpdb;

		delete_option( WP_FILES_PREFIX . 'woocommerce-sync-hide' );

		//Account deactivation time
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'account-deactivation-timestamp', time());

		//Save usage tracking
		Wp_Files_Settings::saveUsageTracking(['current_status' => 'Deactive']);

		Wp_Files_Admin::cleanSchedule();

		//WooCommerce 
		$woocommerce_folders =  $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s WHERE `type` = 1', self::getTable(self::$folder_table)));
		
		if(count($woocommerce_folders) == 0) {
			$wpdb->query("DELETE FROM " . self::getTable(self::$folder_table) . " WHERE wocommerce_parent = 1");
		}
		
	}

	/**
     * Mysql table
     * @since 1.0.0
     * @return void
     */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
}
