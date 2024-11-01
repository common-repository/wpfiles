<?php
/**
 * In this class, you will find all generic settings related to WPFiles.
 */
class Wp_Files_Settings
{
    /**
     * Class instance
     * @var $instance
     * @since 1.0.0
    */
    protected static $instance = null;

    /**
     * Folder table
     * @var $folder_table
     * @since 1.0.0
    */
    private static $folder_table = 'wpf';

    /**
     * Upload file types
     * @var $upload_file_types
     * @since 1.0.0
    */
    private static $upload_file_types = 'wpf_upload_file_types';

    /**
     * Directory optimize/watermark results
     * @var $wpf_dir_optimize_watermark_images
     * @since 1.0.0
    */
    private static $wpf_dir_optimize_watermark_images = 'wpf_dir_optimize_watermark_images';

    /**
     * Colors
     * @var $wpf_colors
     * @since 1.0.0
    */
    private static $wpf_colors = 'wpf_colors';

    /**
     * Return class instance
     * @since 1.0.0
     * @return object
     */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Return WPFiles settings
     * @since 1.0.0
     * @return JSON
     */
    public static function loadSettings()
    {
        global $wpdb;

        $settings = wp_cache_get(WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX);

        if (!$settings) {

            $options = Wp_Files_Helper::settingsOptions();
            
            $result = array();

            foreach ($options as $option) {
                if(in_array($option, ['site_status', 'lazy_disable_classes', 'lazy_disable_urls', 'media_columns', 'image_manual_sizes', 'lazy_media_types', 'lazy_output_location', 'lazy_post_types', 'watermark_image_sizes_manual', 'rename_media_type_values', 'rename_media_remove_char'])) {
                    $value = get_option(WP_FILES_PREFIX. $option);
                    $result[$option] = $value ? $value : [];
                } else {
                    $result[$option] = get_option(WP_FILES_PREFIX. $option, '');
                }
            }

            //Plugin auto update setting
            $result['auto_update'] = self::get_auto_update_setting();

            //Folder colors
            $wpf_colors = $wpdb->prefix . 'wpf_colors';

            if ($wpdb->get_var("show tables like '$wpf_colors'") == $wpf_colors) {

                $folder_colors = $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s', self::getTable(self::$wpf_colors)), ARRAY_A);

                $result['folder_colors'] = (array)$folder_colors;
                
            }

            //Options
            $rating_hide = get_option( WP_FILES_PREFIX . 'rating-hide' );

            $result['is_rating'] = !$rating_hide ? true : false;

            $wocommerce_sync_hide = get_option( WP_FILES_PREFIX . 'woocommerce-sync-hide' );

            $result['is_wocommerce_sync'] = $result['woocommerce_support'] && !$wocommerce_sync_hide && class_exists( 'WooCommerce' ) ? true : false;

            $result['installation_steps'] = get_option( WP_FILES_PREFIX . 'installation-steps', array() );
            
            $result['is_newsletter_widget'] = get_option( WP_FILES_PREFIX . 'newsletter-hide', false ) || get_option( WP_FILES_PREFIX . 'newsletter-id');

            $result['is_usage_tracking'] = get_option( WP_FILES_PREFIX . 'usage-tracking-hide', false );

            $result['is_installed'] = get_option( WP_FILES_PREFIX . 'install-hide', false );

            $result['is_feature_tooltip'] = get_option( WP_FILES_PREFIX . 'feature-tooltip', false );

            $result['is_upgrade_hello_bar'] = !get_option( WP_FILES_PREFIX . 'upgrade-hellobar-hide' );
            
            $result['is_joy_ride'] = !get_option( WP_FILES_PREFIX . 'joy-ride-alert-hide' );

            //Watermark
            $result['watermark_image'] = get_option(WP_FILES_PREFIX . 'last-watermark-save');
            
            //save settings in cache
            wp_cache_add(WP_FILES_PREFIX . 'settings', $result, WP_FILES_CACHE_PREFIX);

            return $result;
        } 
        
        return $settings;
    }

    /**
     * Save settings
     * @since 1.0.0
     * @param  mixed $data
     * @return boolean
    */
    public static function saveSettings($data)
    {
        global $wpdb;

        //Clean cache
        wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );

        $load_settings = self::loadSettings();

        $options = Wp_Files_Helper::settingsOptions();

        if(!Wp_Files_Subscription::is_pro()) {
            $options = array_diff($options, ['watermark_position', 'watermark_x_axis', 'watermark_y_axis', 'watermark_scale_value', 'watermark_opacity']);
        }

        if ( ! empty( $data['lazy_disable_urls'] ) ) {
			$data['lazy_disable_urls'] = preg_split( '/[\r\n\t ]+/', $data['lazy_disable_urls'] );
		} else {
			$data['lazy_disable_urls'] = array();
		}

        if ( ! empty( $data['lazy_disable_classes'] ) ) {
			$data['lazy_disable_classes'] = preg_split( '/[\r\n\t ]+/', $data['lazy_disable_classes'] );
		} else {
			$data['lazy_disable_classes'] = array();
		}
        
        foreach ($options as $key => $option) {
            if(isset($data[$option])) {
                if (in_array($option, ['media_columns'])) {
                    update_option(WP_FILES_PREFIX . $option, $data[$option] && is_array($data['media_columns']) ? array_unique(array_merge($data['media_columns'], ['title'])) : ['title']);
                } else if($option == "image_manual_sizes") {
                    update_option(WP_FILES_PREFIX . $option, $data[$option]);
                } else {
                    update_option(WP_FILES_PREFIX . $option, $data[$option]);
                }
            }
        }

        if(isset($load_settings['folder_colors']) && isset($data['folder_colors']) && count((array)$load_settings['folder_colors']) > 0) {
            foreach((array)$load_settings['folder_colors'] as $record) {
                $count = count(array_filter((array)$data['folder_colors'], function($row) use($record) {
                    return isset($row['id']) && $row['id'] == $record['id'];
                }));
                if($count == 0) {
                    $wpdb->delete(self::getTable(self::$wpf_colors), array('id' => $record['id']), array('%d'));
                }
            }
        }
        
        if(isset($data['folder_colors']) && count((array)$data['folder_colors']) > 0) {
            foreach($data['folder_colors'] as $input) {
                if(isset($input['id']) && $input['id']) {
                    $wpdb->update(
                        self::getTable(self::$wpf_colors),
                        array('color' => $input['color']),
                        array('id' => $input['id']),
                        array('%s'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        self::getTable(self::$wpf_colors),
                        array(
                            'color' => $input['color']
                        )
                    );
                }
            }
        }

        
        
        //Usage tracking
        if($data['usage_tracking'] == 0) {
            Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'usage-tracking-disable-timestamp', time());
        }

        //If usage tracking enabled
        if(isset($data['usage_tracking']) && isset($load_settings['usage_tracking']) && $data['usage_tracking'] != $load_settings['usage_tracking'] && $data['usage_tracking'] == 1) {
            //Save usage tracking
            Wp_Files_Settings::saveUsageTracking(['current_status' => 'Active'], true);

            //Current user who installing plugin
		    Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'activating_user', get_current_user_id());
        }

        //Save auto update settings
        self::auto_update($data);
        
        //Clean cache
        wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );
        
        return true;
    }

    /**
     * Get plugin auto update setting
     * @since 1.0.0
     * @return bool
    */
	public static function get_auto_update_setting() {
        $plugin = is_plugin_active( 'wpfiles/wpfiles.php' ) ? 'wpfiles/wpfiles.php' : 'wpfiles-pro/wpfiles.php';
		return in_array( $plugin, (array) get_site_option( 'auto_update_plugins', [] ), true ) ? 1 : 0;
	}

    /**
     * Toggle auto updates option.
     * @since 1.0.0
     * @param  mixed $setting
     * @return void
    */
	public static function auto_update( $setting ) {
        $plugin = is_plugin_active( 'wpfiles/wpfiles.php' ) ? 'wpfiles/wpfiles.php' : 'wpfiles-pro/wpfiles.php';
        $auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
        if ( ! empty( $setting['auto_update'] ) && 0 !== $setting['auto_update'] ) {
			$auto_updates[] = $plugin;
			update_site_option( 'auto_update_plugins', array_unique( $auto_updates ) );
			return;
		}
		update_site_option( 'auto_update_plugins', array_diff( $auto_updates, [ $plugin ] ) );
	}

    /**
     * Save watermark image
     * @since 1.0.0
     * @param  mixed $svg
     * @return void
    */
    public static function saveWatermark($svg)
    {
        //Delete old file if exist
        if(file_exists(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'))) {
            unlink(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'));
        }
        $watermark_image = time().'.png';
        Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'last-watermark-save', $watermark_image);
        Wp_Files_Helper::saveBase64Image(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/', $svg, $watermark_image);
    }

    /**
     * Load upload file types
     * @since 1.0.0
     * @return object
    */
    public static function loadUploadFileTypes()
    {
        global $wpdb;
        $file_types        = wp_cache_get(WP_FILES_PREFIX . 'upload_file_types', WP_FILES_CACHE_PREFIX);
        if (!$file_types) {
            $file_types = $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s', self::getTable(self::$upload_file_types)), ARRAY_A);
            wp_cache_add(WP_FILES_PREFIX . 'upload_file_types', $file_types, WP_FILES_CACHE_PREFIX);
            return $file_types;
        }
        return $file_types;
    }

    /**
     * Save upload file types
     * @since 1.0.0
     * @param  mixed $data
     * @return boolean
    */
    public static function saveUploadFileTypes($data)
    {
        global $wpdb;

        if (isset($data['exts']) && is_array($data['exts']) && count($data['exts']) > 0) {

            //First clean old settings
            $wpdb->query("DELETE FROM " . self::getTable(self::$upload_file_types));

            foreach ($data['exts'] as $ext) {
                $wpdb->insert(
                    self::getTable(self::$upload_file_types),
                    array(
                        'description' => (string)$ext['description'],
                        'mime_type' => (string)$ext['mime_type'],
                        'ext' => (string)$ext['ext'],
                        'status' => (string)$ext['status'],
                    ),
                    array('%s', '%s', '%s')
                );
            }

            //Clean cache
            wp_cache_delete(WP_FILES_PREFIX . 'upload_file_types', WP_FILES_CACHE_PREFIX );
        }


        return true;
    }

    /**
     * Re-create missing database tables
     * @since 1.0.0
     * @return void
    */
    public static function reCreateTable()
    {
        //Create DB Structure
        Wp_Files_Helper::createDbStructure();
    }

    /**
     * Wipe all data and reset to default factory settings.
     * @since 1.0.0
     * @return boolean
    */
    public static function clearAllData()
    {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::getTable(self::$folder_table)))) == self::getTable(self::$folder_table)) {

            //Delete folder data
            Wp_Files_Media::destroyAll();

            $wpdb->query("DELETE FROM " . self::getTable(self::$wpf_dir_optimize_watermark_images));
            $wpdb->query("DELETE FROM " . self::getTable(self::$upload_file_types));

            update_option('wpfiles_updated_from_enhanced', '0');
            update_option('wpfiles_updated_from_wpmlf', '0');
            update_option('wpfiles_updated_from_wpmf', '0');
            update_option('wpfiles_updated_from_realmedia', '0');
            update_option('wpfiles_updated_from_happyfiles', '0');
            update_option('wpfiles_updated_from_filebird', '0');

            //Reset default settings
            Wp_Files_Helper::resetDefaultSettings();

            //Insert default data for folders
            $starred = $wpdb->get_row($wpdb->prepare('SELECT * FROM %1$s WHERE `name` = "Starred"', self::getTable(self::$folder_table)));

            if (is_null($starred)) {
                $wpdb->insert(
                    self::getTable(self::$folder_table),
                    array(
                        'id' => 1,
                        'name' => 'Starred',
                    ),
                    array('%d', '%s')
                );
            }

            $trashed = $wpdb->get_row($wpdb->prepare('SELECT * FROM %1$s WHERE `name` = "Trashed"', self::getTable(self::$folder_table)));

            if (is_null($trashed)) {
                $wpdb->insert(
                    self::getTable(self::$folder_table),
                    array(
                        'id' => 2,
                        'name' => 'Trashed',
                    ),
                    array('%d', '%s')
                );
            }

            //Clean cache
            wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );

            return true;
            
        } else {
            return false;
        }
    }

    /**
     * Update specific column for settings
     * @since 1.0.0
     * @param  mixed $column
     * @param  mixed $value
     * @return boolean
    */
    public static function updateSetting($column, $value)
    {
        update_option($column, $value);

        //Clean cache
        wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );

        return true;
    }

    /**
     * Return your system's status and see the details of key components.
     * @since 1.0.0
     * @return Array
    */
    public static function getSiteHealth()
    {
        try {
            require_once(ABSPATH . 'wp-admin/includes/update.php');

            require_once(ABSPATH . 'wp-admin/includes/misc.php');

            require_once(ABSPATH . 'wp-admin/includes/screen.php');

            // Load WP_Debug_Data class
            if (!class_exists('WP_Debug_Data')) {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-debug-data.php');
            }

            return WP_Debug_Data::debug_data();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Site health [Copy to clipboard data]
     * @since 1.0.0
     * @param  mixed $response
     * @return string
    */
    public static function getSiteHealthCopyToClipboardData($response)
    {
        $text = "";
        if (count($response) > 0) {
            foreach ($response as $section => $details) {
                $text .= "###" . $section . "####\r\n \r\n \r\n";
                if (count($details['fields']) > 0) {
                    foreach ($details['fields'] as $key => $row) {
                        $text .= $key . ": " . $row['value'] . "\r\n";
                    }
                }
                $text .= "\r\n \r\n";
            }
        }
        return $text;
    }

    /**
     * Submit feedback
     * @since 1.0.0
     * @param  mixed $rating
     * @return boolean
    */
    public static function submitFeedback($rating)
    {
        $response = get_option( WP_FILES_PREFIX . 'rating' );
        
		if ( $response ) {
			return false;
		}

        update_option( WP_FILES_PREFIX . 'rating', (int)$rating );

        update_option( WP_FILES_PREFIX . 'rating-hide', 1 );

        return true;
    }

    /**
     * Delete feedback
     * @since 1.0.0
     * @return void
    */
    public static function deleteFeedback()
    {
        delete_option(WP_FILES_PREFIX . 'rating');
        delete_option(WP_FILES_PREFIX . 'rating-hide');
    }

    /**
     * Dismiss notice
     * @since 1.0.0
     * @param  mixed $notice
     * @return void
    */
    public static function dismissNotice($notice, $value = null)
    {
        update_option($notice, true );

        $attempt = get_option($notice.'-count');

        update_option($notice.'-count', $attempt ? ($attempt + 1) : 1 );

        //Tip notice
        if($notice == WP_FILES_PREFIX . 'feature-tooltip') {
            update_option(WP_FILES_PREFIX . 'feature-tooltip-value', $value );
        }

        //Tour guide notice
        if($notice == WP_FILES_PREFIX . 'joy-ride-tour-completed') {
            update_option(WP_FILES_PREFIX . 'joy-ride-alert-hide', true );
        }
    }

    /**
     * Return table
     * @since 1.0.0
     * @param  mixed $table
     * @return string
    */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }

    /**
     * Save folder colors
     * @since 1.0.0
     * @param  mixed $color
     * @param  mixed $action
     * @param  mixed $id
     * @return void
    */
    public static function saveColor($color, $action = "add", $id = null)
    {
        global $wpdb;

        if($action == "update") {
            $record =  $wpdb->get_row($wpdb->prepare('SELECT * FROM %1$s WHERE `id` = %2$d', self::getTable(self::$wpf_colors), $id));

            if ($record) {
                $wpdb->update(
                    self::getTable(self::$wpf_colors),
                    array('color' => $color),
                    array('id' => $id),
                    array('%s'),
                    array('%d')
                );
            }
        } else {
            $wpdb->insert(
                self::getTable(self::$wpf_colors),
                array(
                    'color' => $color
                )
            );
        }
        
    }

    /**
     * Delete folder color
     * @since 1.0.0
     * @param  mixed $id
     * @return void
    */
    public static function deleteColor($id)
    {
        global $wpdb;
        $wpdb->delete(self::getTable(self::$wpf_colors), array('id' => $id), array('%d'));
    }

    /**
     * Reset default colors
     * @since 1.0.0
     * @return void
    */
    public static function resetDefaultColors()
    {
        global $wpdb;
        
        $colors = array('#e9c445', '#065a93', '#c4694c', '#409692', '#3ac0b1', '#36afd1', '#66b02e', '#74da93', '#7536af', '#563b67', '#8adbbd', '#da1336', '#ff4600', '#000000');

        $folder_colors = (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s', self::getTable(self::$wpf_colors)), ARRAY_A);

        for ($i = (count($folder_colors) + 1); $i <= 14; $i++) { 
            $wpdb->insert(
                self::getTable(self::$wpf_colors),
                array(
                    'color' => $colors[$i - 1]
                )
            );
        }
    }

    /**
     * Update installation steps
     * @since 1.0.0
     * @param  mixed $step
     * @return void
    */
    public static function installationStep($step)
    {
        if($step == "skip") {
            $steps = ["initial", "import-data", "connect-account", "subscriptions", "usage_tracking", "ready"];
            update_option('wpfiles-install-hide', true );
        } else {
            $steps = get_option( WP_FILES_PREFIX . 'installation-steps', array() );
            if(!in_array($step, $steps)) {
                $steps[] = $step;
            }
        }

        update_option(WP_FILES_PREFIX . 'installation-steps', $steps);

        //Reset connect account notice 
        delete_option(WP_FILES_PREFIX . 'account-connect-notice');
    }

   /**
     * Return usage tracking data
     * @since 1.0.0
     * @param  mixed $params
     * @return Array
    */
    public static function getUsageTrackingData($params = array())
    {
        $modules = array('cdn', 'compression', 'lazy_load', 'local_webp', 'watermark', 'media_rename', 'media_replace', 'media_download', 'starred_media', 'folder_color', 'trash_bin', 'user_folder', 'folder_lock', 'woocommerce_support', 'elementor_support', 'beaver_support', 'wpbakery_support', 'brizy_support', 'cornerstone_support', 'divi_support', 'thrive_quiz_support', 'fusion_support', 'oxygen_support', 'tatsu_support', 'dokan_support', 'gutenberg_editor_support', 'woocommerce_editor_support', 'class_editor_support', 'gutenberg_support');

        $response = Wp_Files_Settings::getSiteHealth();

        $total = Wp_Files_Tree::getFolderCount(-1);

        $settings = (array) Wp_Files_Settings::loadSettings();

        $compression = new Wp_Files_Compression_Requests();

        $stats = $compression->getAllStats();

        $tree = Wp_Files_Tree::getAllData('name desc', false);

        $total_folders = Wp_Files_Tree::getFolderChildrensIds($tree);

        $active_modules = array();

        if(!empty($settings) && !is_null($settings)) {
            foreach(array_keys($settings) as $key) {
                if(in_array($key, $modules) && $settings[$key] == 1) {
                    $active_modules[] = $key;
                }
            }
        }
        
        $imported_plugins = array();
        
        if (get_option('wpfiles_updated_from_enhanced', '0') == '1') {
            $imported_plugins[] = 'Enhanced Media Library By wpUXsolutions';
        }
        if (get_option('wpfiles_updated_from_wpmlf', '0') == '1') {
            $imported_plugins[] = 'WordPress Media Library Folders By Max Foundry';
        }
        if (get_option('wpfiles_updated_from_wpmf', '0') == '1') {
            $imported_plugins[] = 'WP Media folder By Joomunited';
        }
        if (get_option('wpfiles_updated_from_realmedia', '0') == '1') {
            $imported_plugins[] = 'WP Real Media Library By devowl.io GmbH';
        }
        if (get_option('wpfiles_updated_from_happyfiles', '0') == '1') {
            $imported_plugins[] = 'HappyFiles by Codeer';
        }
        if (get_option('wpfiles_updated_from_filebird', '0') == '1') {
            $imported_plugins[] = 'FileBird (v4) by NinjaTeam';
        }

        $activating_user_id = get_option(WP_FILES_PREFIX . 'activating_user');

        $author_obj = get_user_by('id', $activating_user_id);

        $post_data = array(
            'user_profile' => array(
                'first_name' => get_the_author_meta('first_name', $author_obj ? $activating_user_id : 0),
                'country_code' => get_option(WP_FILES_PREFIX . 'activation-country-code', ''),
                'last_name' => get_the_author_meta('last_name', $author_obj ? $activating_user_id : 0),
                'email' => get_the_author_meta('user_email', $author_obj ? $activating_user_id : 0),
                'username' => get_the_author_meta('user_login', $author_obj ? $activating_user_id : 0),
                'user_language' => isset($response['wp-core']['fields']['user_language']['value']) ? $response['wp-core']['fields']['user_language']['value'] : ''
            ),
            'website_profile' => array(
                'title' => get_bloginfo('name'),
                'admin_email' => get_bloginfo('admin_email'),
                'site_url' => isset($response['wp-core']['fields']['site_url']['value']) ? $response['wp-core']['fields']['site_url']['value'] : '',
                'home_url' => isset($response['wp-core']['fields']['home_url']['value']) ? $response['wp-core']['fields']['home_url']['value'] : '',
                'timezone' => isset($response['wp-core']['fields']['timezone']['value']) ? $response['wp-core']['fields']['timezone']['value'] : '',
                'site_language' => isset($response['wp-core']['fields']['site_language']['value']) ? $response['wp-core']['fields']['site_language']['value'] : '',
                'multisite' => isset($response['wp-core']['fields']['multisite']['value']) ? $response['wp-core']['fields']['multisite']['value'] : '',
                'environment_type' => isset($response['wp-core']['fields']['environment_type']['value']) ? $response['wp-core']['fields']['environment_type']['value'] : '',
            ),
            'system_profile' => array(
                'wordpress_version' => isset($response['wp-core']['fields']['version']['value']) ? $response['wp-core']['fields']['version']['value'] : '',
                'webserver' => isset($response['wp-server']['fields']['httpd_software']['value']) ? $response['wp-server']['fields']['httpd_software']['value'] : '',
                'php_version' => isset($response['wp-server']['fields']['php_version']['value']) ? $response['wp-server']['fields']['php_version']['value'] : '',
                'database_extension' => isset($response['wp-database']['fields']['extension']['value']) ? $response['wp-database']['fields']['extension']['value'] : '',
                'database_server_version' => isset($response['wp-database']['fields']['server_version']['value']) ? $response['wp-database']['fields']['server_version']['value'] : '',
            ),
            'theme_profile' => array(
                'current_theme' => isset($response['wp-active-theme']['fields']['name']['value']) ? $response['wp-active-theme']['fields']['name']['value'] : '',
                'version' => isset($response['wp-active-theme']['fields']['version']['value']) ? $response['wp-active-theme']['fields']['version']['value'] : '',
                'auto_update' => isset($response['wp-active-theme']['fields']['auto_update']['value']) ? $response['wp-active-theme']['fields']['auto_update']['value'] : '',
                'themes' => isset($response['wp-themes-inactive']['fields']) ? $response['wp-themes-inactive']['fields'] : [],
            ),
            'plugins_profile' => array(
                'active' => isset($response['wp-plugins-active']['fields']) ? $response['wp-plugins-active']['fields'] : [],
                'inactive' => isset($response['wp-plugins-inactive']['fields']) ? $response['wp-plugins-inactive']['fields'] : [],
            ),
            'media_profile' => array(
                'active_media_editor' => isset($response['wp-media']['fields']['image_editor']['value']) ? $response['wp-media']['fields']['image_editor']['value'] : '',
                'media_count' => $total,
            ),
            'wpfiles_profile' => array(
                'version' => get_option('wpfiles_version'),
                'plan_id' => isset($settings['site_status']['plan']['name']) ? $settings['site_status']['plan']['id'] : 0,
                'website_id' => isset($settings['site_status']['website']['id']) ? $settings['site_status']['website']['id'] : 0,
                'total_images_compressed' => isset($stats['combined_stats']['compressed_count']) ? $stats['combined_stats']['compressed_count'] : 0,
                'total_bytes_saved' => isset($stats['combined_stats']['saving_bytes']) ? $stats['combined_stats']['saving_bytes'] : 0,
                'total_images_watermarked' => isset($stats['combined_stats']['watermarked_count']) ? $stats['combined_stats']['watermarked_count'] : 0,
                'total_folders_created' => count($total_folders),
                'active_modules' => count((array)$active_modules),
                'feedback_id' => get_option(WP_FILES_PREFIX . 'feedback-id', 0),
                'rating_id' => get_option(WP_FILES_PREFIX . 'rating-id', 0),
                'newsletter_id' => get_option(WP_FILES_PREFIX . 'newsletter-id', 0),
                'is_tour_guide' => get_option(WP_FILES_PREFIX . 'joy-ride-tour-completed', false) ? 1 : 0,
                'current_plugin_status' => isset($params['current_status']) ? $params['current_status'] : '',
                'active_timestamp' => get_option(WP_FILES_PREFIX . 'account-activation-timestamp', 0),
                'deactive_timestamp' => get_option(WP_FILES_PREFIX . 'account-deactivation-timestamp', 0),
                'uninstall_timestamp' => get_option(WP_FILES_PREFIX . 'account-uninstall-timestamp', 0),
                'usage_tracking_disable_timestamp' => get_option(WP_FILES_PREFIX . 'usage-tracking-disable-timestamp'),
                'subscription_upgrade_timestamp' => get_option(WP_FILES_PREFIX . 'subscription-upgrade-timestamp'),
                'subscription_downgrade_timestamp' => get_option(WP_FILES_PREFIX . 'subscription-downgrade-timestamp'),
                'account_connection_timestamp' => get_option(WP_FILES_PREFIX . 'account-connection-timestamp'),
                'data_imported_plugins' => (array) $imported_plugins,
                'installer_step' => (get_option(WP_FILES_PREFIX . 'install-hide') ? 'completed' : (get_option(WP_FILES_PREFIX . 'installation-steps') ? end(get_option(WP_FILES_PREFIX . 'installation-steps')) : 'uncompleted'))
            )
        );

        return $post_data;
    }
    
    /**
     * Save usage tracking
     * @since 1.0.0
     * @return void
    */
    public static function saveUsageTracking($default_data = array(), $call = false)
    {
        $settings = (array) Wp_Files_Settings::loadSettings();

        if($settings['usage_tracking'] == 1 || $call) {

            $data = Wp_Files_Settings::getUsageTrackingData();

            $data = !empty($default_data) ? array_merge($default_data, $data) : $data;

            $api = new Wp_Files_Api(Wp_Files_Helper::getAccountApikey());
    
            $api->saveUsageTracking($data, get_site_url(), true);
        }
    }
        
    /**
     * Save site health/api status
     * @since 1.0.0
     * @param  mixed $settings
     * @param  mixed $status
     * @return void
    */
    public static function saveSiteStatus($settings, $status)
    {
        //Show notice when website convert pro to free
        if(isset($settings['site_status']['website']) && $settings['site_status']['website']['type'] == "pro" && isset($status->website) && $status->website->type == "free") {
            Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'website-pro-to-free-notice-value', 1);
        } else if(isset($status->is_free) && $status->is_free == 0 && isset($status->website) && $status->website->type == "pro") {
            delete_option( WP_FILES_PREFIX . 'website-pro-to-free-notice' );
            delete_option( WP_FILES_PREFIX . 'website-pro-to-free-notice-value' );
        }

        update_option(WP_FILES_PREFIX .'site_status', json_decode( json_encode($status), true));

        //Clean cache
        wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );

        //Remove connect account notice
        delete_option( WP_FILES_PREFIX . 'domain-mismatch-hide' );
        delete_option( WP_FILES_PREFIX . 'domain-mismatch' );
        
        //Remove dismiss value for CDN suspended notice
        if(isset($settings['site_status']['website']) && $settings['site_status']['website']['status'] != "suspended" && isset($status->website) && $status->website->status == "suspended") {
            delete_option( WP_FILES_PREFIX . 'cdn-suspended-notice' );
        }

        //Remove dismiss value for Payment due notice
        if(isset($settings['site_status']['website']) && $settings['site_status']['subscription']['stripe_status'] != "past_due" && isset($status->subscription) && $status->subscription->stripe_status == "past_due") {
            delete_option( WP_FILES_PREFIX . 'account-payment-due-notice' );
        }

        //If website is free
        if(isset($status->website) && $status->website->type == "free") {
            //Reset default pro settings if website is free
            Wp_Files_Helper::resetDefaultSettings(null, true);
        }
        
        //schedule api update cron job
        if (!wp_next_scheduled('wpfiles_api_update_status')) {
            wp_schedule_event(time() + (DAY_IN_SECONDS / 2), 'twicedaily', 'wpfiles_api_update_status');
        }
    }

    /**
     * Remove credentials
     * @since 1.0.0
     * @return void
    */
    public static function removeCredentials()
    {
        update_option(WP_FILES_PREFIX .'site_status', '');

        update_option(WP_FILES_PREFIX .'api_key', '');

        //Clean cache
        wp_cache_delete( WP_FILES_PREFIX . 'settings', WP_FILES_CACHE_PREFIX );

        //Reset default pro settings if account is disconnected 
        Wp_Files_Helper::resetDefaultSettings(null, true);

    }
        
    /**
     * Return localize script json for JS
     * @since 1.0.0
     * @param  mixed $screen
     * @param  mixed $settings
     * @return Array
    */
    public static function getLocalizeScript($screen, $settings) {

        try {
            $query = get_option('wpf-query-params') ? get_option('wpf-query-params') : [];
        } catch (\Throwable $th) {
            $query = [];
        }

        //Media columns
        try {
            $media_library = new Wp_Files_Media_Library($settings);
            $media_columns = $media_library->media_columns();
        } catch (\Throwable $th) {
            $media_columns = [];
        }   

        $watermark_image = get_option(WP_FILES_PREFIX . 'last-watermark-save', 0);

        $watermark_path = ($settings['watermark_type'] == "text" ? WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.$watermark_image : get_attached_file($settings['watermark_attachment_id']));

        $localize_array = array(
            'admin_url' => admin_url('admin.php'),
            'post_url' => admin_url('post.php'),
            'apiUrl'    => apply_filters('wpfiles_json_url', rtrim(rest_url(WP_FILES_REST_API_PREFIX), "/")),
            'wpfiles_app_url'    => WP_FILES_URL,
            'wpfiles_go_url'    => WP_FILES_GO_URL,
            'nonce'   => wp_create_nonce('wp_rest'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'nonce_error' => __('Your request can\'t be processed.', 'wpfiles'),
            'i18n' => Wp_Files_i18n::getTranslation(),
            'media_mode' => get_user_option('media_library_mode', get_current_user_id()),
            'media_url' => admin_url('upload.php'),
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'async_upload_url' => admin_url('async-upload.php'),
            'media_nonce'      => wp_create_nonce('media-form'),
            'screen' => $screen,
            'pluginUrl'               => WP_FILES_PLUGIN_URL,
            'siteUrl'               => get_site_url(),
            'settings' => $settings,
            'plan' => Wp_Files_Subscription::is_pro($settings) ? 'pro' : 'free',
            'plan_and_plugin' => Wp_Files_Subscription::is_pro($settings) && is_plugin_active( 'wpfiles-pro/wpfiles.php' ) && WP_FILES_BASENAME == 'wpfiles-pro/wpfiles.php' ? 'pro' : 'free',
            'active_account' => Wp_Files_Subscription::is_active($settings) ? 1 : 0,
            'is_download_pro' => (is_plugin_active( 'wpfiles/wpfiles.php' ) && Wp_Files_Subscription::is_pro()) && !is_plugin_active( 'wpfiles-pro/wpfiles.php' ) ? 1 : 0,
            'is_pro' => is_plugin_active( 'wpfiles-pro/wpfiles.php' ) && WP_FILES_BASENAME == 'wpfiles-pro/wpfiles.php' ? 1 : 0,
            'current_user_id' => get_current_user_id(),
            'restUrl' => get_rest_url(),
            'is_administrator' => is_admin() && is_super_admin(),
            'is_rtl' => is_rtl(),
            'is_watermark_image_exist' => file_exists($watermark_path) ? 1 : 0,
            'css' => array(
                'left' => is_rtl() ? 'right' : 'left',
                'right' => is_rtl() ? 'left' : 'right'
            ),
            'current_user' => array(
                'name' => Wp_Files_Helper::getUserName()
            ), 
            'website_profile' => array(
                'first_name' => ucfirst(get_the_author_meta('first_name', get_current_user_id())),
                'last_name' => ucfirst(get_the_author_meta('last_name', get_current_user_id())),
                'admin_email' => get_the_author_meta('user_email', get_current_user_id()),
                'admin_username' => ucfirst(get_the_author_meta('user_login', get_current_user_id())),
            ),
            'media_columns' => (array)$media_columns
        );

        //Compression
        $compression = array(
            'count_supercompressed' => '',
            'count_compressed'      => '',
            'count_total'        => '',
            'count_images'       => '',
            'uncompressed'          => '',
            'recompress'            => '',
            'savings_bytes'      => '',
            'savings_resize'     => '',
            'savings_conversion' => '',
            'savings_supercompression' => '',
            'pro_savings'        => '',
        );

        $upgrade_url = add_query_arg(
            array(
                'utm_source'   => 'wpfiles',
                'utm_medium'   => 'plugin',
                'utm_campaign' => 'wpfiles_bulkwpfiles_issues_filesizelimit_notice',
            ),
            WP_FILES_GO_URL.'/pricing'
        );

        if (Wp_Files_Subscription::is_pro()) {
            $error_in_bulk = esc_html__('{{compressed}}/{{total}} images were successfully compressed, {{errors}} encountered issues.', 'wpfiles');
        } else {
            $error_in_bulk = sprintf(
                esc_html__('{{compressed}}/{{total}} images were successfully compressed, {{errors}} encountered issues. Are you hitting the 5MB "size limit exceeded" warning? %1$sUpgrade to WPFiles Pro for FREE%2$s to optimize unlimited image files.', 'wpfiles'),
                '<a href="' . esc_url($upgrade_url) . '" target="_blank">',
                '</a>'
            );
        }

        $wpfiles_msgs = array(
            'settingsUpdated'         => esc_html__('Your settings have been updated', 'wpfiles'),
            'recompress'                 => esc_html__('Recompressing image...', 'wpfiles'),
            'compress_now'               => esc_html__('Compress Now', 'wpfiles'),
            'error_in_bulk'           => $error_in_bulk,
            'all_recompress'           => esc_html__('All images are fully optimized.', 'wpfiles'),
            'restore'                 => esc_html__('Restoring image...', 'wpfiles'),
            'compressing'                => esc_html__('Compressing image...', 'wpfiles'),
            'all_done'                => esc_html__('All Done!', 'wpfiles'),
            'sync_stats'              => esc_html__('Give us a moment while we sync the stats.', 'wpfiles'),
            // Errors.
            'error_ignore'            => esc_html__('Ignore this image from bulk-compression', 'wpfiles'),
            // Ignore text.
            'ignored'                 => esc_html__('Ignored from bulk-compression', 'wpfiles'),
            'not_processed'           => esc_html__('Could not compress', 'wpfiles'),
            // URLs.
            'compression_url'               => network_admin_url('admin.php?page=wpfiles'),
            'directory_url'           => network_admin_url('admin.php?page=page=wpfiles'),
            'localWebpURL'            => network_admin_url('admin.php?page=page=wpfiles'),
        );

        $localize_array = array_merge($localize_array, $compression);
        
        $data = Wp_Files_Media_Controller::getAllData();
        
        $tree = Wp_Files_Tree::updateTreeOrder($data['tree'], 'move-starred-top');

        $data['tree'] = $tree;

        $final_data = array_merge($localize_array, array(
            'current_folder' => ((isset($_GET['wpf'])) ? (int)sanitize_text_field($_GET['wpf']) : -1), //-1: all files. 0: uncategorized
            'folders' => Wp_Files_Media::getAllFolders('id as term_id, name as term_name', array('term_id', 'term_name')),
            'relations' => Wp_Files_Media::getFolderMediaRelations(),
            //'is_upload' => get_current_screen() && get_current_screen()->base === 'upload' ? 1 : 0, [If this is calling from followings hooks[init, plugin_loaded]] then it will not work
            'auto_import_url' => esc_url(add_query_arg(array('page' => 'wpfiles-settings', 'tab' => 'update-db', 'autorun' => 'true'), admin_url('/options-general.php'))),
            'sort_folder' => get_option('wpfiles_sort_folder', 'reset'),
            'attachmentsBrowser'  => '',
            'filterId'  => 'custom-search-filter',
            'filterBy'             => 'wpf',
            'term'             => '', //New term added with action
            'terms'               => Wp_Files_Tree::getAllFoldersId("name asc"),
            'data'               => $data,
            'trash'               => MEDIA_TRASH,
            'query' => $query,
            'wpfiles_msgs' => $wpfiles_msgs
        ));

        return $final_data;
    }
    
    /**
     * This is to detect wordpress default/active translation language
     * @since 1.0.0
     * @return string
     */
    public static function getActiveTranslation() {
        $active_locale = get_locale();
		if ( 'en' === $active_locale || 'en_US' === $active_locale ) {
			$language = 'English';
		} else {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$available_translations  = wp_get_available_translations();
			$language = isset( $available_translations[ $active_locale ] ) ? $available_translations[ $active_locale ]['native_name'] : __( 'No language detected', 'wpfiles' );
		}
        return $language;
    }
}
