<?php

/**
 * WPFiles Media library class.
 * Responsible for displaying a UI (stats + action links) in the media library and the editor.
 * @since 1.0.0
 */

class Wp_Files_Media_Library extends WP_Media_List_Table
{
    /**
     * Folder table
     * @since 1.0.0
     * @var $folder_table
    */
    private static $folder_table = 'wpf';

    /**
     * Folder relation table
     * @since 1.0.0
     * @var $relation_table
    */
    private static $relation_table = 'wpf_attachment_folder';

    /**
     * Media library list view default columns
     * @since 1.0.0
     * @var $media_default_columns
    */
    public $media_default_columns = [];

    /**
     * Media library list view wordpress columns
     * @since 1.0.0
     * @var $wp_columns
    */
    public $wp_columns = ['cb', 'title', 'author', 'parent', 'comments', 'date', 'compressit'];

    /**
     * Media library list view skip columns
     * @since 1.0.0
     * @var $skip_columns
    */
    public $skip_columns = ['cb', 'compressit', 'comments'];

    /**
     * settings
     * @since 1.0.0
     * @var $settings
    */
    private $settings;

    /**
     * compression
     * @since 1.0.0
     * @var $compression
     */
    private $compression;

    /**
     * Media_Library constructor.
     * @since 1.0.0
     * @return void
     */
    public function __construct($settings)
    {
        parent::__construct(['screen' => 'upload.php']);
        $this->settings = $settings;
        $this->compression = new Wp_Files_Compression($settings);
        $this->compression->init($this->settings);
        $this->media_default_columns = $this->get_columns();
    }

    /**
     * Init functionality that is related to the UI.
     * @since 1.0.0
     * @return void
    */
    public function init_ui()
    {
        // Media library columns.
        add_filter('manage_media_columns', array($this, 'columns'));

        add_filter('manage_upload_sortable_columns', array($this, 'sortable_column'));

        add_action('manage_media_custom_column', array($this, 'custom_column'), 10, 2);

        // Manage column sorting.
        add_action('pre_get_posts', array($this, 'compressit_orderby'));

        // Compress image filter from Media Library.
        add_filter('ajax_query_attachments_args', array($this, 'filter_media_query'));

        // Compress image filter from Media Library (list view).
        add_action('restrict_manage_posts', array($this, 'add_filter_dropdown'));

        // Add pre WordPress 5.0 compatibility.
        add_filter('wp_kses_allowed_html', array($this, 'filter_html_attributes'));

        add_action('admin_enqueue_scripts', array($this, 'extend_media_modal'), 15);

        

        //Media relations
        add_filter('wp_prepare_attachment_for_js', array($this, 'media_relation'), 99, 5);

        //Max file upload size
        if ($this->settings['max_file_upload_default'] == 0) {
            add_filter('upload_size_limit', array($this, 'upload_max_increase_upload'));
        }

        //Allow file types
        add_filter('upload_mimes', array($this, 'allowed_types'));
    }

    /**
     * Third party media columns
     * @since 1.0.0
     * @param array $defaults  Defaults array.
     * @return array
    */
    public function media_columns()
    {
        $columns = array();

        if(count($this->media_default_columns) > 0) {
            foreach($this->media_default_columns as $column => $label) {
                if(!in_array($column, $this->skip_columns)) {
                    $columns[] = array(
                        'column' => $column,
                        'label' => $label,
                    );
                }
            }
        }

        return $columns;
    }

    /**
     * Print column header for Compress results in the media library
     * @since 1.0.0
     * @param array $defaults  Defaults array.
     * @return array
     */
    public function columns($defaults)
    {
        $defaults['compressit'] = 'Compress';

        return $defaults;
    }

    /**
     * Add the compressit Column to sortable list
     * @since 1.0.0
     * @param array $columns
     * @return array
     */
    public function sortable_column($columns)
    {
        $columns['compressit'] = 'compressit';

        return $columns;
    }

    /**
     * Print column data for Compress results in the media library
     * @since 1.0.0
     * @param string $column_name
     * @param int $id 
     */
    public function custom_column($column_name, $id)
    {
        if ('compressit' === $column_name) {
            echo wp_kses_post($this->compression->generateHtml($id));
        }
    }

    /**
     * Order by query for compress columns.
     * @since 1.0.0
     * @param WP_Query $query
     * @return WP_Query
     */
    public function compressit_orderby($query)
    {
        global $current_screen;

        // Filter only media screen.
        if (!is_admin() || (!empty($current_screen) && 'upload' !== $current_screen->base)) {
            return $query;
        }

        // Ignored.
        if (isset($_REQUEST['compression-filter']) && 'ignored' === $_REQUEST['compression-filter']) {
            $query->set('meta_query', $this->query_ignored());
            return $query;
        }

        // Could not compress.
        if (isset($_REQUEST['compression-filter']) && 'uncompressed' === $_REQUEST['compression-filter']) {
            $query->set('meta_query', $this->query_uncompressed());
            return $query;
        }

        $orderby = $query->get('orderby');

        if (isset($orderby) && 'compressit' === $orderby) {
            $query->set(
                'meta_query',
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => Wp_Files_Compression::$compressedMetaKey,
                        'compare' => 'EXISTS',
                    ),
                    array(
                        'key'     => Wp_Files_Compression::$compressedMetaKey,
                        'compare' => 'NOT EXISTS',
                    ),
                )
            );
            $query->set('orderby', 'meta_value_num');
        }

        return $query;
    }

    /**
     * Meta query for skipped images from bulk compress.
     * @since 1.0.0
     * @return array
     */
    private function query_ignored()
    {
        return array(
            array(
                'key'     => WP_FILES_PREFIX . 'ignore-bulk',
                'value'   => 'true',
                'compare' => 'EXISTS',
            ),
        );
    }

    /**
     * Meta query for uncompressed images.
     * @since 1.0.0
     * @return array
     */
    private function query_uncompressed()
    {
        return array(
            array(
                'key'     => Wp_Files_Compression::$compressedMetaKey,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => WP_FILES_PREFIX . 'ignore-bulk',
                'compare' => 'NOT EXISTS',
            ),
        );
    }

    /**
     * Add filter to the media query filter in Media Library.
     * @since 1.0.0
     * @param array $query
     * @return mixed
     */
    public function filter_media_query($query)
    {
        $post_query = filter_input(INPUT_POST, 'query', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);

        // Excluded.
        if (isset($post_query['stats']) && 'excluded' === $post_query['stats']) {
            $query['meta_query'] = $this->query_ignored();
        }

        // Uncompressed.
        if (isset($post_query['stats']) && 'uncompressed' === $post_query['stats']) {
            $query['meta_query'] = $this->query_uncompressed();
        }

        return $query;
    }

    /**
     * Add filter drop down
     * @since 1.0.0
     * @return mixed
     */
    public function add_filter_dropdown()
    {
        $scr = function_exists('get_current_screen') ? get_current_screen() : false;

        if ('upload' !== $scr->base) {
            return;
        }

        $ignored = filter_input(INPUT_GET, 'compression-filter', FILTER_UNSAFE_RAW);

        ?>
        <label for="compression_filter" class="screen-reader-text">
            <?php esc_html_e('Filter by Compress status', 'wpfiles'); ?>
        </label>
        <select class="compression-filters" name="compression-filter" id="compression_filter">
            <option value="" <?php selected($ignored, ''); ?>><?php esc_html_e('Compress: All images', 'wpfiles'); ?></option>
            <option value="uncompressed" <?php selected($ignored, 'uncompressed'); ?>><?php esc_html_e('Compress: Could not compress', 'wpfiles'); ?></option>
            <option value="ignored" <?php selected($ignored, 'ignored'); ?>><?php esc_html_e('Compress: Bulk ignored', 'wpfiles'); ?></option>
        </select>
        <?php
    }

    /**
     * Some data attributes are not allowed on <a> tag/elements on WordPress < 5.0.0.
     * @since 1.0.0
     * @param array $context
     * @return mixed
     */
    public function filter_html_attributes($context)
    {
        global $wp_version;

        if (version_compare('5.0.0', $wp_version, '<')) {
            return $context;
        }

        $context['a']['data-tooltip'] = true;

        $context['a']['data-id']      = true;

        $context['a']['data-nonce']   = true;

        return $context;
    }

    /**
     * Load media assets.
     * Localization also used in Gutenberg integration.
     * @since 1.0.0
     * @return mixed
     */
    public function extend_media_modal()
    {
        // Get current screen.
        $current_screen = function_exists('get_current_screen') ? get_current_screen() : false;

        // Only run on required pages.
        if (!empty($current_screen) && !in_array($current_screen->id, Wp_Files_Compression::$externalPages, true) && empty($current_screen->is_block_editor)) {
            return;
        }

        if (wp_script_is('wpfiles-backbone-extension', 'enqueued')) {
            return;
        }

        wp_enqueue_script(
            'wpfiles-backbone-extension',
            WP_FILES_PLUGIN_URL . 'admin/js/libraries/media.min.js',
            array(
                'jquery',
                'media-editor', // Used in image filters.
                'media-views',
                'media-grid',
                'wp-util',
                'wp-api',
            ),
            WP_FILES_VERSION,
            true
        );

        wp_enqueue_script(
            'wpfiles-helper',
            WP_FILES_PLUGIN_URL . 'admin/js/helper.min.js',
            array(
                'jquery',
            ),
            WP_FILES_VERSION,
            true
        );

        wp_enqueue_script(
            'wpfiles-compression',
            WP_FILES_PLUGIN_URL . 'admin/js/compression/index.min.js',
            array(
                'jquery', 'wpfiles-helper'
            ),
            WP_FILES_VERSION,
            true
        );

        wp_localize_script(
            'wpfiles-backbone-extension',
            'compression_vars',
            array(
                'strings' => array(
                    'stats_label'          => esc_html__('Compress', 'wpfiles'),
                    'filter_all'           => esc_html__('Compress: All images', 'wpfiles'),
                    'filter_not_processed' => esc_html__('Compress: Could not compress', 'wpfiles'),
                    'filter_excl'          => esc_html__('Compress: Bulk ignored', 'wpfiles'),
                    'gb'                   => array(
                        'stats'        => esc_html__('Compression Stats', 'wpfiles'),
                        'select_image' => esc_html__('Select an image to view Compression stats.', 'wpfiles'),
                        'size'         => esc_html__('Image size', 'wpfiles'),
                        'savings'      => esc_html__('Savings', 'wpfiles'),
                    ),
                ),
            )
        );
    }

    /**
     * Get the compression button text for attachment.
     * @since 1.0.0
     * @param int $id
     * @return string
     */
    private function compression_status($id)
    {
        $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);

        // Show Temporary Status, For Async Optimization, No Good workaround.
        if (!get_transient("wpfiles-restore-{$id}") && 'upload-attachment' === $action && $this->settings['automatic_compression']) {

            $status_txt = '<p class="compression-status">' . __('Compression in progress...', 'wpfiles') . '</p>';

            // We need to show the compress button.
            $show_button = false;

            $button_txt  = __('Compress Now!', 'wpfiles');

            return $this->column_html($id, $status_txt, $button_txt, $show_button);
        }

        // Else Return the normal status.
        return trim($this->compression->generateHtml($id));
    }

    /**
     * Get the compressed attachment.
     * @since 1.0.0
     * @param int $id 
     * @return array
     */
    private function getCompressedAttachment($id)
    {
        return $this->compression->getCompressedAttachment($id);
    }

    /**
     * Get the watermark status for attachment.
     * @since 1.0.0
     * @param int $id 
     * @return string
     */
    private function attachmentWatermarkStatus($id)
    {
        return $this->compression->attachmentWatermarkStatus($id);
    }

    /**
     * Print the column html.
     * @since 1.0.0
     * @param string  $id          
     * @param string  $html        
     * @param string  $button_txt   
     * @param boolean $show_button 
     * @return string
     */
    private function column_html($id, $html = '', $button_txt = '', $show_button = true)
    {
        if (!wp_attachment_is_image($id) || !in_array(get_post_mime_type($id), Wp_Files_Compression::$mimeTypes, true)) {
            return __('Could not compress', 'wpfiles');
        }

        if (!$show_button) {
            return $html;
        }

        if ('Super-Compression' === $button_txt) {
            $html .= ' | ';
        }

        $html .= "<a href='#' class='wpfiles-send' data-id='{$id}'>{$button_txt}</a>";

        $skipped = get_post_meta($id, WP_FILES_PREFIX . 'ignore-bulk', true);
        if ('true' === $skipped) {
            $nonce = wp_create_nonce('wpfiles-remove-skipped');
            $html .= " | <a href='#' class='wpfiles-remove-skipped' data-id={$id} data-nonce={$nonce}>" . __('Add to bulk-compression', 'wpfiles') . '</a>';
        } else {
            $html .= " | <a href='#' class='wpfiles-ignore-image' data-id='{$id}'>" . esc_html__('Ignore', 'wpfiles') . '</a>';
        }

        $html .= self::progress_bar();

        return $html;
    }

    /**
     * Returns the HTML for progress bar
     * @since 1.0.0
     * @return string
     */
    public static function progress_bar()
    {
        return '<span class="spinner wpfiles-progress"></span>';
    }

    

    

    

    

    

    

    

    

    

    /**
     * Media relations
     * @since 1.0.0
     * @param $response
     * @param $attachment
     * @return array
     */
    public function media_relation($response, $attachment)
    {
        global $wpdb;

        if (!isset($attachment->ID) && !isset($attachment->id)) {
            return $response;
        }

        $attachment_id = isset($attachment->ID) ? $attachment->ID : $attachment->id;

        if ((int)$attachment->post_parent > 0) {
            $response['post_parent'] = $attachment->post_parent;
            $post = get_post($attachment->post_parent);
            if ($post) {
                $response['attached_post_title'] = $post->post_title;
            }
        }

        $relation = $wpdb->get_row($wpdb->prepare('SELECT * FROM %1$s WHERE `attachment_id` = %2$d AND deleted_at IS NULL', self::getTable(self::$relation_table), $attachment_id));

        $response['folder_id'] = $relation ? $relation->folder_id : 0;

        $response['starred'] = get_post_meta((int)$attachment_id, WP_FILES_PREFIX . 'starred', true);

        $response['deleted_at'] = $attachment->post_modified;

        //compression
        $status            = $this->compression_status($attachment->ID);

        if($this->settings['compression']) {
            $response['compress'] = $status;
        }

        $compressed_attachment = $this->getCompressedAttachment($attachment->ID);

        $response['compress_percentage'] = $compressed_attachment ? ceil((float)$compressed_attachment['percent']) : 0;

        //watermark status
        $response['watermark_status'] = $this->attachmentWatermarkStatus($attachment->ID);
        
        $restored = get_post_meta($attachment->ID, Wp_Files_Compression::$restoreAt, 0);

        //Add versioning if image were compressed to load from browser cache
        if($compressed_attachment || $response['watermark_status'] || $restored) {
            $sizes = array();
            $version = isset($compressed_attachment['timestamp']) && $compressed_attachment['timestamp'] ? $compressed_attachment['timestamp'] : (isset($response['watermark_status']['timestamp']) && $response['watermark_status']['timestamp'] ? $response['watermark_status']['timestamp'] : $restored);
            if(isset($response['sizes']) && count($response['sizes']) && is_array($response['sizes']) && count($response['sizes']) > 0) {
                foreach ($response['sizes'] as $size => $row) {
                    $row['url'] = $row['url'].'?v='.$version;
                    $sizes[$size] = $row;
                }
                $response['sizes'] = $sizes;
            }
            $response['version'] = $version;
        } else {
            $response['version'] = 0;
        }

        //Third party plugins support for media columns
        if(count($this->media_default_columns) > 0 && isset($_POST['wpf']) && isset($_POST['mode']) && $_POST['mode'] == "list") {
            foreach($this->media_default_columns as $column => $row) {
                if(!in_array($column, $this->wp_columns)) {
                    ob_start();
                    $this->column_default(get_post($attachment_id), $column);
                    $contents=ob_get_clean();
                    //column output
                    $response[$column] = $contents;
                }
            }
        }

        return $response;
    }

    /**
     * Filter to increase max_file_size
     * @since 1.0.0
     * @return int 
     */
    public function upload_max_increase_upload()
    {
        $max_size = (int) $this->settings['max_file_upload_size'];

        if (!$max_size) {
            $max_size = 64 * 1024 * 1024;
        } else {
            $max_size = $max_size * 1024 * 1024;
        }

        return $max_size;
    }

    /**
     * File types allowed to upload.
     * @since 1.0.0
     * @param array $mimeTypes 
     * @return array
     */
    public function allowed_types($mimeTypes)
    {
        if ($this->settings['file_types'] == 1) {

            // Only add first mime type to the allowed list. Aliases will be dynamically added when required.
            $enabled_types = array_map(
                function ($enabled_types) {
                    return sanitize_mime_type(!is_array($enabled_types) ? $enabled_types : $enabled_types[0]);
                },
                Wp_Files_Helper::enabledTypes()
            );

            //Svg support
            if(array_key_exists("svg", $enabled_types)) {
                new WpFiles_svg_support();
            }
            
            return array_replace($mimeTypes, $enabled_types);
        } else {
            return $mimeTypes;
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
