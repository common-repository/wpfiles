<?php

/**
 * Class that contain all about WPFiles subscription
 */
class Wp_Files_Subscription
{    
    /**
     * Detect account subscription
     * @since 1.0.0
     * @return boolean
     */
    public static function is_pro($settings = array())
    {
        if(empty($settings)) {
            $settings = Wp_Files_Settings::loadSettings();
        }
        
        if(self::is_active($settings) && (isset($settings['site_status']['is_free']) && $settings['site_status']['is_free'] == 0) && (isset($settings['site_status']['website']['type']) && $settings['site_status']['website']['type'] == "pro")) {
            return true;
        }

        return false;
    }
    
    /**
     * Verify account is connected
     * @since 1.0.0
     * @param $settings
     * @return boolean
     */
    public static function is_active($settings = array())
    {
        if(empty($settings)) {
            $settings = Wp_Files_Settings::loadSettings();
        }

        if($settings['api_key']) {
            return true;
        }

        return false;
    }
}
