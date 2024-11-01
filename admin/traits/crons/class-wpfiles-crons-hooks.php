<?php
/**
 * Class to manage all WPFiles cron jobs / schedule jobs
 */
trait Wp_Files_Crons_hooks
{
    /**
     * Class instance
     * @since 1.0.0
     * @var object $instance
    */
    private static $_instance = null;

    /**
     * Register schedules
     * @since 1.0.0
     * @return void
    */
    public static function registerSchedules()
    {
        //Remove zip files
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'delete_zip_files')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS / 2), 'twicedaily', WP_FILES_PREFIX . 'delete_zip_files');
        }

        //Feedback modal schedule if rating not given yet or hide modal attempt < 3
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'feedback_notice_cron') && !get_option( WP_FILES_PREFIX . 'rating') && (int)get_option( WP_FILES_PREFIX . 'rating-hide-count') < 3) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 30), WP_FILES_PREFIX . 'monthly', WP_FILES_PREFIX . 'feedback_notice_cron');
        }

        //Usage tracking modal cron
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'usage_tracking_modal_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 20), WP_FILES_PREFIX . 'every_twenty_days', WP_FILES_PREFIX . 'usage_tracking_modal_cron');
        }

        //Connect account notice cron
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'connect_account_notice_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 15), WP_FILES_PREFIX . 'every_fifteen_days', WP_FILES_PREFIX . 'connect_account_notice_cron');
        }

        //Post usage tracking cron
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'post_usage_tracking_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 14), WP_FILES_PREFIX . 'biweekly', WP_FILES_PREFIX . 'post_usage_tracking_cron');
        }

        //Watermark fonts cron
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'watermark_font_crons_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 30), WP_FILES_PREFIX . 'monthly', WP_FILES_PREFIX . 'watermark_font_crons_cron');
        }

        //Upgrade to pro cron
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'upgrade_to_pro_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 15), WP_FILES_PREFIX . 'every_fifteen_days', WP_FILES_PREFIX . 'upgrade_to_pro_cron');
        }

        //Daily crons
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'daily_cron')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', WP_FILES_PREFIX . 'daily_cron');
        }

        //Clean folders that are not linked to anything
        if (!wp_next_scheduled(WP_FILES_PREFIX . 'clean_folders_cron')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', WP_FILES_PREFIX . 'clean_folders_cron');
        }
    }

    /**
     * Clean schedules
     * @since 1.0.0
     * @return void
    */
    public static function cleanSchedule()
    {
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'delete_zip_files');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'api_update_status');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'feedback_notice_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'clean_folders_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'upgrade_hello_bar_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'usage_tracking_modal_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'connect_account_notice_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'post_usage_tracking_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'watermark_font_crons_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'upgrade_to_pro_cron');
        wp_clear_scheduled_hook(WP_FILES_PREFIX . 'daily_cron');
    }

    /**
     * Api update status
     * @since 1.0.0
     * @return void
    */
    public function api_update_status()
    {
        $this->settings = (array) Wp_Files_Settings::loadSettings();
        
        //If account is connected
        if(Wp_Files_Subscription::is_active($this->settings)) {

            $stats = new Wp_Files_Stats($this->settings);

            $response = $stats->update_api_status(true);

            if($response->success) {
                wp_send_json_success([
                    "message" => __("API status updated successfully", 'wpfiles')
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response->message
                ]);
            }
        }
    }

    /**
     * Remove useless zip files
     * @since 1.0.0
     * @return void
    */
    public function deleteZipFiles()
    {
        $upload_fl = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . WP_FILES_UPLOAD_DIR . DIRECTORY_SEPARATOR;

        if(is_dir($upload_fl)) {

            $scanDir = scandir($upload_fl);

            foreach ($scanDir as $i => $file) {

                $createdAt = filemtime($upload_fl . $file);

                if ((time() - $createdAt) >= (24 * 60 * 60)) {
                    @unlink($upload_fl . $file);
                }

            }
            
        }
    }
    
    /**
     * Feedback notice cron
     * @since 1.0.0
     * @return void
    */
    public function feedback_notice_cron()
    {
        //If still rating not given and maximum hide attempt is < 3 then show modal again
        if(!get_option( WP_FILES_PREFIX . 'rating') && (int)get_option( WP_FILES_PREFIX . 'rating-hide-count') < 3) {
            delete_option(WP_FILES_PREFIX . 'rating-hide');
        }

        //Clear job
        if(((int)get_option( WP_FILES_PREFIX . 'rating-hide-count') >= 3 || get_option( WP_FILES_PREFIX . 'rating')) && get_option( WP_FILES_PREFIX . 'rate-notice-already-done')) {
            wp_clear_scheduled_hook(WP_FILES_PREFIX . 'feedback_notice_cron');  
        }
    }

    /**
     * Clear useless folders
     * @since 1.0.5
     * @return void
    */
    public function clean_folders_cron()
    {
        //Clear useless folders
        global $wpdb;
		$folders = Wp_Files_Tree::getAllData(null, true, 0, false, false, true);
        if(count($folders) > 0) {
            foreach ($folders as $key => $folder) {
                $folder_ids[] = $folder['id'];
            }
            if(count($folder_ids) > 0) {
                $wpdb->query($wpdb->prepare('DELETE FROM %1$s WHERE id NOT IN(%2$s)', self::getTable(self::$folder_table), implode(',', $folder_ids)));
                $wpdb->query($wpdb->prepare('DELETE FROM %1$s WHERE folder_id NOT IN(%2$s)', self::getTable(self::$relation_table), implode(',', $folder_ids)));
            }
        }
    }

    /**
     * Upgrade hello bar cron
     * @since 1.0.0
     * @return void
    */
    public function upgrade_hello_bar_cron()
    {
        //If still on free plan
        if(!Wp_Files_Subscription::is_pro($this->settings)) {
            delete_option(WP_FILES_PREFIX . 'upgrade-hellobar-hide');
            delete_option(WP_FILES_PREFIX . 'upgrade-hellobar-hide-count');
        }
    }

    /**
     * Tracking modal cron
     * @since 1.0.0
     * @return void
    */
    public function usage_tracking_modal_cron()
    {
        //If still usage tracking is disabled and maximum hide attempt is < 4 then show usage tracking modal again
        if($this->settings['usage_tracking'] == 0 && get_option( WP_FILES_PREFIX . 'usage-tracking-hide') && (int)get_option( WP_FILES_PREFIX . 'usage-tracking-hide-count') < 4) {
            delete_option(WP_FILES_PREFIX . 'usage-tracking-hide');
        }
    }

    /**
     * Connect account notice cron
     * @since 1.0.0
     * @return void
    */
    public function connect_account_notice_cron()
    {
        //Show notice
        if(get_option( WP_FILES_PREFIX . 'account-connect-notice')) {
            delete_option(WP_FILES_PREFIX . 'account-connect-notice');
        }
    }

    /**
     * Post usage tracking cron
     * @since 1.0.0
     * @return void
    */
    public function post_usage_tracking_cron()
    {
        //Save usage tracking
		Wp_Files_Settings::saveUsageTracking(['current_status' => 'Active']);
    }

    /**
     * Watermark font cron
     * @since 1.0.0
     * @return void
    */
    public function watermark_font_crons_cron()
    {
        //Load fonts
        $response = wp_remote_request(WP_FILES_CDN_ENDPOINT.'/plugin-assets/fonts/fonts.json');

        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            
            $fonts = json_decode($response['body']);

            $file_name = WP_FILES_PLUGIN_DIR.'admin/json/fonts.json';

            file_put_contents($file_name, json_encode($fonts));
        
        }
    }

    /**
     * Upgrade to pro cron
     * @since 1.0.0
     * @return void
    */
    public function upgrade_to_pro_cron()
    {
        //Show notice
        if(get_option( WP_FILES_PREFIX . 'upgrade-to-pro-notice')) {
            delete_option(WP_FILES_PREFIX . 'upgrade-to-pro-notice');
        }

        //Show upgrade to pro notice when open media library on Third party page builders
        if(get_option( WP_FILES_PREFIX.'media-library-pro-alert-hide')) {
            delete_option(WP_FILES_PREFIX.'media-library-pro-alert-hide');
        }
    }

    /**
     * Daily cron
     * @since 1.0.0
     * @return void
    */
    public function dailyCron()
    {
        //Show tip on media screen
        if(get_option( WP_FILES_PREFIX.'feature-tooltip-value') && get_option( WP_FILES_PREFIX.'feature-tooltip') && get_option( WP_FILES_PREFIX.'feature-tooltip-value') != "permanently") {
            $current_date = date('Y-m-d');
            $resume_date = get_option( WP_FILES_PREFIX.'feature-tooltip-value');
            if ($current_date >= $resume_date) {
                delete_option(WP_FILES_PREFIX.'feature-tooltip');
                delete_option(WP_FILES_PREFIX.'feature-tooltip-value');
            }
        }
    }

    /**
     * Return class instance
     * @since 1.0.0
     * @return object
    */
    public static function instance()
    {
        if (is_null(self::$_instance)) self::$_instance = new self;
        return self::$_instance;
    }

    /**
     * Schedules crons
     * @since 1.0.0
     * @param $schedules
     * @return array
    */
    public function cron_schedules( $schedules ) {

		$schedules[WP_FILES_PREFIX . 'monthly'] = array(
			'interval' => 2635200,
			'display'  => __( 'Once a month' )
		);

        $schedules[WP_FILES_PREFIX . 'biweekly'] = array(
			'interval' => 1209600,
			'display'  => __( 'Biweekly' )
		);

        $schedules[WP_FILES_PREFIX . 'every_twenty_days'] = array(
			'interval' => 1728000,
			'display'  => __( 'Every 20 days' )
		);

        $schedules[WP_FILES_PREFIX . 'every_fifteen_days'] = array(
			'interval' => 1296000,
			'display'  => __( 'Every 15 days' )
		);

		return $schedules;
	}

    /**
     * check schedules
     * @since 1.0.0
     * @return array
    */
    public function check_schedules( ) {
        
        //Upgrade hello bar
        if(Wp_Files_Subscription::is_pro($this->settings) && wp_next_scheduled(WP_FILES_PREFIX . 'upgrade_hello_bar_cron')) {
            wp_clear_scheduled_hook(WP_FILES_PREFIX . 'upgrade_hello_bar_cron');
        } else if(!Wp_Files_Subscription::is_pro($this->settings) && !wp_next_scheduled(WP_FILES_PREFIX . 'upgrade_hello_bar_cron')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 14), WP_FILES_PREFIX . 'biweekly', WP_FILES_PREFIX . 'upgrade_hello_bar_cron');
        }

        //Usage tracking modal
        if(($this->settings['usage_tracking'] == 1 && wp_next_scheduled(WP_FILES_PREFIX . 'usage_tracking_modal_cron')) || (int)get_option( WP_FILES_PREFIX . 'usage-tracking-hide-count') >= 4) {
            wp_clear_scheduled_hook(WP_FILES_PREFIX . 'usage_tracking_modal_cron');
        } else if($this->settings['usage_tracking'] == 0 && !wp_next_scheduled(WP_FILES_PREFIX . 'usage_tracking_modal_cron') && (int)get_option( WP_FILES_PREFIX . 'usage-tracking-hide-count') < 4) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 20), WP_FILES_PREFIX . 'every_twenty_days', WP_FILES_PREFIX . 'usage_tracking_modal_cron');
        }

        //Connect account notice
        if((wp_next_scheduled(WP_FILES_PREFIX . 'connect_account_notice_cron')) && Wp_Files_Subscription::is_active($this->settings)) {
            wp_clear_scheduled_hook(WP_FILES_PREFIX . 'connect_account_notice_cron');
        } else if(!wp_next_scheduled(WP_FILES_PREFIX . 'connect_account_notice_cron') && !Wp_Files_Subscription::is_active($this->settings)) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 15), WP_FILES_PREFIX . 'every_fifteen_days', WP_FILES_PREFIX . 'connect_account_notice_cron');
        }

        //Upgrade to pro notice
        if((wp_next_scheduled(WP_FILES_PREFIX . 'upgrade_to_pro_cron')) && Wp_Files_Subscription::is_pro($this->settings)) {
            wp_clear_scheduled_hook(WP_FILES_PREFIX . 'upgrade_to_pro_cron');
        } else if(!wp_next_scheduled(WP_FILES_PREFIX . 'upgrade_to_pro_cron') && !Wp_Files_Subscription::is_pro($this->settings)) {
            wp_schedule_event(time() + (DAY_IN_SECONDS * 15), WP_FILES_PREFIX . 'every_fifteen_days', WP_FILES_PREFIX . 'upgrade_to_pro_cron');
        }
    }
}
