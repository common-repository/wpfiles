<?php
/**
 * Wp_Files_Compression_Requests class that contain every thing about compression & watermark
 */

class Wp_Files_Compression_Requests
{

    /**
     * Settings instance
     * @since 1.0.0
     * @var string $settings
    */
    private $settings = array();

    /**
     * Wp_Files_Compression class instance
     * @since 1.0.0
     * @var string $compression
    */
    private $compression;

    

    /**
     * Wp_Files_Stats class instance
     * @since 1.0.0
     * @var string $stats
    */
    private $stats = null;

    /**
     * Wp_Files_Resize class instance
     * @since 1.0.0
     * @var string $resize
    */
    private $resize = null;

    

    /**
     * Wp_Files_Directory class instance
     * @since 1.0.0
     * @var string $directory
    */
    private $directory = null;

    /**
     * Initilazation class required things
     * @since    1.0.0
     * @access   public
    */
    public function __construct()
    {
        $this->settings = Wp_Files_Settings::loadSettings();

        $this->compression = new Wp_Files_Compression($this->settings);

        $this->compression->init($this->settings);

        

        $this->stats = new Wp_Files_Stats($this->settings);

        $this->stats->init();

        $this->resize = new Wp_Files_Resize($this->settings);

        

        $this->directory = new Wp_Files_Directory($this->settings);
    }

    /**
     * Bulk compression Handler
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function processCompressionRequest($request)
    {
        @error_reporting(0);

        $attachment_id = sanitize_text_field($request->get_param('attachment_id'));

        $attachment_id = isset($attachment_id) ? sanitize_text_field($attachment_id) : '';

        $compression = $this->compression;

        if (empty($attachment_id)) {
            wp_send_json_error(
                array(
                    'error'         => 'missing_id',
                    'error_message' => __('Attachment ID required', 'wpfiles'),
                    'subscription_failed'  => (int) $compression->subscription_failed(),
                )
            );
        }

        if (!Wp_Files_Subscription::is_pro()) {
            if(!Wp_Files_Compression::checkBulkLimit()) {
                wp_send_json_error(
                    array(
                        'message'    => sprintf(
                            __('Upgrade to Pro for bulk compression with no limit. Free users can compress %1$d attachments per click', 'wpfiles'),
                            Wp_Files_Compression::$maximumFreeBulk
                        ),
                        'error'    => 'limit_exceeded',
                        'continue' => false,
                    )
                );
            }
        }

        if ('true' === get_post_meta($attachment_id, WP_FILES_PREFIX . 'ignore-bulk', true)) {
            wp_send_json_error(
                array(
                    'error' => __('Ignored from bulk-compression', 'wpfiles')
                )
            );
        
        }

        $attachment_id = (int) $attachment_id;

        $original_meta = wp_get_attachment_metadata($attachment_id, true);

        if (!isset($original_meta['file'])) {
            wp_send_json_error(
                array(
                    'error'         => 'no_file_meta',
                    'error_message' => __('File data not found in attachment meta', 'wpfiles'),
                    'file_name'     => sprintf(
                        '(Attachment ID: %d)',
                        (int) $attachment_id
                    ),
                )
            );
        }

        $file_name = explode('/', $original_meta['file']);

        if (is_array($file_name)) {
            $file_name = array_pop($file_name);
        } else {
            $file_name = $original_meta['file'];
        }

        if (!apply_filters('wp_files_image', true, $attachment_id)) {
            wp_send_json_error(
                array(
                    'error'         => 'skipped',
                    'error_message' => __('Skipped with wp_files_image filter', 'wpfiles'),
                    'subscription_failed'  => (int) $compression->subscription_failed(),
                    'file_name'     => Wp_Files_Helper::getMediaAttachmentLink($attachment_id, $file_name),
                    'thumbnail'     => wp_get_attachment_image($attachment_id),
                )
            );
        }

        $attachment_file_path = get_attached_file($attachment_id);

        Wp_Files_Helper::checkAnimatedStatus($attachment_file_path, $attachment_id);

        $this->compression->createBackup($attachment_file_path, $attachment_id);

        if (!get_transient('compression-in-progress-' . $attachment_id)) {

            set_transient( 'compression-in-progress-' . $attachment_id, 1, HOUR_IN_SECONDS );

            if (apply_filters('wp_files_resize_media_image', true, $attachment_id)) {

                $updated_meta  = $compression->resizeImage($attachment_id, $original_meta);

                $original_meta = !empty($updated_meta) ? $updated_meta : $original_meta;
            }

            

            $compression_response = $compression->resizeFromMetaData($original_meta, $attachment_id);

            wp_update_attachment_metadata($attachment_id, $original_meta);
        }

        delete_transient('compression-in-progress-' . $attachment_id);

        $compressed_data         = get_post_meta($attachment_id, Wp_Files_Compression::$compressedMetaKey, true);

        $resized_savings     = get_post_meta($attachment_id, WP_FILES_PREFIX . 'resize_savings', true);

        $png_to_jpg_savings = Wp_Files_Helper::fetchPngTojpgConversionSavings($attachment_id);

        $stats_array = array(
            'is_lossy'           => $compressed_data && !empty($compressed_data['stats']) ? $compressed_data['stats']['lossy'] : false,
            'count'              => $compressed_data && !empty($compressed_data['sizes']) ? count($compressed_data['sizes']) : 0,
            'size_before'        => $compressed_data && !empty($compressed_data['stats']) ? $compressed_data['stats']['size_before'] : 0,
            'size_after'         => $compressed_data && !empty($compressed_data['stats']) ? $compressed_data['stats']['size_after'] : 0,
            'savings_resize'     => $resized_savings > 0 ? $resized_savings : 0,
            'savings_conversion' => $png_to_jpg_savings && $png_to_jpg_savings['bytes'] > 0 ? $png_to_jpg_savings : 0,
        );

        if (isset($compression_response) && is_wp_error($compression_response)) {

            $error_message = $compression_response->get_error_message();

            if (strpos($error_message, 'timed out')) {

                $error         = 'timeout';
                $error_message = esc_html__("Server timeout error. You can increase the timeout to make sure Compression has enough time to process larger files. `define('WP_FILES_TIMEOUT', 300);`", 'wpfiles');
            }

            $error = isset($error) ? $error : 'other';

            if (!empty($error_message)) {
                $error_message = Wp_Files_Helper::filterTheError($error_message, $attachment_id);
            }

            wp_send_json_error(
                array(
                    'stats'         => $stats_array,
                    'error'         => $error,
                    'error_message' => $error_message,
                    'subscription_failed'  => (int) $compression->subscription_failed(),
                    'error_class'   => isset($error_class) ? $error_class : '',
                    'file_name'     => Wp_Files_Helper::getMediaAttachmentLink($attachment_id, $file_name),
                )
            );
        }

        if (!empty($_REQUEST['is_bulk_recompress']) && 'false' !== $_REQUEST['is_bulk_recompress'] && $_REQUEST['is_bulk_recompress']) {
            $compression->updateRecompressionList($attachment_id);
        } else {
            Wp_Files_Stats::addToCompressionList($attachment_id);
        }

        do_action('image_compressed', $attachment_id, $stats_array);

        Wp_Files_Compression::updateCompressionCount();

        wp_send_json_success(
            array(
                'stats'        => $stats_array,
                'subscription_failed' => (int) $compression->subscription_failed(),
            )
        );
    }

    /**
     * Scan all images
     * @since 1.0.0
     * @return mixed    
     */
    public function scanImages()
    {
        $recompress_list = array();

        $type = isset($_REQUEST['type']) ? sanitize_text_field(wp_unslash($_REQUEST['type'])) : '';

        if (0 === count($this->stats->getMediaAttachments())) {
            wp_send_json_success(
                array(
                    'notice'      => esc_html__('We have not found any attachments in your media library yet so there is no compression to be happened', 'wpfiles'),
                    'super_compress' => $this->settings['super_compression'],
                )
            );
        }

        if (empty($this->stats->remainingCompressionCount)) {
            $this->stats->setupGlobalStats(true);
        }

        $key = 'wpfiles-recompress-list';

        $remainingCompressionCount = $this->stats->remainingCompressionCount;

        if (0 === (int) $remainingCompressionCount 
            
            && !$this->settings['strip_exif']
        ) {

            delete_option($key);

            wp_send_json_success(
                array(
                    'notice' => esc_html__('Nice! All images are optimized as per your current settings', 'wpfiles'),
                )
            );

        }

        $content = '';

        $attachments = !empty($this->stats->compressed_attachments) ? $this->stats->compressed_attachments : $this->stats->getCompressedAttachments();

        $stats_array = array(
            'size_before'        => 0,
            'savings_resize'     => 0,
            'size_after'         => 0,
            'savings_conversion' => 0,
        );

        $count_image = $super_compressed_count = $compressed_count = $resized_count = 0;

        if (!empty($attachments) && is_array($attachments)) {

            $this->resize->initialize();

            foreach ($attachments as $attachment_k => $attachment) {

                if (!empty($this->stats->recompress_ids) && in_array($attachment, $this->stats->recompress_ids) || (!empty($this->stats->skippedAttachments) && in_array($attachment, $this->stats->skippedAttachments))) {
                    continue;
                }

                $should_recompress = false;

                $compressed_data = get_post_meta($attachment, Wp_Files_Compression::$compressedMetaKey, true);

                if (is_array($compressed_data) && !empty($compressed_data['stats'])) {

                    

                    $strip_exif = $this->settings['strip_exif'] && isset($compressed_data['stats']['keep_exif']) && $compressed_data['stats']['keep_exif'];

                    if (
                        
                        $strip_exif
                        ) {
                        $should_recompress = true;
                    }

                    $image_sizes = $this->settings['image_sizes'] == "custom" && count((array)$this->settings['image_manual_sizes']) > 0 ? $this->settings['image_manual_sizes'] : [];

                    if (empty($image_sizes)) {
                        $image_sizes = array_keys(Wp_Files_Helper::getImageDimensions());
                    }

                    if (is_array($image_sizes) && count($image_sizes) > count($compressed_data['sizes']) && !has_filter('wp_image_editors', 'photon_subsizes_override_image_editors')) {

                        $attachment_data = wp_get_attachment_metadata($attachment);

                        if (isset($attachment_data['sizes']) && count($attachment_data['sizes']) !== count($compressed_data['sizes'])) {

                            foreach ($image_sizes as $image_size) {

                                if (isset($compressed_data['sizes'][$image_size])) {
                                    continue;
                                }

                                if (isset($attachment_data['sizes'][$image_size])) {
                                    $should_recompress = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$should_recompress) {
                        $should_recompress = $this->resize->shouldResize($attachment);
                    }

                    
                    
                    

                    if ($should_recompress) {
                        $recompress_list[] = $attachment;
                    }

                    $resized_savings     = get_post_meta($attachment, WP_FILES_PREFIX . 'resize_savings', true);

                    $png_to_jpg_savings = Wp_Files_Helper::fetchPngTojpgConversionSavings($attachment);

                    $compressed_count++;

                    if (!empty($resized_savings)) {
                        $resized_count++;
                    }

                    $count_image += (!empty($compressed_data['sizes']) && is_array($compressed_data['sizes'])) ? count($compressed_data['sizes']) : 0;

                    $super_compressed_count += ($compressed_data['stats']['lossy']) ? 1 : 0;

                    $stats_array['size_after'] += !empty($compressed_data['stats']) ? $compressed_data['stats']['size_after'] : 0;
                    
                    $stats_array['size_after'] += !empty($png_to_jpg_savings['size_after']) ? $png_to_jpg_savings['size_after'] : 0;
                    
                    $stats_array['size_after'] += !empty($resized_savings['size_after']) ? $resized_savings['size_after'] : 0;
                    
                    $stats_array['size_before'] += !empty($compressed_data['stats']) ? $compressed_data['stats']['size_before'] : 0;
                   
                    $stats_array['size_before'] += !empty($png_to_jpg_savings['size_before']) ? $png_to_jpg_savings['size_before'] : 0;

                    $stats_array['size_before'] += !empty($resized_savings['size_before']) ? $resized_savings['size_before'] : 0;

                    $stats_array['savings_conversion'] += !empty($png_to_jpg_savings) && isset($png_to_jpg_savings['bytes']) ? $png_to_jpg_savings['bytes'] : 0;
                    
                    $stats_array['savings_resize']     += !empty($resized_savings) && isset($resized_savings['bytes']) ? $resized_savings['bytes'] : 0;
                    
                }
            } 

            update_option($key, $recompress_list, false);
        }

        if (empty($recompress_list)) {
            delete_option($key);
        }

        $uncompressed_ids = array();

        $uncompressed_count = $this->stats->remainingCompressionCount;

        if (0 < $uncompressed_count) {
            $uncompressed_ids = array_values($this->stats->getUncompressedAttachments());
        }

        $recompress_count = count($recompress_list);

        $count         = $uncompressed_count + $recompress_count;

        $return_ui = isset($_REQUEST['get_ui']) && 'true' == sanitize_text_field($_REQUEST['get_ui']) ? true : false;

        if ($return_ui) {
            if ($count) {
                ob_start();
                $this->stats-> displayPendingCompressionMessage($count, $recompress_count, $uncompressed_count);
                $content = ob_get_clean();
            }
        }

        $dir_compression_stats = get_option('dir_compression_stats');

        if (!empty($dir_compression_stats) && is_array($dir_compression_stats)) {

            if (!empty($dir_compression_stats['dir_compression']) && !empty($dir_compression_stats['optimized'])) {
                $dir_compression_stats = $dir_compression_stats['dir_compression'];
                $count_image    += $dir_compression_stats['optimized'];
            }

            if (!empty($dir_compression_stats['image_size']) && !empty($dir_compression_stats['orig_size'])) {
                $stats_array['size_before'] += $dir_compression_stats['orig_size'];
                $stats_array['size_after']  += $dir_compression_stats['image_size'];
            }
        }

        $return = array(
            'recompress_ids'        => $recompress_list,
            'uncompressed'          => $uncompressed_ids,
            'count_image'        => $count_image,
            'count_supercompressed' => $super_compressed_count,
            'count_compressed'      => $compressed_count,
            'count_resize'       => $resized_count,
            'size_before'        => !empty($stats_array['size_before']) ? $stats_array['size_before'] : 0,
            'size_after'         => !empty($stats_array['size_after']) ? $stats_array['size_after'] : 0,
            'savings_resize'     => !empty($stats_array['savings_resize']) ? $stats_array['savings_resize'] : 0,
            'savings_conversion' => !empty($stats_array['savings_conversion']) ? $stats_array['savings_conversion'] : 0,
        );

        if (!empty($content)) {
            $return['content'] = $content;
        }

        if (!empty($count) && $count) {
            $return['count'] = $count;
        }

        if (!empty($count)) {
            $return['noticeType'] = 'warning';
            $return['notice']     = sprintf(
                esc_html__('Image checking complete, you have %1$d attachments that need compression. %2$sBulk compress now!%3$s', 'wpfiles'),
                $count,
                '<a href="#" class="wpfiles-trigger-bulk" data-type="' . $type . '">',
                '</a>'
            );
        }

        $return['super_compress'] = Wp_Files_Subscription::is_pro() && $this->settings['super_compression'];

        wp_send_json_success($return);
    }

    /**
     * Compress one image
     * @since 1.0.0
     * @return mixed 
     */
    public function compressOne()
    {
        @error_reporting(0);

        $attachment_id = (int) sanitize_text_field($_POST['attachment_id']);

        if (!current_user_can('upload_files')) {
            wp_send_json_error(
                array(
                    'error' => __("You don not have permission to work with uploaded files", 'wpfiles'),
                )
            );
        }

        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(
                array(
                    'error' => __('Attachment ID is required', 'wpfiles'),
                )
            );
        }

        if ('true' === get_post_meta($attachment_id, WP_FILES_PREFIX . 'ignore-bulk', true)) {
            wp_send_json_error(
                array(
                    'error' => __('Ignored from bulk-compression', 'wpfiles')
                )
            );

        }

        if (!apply_filters('wp_files_image', true, $attachment_id)) {

            $error = Wp_Files_Helper::filterTheError(esc_html__('Attachment Skipped - Check `wp_files_image` filter', 'wpfiles'), $attachment_id);

            wp_send_json_error(
                array(
                    'error'    => $error,
                    'subscription_failed' => (int) $this->compression->subscription_failed(),
                )
            );
        }

        $response = $this->compression->compressOne($attachment_id);

        if (isset($response['error']) || (isset($response['success']) && !$response['success'])) {
            wp_send_json_error($response);
        }

        wp_send_json_success($response);
        
    }

    /**
     * Recompress the image
     * @since 1.0.0
     * @return mixed 
     */
    public function recompressImage()
    {
        if (empty(sanitize_text_field($_POST['attachment_id']))) {
            wp_send_json_error(
                array(
                    'error' => esc_html__('Attachment ID is required', 'wpfiles'),
                )
            );
        }

        $image_id = (int) sanitize_text_field($_POST['attachment_id']);

        $response = $this->compression->compressOne($image_id);

        if (isset($response['error']) || (isset($response['success']) && !$response['success'])) {
            wp_send_json_error($response);
        }

        wp_send_json_success($response);
    }

    /**
     * Restore the image
     * @since 1.0.0
     * @param object $request     
     * @param boolean $resp 
     * @return mixed
     */
    public function restoreImage($request, $resp = true)
    {
        $attachment_id = sanitize_text_field($request->get_param('attachment_id'));

        $response = $this->compression->restoreImage($attachment_id, $resp);

        if (isset($response['error']) || (isset($response['success']) && !$response['success'])) {
            wp_send_json_error($response);
        }

        wp_send_json_success($response);
    }

    /**
     * Ignore image from bulk Compression
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function ignoreBulkImage($request)
    {
        $id = sanitize_text_field($request->get_param('id'));

        if (!isset($id)) {
            wp_send_json_error();
        }

        $id = absint($id);

        update_post_meta($id, 'wpfiles-ignore-bulk', 'true');

        wp_send_json_success(
            array(
                'links' => $this->compression->getOptimizationLinks($id),
            )
        );
    }

    /**
     * Delete the recompress list for the Media Library
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function deleteRecompressList($request)
    {
        $stats_array = array();

        $key = 'wpfiles-recompress-list';

        $recompress_list = get_option($key);

        if (!empty($recompress_list) && is_array($recompress_list)) {
            $stats_array = $this->stats->getStatsForAttachments($recompress_list);
        }

        delete_option($key);

        wp_send_json_success(array('stats' => $stats_array));
    }

    /**
     * Return Latest stats.
     * @since 1.0.0
     * @return mixed 
     */
    public function getStats()
    {
        if (empty($this->stats->stats)) {
            $this->stats->setupGlobalStats(true);
        }

        $stats_array = array(
            'count_images'       => !empty($this->stats->stats) && isset($this->stats->stats['total_images']) ? $this->stats->stats['total_images'] : 0,
            'count_resize'       => !empty($this->stats->stats) && isset($this->stats->stats['resize_count']) ? $this->stats->stats['resize_count'] : 0,
            'count_compressed'      => $this->stats->compressed_count,
            'count_supercompressed' => $this->stats->super_compressed,
            'count_total'        => $this->stats->count_of_attachments_for_compression,
            'savings_bytes'      => !empty($this->stats->stats) && isset($this->stats->stats['bytes']) ? $this->stats->stats['bytes'] : 0,
            'savings_conversion' => !empty($this->stats->stats) && isset($this->stats->stats['conversion_savings']) ? $this->stats->stats['conversion_savings'] : 0,
            'savings_resize'     => !empty($this->stats->stats) && isset($this->stats->stats['resize_savings']) ? $this->stats->stats['resize_savings'] : 0,
            'size_before'        => !empty($this->stats->stats) && isset($this->stats->stats['size_before']) ? $this->stats->stats['size_before'] : 0,
            'size_after'         => !empty($this->stats->stats) && isset($this->stats->stats['size_after']) ? $this->stats->stats['size_after'] : 0,
        );

        wp_send_json_success($stats_array);
    }

    /**
     * Skip from remove list
     * @since 1.0.0
     * @param object $request     
     * @return mixed 
     */
    public function removeFromSkipList($request)
    {
        $id = sanitize_text_field($request->get_param('id'));

        if (!isset($id)) {
            wp_send_json_error();
        }

        delete_post_meta(absint($id), 'wpfiles-ignore-bulk');

        wp_send_json_success(
            array(
                'links' => $this->compression->getOptimizationLinks(absint($id)),
            )
        );
    }

    

    /**
     * Directory listing
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function loadDirectories($request)
    {
        $this->directory->loadDirectories($request);

        wp_send_json_success();
    }

    /**
     * Scan the given directory path for the list of images.
     * @since 1.0.0
     * @param object $request    
     * @return mixed
     */
    public function imageList($request)
    {
        $this->directory->imageList($request);

        wp_send_json_success();
    }

    /**
     * Start compression
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function initScan($request)
    {
        $this->directory->initScan($request);

        wp_send_json_success();
    }

    /**
     * Verify directory image
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function verifyDirectoryImage($request)
    {
        $this->directory->verifyDirectoryImage($request);

        wp_send_json_success();
    }

    /**
     * Directory Compression: Finish compression.
     * @since 1.0.0
     * @param object $request   
     * @return mixed
     */
    public function directoryCompressionFinish($request)
    {
        $this->directory->directoryCompressionFinish($request);

        wp_send_json_success();
    }

    /**
     * Directory Compression: Cancel compression.
     * @since 1.0.0
     * @param object $request  
     * @return mixed
     */
    public function directoryCompressionCancel($request)
    {
        $this->directory->directoryCompressionCancel($request);

        wp_send_json_success();
    }

    /**
     * Returns Directory Compression stats and Cumulative stats
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function getDirCompressionStats($request)
    {
        $result = array();

        $stats_array = $this->directory->totalStats();

        $result['dir_compression'] = $stats_array;

        update_option('dir_compression_stats', $result, false);

        wp_send_json_success($result);
    }

    /**
     * Returns Directory stats and media library stats and Cumulative stats
     * @since 1.0.0
     * @param array $json     
     * @return mixed
     */
    public function getAllStats($json = true)
    {
        $result = array();

        $this->stats->setupGlobalStats(true);

        $result['dir_compression'] = $this->stats->dir_compression_stats;

        $remaining = $this->stats->getTotalImagesToCompress();

        $uncompressed_count = $this->stats->count_of_attachments_for_compression - $this->stats->compressed_count - $this->stats->skippedCount;

        $recompress_count = count(get_option('wpfiles-recompress-list', array()));

        $recompress_ids = get_option('wpfiles-recompress-list', array());

        $uncompressed_attachments = !empty($_REQUEST['ids']) ? Wp_Files_Helper::sanitizeArray(array_map('intval', explode(',', $_REQUEST['ids']))) : array();

        if (empty($uncompressed_attachments)) {
            $uncompressed_attachments = $this->stats->remainingCompressionCount > 0 ? $this->stats->getUncompressedAttachments() : array();
            $uncompressed_attachments = !empty($uncompressed_attachments) && is_array($uncompressed_attachments) ? array_values($uncompressed_attachments) : $uncompressed_attachments;
        }

        $compressed_attachments = !empty($this->stats->compressed_attachments) ? $this->stats->compressed_attachments : $this->stats->getCompressedAttachments();

        /***********watermark stats**************/
        $result['dir_watermark'] = $this->stats->dir_watermark_stats;

        $unwatermarked_attachments = !empty($_REQUEST['ids']) ? Wp_Files_Helper::sanitizeArray(array_map('intval', explode(',', $_REQUEST['ids']))) : array();

        if (empty($unwatermarked_attachments)) {
            $unwatermarked_attachments = $this->stats->remainingWatermarkCount > 0 ? $this->stats->getUnwatermarkedAttachments() : array();
            $unwatermarked_attachments = !empty($unwatermarked_attachments) && is_array($unwatermarked_attachments) ? array_values($unwatermarked_attachments) : $unwatermarked_attachments;
        }

        $remaining_watermark = $this->stats->getTotalImagesToWatermark();

        $rewatermark_count = count(get_option('wpfiles-rewatermark-list', array()));

        $unwatermarkedcount = $this->stats->count_of_attachments_for_watermark - $this->stats->watermarked_count;

        $watermarked_attachments = !empty($this->stats->watermarked_attachments) ? $this->stats->watermarked_attachments : $this->stats->getWatermarkedAttachments();

        $media_library_stats = array(
            'compressed_attachments' => $compressed_attachments,
            'count_all_compressed_images'       => !empty($this->stats->stats) && isset($this->stats->stats['total_images']) ? $this->stats->stats['total_images'] : 0,
            'count_resize'       => !empty($this->stats->stats) && isset($this->stats->stats['resize_count']) ? $this->stats->stats['resize_count'] : 0,
            'count_compressed'      => $this->stats->compressed_count,
            'count_compressed_percent'      => ($this->stats->count_of_attachments_for_compression > 0 ? round(($this->stats->compressed_count / $this->stats->count_of_attachments_for_compression) * 100, 0) : 0),
            'count_need_compression'      => abs($this->stats->count_of_attachments_for_compression - $this->stats->compressed_count),
            'count_supercompressed' => $this->stats->super_compressed,
            'count_total'        => $this->stats->count_of_attachments_for_compression,
            'savings_bytes'      => !empty($this->stats->stats) && isset($this->stats->stats['bytes']) ? $this->stats->stats['bytes'] : 0,
            'savings_conversion' => !empty($this->stats->stats) && isset($this->stats->stats['conversion_savings']) ? $this->stats->stats['conversion_savings'] : 0,
            'savings_resize'     => !empty($this->stats->stats) && isset($this->stats->stats['resize_savings']) ? $this->stats->stats['resize_savings'] : 0,
            'size_before'        => !empty($this->stats->stats) && isset($this->stats->stats['size_before']) ? $this->stats->stats['size_before'] : 0,
            'size_after'         => !empty($this->stats->stats) && isset($this->stats->stats['size_after']) ? $this->stats->stats['size_after'] : 0,
            'remaining' => $this->stats-> displayPendingCompressionMessage($remaining, $recompress_count, $uncompressed_count),
            'uncompressed_attachments' => $uncompressed_attachments,
            'recompress_ids' => $recompress_ids,
            'unwatermarked_attachments' => $unwatermarked_attachments,
            'rewatermark_ids' => $this->compression->rewatermark_ids,
            'watermarked_attachments' => $watermarked_attachments,
            'remaining_watermark' => $this->stats->displayPendingWatermarkMessage($remaining_watermark, $rewatermark_count, $unwatermarkedcount),
        );

        $result['media_library_stats'] = $media_library_stats;

        $result['combined_stats'] = $this->directory->combineStats($this->stats);

        update_option('dir_compression_stats', $result, false);

        return $result;
    }

    /**
     * Add watermark to the respected attachment
     * @since 1.0.0
     * @param int $attachment_id 
     * @return mixed    
     */
    public function addWatermark($attachment_id)
    {
        @error_reporting(0);

        if (!current_user_can('upload_files')) {
            wp_send_json_error(
                array(
                    'error' => __("You don not have permission to add watermark", 'wpfiles'),
                )
            );
        }

        if (!isset($attachment_id)) {
            wp_send_json_error(
                array(
                    'error' => __('Attachment ID is required', 'wpfiles'),
                )
            );
        }

        $compressed_attachments = $this->stats->getCompressedAttachments();

        $attachment_id = (int) $attachment_id;

        $this->compression->restoreImage($attachment_id, true);

        $response = $this->compression->addWatermark($attachment_id);

        //Account must be connected when do compression
        if (in_array($attachment_id, $compressed_attachments) && $this->settings['api_key']) {
            $response = $this->compression->compressOne($attachment_id);

            if (isset($response['error']) || (isset($response['success']) && !$response['success'])) {
                wp_send_json_error($response);
            }

            wp_send_json_success($response);
        }

        if (isset($response['error']) || isset($response['success'])) {
            wp_send_json_error($response);
        }

        wp_send_json_success($response);
    }

    /**
     * Scan all watermark images
     * @since 1.0.0
     * @return void    
     */
    public function scanWatermarkImages()
    {
        $rewatermark_list = array();

        $type = isset($_REQUEST['type']) ? sanitize_text_field(wp_unslash($_REQUEST['type'])) : '';

        if (0 === count($this->stats->getMediaAttachments())) {
            wp_send_json_success(
                array(
                    'notice'      => esc_html__('We have not found any attachments in your media library yet so there is no watermarking to be done!', 'wpfiles'),
                )
            );
        }

        if (empty($this->stats->remainingWatermarkCount)) {
            $this->stats->setupGlobalStats(true);
        }

        $key = 'wpfiles-rewatermark-list';

        $remainingWatermarkCount = $this->stats->remainingWatermarkCount;

        if (
            0 === (int) $remainingWatermarkCount
        ) {
            delete_option($key);

            wp_send_json_success(
                array(
                    'notice' => esc_html__('Nice! All images are watermarked as per your current settings', 'wpfiles'),
                )
            );
        }

        $content = '';

        $attachments = !empty($this->stats->watermarked_attachments) ? $this->stats->watermarked_attachments : $this->stats->getWatermarkedAttachments();

        $count_image = $watermarked_count = 0;

        if (!empty($attachments) && is_array($attachments)) {

            foreach ($attachments as $attachment_k => $attachment) {

                if (!empty($this->stats->rewatermark_ids) && in_array($attachment, $this->stats->rewatermark_ids)) {
                    continue;
                }

                $should_rewatermark = false;

                $watermarked_data = get_post_meta($attachment, Wp_Files_Compression::$watermarkMetaKey, true);

                if (is_array($watermarked_data) && !empty($watermarked_data['sizes'])) {

                    $image_sizes = $this->settings['watermark_image_sizes'] == "custom" && count((array)$this->settings['watermark_image_sizes_manual']) > 0 ? $this->settings['watermark_image_sizes_manual'] : [];

                    if (empty($image_sizes)) {
                        $image_sizes = array_keys(Wp_Files_Helper::getImageDimensions());
                    }

                    if (is_array($image_sizes) && count($image_sizes) > count($watermarked_data['sizes']) && !has_filter('wp_image_editors', 'photon_subsizes_override_image_editors')) {

                        $attachment_data = wp_get_attachment_metadata($attachment);

                        if (isset($attachment_data['sizes']) && count($attachment_data['sizes']) !== count($watermarked_data['sizes'])) {

                            foreach ($image_sizes as $image_size) {

                                if (isset($watermarked_data['sizes'][$image_size])) {
                                    continue;
                                }

                                if (isset($attachment_data['sizes'][$image_size])) {
                                    $should_rewatermark = true;
                                    break;
                                }
                            }
                        }
                    }

                    if(isset($watermarked_data['watermark_type']) && $watermarked_data['watermark_type'] != $this->settings['watermark_type']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_attachment_url']) && $watermarked_data['watermark_attachment_url'] != $this->settings['watermark_attachment_url']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_text']) && $watermarked_data['watermark_text'] != $this->settings['watermark_text']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_font']) && $watermarked_data['watermark_font'] != $this->settings['watermark_font']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_size']) && $watermarked_data['watermark_size'] != $this->settings['watermark_size']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_color']) && $watermarked_data['watermark_color'] != $this->settings['watermark_color']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_opacity']) && $watermarked_data['watermark_opacity'] != $this->settings['watermark_opacity']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_position']) && $watermarked_data['watermark_position'] != $this->settings['watermark_position']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_x_axis']) && $watermarked_data['watermark_x_axis'] != $this->settings['watermark_x_axis']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_y_axis']) && $watermarked_data['watermark_y_axis'] != $this->settings['watermark_y_axis']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_scale_value']) && $watermarked_data['watermark_scale_value'] != $this->settings['watermark_scale_value']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_image_sizes']) && $watermarked_data['watermark_image_sizes'] != $this->settings['watermark_image_sizes']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_variant']) && $watermarked_data['watermark_variant'] != $this->settings['watermark_variant']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_fill']) && $watermarked_data['watermark_fill'] != $this->settings['watermark_fill']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_rounded_corner']) && $watermarked_data['watermark_rounded_corner'] != $this->settings['watermark_rounded_corner']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_text_padding']) && $watermarked_data['watermark_text_padding'] != $this->settings['watermark_text_padding']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_bg_color']) && $watermarked_data['watermark_bg_color'] != $this->settings['watermark_bg_color']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_stroke_color']) && $watermarked_data['watermark_stroke_color'] != $this->settings['watermark_stroke_color']) {
                        $should_rewatermark = true;
                    }

                    if(isset($watermarked_data['watermark_stroke_width']) && $watermarked_data['watermark_stroke_width'] != $this->settings['watermark_stroke_width']) {
                        $should_rewatermark = true;
                    }
                    
                    if ($should_rewatermark) {
                        $rewatermark_list[] = $attachment;
                    }
                }
            } 

            update_option($key, $rewatermark_list, false);
        }

        if (empty($rewatermark_list)) {
            delete_option($key);
        }

        $unwatermarked_ids = array();

        $unwatermarked_count = $this->stats->remainingWatermarkCount;

        if (0 < $unwatermarked_count) {
            $unwatermarked_ids = array_values($this->stats->getUnwatermarkedAttachments());
        }

        $rewatermark_count = count($rewatermark_list);

        $count         = $unwatermarked_count + $rewatermark_count;

        $return_ui = isset($_REQUEST['get_ui']) && 'true' == sanitize_text_field($_REQUEST['get_ui']) ? true : false;

        if ($return_ui) {
            if ($count) {
                ob_start();
                $this->stats->displayPendingWatermarkMessage($count, $rewatermark_count, $unwatermarked_count);
                $content = ob_get_clean();
            }
        }

        $return = array(
            'rewatermark_ids'        => $rewatermark_list,
            'unwatermarked'          => $unwatermarked_ids,
            'count_image'        => $count_image,
            'count_watermarked'      => $watermarked_count,
        );

        if (!empty($content)) {
            $return['content'] = $content;
        }

        if (!empty($count) && $count) {
            $return['count'] = $count;
        }

        if (!empty($count)) {
            $return['noticeType'] = 'warning';
            $return['notice']     = sprintf(
                esc_html__('Image checking complete, you have %1$d attachments that need watermarking. %2$sBulk watermark now!%3$s', 'wpfiles'),
                $count,
                '<a href="#" class="wpfiles-trigger-bulk" data-type="' . $type . '">',
                '</a>'
            );
        }

        wp_send_json_success($return);
    }

    /**
     * Directory tart watermark
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function directoryWatermarkStart($request)
    {
        $this->directory->directoryWatermarkStart($request);

        wp_send_json_success();
    }

    /**
     * Watermark step
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function directoryWatermarkCheckStep($request)
    {
        $this->directory->directoryWatermarkCheckStep($request);

        wp_send_json_success();
    }

    /**
     * Delete the rewatermark list for the Media Library
     * @since 1.0.0
     * @param object $request     
     * @return mixed
     */
    public function deleteRewatermarkList($request)
    {
        $stats_array = array();

        $key = 'wpfiles-rewatermark-list';

        $rewatermark_list = get_option($key);

        if (!empty($rewatermark_list) && is_array($rewatermark_list)) {
            $stats_array = $this->stats->getStatsForAttachments($rewatermark_list);
        }

        delete_option($key);

        wp_send_json_success(array('stats' => $stats_array));
    }
}
