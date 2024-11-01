<?php

/**
 * Fired during plugin uninstallation
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */

/**
 * Fired during plugin uninstallation.
 * This class defines all code necessary to run during the plugin's uninstallation.
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */
class Wp_Files_Uninstall {

    /**
     * Folder table
     * @since 1.0.0
     * @var string $folder_table
    */
	private static $folder_table = 'wpf';

    /**
     * Attachment Folder relation table
     * @since 1.0.0
     * @var string $relation_table
    */
	private static $relation_table = 'wpf_attachment_folder';

    /**
     * Upload file types
     * @since 1.0.0
     * @var string $upload_file_types
    */
    private static $upload_file_types = 'wpf_upload_file_types';

    /**
     * Colors table
     * @since 1.0.0
     * @var string $wpf_colors
    */
    private static $wpf_colors = 'wpf_colors';

    /**
     * Directory optimization/watermarked images
     * @since 1.0.0
     * @var string $wpf_dir_optimize_watermark_images
    */
    private static $wpf_dir_optimize_watermark_images = 'wpf_dir_optimize_watermark_images';

	/**
	 * Fired during plugin uninstallation.
	 * @since 1.0.0
     * @return void
	 */
	public static function uninstall() {

		global $wpdb;
		
		$settings = (array) Wp_Files_Settings::loadSettings();
		
        //Account uninstall time
		Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'account-uninstall-timestamp', time());

		//Save usage tracking
		Wp_Files_Settings::saveUsageTracking(['current_status' => 'Uninstalled']);

		if(isset($settings['is_plugin_removal_delete_data']) && $settings['is_plugin_removal_delete_data'] == 1) {

			$wpdb->query( "DROP TABLE IF EXISTS ".self::getTable(self::$folder_table) );
			$wpdb->query( "DROP TABLE IF EXISTS ".self::getTable(self::$relation_table) );
			$wpdb->query( "DROP TABLE IF EXISTS ".self::getTable(self::$upload_file_types) );
			$wpdb->query( "DROP TABLE IF EXISTS ".self::getTable(self::$wpf_dir_optimize_watermark_images) );
			$wpdb->query( "DROP TABLE IF EXISTS ".self::getTable(self::$wpf_colors) );

            // Option names prefixed with WP_FILES_PREFIX.
            $wfiles_keys = array(
                //Thirdparty
                'external_updates-wpfiles-pro',
                'wpfiles_version',

                WP_FILES_PREFIX . 'recompress-list',
                WP_FILES_PREFIX . 'resize_sizes',
                WP_FILES_PREFIX . 'transparent_png',
                WP_FILES_PREFIX . 'image_sizes',
                WP_FILES_PREFIX . 'super_compressed',
                WP_FILES_PREFIX . 'settings_updated',
                WP_FILES_PREFIX . 'hide_wpfiles_welcome',
                WP_FILES_PREFIX . 'hide_update_info',
                WP_FILES_PREFIX . 'install-type',
                WP_FILES_PREFIX . 'version',
                WP_FILES_PREFIX . 'scan',
                WP_FILES_PREFIX . 'settings',
                WP_FILES_PREFIX . 'cdn_status',
                WP_FILES_PREFIX . 'lazy_load',
                WP_FILES_PREFIX . 'last_run',
                WP_FILES_PREFIX . 'networkwide',
                WP_FILES_PREFIX . 'cron_update_running',
                WP_FILES_PREFIX . 'conflict-notice',
                WP_FILES_PREFIX . 'account-connect-notice',
                WP_FILES_PREFIX . 'cdn-suspended-notice',
                WP_FILES_PREFIX . 'initial-trial-upgrade-notice',
                WP_FILES_PREFIX . 'account-payment-due-notice',
                WP_FILES_PREFIX . 'upgrade-to-pro-notice',
                WP_FILES_PREFIX . 'website-pro-to-free-notice',
                WP_FILES_PREFIX . 'website-pro-to-free-notice-value',
                WP_FILES_PREFIX . 'show_upgrade_modal',
                WP_FILES_PREFIX . 'preset_configs',
                WP_FILES_PREFIX . 'webp_hide_wizard',
                WP_FILES_PREFIX . 'tutorials',
                WP_FILES_PREFIX . 'hide_tutorials_from_bulk_compress',
                WP_FILES_PREFIX . 'rating',
                WP_FILES_PREFIX . 'rating-hide',
                WP_FILES_PREFIX . 'rating-hide-count',
                WP_FILES_PREFIX . 'rating-id',
                WP_FILES_PREFIX . 'feedback-id',
                WP_FILES_PREFIX . 'install-hide',
                WP_FILES_PREFIX . 'install-hide-count',
                WP_FILES_PREFIX . 'usage-tracking-hide',
                WP_FILES_PREFIX . 'usage-tracking-hide-count',
                WP_FILES_PREFIX . 'usage-tracking-notice-hide',
                WP_FILES_PREFIX . 'rate-notice-hide',
                WP_FILES_PREFIX . 'rate-notice-already-done',
                WP_FILES_PREFIX . 'upgrade-hellobar-hide',
                WP_FILES_PREFIX . 'upgrade-hellobar-hide-count',
                WP_FILES_PREFIX . 'newsletter-hide',
                WP_FILES_PREFIX . 'newsletter-id',
                WP_FILES_PREFIX . 'activation-redirect',
                WP_FILES_PREFIX . 'woocommerce-sync-hide',
                WP_FILES_PREFIX . 'usage-tracking-disable-timestamp',
                WP_FILES_PREFIX . 'account-connection-timestamp',
                WP_FILES_PREFIX . 'subscription-upgrade-timestamp',
                WP_FILES_PREFIX . 'subscription-downgrade-timestamp',
                WP_FILES_PREFIX . 'account-activation-timestamp',
                WP_FILES_PREFIX . 'account-deactivation-timestamp',
                WP_FILES_PREFIX . 'account-uninstall-timestamp',
                WP_FILES_PREFIX . 'account-plan-id',
                WP_FILES_PREFIX . 'activation-country-code',
                WP_FILES_PREFIX . 'domain-mismatch',
                WP_FILES_PREFIX . 'domain-mismatch-hide',
                WP_FILES_PREFIX . 'free-to-pro-plugin-conversion-notice',
                WP_FILES_PREFIX . 'feature-tooltip',
                WP_FILES_PREFIX . 'feature-tooltip-value',
                WP_FILES_PREFIX . 'installation-steps',
                'wpfiles_updated_from_enhanced',
                'wpfiles_updated_from_wpmlf',
                'wpfiles_updated_from_wpmf',
                'wpfiles_updated_from_realmedia',
                'wpfiles_updated_from_happyfiles',
                'wpfiles_updated_from_filebird',
                'wpf-query-params',
                'wpfiles_sort_folder',
                'wpfiles_updated_from_wpmlf',
                WP_FILES_PREFIX . 'joy-ride-alert-hide',
                WP_FILES_PREFIX . 'joy-ride-tour-completed',
                WP_FILES_PREFIX . 'activating_user',
                WP_FILES_PREFIX . 'last-watermark-save'
            );

            //Unlink watermark
            if(file_exists(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'))) {
                unlink(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'));
            }

            //Settings options
            $options = Wp_Files_Helper::settingsOptions();

            foreach ($options as $key => $option) {
                array_push($wfiles_keys, WP_FILES_PREFIX .$option);
            }

            $db_keys = array(
                'skip-wpfiles-setup',
                'wpfiles_global_stats',
            );

            // Cache Keys.
            $cache_wpfiles_group = array(
                'exceeding_items',
                WP_FILES_PREFIX . 'resize_count',
                WP_FILES_PREFIX . 'resize_savings',
                WP_FILES_PREFIX . 'pngjpg_savings',
                WP_FILES_PREFIX . 'compressed_ids',
                'media_attachments',
                'skipped_images',
                'images_with_backups',
                WP_FILES_PREFIX . 'dir_total_compression_stats',
                WP_FILES_PREFIX . 'dir_total_watermark_stats',
                WP_FILES_PREFIX . 'settings',
                WP_FILES_PREFIX . 'mapped_site_domain',
                WP_FILES_PREFIX . 'load-google-fonts',
            );

            if ( ! is_multisite() ) {
                // Delete Options.
                foreach ( $wfiles_keys as $key ) {
                    delete_option( $key );
                    delete_site_option( $key );
                }
            
                foreach ( $db_keys as $key ) {
                    delete_option( $key );
                    delete_site_option( $key );
                }
            
                // Delete Cache data.
                foreach ( $cache_wpfiles_group as $s_key ) {
                    wp_cache_delete( $s_key, WP_FILES_CACHE_PREFIX );
                }
            
                wp_cache_delete( 'get_image_sizes', 'wpfiles_mage_sizes' );
            
                delete_transient( WP_FILES_PREFIX . 'conflict_check' );
            }
            
            // Delete Directory Compression stats.
            delete_option( 'dir_compression_stats' );
            delete_option( 'wp_files_scan' );
            delete_option( 'wp_files_api_auth' );
            delete_site_option( 'wp_files_api_auth' );

            // Delete Post meta.
            $meta_type  = 'post';
            $meta_key   = 'wpfiles-pro-compress-data';
            $meta_value = '';
            $delete_all = true;

            if ( is_multisite() ) {
                $offset = 0;
                $limit  = 100;
                while ( $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT $offset, $limit", ARRAY_A ) ) {
                    if ( $blogs ) {
                        foreach ( $blogs as $blog ) {
                            switch_to_blog( $blog['blog_id'] );
                            delete_metadata( $meta_type, null, $meta_key, $meta_value, $delete_all );
                            delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'lossy', '', $delete_all );
                            delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'resize_savings', '', $delete_all );
                            delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'original_file', '', $delete_all );
                            delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'pngjpg_savings', '', $delete_all );

                            foreach ( $wfiles_keys as $key ) {
                                delete_option( $key );
                                delete_site_option( $key );
                            }

                            foreach ( $db_keys as $key ) {
                                delete_option( $key );
                                delete_site_option( $key );
                            }

                            // Delete Cache data.
                            foreach ( $cache_wpfiles_group as $s_key ) {
                                wp_cache_delete( $s_key, WP_FILES_CACHE_PREFIX );
                            }

                            wp_cache_delete( 'get_image_sizes', 'wpfiles_mage_sizes' );
                        }
                        restore_current_blog();
                    }
                    $offset += $limit;
                }
            } else {
                delete_metadata( $meta_type, null, $meta_key, $meta_value, $delete_all );
                delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'lossy', '', $delete_all );
                delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'resize_savings', '', $delete_all );
                delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'original_file', '', $delete_all );
                delete_metadata( $meta_type, null, WP_FILES_PREFIX . 'pngjpg_savings', '', $delete_all );
            }

            // Delete directory scan data.
            delete_option( WP_FILES_PREFIX . 'scan-step' );

            // Delete all WebP images.
            global $wp_filesystem;
            if ( is_null( $wp_filesystem ) ) {
                WP_Filesystem();
            }

            $upload_dir = wp_get_upload_dir();
            $webp_dir   = dirname( $upload_dir['basedir'] ) . '/wpfiles-webp';
            $wp_filesystem->delete( $webp_dir, true );

            // Delete WebP test image.
            $webp_img = $upload_dir['basedir'] . '/wpfiles-webp-test.png';
            $wp_filesystem->delete( $webp_img );
            
		}
	}

	/**
	 * Get table
	 * @since 1.0.0
     * @return void
	 */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
}
