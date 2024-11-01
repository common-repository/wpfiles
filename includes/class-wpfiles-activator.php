<?php

/**
 * Fired during plugin activation
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */

/**
 * Fired during plugin activation.
 * This class defines all code necessary to run during the plugin's activation.
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */
class Wp_Files_Activator
{

	/**
	 * Fired during plugin activation.
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate()
	{
		//Create DB Structure
		Wp_Files_Helper::createDbStructure();

		//Save default settings if not exist
		$options = Wp_Files_Helper::settingsOptions();
		
		foreach ($options as $index => $option) {
			$value = Wp_Files_Helper::existOption(WP_FILES_PREFIX. $option);
			if(!$value) {
				Wp_Files_Helper::resetDefaultSettings($option);
			}
		}

		//Reset default settings 
		Wp_Files_Helper::resetDefaultSettings(null, false, true);

		//Reset default colors
		Wp_Files_Settings::resetDefaultColors();
		
		//Redirect to installer page if activating plugin first time
		if(!get_option('wpfiles-install-hide')) {
			add_option('wpfiles-activation-redirect', true);
		}

		//Account activation time
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'account-activation-timestamp', time());

		$location_info = Wp_Files_Helper::getLocationInfoByIp();

		//User activation country code
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'activation-country-code', $location_info['country']);
		
		//Save usage tracking
		Wp_Files_Settings::saveUsageTracking(['current_status' => 'Active']);

		//Hide rating modal/notice [Will active after a month by cron job]
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'rating-hide', 1);
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'rate-notice-hide', 1);

		//Hide it when initial activation of plugin
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'upgrade-to-pro-notice', 1);

		$settings = Wp_Files_Settings::loadSettings();

		

		//Current user who installing plugin
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'activating_user', get_current_user_id());
	}
}
