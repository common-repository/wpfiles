<?php

class Wp_Files_Helper
{
    /**
     * Class instance
     * @since 1.0.0
     * @var object $instance
    */
    protected static $instance = null;

    /**
     * Return class instance statically
     * @since    1.0.0
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
     * Sanitize array input
     * @since    1.0.0
     * @var string $var
     * @return array
     */
    public static function sanitizeArray($var)
    {
        if (is_array($var)) {
            return array_map('self::sanitizeArray', $var);
        } else {
            return is_scalar($var) ? sanitize_text_field($var) : $var;
        }
    }

    /**
     * Media screen mode [List | Grid]
     * @since    1.0.0
     * @return boolean
    */
    public static function hasListMode()
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            return (isset($screen->id) && 'upload' == $screen->id);
        }
        return false;
    }

    /**
     * Return site url
     * @since    1.0.0
     * @return string
    */
    public static function wxrSiteUrl()
    {
        if (is_multisite()) {
            return network_home_url();
        } else {
            return get_bloginfo_rss('url');
        }
    }

    /**
     * Get file extensions by mime type
     * @since    1.0.0
     * @var string $mime_type
     * @return array
    */
    public static function getFileExtensionByMimeType($mime_type)
    {
        static $map = null;

        if (is_array($map)) {
            return isset($map[$mime_type]) ? $map[$mime_type] : null;
        }

        $mimeTypes = wp_get_mime_types();

        $map = array_flip($mimeTypes);

        foreach ($map as $type => $extensions) {
            $map[$type] = strtok($extensions, '|');
        }

        return isset($map[$mime_type]) ? $map[$mime_type] : null;
    }

    /**
     * Link to the media library page for attachment
     * @since 1.0.0
     * @param int    $id
     * @param string $name
     * @param bool   $src
     * @return string
     */
    public static function getMediaAttachmentLink($id, $name, $src = false)
    {
        $mode = get_user_option('media_library_mode');

        if ('grid' === $mode) {
            $link = admin_url("upload.php?item={$id}");
        } else {
            $link = admin_url("post.php?post={$id}&action=edit");
        }

        if (!$src) {
            return "<a href='{$link}'>{$name}</a>";
        }

        return $link;
    }

    /**
     * Detect file animation
     * @since 1.0.0
     * @param string $path
     * @param int $id
     * @return void
     */
    public static function checkAnimatedStatus($path, $id)
    {
        if ('image/gif' !== get_post_mime_type($id) || !isset($path)) {
            return;
        }

        $fileContents = file_get_contents($path);

        $stringLocation = 0;

        $count   = 0;

        // After finding a 2nd frame, there is no point to continue
        while ($count < 2) {
            $whr1 = strpos($fileContents, "\x00\x21\xF9\x04", $stringLocation);
            if (false === $whr1) {
                break;
            } else {
                $stringLocation = $whr1 + 1;
                $whr2  = strpos($fileContents, "\x00\x2C", $stringLocation);
                if (false === $whr2) {
                    break;
                } else {
                    if ($whr2 === $whr1 + 8) {
                        $count++;
                    }
                    $stringLocation = $whr2 + 1;
                }
            }
        }

        if ($count > 1) {
            update_post_meta($id, WP_FILES_PREFIX . 'animated', true);
        }
    }

    /**
     * Return attached file path
     * @since 1.0.0
     * @param int $id
     * @return bool|false|string
     */
    public static function getAttachedFile($id)
    {
        if (empty($id)) {
            return false;
        }

        if (function_exists('wp_get_original_image_path')) {
            $file_path = wp_get_original_image_path($id);
            if (! empty($file_path) && strpos($file_path, 's3') !== false) {
                $file_path = wp_get_original_image_path($id, true);
            }
        } else {
            $file_path = get_attached_file($id);
            if (! empty($file_path) && strpos($file_path, 's3') !== false) {
                $file_path = get_attached_file($id, true);
            }
        }

        return $file_path;
    }

    /**
     * File exists
     * @since 1.0.0
     * @param int $id
     * @param string $path
     * @return bool
     */
    public static function fileExists($id, $path = '')
    {
        if (empty($id)) {
            return false;
        }

        if (empty($path)) {
            $path = self::getAttachedFile($id);
        }

        $file_exists = file_exists($path);

        return $file_exists;
    }

    /**
     * Get file mime type
     * @since 1.0.0
     * @param string $path
     * @return bool|string
     */
    public static function getFileMimeType($path)
    {
        if (!stream_is_local($path)) {
            return false;
        }

        if (class_exists('finfo')) {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        } else {
            $fileInfo = false;
        }

        if ($fileInfo) {
            $mimeType = file_exists($path) ? $fileInfo->file($path) : '';
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);
        } else {
            $mimeType = false;
        }

        return $mimeType;
    }

    /**
     * Return savings PNG to GPG for attachment
     * @since 1.0.0
     * @param string $id
     * @return array|bool
     */
    public static function fetchPngTojpgConversionSavings($id = '')
    {
        $savings = array(
            'bytes'       => 0,
            'size_before' => 0,
            'size_after'  => 0,
        );

        if (empty($id)) {
            return $savings;
        }

        $savings = get_post_meta($id, WP_FILES_PREFIX . 'pngjpg_savings', true);

        if (!is_array($savings) || empty($savings)) {
            return $savings;
        }

        foreach ($savings as $size => $row) {
            if (empty($row)) {
                continue;
            }

            $savings['size_before'] += isset($row['size_before']) ? $row['size_before'] : 0;

            $savings['size_after']  += isset($row['size_after']) ? $row['size_after'] : 0;
        }

        $savings['bytes'] = $savings['size_before'] - $savings['size_after'];

        return $savings;
    }

    /**
     * Filter error message
     * @since 1.0.0
     * @param string $error
     * @param string $id
     * @return mixed|null|string
     */
    public static function filterTheError($error = '', $id = '')
    {
        if (empty($error)) {
            return null;
        }

        if (false !== strpos($error, '500 Internal Server Error')) {
            $error = __("Some error occurred during process attachment due to bad headers", 'wpfiles');
        }

        $error = apply_filters('wp_files_error', $error, $id);

        return $error;
    }

    /**
     * Gets the WPFiles API key.
     * @since 1.0.0
     * @return string|false
     */
    public static function getAccountApikey()
    {
        $settings = Wp_Files_Settings::loadSettings();
        return $settings['api_key'];
    }

    /**
     * Filter Posts
     * @since 1.0.0
     * @param array $posts
     * @return mixed
     */
    public static function filterPostByMimeType($posts)
    {
        if (empty($posts)) {
            return $posts;
        }

        foreach ($posts as $key => $post) {
            if (!isset($post->post_mime_type) || !in_array($post->post_mime_type, Wp_Files_Compression::$mimeTypes, true)) {
                unset($posts[$key]);
            } else {
                $posts[$key] = $post->ID;
            }
        }

        return $posts;
    }

    /**
     * Get registered image sizes with dimension
     * @since 1.0.0
     * @return array
     */
    public static function getImageDimensions()
    {
        $sizes = wp_cache_get('get_image_sizes', 'wpfiles_mage_sizes');
        ;

        if ($sizes) {
            return $sizes;
        }

        global $_wp_additional_image_sizes;

        $additionalSizes = get_intermediate_image_sizes();

        $sizes = array();

        if (empty($additionalSizes)) {
            return $sizes;
        }

        foreach ($additionalSizes as $size) {
            if (in_array($size, array('thumbnail', 'medium', 'large'), true)) {
                $sizes[$size]['height'] = get_option($size . '_size_h');
                $sizes[$size]['width']  = get_option($size . '_size_w');
                $sizes[$size]['crop']   = (bool) get_option($size . '_crop');
            } elseif (isset($_wp_additional_image_sizes[$size])) {
                $sizes[$size] = array(
                    'height' => $_wp_additional_image_sizes[$size]['height'],
                    'width'  => $_wp_additional_image_sizes[$size]['width'],
                    'crop'   => $_wp_additional_image_sizes[$size]['crop'],
                );
            }
        }

        if (!isset($sizes['medium_large']) || empty($sizes['medium_large'])) {
            $height = (int) get_option('medium_large_size_h');
            $width  = (int) get_option('medium_large_size_w');

            $sizes['medium_large'] = array(
                'height' => $height,
                'width'  => $width,
            );
        }

        wp_cache_set('get_image_sizes', $sizes, 'wpfiles_mage_sizes');

        return $sizes;
    }

    /**
     * Returns current user name to be displayed
     * @since 1.0.0
     * @return string
     */
    public static function getUserName()
    {
        $current_user = wp_get_current_user();
        return !empty($current_user->first_name) ? $current_user->first_name : $current_user->display_name;
    }

    /**
     * Format metadata from $_POST request.
     * @since 1.0.0
     * @param array $metaData
     * @return array
     */
    public static function formatMetaFromPost($metaData = array())
    {
        //This is required only when Async requests are used.

        if (empty($metaData)) {
            return $metaData;
        }

        if (is_array($metaData)) {
            array_walk_recursive($metaData, array('self', 'formatAttachmentMetaItem'));
        }

        return $metaData;
    }

    /**
     * Format attachment meta item
     * @since 1.0.0
     * @param mixed $value
     * @param string $key
     */
    public static function formatAttachmentMetaItem(&$value, $key)
    {
        if ('height' === $key || 'width' === $key) {
            $value = (int) $value;
        }

        // This filter will be used only for Async, post requests.
        $value = apply_filters('wpFilesFormatAttachmentMetaItem', $value, $key);
    }

    /**
     * Original File path
     * @since 1.0.0
     * @param string $original_file
     * @return string
     */
    public static function originalFilePath($original_file = '')
    {
        $uploads     = wp_get_upload_dir();

        $upload_path = $uploads['basedir'];

        return path_join($upload_path, $original_file);
    }

    /**
     * Save image with base64 on server
     * @since 1.0.0
     * @param string $dir
     * @param string $base64_img
     * @param string $filename
     * @return void
    */
    public static function saveBase64Image($dir, $base64_img, $filename)
    {
        $img             = str_replace('data:image/png;base64,', '', $base64_img);

        $img             = str_replace(' ', '+', $img);

        $decoded         = base64_decode($img);

        file_put_contents($dir . $filename, $decoded);
    }

    /**
     * Detect environment stage is local
     * @since 1.0.0
     * @return boolean
     */
    public function isLocalDev()
    {
        if (in_array($_SERVER['REMOTE_ADDR'], array('10.255.0.2', '::1', '192.168.32.1'))) {
            return true;
        }
    }

    /**
     * Additional available file types.
     * @since 1.0.0
     * @return array
     */
    public static function getAvailableFileTypes()
    {
        $file = 'file-types-list';

        $mimeTypes = trim(file_get_contents(WP_FILES_PLUGIN_DIR . '/admin/json/' . $file . '.json'));

        return json_decode($mimeTypes, true);
    }

    /**
     * Create DB structure
     * @since 1.0.0
     * @return void
     */
    public static function createDbStructure()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_wpf = $wpdb->prefix . 'wpf';

        //Set sql mode
        $wpdb->set_sql_mode(['ALLOW_INVALID_DATES']);

        //type == 0: default
        //type == 1: woocommerce
        if ($wpdb->get_var("show tables like '$table_wpf'") != $table_wpf) {
            $sql = 'CREATE TABLE ' . $table_wpf . ' (
				`id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
                `wocommerce_parent` TINYINT(1) NULL DEFAULT 0,
				`name` varchar(250) NULL,
				`parent` BIGINT(20) NULL DEFAULT 0,
				`type` int(2) NULL DEFAULT 0,
				`ord` BIGINT(20) NULL DEFAULT 0,
				`color` varchar(30) NULL,
				`starred` TINYINT(1) NULL DEFAULT 0,
				`created_by` BIGINT(20) NULL DEFAULT 0,
				`term_id` BIGINT(20) NULL DEFAULT 0,
				`product_id` BIGINT(20) NULL DEFAULT 0,
				`shortcut` BIGINT(20) NULL DEFAULT 0,
				`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				`deleted_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY `id` (id)) ' . 'ENGINE = InnoDB ' . $charset_collate . ';';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        $table = $wpdb->prefix . 'wpf_attachment_folder';

        //type == 0: default
        //type == 1: woocommerce

        if ($wpdb->get_var("show tables like '$table'") != $table) {
            $sql = 'CREATE TABLE ' . $table . ' (
				`folder_id` BIGINT(20) unsigned NOT NULL,
				`attachment_id` bigint(20) unsigned NOT NULL,
				`product_id` BIGINT(20) NULL DEFAULT 0,
				`restore` TINYINT(1) NULL DEFAULT 0,
				`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				`deleted_at` DATETIME NULL DEFAULT NULL,
				UNIQUE( `folder_id`, `attachment_id`)
				) ' . 'ENGINE = InnoDB ' . $charset_collate . ';';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        $wpf_upload_file_types = $wpdb->prefix . 'wpf_upload_file_types';

        if ($wpdb->get_var("show tables like '$wpf_upload_file_types'") != $wpf_upload_file_types) {
            $sql = 'CREATE TABLE ' . $wpf_upload_file_types . ' (
				`id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
				`description` varchar(500) NULL,
				`mime_type` varchar(100) NULL,
				`ext` varchar(50) NULL,
				`status` TINYINT(1) NULL DEFAULT 0,
				PRIMARY KEY (id),
				UNIQUE KEY `id` (id)) ' . 'ENGINE = InnoDB ' . $charset_collate . ';';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        $wpf_colors = $wpdb->prefix . 'wpf_colors';

        if ($wpdb->get_var("show tables like '$wpf_colors'") != $wpf_colors) {
            $sql = 'CREATE TABLE ' . $wpf_colors . ' (
				`id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
				`color` varchar(22) NULL,
				PRIMARY KEY (id),
				UNIQUE KEY `id` (id)) ' . 'ENGINE = InnoDB ' . $charset_collate . ';';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        $table_wpf_dir_optimize_watermark_images = $wpdb->prefix . 'wpf_dir_optimize_watermark_images';

        if ($wpdb->get_var("show tables like '$table_wpf_dir_optimize_watermark_images'") != $table_wpf_dir_optimize_watermark_images) {
            $sql = 'CREATE TABLE ' . $table_wpf_dir_optimize_watermark_images . ' (
				`id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
				`path` text NULL COLLATE utf8_unicode_ci,
				`path_hash` char(32) NULL,
				`resize` varchar(55) NULL,
				`lossy` varchar(55) NULL,
				`error` varchar(55) NULL,
				`image_size` INT(11) NULL DEFAULT 0,
				`orig_size` INT(11) NULL DEFAULT 0,
				`file_time` INT(11) NULL DEFAULT 0,
                `watermark` TINYINT(1) NULL DEFAULT 0,
				`last_scan` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				`meta` text NULL COLLATE utf8_unicode_ci,
				PRIMARY KEY (id),
				UNIQUE KEY `id` (id)) ' . 'ENGINE = InnoDB ' . $charset_collate . ';';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        $current_version = get_option('wpfiles_version');

        if (version_compare(WP_FILES_VERSION, $current_version, '>')) {
            update_option('wpfiles_version', WP_FILES_VERSION);
        }

        //Insert default data for folders
        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` = "1"', $table_wpf);

        $starred = $wpdb->get_row($query);

        if (is_null($starred)) {
            $wpdb->insert(
                $table_wpf,
                array(
                    'id' => 1,
                    'name' => 'Starred',
                ),
                array('%d', '%s')
            );
        }

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` = "2"', $table_wpf);

        $trashed = $wpdb->get_row($query);

        if (is_null($trashed)) {
            $wpdb->insert(
                $table_wpf,
                array(
                    'id' => 2,
                    'name' => 'Trashed',
                ),
                array('%d', '%s')
            );
        }

        Wp_Files_Admin::registerSchedules();
    }

    /**
     * Reset default settings
     * @since 1.0.0
     * @return $column
     * @return $free
     * @return void
    */
    public static function resetDefaultSettings($column = null, $free = false, $reset_watermark = false)
    {
        $default_options = [
            'cdn' => 0,
            'compression' => 1,
            'lazy_load' => 1,
            'local_webp' => 1,
            'watermark' => 1,
            'media_rename' => 1,
            'media_replace' => 1,
            'media_download' => 1,
            'starred_media' => 1,
            'folder_color' => 1,
            'trash_bin' => 1,
            'user_folder' => 1,
            'folder_lock' => 1,
            'woocommerce_support' => 1,
            'gutenberg_support' => 1,
            'thirdparty_compatibility' => 1,
            'gutenberg_editor_support' => 1,
            'woocommerce_editor_support' => 1,
            'class_editor_support' => 1,
            'elementor_support' => 1,
            'beaver_support' => 1,
            'wpbakery_support' => 1,
            'brizy_support' => 1,
            'cornerstone_support' => 1,
            'divi_support' => 1,
            'thrive_quiz_support' => 1,
            'fusion_support' => 1,
            'oxygen_support' => 1,
            'tatsu_support' => 1,
            'dokan_support' => 1,
            'max_file_upload_size' => 20,
            'max_file_upload_default' => 1,
            'file_types' => 0,
            'language' => 'en',
            'auto_update' => 0,
            'usage_tracking' => 0,
            'is_plugin_removal_delete_data' => 0,
            'cdn_bg_image' => 1,
            'compress_on_fly' => 1,
            'cdn_super_compression' => 0,
            'cdn_strip_exif' => 1,
            'cdn_automatic_resize' => 1,
            'cdn_webp_conversion' => 1,
            'access_setting' => 1,
            'cdn_rest_api' => 1,
            'image_sizes' => 'all',
            'media_header' => 0,
            'disable_wp_compression' => 1,
            'media_columns' => ['title'],
            'automatic_compression' => 1,
            'super_compression' => 0,
            'png_to_jpg' => 0,
            'strip_exif' => 1,
            'image_backup' => 0,
            'image_resizing' => 'default',
            'image_resizing_width' => 2560,
            'image_resizing_height' => 2560,
            'compress_original_image' => 0,
            'lazy_media_type' => 'all',
            'lazy_media_types' => [],
            'lazy_output_location' => ['content', 'widget','thumbnail','gravatars'],
            'lazy_animation_type' => 'fadein',
            'lazy_animation_duration' => 400,
            'lazy_animation_delay' => 0,
            'lazyload_attachment_id' => 0,
            'lazyload_attachment_url' => '',
            'lazyload_active_spinner' => 'loader-1.gif',
            'lazyload_placeholder_attachment_id' => 0,
            'lazyload_placeholder_attachment_url' => '',
            'lazyload_active_placeholder' => 'placeholder-1.png',
            'lazyload_bg_color_1' => '#fafafa',
            'lazyload_bg_color_2' => '#333',
            'lazyload_bg_color_3' => '',
            'lazy_post_types' => [],
            'lazy_post_type' => 'all',
            'lazy_disable_urls' => '',
            'lazy_disable_classes' => '',
            'lazy_script_location' => 'footer',
            'native_lazy_loading' => 1,
            'disable_no_script' => 1,
            'auto_watermark' => 0,
            'watermark_type' => 'text',
            'watermark_attachment_url' => '',
            'watermark_attachment_id' => 0,
            'watermark_text' => 'WPFiles',
            'watermark_font' => 167,
            'watermark_size' => 20,
            'watermark_color' => '#ffffff',
            'watermark_opacity' => 1,
            'watermark_position' => 'bottom-right',
            'watermark_x_axis' => -10,
            'watermark_y_axis' => -10,
            'watermark_scale_value' => 20,
            'watermark_image_sizes' => 'all',
            'watermark_image_sizes_manual' => [],
            'watermark_variant' => 5,
            'watermark_fill' => 1,
            'watermark_rounded_corner' => 10,
            'watermark_text_padding' => 100,
            'watermark_bg_color' => '#006e52',
            'watermark_stroke_color' => '',
            'watermark_stroke_width' => 1.2,
            'starred_folder' => 1,
            'starred_attachment' => 1,
            'auto_delete_old_items' => 0,
            'auto_delete_item_after_days' => 30,
            'is_folder_media_deleted' => 1,
            'woocommerce_sub_folder_creation_by' => 'name',
            'rename_media_type_values' => [],
            'rename_media_type' => 'all',
            'rename_media_remove_char' => [],
            'auto_media_format' => 1,
            'auto_media_rename' => 1,
            'auto_media_format_text' => 1,
            'image_manual_sizes' => []
        ];

        if ($column) {
            if (!in_array($column, ['api_key', 'site_status'])) {
                update_option(WP_FILES_PREFIX . $column, $default_options[$column]);
            }
        } else {
            if($reset_watermark) {
                //Reset watermark
                try {
                    //Delete old file if exist
                    if(file_exists(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'))) {
                        unlink(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'));
                    }
                    $watermark_image = time().'.png';
                    Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'last-watermark-save', $watermark_image);
                    @copy(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/reset-watermark.png', WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.$watermark_image);
                } catch (\Throwable $th) { }
            } else {
                foreach ($default_options as $option => $value) {
                    if ($free) {
                        if (in_array($value, ['cdn', 'max_file_upload_default', 'file_types', 'media_header', 'super_compression', 'png_to_jpg', 'compress_original_image', 'watermark_position', 'watermark_x_axis', 'watermark_y_axis', 'watermark_scale_value', 'watermark_opacity'])) {
                            update_option(WP_FILES_PREFIX . $option, $value);
                        }
                    } else {
                        if (!in_array($option, ['api_key', 'site_status'])) {
                            update_option(WP_FILES_PREFIX . $option, $value);
                        }
                    }
                }
    
                if (!$free) {
                    //Reset auto update settings
                    Wp_Files_Settings::auto_update(['auto_update' => 0]);
        
                    //Reset upload media types
                    Wp_Files_Settings::saveUploadFileTypes(
                        [
                            'exts' => [
                                [
                                    'description' => 'Svg support',
                                    'mime_type' => 'image/svg+xml',
                                    'ext' => '.svg',
                                    'status' => 0
                                ]
                            ]
                        ]
                    );
        
                    //Reset default colors
                    Wp_Files_Settings::resetDefaultColors();
                }
        
                //Reset watermark
                try {
                    //Delete old file if exist
                    if(file_exists(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'))) {
                        unlink(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.get_option(WP_FILES_PREFIX . 'last-watermark-save'));
                    }
                    $watermark_image = time().'.png';
                    Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'last-watermark-save', $watermark_image);
                    @copy(WP_FILES_PLUGIN_DIR . 'admin/images/watermark/reset-watermark.png', WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.$watermark_image);
                } catch (\Throwable $th) { }
            }
        }
    }

    /**
     * Settings options
     * @since 1.0.0
     * @return array
     */
    public static function settingsOptions()
    {
        return ['cdn', 'compression', 'lazy_load', 'local_webp', 'watermark', 'media_rename', 'media_replace', 'media_download', 'starred_media', 'folder_color', 'trash_bin', 'user_folder', 'folder_lock', 'woocommerce_support', 'gutenberg_editor_support', 'woocommerce_editor_support', 'class_editor_support', 'elementor_support', 'beaver_support', 'wpbakery_support', 'brizy_support', 'cornerstone_support', 'divi_support', 'thrive_quiz_support', 'fusion_support', 'oxygen_support', 'tatsu_support', 'dokan_support', 'thirdparty_compatibility', 'gutenberg_support', 'max_file_upload_size', 'max_file_upload_default', 'file_types', 'language', 'usage_tracking', 'is_plugin_removal_delete_data', 'cdn_bg_image', 'compress_on_fly', 'cdn_super_compression', 'cdn_strip_exif', 'cdn_automatic_resize', 'cdn_webp_conversion', 'access_setting', 'cdn_rest_api', 'image_sizes', 'media_header', 'disable_wp_compression', 'image_manual_sizes', 'media_columns', 'automatic_compression', 'super_compression', 'png_to_jpg', 'strip_exif', 'image_backup', 'image_resizing', 'image_resizing_width', 'image_resizing_height', 'compress_original_image', 'lazy_media_type', 'lazy_media_types', 'lazy_output_location', 'lazy_animation_type', 'lazy_animation_duration', 'lazy_animation_delay', 'lazyload_attachment_id', 'lazyload_attachment_url', 'lazyload_active_spinner', 'lazyload_placeholder_attachment_id', 'lazyload_placeholder_attachment_url', 'lazyload_active_placeholder', 'lazyload_bg_color_1', 'lazyload_bg_color_2', 'lazyload_bg_color_3', 'lazy_post_types', 'lazy_post_type', 'lazy_disable_urls', 'lazy_disable_classes', 'lazy_script_location', 'native_lazy_loading', 'disable_no_script', 'api_key', 'site_status', 'auto_watermark', 'watermark_type', 'watermark_attachment_url', 'watermark_attachment_id', 'watermark_text', 'watermark_font', 'watermark_size', 'watermark_color', 'watermark_opacity', 'watermark_position', 'watermark_x_axis', 'watermark_y_axis', 'watermark_scale_value', 'watermark_image_sizes', 'watermark_image_sizes_manual', 'watermark_variant', 'watermark_fill', 'watermark_rounded_corner', 'watermark_text_padding', 'watermark_bg_color', 'watermark_stroke_color', 'watermark_stroke_width', 'starred_folder', 'starred_attachment', 'auto_delete_item_after_days', 'woocommerce_sub_folder_creation_by', 'rename_media_type_values', 'rename_media_type', 'rename_media_remove_char', 'auto_media_format', 'auto_media_rename', 'auto_media_format_text', 'is_folder_media_deleted', 'auto_delete_old_items'];
    }

    /**
     * Fetch post types
     * @since 1.0.0
     * @return array
     */
    public static function getPostTypes()
    {
        $customPostTypes = get_post_types(
            array(
                'public'   => true,
                '_builtin' => false,
            ),
            'objects',
            'and'
        );

        $types = array();

        foreach ($customPostTypes as $type => $row) {
            $types[] = $type;
        }

        return $types;
    }

    /**
     * Format mime types stored in the database in the 'ext => mime' format.
     * @since 1.0.0
     * @return array
     */
    public static function enabledTypes()
    {
        $stored_types = (array) Wp_Files_Settings::loadUploadFileTypes();

        $stored_types =  (array)array_filter($stored_types, function ($row) {
            return $row['status'] == 1;
        });

        $enabled_types    = count($stored_types) > 0 ? (array) array_column($stored_types, 'ext') : array();

        $available_types  = Wp_Files_Helper::getAvailableFileTypes();

        $return_types     = array();

        foreach ($available_types as $type) {
            if (in_array($type['ext'], $enabled_types, true)) {
                $ext = trim($type['ext'], '.');

                $ext = str_replace(',', '|', $ext);

                $return_types[$ext] = $type['mime'];
            }
        }

        foreach ($stored_types as $type) {
            if (empty($type['ext']) || empty($type['mime_type'])) {
                continue;
            }

            $ext = trim($type['ext'], '.');

            $ext = str_replace(',', '|', $ext);

            $return_types[$ext] = $type['mime_type'];
        }

        return $return_types;
    }

    /**
     * Add/update wordpress option value
     * @since 1.0.0
     * @param string $key
     * @param string $value
     * @return void
    */
    public static function addOrUpdateOption($key, $value)
    {
        $option = self::existOption($key);
        if ($option) {
            update_option($key, $value);
        } else {
            add_option($key, $value);
        }
    }

    /**
     * Check option key exist or not
     * @since 1.0.0
     * @param string $arg
     * @return boolean
    */
    public static function existOption( $arg ) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $db_options = $prefix.'options';
        $sql_query = 'SELECT * FROM ' . $db_options . ' WHERE option_name LIKE "' . $arg . '"';
        $results = $wpdb->get_results( $sql_query, OBJECT );
        if ( count( $results ) === 0 ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Geo location info
     * @since 1.0.0
     * @return array
     */
    public static function getLocationInfoByIp()
    {
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = @$_SERVER['REMOTE_ADDR'];
        $result  = array('country'=>'', 'city'=>'');
        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }
        $ip_data = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip));
        if ($ip_data && $ip_data->geoplugin_countryName != null) {
            $result['country'] = $ip_data->geoplugin_countryCode;
            $result['city'] = $ip_data->geoplugin_city;
        }
        return $result;
    }

    /**
     * Get domain
     * @since 1.0.0
     * @param string $url
     * @return string
     */
    public static function getDomain($url)
    {
        if (substr($url, 0, 7) == 'http://') {
            return $url;
        }

        if (substr($url, 0, 8) == 'https://') {
            return $url;
        }

        $site_url_parts = parse_url(get_site_url());

        if (isset($site_url_parts['scheme']) && $site_url_parts['scheme'] && $site_url_parts['scheme'] == 'https') {
            return 'https://'.$url;
        } else {
            return 'http://'.$url;
        }
    }

    /**
     * Get host name
     * @since 1.0.0
     * @param mixed $url
     * @return string
     */
    public static function getHostname($url)
    {
        $parse = parse_url($url);
        if (isset($parse['host']) && $parse['host']) {
            return str_ireplace('www.', '', $parse['host']);
        } else {
            return $url;
        }
    }

    /**
     * Get current user role
     * @since 1.0.0
     * @return string
    */
    public static function getCurrentUserRole()
    {
        $role = null;

        $current_user = wp_get_current_user();

        if (! empty($current_user->roles)) {
            $role = $current_user->roles[0];
        }

        return $role;
    }

    /**
     * Sanitize input
     * @since 1.0.0
     * @param $arr
     * @return array|string
     */
    public static function sanitizeTextOrArrayField($array_or_string)
    {
        if (is_string($array_or_string)) {
            $array_or_string = sanitize_text_field($array_or_string);
        } elseif (is_array($array_or_string)) {
            foreach ($array_or_string as $key => &$value) {
                if (is_array($value)) {
                    $value = self::sanitizeTextOrArrayField($value);
                } else {
                    $value = sanitize_text_field($value);
                }
            }
        }

        return $array_or_string;
    }

    /**
     * Get spaced used by current site
     * @since 1.0.0
     * @param $arr
     * @return array|string
     */
    public static function getSpaceUsed()
    {
        try {
            $upload_dir = wp_upload_dir();
            $bytes = get_dirsize($upload_dir['basedir']);
            return $bytes;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /**
     * Log error
     * @since 1.0.0
     * @param $log
     * @return void
     */
    public static function write_log($log)
    {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

    /**
     * Check for svg
     * @since 1.0.5
     * @param $url
     * @return string
     */
    public static function isSvg($url)
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $extension  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if($extension == "svg") {
            return true;
        }
        return false;
    }
}
