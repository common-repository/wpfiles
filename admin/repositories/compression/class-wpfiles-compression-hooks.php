<?php 
trait Wp_Files_Compression_Hooks {

    /**
     * Compression/Watermark related hooks
     * @since 1.0.0
     * @var void
     */
    public function hooks() {
        
        add_filter('bigImageSizeThreshold', array($this, 'bigImageSizeThreshold'), 10, 4);

        if (is_admin()) {
            add_filter('wp_files_media_image', array($this, 'skipImages'), 10, 2);
        }

        add_action('wp_files_image_optimized', array($this, 'updateLists'), '', 2);

        if(isset($this->settings['watermark']) && $this->settings['watermark']) {
            add_filter('wp_generate_attachment_metadata', array($this, 'watermarkImage'), 14, 2);
        }

        if(isset($this->settings['compression']) && $this->settings['compression']) {
            add_filter('wp_generate_attachment_metadata', array($this, 'compressionImage'), 15, 2);
        }

        add_action('wp_async_wp_generate_attachment_metadata', array($this, 'wpFilesHandleAsync'));

        add_action('delete_attachment', array($this, 'deleteImages'), 12);

        add_action('wp_files_remove_filters', array($this, 'removeFilters'));
    }

    /**
     * Set the large image threshold.
     * @since 1.0.0
     * @param int $threshold Threshold value in pixels. Default is 2560.
     * @return int New threshold.
     */
    public function bigImageSizeThreshold($threshold)
    {
        if ($this->settings['image_resizing'] == "default") {
            return $threshold;
        }

        if (!$this->settings['image_resizing_width'] || !$this->settings['image_resizing_height']) {
            return $threshold;
        }

        return $this->settings['image_resizing_width'] > $this->settings['image_resizing_height'] ? $this->settings['image_resizing_width'] : $this->settings['image_resizing_height'];
    }

    /**
     * Skip images
     * @since 1.0.0
     * @param string $image 
     * @param string $size 
     * @return bool
     */
    public function skipImages($image, $size)
    {
        if (empty($_POST['regen']) || !is_array($_POST['regen'])) {
            return $image;
        }

        $compressionSizes = wp_unslash(Wp_Files_Helper::sanitizeArray($_POST['regen']));

        if (in_array($size, $compressionSizes, true)) {
            return $image;
        }

        return false;
    }

    /**
     * Detect if the image compression is lossy, stores the image id in options table
     * @since 1.0.0
     * @param int $id
     * @param array 
     * @param string 
     * @return bool
     */
    public function updateLists($id, $stats, $key = '')
    {
        if (empty($stats) || empty($id) || empty($stats['stats'])) {
            return false;
        }

        if (isset($stats['stats']['lossy']) && 1 == $stats['stats']['lossy']) {
            if (empty($key)) {
                update_post_meta($id, 'wpfiles-lossy', 1);
            } else {
                $this->updateSuperCompressCount($id, 'add', $key);
            }
        }

        if (!empty($this->recompress_ids) && in_array($id, $this->recompress_ids)) {
            $this->updateRecompressionList($id);
        }
    }

    /**
     * Process image compression
     * @since 1.0.0
     * @param array $meta 
     * @param int $id
     * @uses resizeFromMetaData 
     * @return mixed
     */
    public function compressionImage($meta, $id)
    {
        // Check if this call originated from Gutenberg
        // Allow only media.
        if (!empty($GLOBALS['wp']->query_vars['rest_route'])) {
            $route = untrailingslashit($GLOBALS['wp']->query_vars['rest_route']);
            if (empty($route) || '/wp/v2/media' !== $route) {
                return $meta;
            }
        }

        $upload_attachment    = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
        
        $isUploadAttachment = 'upload-attachment' === $upload_attachment || isset($_POST['post_id']);

        if ($isUploadAttachment && defined('WP_FILES_ASYNC') && WP_FILES_ASYNC) {
            return $meta;
        }

        if (!wp_attachment_is_image($id)) {
            return $meta;
        }

        if (get_transient("wpfiles-restore-$id") || get_transient("compression-in-progress-$id")) {
            return $meta;
        }

        if (!apply_filters('wp_files_image', true, $id)) {
            return $meta;
        }

        set_transient( 'compression-in-progress-' . $id, 1, HOUR_IN_SECONDS );

        // When uploading from Mobile App or other sources,  May be admin_init action not fire
        
        // So we need to manually initialize those.
        $this->resize->initialize(true);

        $auto_compression = $this->isAutoCompressionEnabled();

        $attachment_file_path = get_attached_file($id);

        Wp_Files_Helper::checkAnimatedStatus($attachment_file_path, $id);

        $this->createBackup($attachment_file_path, $id);

        $meta = $this->resize->autoResize($id, $meta);

        if ($auto_compression) {

            

            $this->resizeFromMetaData($meta, $id);

        } else {
            delete_post_meta($id, self::$compressedMetaKey);
        }

        delete_transient('compression-in-progress-' . $id);

        return $meta;
    }

    /**
     * Process watermark
     * @since 1.0.0
     * @uses resizeFromMetaData
     * @param array $meta
     * @param int   $id
     * @return mixed
     */
    public function watermarkImage($meta, $id)
    {
        // Check if this call originated from Gutenberg
        // Allow only media.
        if (!empty($GLOBALS['wp']->query_vars['rest_route'])) {
            $route = untrailingslashit($GLOBALS['wp']->query_vars['rest_route']);
            if (empty($route) || '/wp/v2/media' !== $route) {
                return $meta;
            }
        }

        $upload_attachment    = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
        
        $isUploadAttachment = 'upload-attachment' === $upload_attachment || isset($_POST['post_id']);

        if ($isUploadAttachment && defined('WP_FILES_ASYNC') && WP_FILES_ASYNC) {
            return $meta;
        }

        if (!wp_attachment_is_image($id)) {
            return $meta;
        }

        if (get_transient("wpfiles-restore-$id")) {
            return $meta;
        }

        if (!apply_filters('wp_files_image', true, $id)) {
            return $meta;
        }

        $auto_watermark = $this->isAutoWatermarkEnabled();

        if ($auto_watermark) {
            $this->addWatermark($id);
        } else {
            delete_post_meta($id, self::$watermarkMetaKey);
        }

        return $meta;
    }

    /**
     * Compression request for the attachment
     * @since 1.0.0
     * @param int $id 
     */
    public function wpFilesHandleAsync($id)
    {
        if (empty($id) || get_transient('compression-in-progress-' . $id) || get_transient("wpfiles-restore-$id")) {
            return;
        }

        if (!$this->isAutoCompressionEnabled()) {
            return;
        }

        if (!apply_filters('wp_files_image', true, $id)) {
            return;
        }

        $this->compressOne($id, true);
    }

    /**
     * When an attachment is deleted ,deletes all the backup files
     * Update recompress List
     * Update Super Compress image count
     * @since 1.0.0
     * @param int
     * @return bool|void
     */
    public function deleteImages($image_id)
    {
        $this->stats->getSavings('resize');

        $this->stats->getSavings('pngjpg');

        if (empty($image_id)) {
            return false;
        }

        $recompress_list = get_option('wpfiles-recompress-list');

        if ($recompress_list) {
            $this->updateRecompressionList($image_id, 'wpfiles-recompress-list');
        }

        $this->deleteBackupFiles($image_id);

        

        $rewatermark_list = get_option('wpfiles-rewatermark-list');

        if ($rewatermark_list) {
            $this->updateRewatermarkList($image_id, 'wpfiles-rewatermark-list');
        }
    }

    /**
     * Remove filters
     * @since 1.0.0
     * @return bool|void
    */
    public function removeFilters()
    {
        if (class_exists('Wp_Media_Folder')) {
            global $wp_media_folder;
            if (is_object($wp_media_folder)) {
                remove_filter('pre_get_posts', array($wp_media_folder, 'wpmf_pre_get_posts1'));
                remove_filter('pre_get_posts', array($wp_media_folder, 'wpmf_pre_get_posts'), 0, 1);
            }
        }

        global $wpml_query_filter;

        if (!is_object($wpml_query_filter)) {
            return;
        }

        if (has_filter('posts_join', array($wpml_query_filter, 'posts_join_filter'))) {
            remove_filter('posts_join', array($wpml_query_filter, 'posts_join_filter'), 10, 2);
            remove_filter('posts_where', array($wpml_query_filter, 'posts_where_filter'), 10, 2);
        }
    }
    
}