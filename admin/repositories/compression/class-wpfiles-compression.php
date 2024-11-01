<?php
/**
 * Wp_Files_Compression class that contain every thing about compression & watermark
 */
class Wp_Files_Compression
{
    /**
     * Key to save compression result.
     * @since 1.0.0
     * @var string $compressedMetaKey
     */
    public static $compressedMetaKey = 'wpfiles-pro-compress-data';

    /**
     * Key to save watermark result.
     * @since 1.0.0
     * @var string $watermarkMetaKey
     */
    public static $watermarkMetaKey = 'wpfiles-watermark-status';

    /**
     * Restore at.
     * @since 1.0.3
     * @var string $restoreAt
     */
    public static $restoreAt = 'wpfiles-restore-at';

    /**
     * Dimensions for image array.
     * @since 1.0.0
     * @var array $image_sizes
     */
    public $image_sizes = array();

    /**
     * Attachment type, being Compressed currently.
     * @since 1.0.0
     * @var string $mediaType For now only :wp
     */
    public $mediaType = 'wp';

    /**
     * Attachment ID for the image being Compressed currently.
     * @since 1.0.0
     * @var int $attachment_id
     */
    public $attachment_id;

    /**
     * Images per bulk request.
     * This is enforced at api level too.
     * @since 1.0.0
     * @var int $maximumFreeBulk
     */
    public static $maximumFreeBulk = 50;

    /**
     * settings
     * @since 1.0.0
     * @var object $settings
     */
    private $settings;

    

    /**
     * Resize
     * @since 1.0.0
     * @var object $resize
     */
    private $resize;

    

    /**
     * Stats
     * @since 1.0.0
     * @var object $stats
     */
    private $stats;

    /**
     * Key for storing file path for image backup
     * @since 1.0.0
     * @var string
     */
    private $backupKey = 'compress-full';

    /**
     * Allowed mime types of image.
     * @since 1.0.0
     * @var array $mimeTypes
     */
    public static $mimeTypes = array(
        'image/jpg',
        'image/jpeg',
        'image/x-citrix-jpeg',
        'image/gif',
        'image/png',
        'image/x-png',
    );

    /**
     * External pages where compression needs to be loaded.
     * @since 1.0.0
     * @var array $pages
     */
    public static $externalPages = array(
        'post',
        'post-new',
        'nggallery-manage-images',
        'gallery_page_nggallery-manage-gallery',
        'page',
        'upload',
        'edit-page',
    );

    /**
     * Image attachment ids that needs to be recompressed.
     * @since 1.0.0
     * @var array $recompress_ids
     */
    public $recompress_ids = array();

    /**
     * Image attachment ids that needs to be rewatermark.
     * @since 1.0.0
     * @var array $rewatermark_ids
     */
    public $rewatermark_ids = array();

    /**
     * Image extensions
     * @since 1.0.0
     * @var array $extensions
     */
    private $extensions;

    /**
     * Image extension
     * @since 1.0.0
     * @var array $extension
     */
    private $extension = false;

    use Wp_Files_Compression_Hooks;

    /**
     * Constructor
     * @since 1.0.0
     * @var object $settings
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initilazation class required things
     * @since    1.0.0
     * @access   public
     */
    public function init($settings)
    {
        

        $this->resize = new Wp_Files_Resize($settings);

        
        
        $this->stats = new Wp_Files_Stats($settings);

        $this->hooks();

        // Recompress list
        $recompress_ids = get_option('wpfiles-recompress-list', array());

        if ($recompress_ids) {
            $this->recompress_ids = $recompress_ids;
        }

        // Rewatermark list
        $rewatermark_ids = get_option('wpfiles-rewatermark-list', array());

        if ($rewatermark_ids) {
            $this->rewatermark_ids = $rewatermark_ids;
        }

        //Available extensions
        $this->checkExtensions();
    }

    /**
     * Subscription failed
     * @since 1.0.0
     * @return bool
    */
    public function subscription_failed()
    {
		if ( ! Wp_Files_Subscription::is_pro() ) {
			return false;
		}

        return true;
    }

    /**
     * First check bulk count, for allow further compressing or not
     * @since 1.0.0
     * @param bool $reset 
     * @param string $key   
     * @return bool
     */
    public static function checkBulkLimit($reset = false, $key = 'bulk_limit')
    {
        $transient = WP_FILES_PREFIX . $key;

        if ($reset) {
            set_transient($transient, 0, 60);
            return;
        }

        $bulkCount = (int) get_transient($transient);

        if (!$bulkCount || $bulkCount < self::$maximumFreeBulk) {
            $continue = true;
        } elseif ($bulkCount === self::$maximumFreeBulk) {
            $continue = false;
            $reset    = true;
        } else {
            $continue = false;
        }

        if ($reset) {
            set_transient($transient, 0, 60);
        }

        return $continue;
    }

    /**
     * Backup of file
     * @since 1.0.0
     * @param string $path     
     * @param string $attachment_id 
     */
    public function createBackup($path = '', $attachment_id = '')
    {
        //Create a backup if does not exist

        if (empty($path)) {
            return;
        }

        if (empty($attachment_id)) {
            return;
        }

        if(!$this->settings['compress_original_image']) {

            // WordPress 5.3 support for -scaled images size.
            if (false !== strpos($path, '-scaled.') && function_exists('wp_get_original_image_path')) {
    
                // Scaled type images have already backup, so use it and not to create new one
                $path = wp_get_original_image_path($attachment_id);
    
                $this->addToImageBackupSizes($attachment_id, $path);
    
                return;
            }
    
            

        }

        $backup = $this->getImageBackupPath($path);

        if (empty($backup)) {
            return;
        }
        
        $copied = false;

        // See backup from other plugins, if not exists, then create your own.
        if (!file_exists($backup)) {
            $copied = @copy($path, $backup);
        }

        if ($copied) {
            $this->addToImageBackupSizes($attachment_id, $backup);
        }
    }

    /**
     * Backup path for attachment
     * @since 1.0.0
     * @param string $path
     * @return bool|string
     */
    public function getImageBackupPath($path)
    {
        if (empty($path)) {
            return false;
        }
        $path = pathinfo($path);

        if (empty($path['extension'])) {
            return false;
        }

        return trailingslashit($path['dirname']) . $path['filename'] . '.bak.' . $path['extension'];
    }

    /**
     * Save new backup path for the image
     * @since 1.0.0
     * @param string $id 
     * @param string $backup  
     * @param string $backupKey
     * @return bool|int
    */
    public function addToImageBackupSizes($id = '', $backup = '', $backupKey = '')
    {
        if (empty($id) || empty($backup)) {
            return false;
        }

        $backupSizes = get_post_meta($id, '_wp_attachment_backup_sizes', true);

        if (empty($backupSizes)) {
            $backupSizes = array();
        }

        if (false !== stripos($backup, 'phar://')) {
            return false;
        }

        if (!file_exists($backup)) {
            return false;
        }

        list($width, $height) = getimagesize($backup);

        $backupKey = empty($backupKey) ? $this->backupKey : $backupKey;

        $backupSizes[$backupKey] = array(
            'file'   => wp_basename($backup),
            'width'  => $width,
            'height' => $height,
        );

        $this->addToImagesWithBackupsCacheList($id);

        return update_post_meta($id, '_wp_attachment_backup_sizes', $backupSizes);
    }

    /**
     * Adds to cached list with backup.
     * @since 1.0.0
     * @param integer $id 
     */
    private function addToImagesWithBackupsCacheList($id)
    {
        $images = wp_cache_get('images_with_backups', WP_FILES_CACHE_PREFIX);

        $id = strval($id);

        if (empty($images)) {
            $images = array($id);
        } elseif (!in_array($id, $images, true)) {
            $images[] = $id;
        }

        wp_cache_set('images_with_backups', $images, WP_FILES_CACHE_PREFIX);
    }

    /**
     * Perform the resize operation for the image
     * @since 1.0.0
     * @param int $id
     * @param array $meta
     * @return mixed
     */
    public function resizeImage($id, $meta)
    {
        if (empty($id) || empty($meta)) {
            return $meta;
        }

        // So we need to manually initialize those.
        $this->resize->initialize(true);
        
        return $this->resize->autoResize($id, $meta);
    }

    /**
     * Check to skip a image size or not
     * @since 1.0.0
     * @param string $size  
     * @return bool
     */
    public static function skipImageSize($settings, $size = '')
    {
        if (empty($size)) {
            return false;
        }

        if ("all" === $settings['image_sizes'] || ("custom" === $settings['image_sizes'] && count((array)$settings['image_manual_sizes']) == 0)) {
            return false;
        }

        return is_array($settings['image_manual_sizes']) && !in_array($size, $settings['image_manual_sizes']);
    }

    /**
     * Start optimizing the image
     * @since 1.0.0
     * @param array $meta 
     * @param null|int $id  
     * @return mixed
     */
    public function resizeFromMetaData($meta, $id = null)
    {
        $original   = $this->settings['compress_original_image'];

        $fullCompression = 1 == $original;

        $errors = new WP_Error();

        $stats_array  = array(
            'stats' => array_merge(
                $this->getSizeSignature(),
                array(
                    'lossy'       => -1,
                    'timestamp'   => time(),
                    'keep_exif'   => false,
                )
            ),
            'sizes' => array(),
        );

        if ($id && false === wp_attachment_is_image($id)) {
            return $meta;
        }

        $this->attachment_id = $id;

        $this->mediaType    = 'wp';

        $attachmentFilePath = Wp_Files_Helper::getAttachedFile($id);

        $webp_has_error         = false;
        
        

        $webpFiles = array();

        if (!empty($meta['sizes']) && !has_filter('wp_image_editors', 'photon_subsizes_override_image_editors')) {

            foreach ($meta['sizes'] as $sizeKey => $sizeData) {

                if ('full' !== $sizeKey && $this->skipImageSize($this->settings, $sizeKey)) {
                    continue;
                }

                $attachment_file_path_size = path_join(dirname($attachmentFilePath), $sizeData['file']);

                $ext = Wp_Files_Helper::getFileMimeType($attachment_file_path_size);

                if ($ext) {
                    $valid_mime = array_search(
                        $ext,
                        array(
                            'png' => 'image/png',
                            'jpg' => 'image/jpeg',
                            'gif' => 'image/gif',
                        ),
                        true
                    );

                    if (false === $valid_mime) {
                        continue;
                    }
                }

                if (!apply_filters('wp_files_media_image', true, $sizeKey)) {
                    continue;
                }

                

                $response = $this->startCompression($attachment_file_path_size);

                if (is_wp_error($response)) {
                    
                    return $response;
                }

                if (empty($response['data'])) {
                    continue;
                }

                if ($response['data']->after_size > $response['data']->before_size) {
                    continue;
                }

                $stats_array['sizes'][$sizeKey] = (object) $this->arrayFillPlaceholders($this->getSizeSignature(), (array) $response['data']);

                $stats_array['stats']['lossy']       = $response['data']->lossy;

                $stats_array['stats']['keep_exif']   = !empty($response['data']->keep_exif) ? $response['data']->keep_exif : 0;

            }
        } elseif (!has_filter('photon_subsizes_override_image_editors', 'wp_image_editors')) {
            $fullCompression = true;
        }

        $compression_full_image = apply_filters('wp_files_media_image', true, 'full');

        $store_stats = true;

        

        if ($fullCompression && $compression_full_image) {

            $full_image_response = $this->startCompression($attachmentFilePath);

            if (is_wp_error($full_image_response)) {

                

                return $full_image_response;
            }

            if (empty($full_image_response['data'])) {
                $store_stats = false;
            }

            if ($full_image_response['data']->after_size > $full_image_response['data']->before_size) {
                $store_stats = false;
            }

            if ($store_stats) {
                $stats_array['sizes']['full'] = (object) $this->arrayFillPlaceholders($this->getSizeSignature(), (array) $full_image_response['data']);
            }

            $stats_array['stats']['lossy']       = $full_image_response['data']->lossy;
            
            $stats_array['stats']['keep_exif']   = !empty($full_image_response['data']->keep_exif) ? $full_image_response['data']->keep_exif : 0;
        }

        $has_errors = (bool) count($errors->get_error_messages());

        if (!$has_errors) {

            $existingStats = get_post_meta($id, self::$compressedMetaKey, true);

            if (!empty($existingStats)) {

                if (isset($existingStats['sizes']) && !empty($stats_array['sizes'])) {

                    foreach ($existingStats['sizes'] as $size_name => $sizeStats) {

                        if (empty($stats_array['sizes'][$size_name])) {

                            $stats_array['sizes'][$size_name] = $existingStats['sizes'][$size_name];

                        } else {

                            $existing_stats_size = (object) $existingStats['sizes'][$size_name];

                            $stats_array['sizes'][$size_name]->bytes   = $stats_array['sizes'][$size_name]->bytes + $existing_stats_size->bytes;
                            
                            $stats_array['sizes'][$size_name]->size_before = (!empty($existing_stats_size->size_before) && $existing_stats_size->size_before > $stats_array['sizes'][$size_name]->size_before) ? $existing_stats_size->size_before : $stats_array['sizes'][$size_name]->size_before;

                            $stats_array['sizes'][$size_name]->percent = $this->calculatePercentage($stats_array['sizes'][$size_name], $existing_stats_size);
                        
                        }
                    }
                }
            }

            $stats_array = $this->stats->totalCompression($stats_array);

            if (isset($stats_array['stats']['bytes']) && $stats_array['stats']['bytes'] >= 0 && !$has_errors) {
                do_action('wp_files_image_optimized', $id, $stats_array, $meta);
            }

            

            //Delete restore at
            delete_post_meta($id, self::$restoreAt);

            //Update post meta
            update_post_meta($id, self::$compressedMetaKey, $stats_array);

        } else {

            
        }

        unset($stats_array);

        if (!empty($response)) {
            unset($response);
        }

        return $meta;
    }

    /**
     * Compression data to save in DB
     * @since 1.0.0
     * @return array
     */
    public function getSizeSignature()
    {
        return array(
            'percent'     => 0,
            'bytes'       => 0,
            'size_before' => 0,
            'size_after'  => 0,
            'time'        => 0,
        );
    }

    /**
     * Process an image with Compression.
     * @since 1.0.0
     * @param string $filePath        
     * @param bool   $convertToWepP
     * @return array|bool|WP_Error
     */
    public function startCompression($filePath = '', $convertToWepP = false)
    {
        $errors   = new WP_Error();

        $directoryName = trailingslashit(dirname($filePath));

        if (empty($filePath)) {
            $errors->add('empty_path', __('File path dost not exist', 'wpfiles'));
        } elseif (!file_exists($filePath) || !is_file($filePath)) {
            $errors->add('file_not_found', sprintf(__('Not found %s', 'wpfiles'), $filePath));
        } elseif (!is_writable($directoryName)) {
            $errors->add('not_writable', sprintf(__('%s is not writable', 'wpfiles'), $directoryName));
        }

        $fileSize = file_exists($filePath) ? filesize($filePath) : '';

        $maximumSize = Wp_Files_Subscription::is_pro() ? WP_FILES_PRO_MAX_BYTES : WP_FILES_MAX_FREE_BYTES;

        if (0 === (int) $fileSize) {
            $errors->add('image_not_found', sprintf(__('(%1$s), not found', 'wpfiles'), size_format($fileSize, 1)));
        } elseif ($fileSize > $maximumSize) {
            $errors->add('size_limit', sprintf(__('(%1$s), size limit exceeded', 'wpfiles'), size_format($fileSize, 1)));
        }

        if (count($errors->get_error_messages())) {
            return $errors;
        }

        clearstatcache();

        $permission = fileperms($filePath) & 0777;

        $response = $this->_post($filePath, $fileSize, $convertToWepP);

        if (!$response['success']) {
            $errors->add('false_response', $response['message']);
        } elseif (empty($response['data'])) {
            $errors->add('no_data', __('API error', 'wpfiles'));
        }

        if (count($errors->get_error_messages())) {
            return $errors;
        }

        if ((!empty($response['data']->bytes_saved) && (int) $response['data']->bytes_saved) <= 0 || empty($response['data']->image)) {
            return $response;
        }

        

            $temporaryFile = $filePath . '.tmp';

            file_put_contents($temporaryFile, $response['data']->image);

            $success = @rename($temporaryFile, $filePath);

            if (file_exists($temporaryFile)) {
                @unlink($temporaryFile);
            }

            if (!$success) {
                @copy($temporaryFile, $filePath);
                @unlink($temporaryFile);
            }
        

        if (empty($permission) || !$permission) {

            $stats  = stat(dirname($filePath));

            $permission = $stats['mode'] & 0000666;

        }

        if ($convertToWepP) {
            @chmod($webpFile, $permission);
        } else {
            @chmod($filePath, $permission);
        }

        return $response;
    }

    /**
     * Send request to WPFiles server.
     * @since 1.0.0 
     * @param string $filePath       
     * @param int    $fileSize      
     * @param bool   $convertToWepP  
     * @return bool|array 
     */
    private function _post($filePath, $fileSize, $convertToWepP = false)
    {
        $data = array();

        $headers = array(
            'accept'       => 'application/json',   // The API returns JSON.
            'content-type' => 'application/binary', // Set content type to binary.
        );

        if(Wp_Files_Subscription::is_pro() && $this->settings['super_compression']) {
            $headers['lossy'] = 1;
        }

        if($this->settings['strip_exif']) {
            $headers['exif'] = 1;
        }

        if ($convertToWepP) {
            $headers['webp'] = 'true';
        }

        $apiKey = Wp_Files_Helper::getAccountApikey();

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $apiUrl = defined('WP_FILES_API_HTTP') ? WP_FILES_API_HTTP : WP_FILES_OPTIMIZE_API;

        $args    = array(
            'headers'    => $headers,
            'body'       => file_get_contents($filePath),
            'timeout'    => WP_FILES_TIMEOUT,
            'user-agent' => WP_FILES_UA,
        );

        wp_raise_memory_limit('image');

        $result = wp_remote_post($apiUrl . '/process-image', $args);

        unset($args);

        if (is_wp_error($result)) {

            $errorMsg = $result->get_error_message();

            // Some hosting provider issues like Hostgator.
            if (!empty($errorMsg) && strpos($errorMsg, 'SSL CA cert') !== false) {
                Wp_Files_Settings::updateSetting('use_http', 1);
            }

            if (strpos($errorMsg, 'timed out')) {
                $data['message'] = esc_html__("Skipped due to a timeout error. You can increase the request timeout to make sure Compression has enough time to process larger files. define(WP_FILES_TIMEOUT, 150);", 'wpfiles');
            } else {
                $data['message'] = sprintf(__('Error posting to API: %s', 'wpfiles'), $result->get_error_message());
            }

            $data['success'] = false;

            unset($result); 

            return $data;

        } elseif (200 !== wp_remote_retrieve_response_code($result)) {

            $data['message'] = sprintf(__('Error posting to API: %1$s %2$s', 'wpfiles'), wp_remote_retrieve_response_code($result), wp_remote_retrieve_response_message($result));

            $data['success'] = false;

            unset($result);

            return $data;

        }

        $response = json_decode($result['body']);

        if ($response && true === $response->success) {

            if ($response->data->bytes_saved > 0) {

                $image     = base64_decode($response->data->image);

                $data['success']     = true;

                $data['data']        = $response->data;

                $data['data']->image = $image;

                unset($image); 

            } else {

                $data['success'] = true;

                $data['data']    = $response->data;

            }

        } else {

            $data['message'] = !empty($response->data) ? $response->data : __("Image couldn't be compressed", 'wpfiles');

            $data['success'] = false;
        }

        unset($result);

        unset($response);

        return $data;
    }

    /**
     * Array placeholder with values
     * @since 1.0.0
     * @param array $placeholders
     * @param array $data
     * @return array
     */
    public function arrayFillPlaceholders(array $placeholders, array $data)
    {

        $placeholders['percent']     = $data['compression'];

        $placeholders['bytes']       = $data['bytes_saved'];

        $placeholders['size_before'] = $data['before_size'];

        $placeholders['size_after']  = $data['after_size'];

        $placeholders['time']        = $data['time'];

        return $placeholders;
    }

    /**
     * Calculate saving percentage from current and existing stats
     * @since 1.0.0
     * @param object|string $stats_array        
     * @param object|string $existingStats 
     * @return float
     */
    public function calculatePercentage($stats_array = '', $existingStats = '')
    {
        if (empty($stats_array) || empty($existingStats)) {
            return 0;
        }

        $sizeBefore = !empty($stats_array->size_before) ? $stats_array->size_before : $existingStats->size_before;

        $sizeAfter  = !empty($stats_array->size_after) ? $stats_array->size_after : $existingStats->size_after;

        $savings     = $sizeBefore - $sizeAfter;

        if ($savings > 0) {

            $percentage = ($savings / $sizeBefore) * 100;

            $percentage = $percentage > 0 ? round($percentage, 2) : $percentage;

            return $percentage;
        }

        return 0;
    }

    /**
     * Super Compressed images count.
     * @since 1.0.0
     * @param int $id       
     * @param string $type 
     * @param string $key  
     * @return bool 
     */
    public function updateSuperCompressCount($id, $type = 'add', $key = 'wpfiles-super_compressed')
    {
        $superCompression = get_option($key, false);

        if (!$superCompression || empty($superCompression['ids'])) {
            $superCompression = array(
                'ids' => array(),
            );
        }

        if ('add' === $type && !in_array($id, $superCompression['ids'])) {
            $superCompression['ids'][] = $id;
        } elseif ('remove' === $type && false !== ($k = array_search($id, $superCompression['ids']))) {
            unset($superCompression['ids'][$k]);
            $superCompression['ids'] = array_values($superCompression['ids']);
        }

        $superCompression['timestamp'] = current_time('timestamp');

        update_option($key, $superCompression, false);

        return true;
    }

    /**
     * Update recompression list
     * @since 1.0.0
     * @param string $id  
     * @param string $key  
     */
    public function updateRecompressionList($id, $key = 'wpfiles-recompress-list')
    {
        $recompressList = get_option($key);

        if (!empty($recompressList) && count($recompressList) > 0) {
            $key = array_search($id, $recompressList);
            if ($recompressList) {
                unset($recompressList[$key]);
            }
            $recompressList = array_values($recompressList);
        }

        if (empty($recompressList) || 0 === count($recompressList)) {
            delete_option($key);
        } else {
            update_option($key, $recompressList, false);
        }
    }

    /**
     * Update image compression count
     * @since 1.0.0
     * @param string $key
     */
    public static function updateCompressionCount($key = 'bulk_limit')
    {
        $transient = WP_FILES_PREFIX . $key;

        $bulkCount = get_transient($transient);

        if (false === $bulkCount) {
            set_transient($transient, 1, 200);
        } elseif ($bulkCount < self::$maximumFreeBulk) {
            set_transient($transient, $bulkCount + 1, 200);
        }
    }

    /**
     * Compression one image
     * @since 1.0.0
     * @param int  $id  
     * @param bool $return     
     * @return array|string
     */
    public function compressOne($id, $return = false)
    {
        if (get_transient("compression-in-progress-{$id}") || get_transient("wpfiles-restore-{$id}")) {

            $status = $this->generateHtml($id);

            if ( $return ) {
				return $status;
			}

            wp_send_json_success( $status );
        }

        set_transient( 'compression-in-progress-' . $id, 1, HOUR_IN_SECONDS );

        $id = absint((int) $id);

        $attachmentFilePath = get_attached_file($id);

        Wp_Files_Helper::checkAnimatedStatus($attachmentFilePath, $id);

        $this->createBackup($attachmentFilePath, $id);

        $originalMeta = !empty($_POST['metadata']) ? Wp_Files_Helper::formatMetaFromPost(Wp_Files_Helper::sanitizeArray($_POST['metadata'])) : '';

        $originalMeta = empty($originalMeta) ? wp_get_attachment_metadata($id) : $originalMeta;

        $updatedMeta = $this->resizeImage($id, $originalMeta);

        

        $originalMeta = !empty($updatedMeta) ? $updatedMeta : $originalMeta;

        $compression = $this->resizeFromMetaData($originalMeta, $id);

        wp_update_attachment_metadata($id, $originalMeta);

        delete_transient('compression-in-progress-' . $id);

        $status = $this->generateHtml($id);

        if(empty($compression)) {
            return array(
                'success' => false,
                'error'    => __('Image is invalid', 'wpfiles'),
            );
        } else if (is_wp_error($compression)) {
            return array(
                'success' => false,
                'error'    => Wp_Files_Helper::filterTheError($compression->get_error_message(), $id),
                'subscription_failed' => (int) $this->subscription_failed(),
            );
        }

        $this->updateRecompressionList($id);

        Wp_Files_Stats::addToCompressionList($id);

        return $status;
    }

    /**
     * Return status
     * @since 1.0.0 
     * @param int $id 
     * @return string|array 
     */
    public function generateHtml($id)
    {
        if (!wp_attachment_is_image($id) || !in_array(get_post_mime_type($id), Wp_Files_Compression::$mimeTypes, true)) {
            return __('Could not compress', 'wpfiles');
        }

        $compressedData = get_post_meta($id, Wp_Files_Compression::$compressedMetaKey, true);

        $attachmentData = wp_get_attachment_metadata($id);

        $html = '<p class="compression-status">' . $this->getOptimizationStatus($id, $compressedData) . '</p>';

        if(isset($_POST['response']) && in_array(sanitize_text_field($_POST['response']), ['optimize-status', 'watermark-status'])) {
            return strip_tags($this->getOptimizationStatus($id, $compressedData));
        }
        
        $links = $this->getOptimizationLinks($id, $compressedData, $attachmentData);

        if (!empty($links)) {
            $html .= '<div class="wpf-compression-media compression-status-links">' . $links . '</div>';
        }

        if (isset($compressedData['sizes'])) {
            $html .= $this->getDetailedStats($id, $compressedData, $attachmentData);
        }

        return $html;
    }

    /**
     * Get the image optimization status.
     * @since 1.0.0
     * @param int   $id        
     * @param array $compressedData
     * @return string
     */
    private function getOptimizationStatus($id, $compressedData)
    {
        if (get_transient('compression-in-progress-' . $id)) {
            return __('Compression in progress...', 'wpfiles');
        }

        if ('true' === get_post_meta($id, WP_FILES_PREFIX . 'ignore-bulk', true)) {
            return __('Ignored from bulk-compression', 'wpfiles');
        }

        if (empty($compressedData)) {
            return __('Could not compress', 'wpfiles');
        }

        $stats_array = $this->stats->getStatsForAttachments(array($id));

        if ($stats_array['size_after'] === $stats_array['size_before']) {
            return __('Already compressed', 'wpfiles');
        }

        $percent     = ($stats_array['size_before'] - $stats_array['size_after']) / $stats_array['size_before'] * 100;

        $status = sprintf(
            _n('Reduced by %1$s (%2$s)', '%3$d images reduced by %1$s (%2$s)', $stats_array['count_images'], 'wpfiles'),
            esc_html(size_format($stats_array['size_before'] - $stats_array['size_after'], 1)),
            sprintf('%01.1f%%', number_format_i18n(ceil($percent), 2)),
            $stats_array['count_images']
        );

        $filePath = get_attached_file($id);

        $size = file_exists($filePath) ? filesize($filePath) : 0;

        if ($size > 0) {
            $status .= sprintf(__('%1$sImage size: %2$s', 'wpfiles'), ' ', size_format($size, 1));
        }

        return $status;
    }

    /**
     * Get optimization links
     * @since 1.0.0
     * @param int   $id
     * @param array $compressedData
     * @param array $attachmentData
     * @return string
     */
    public function getOptimizationLinks($id, $compressedData = array(), $attachmentData = array())
    {
        if (get_transient('compression-in-progress-' . $id)) {
            return '';
        }

        if ('true' === get_post_meta($id, WP_FILES_PREFIX . 'ignore-bulk', true)) {
            return "<a href='#' class='wpfiles-remove-skipped' data-id='{$id}'>" . __('Undo', 'wpfiles') . '</a>';
        }

        if (empty($compressedData)) {
            $links  = "<a href='#' class='wpfiles-send' data-id='{$id}'>" . __('Compress', 'wpfiles') . '</a>';
            $links .= ' | ';
            $links .= "<a href='#' class='wpfiles-ignore-image' data-id='{$id}'>" . __('Ignore', 'wpfiles') . '</a>';
            return $links;
        }

        $stats_array = $this->stats->getStatsForAttachments(array($id));

        $showRecompress = $this->showRecompress($id, $compressedData, $attachmentData);

        if ($stats_array['size_after'] === $stats_array['size_before'] && $showRecompress) {
            return self::getRecompressLink($id);
        }

        $links = $showRecompress ? self::getRecompressLink($id) : $this->getSuperCompressionLink($id, $compressedData);

        if ($stats_array['size_after'] !== $stats_array['size_before'] && $this->showRestoreOption($id, $attachmentData)) {

            $links .= empty($links) ? '' : ' | ';

            $links .= self::getRestoreLink($id);
        }

        if ($stats_array['size_after'] !== $stats_array['size_before']) {
            $links .= empty($links) ? '' : ' | ';
            $links .= sprintf(
                '<a href="#" class="wpfiles-action compression-stats-details wpfiles-title" wf-tooltip="%s">%s</a>',
                esc_html__('Detailed stats for all the image sizes', 'wpfiles'),
                esc_html__('View Stats', 'wpfiles')
            );
        }

        return $links;
    }

    /**
     * Detail stats
     * @since 1.0.0
     * @param int   $imageID            
     * @param array $compressedData   
     * @param array $attachmentMetaData
     * @return string
     */
    private function getDetailedStats($imageID, $compressedData, $attachmentMetaData)
    {
        $stats_table      = '<div id="compression-stats-' . $imageID . '" class="wpf-compression-media compression-stats-wrapper" style="display:none">
			<table class="wpfiles-stats-holder">
				<thead>
					<tr>
						<th class="compression-stats-header">' . esc_html__('Image size', 'wpfiles') . '</th>
						<th class="compression-stats-header">' . esc_html__('Savings', 'wpfiles') . '</th>
					</tr>
				</thead>
				<tbody>';

        $sizeStats = $compressedData['sizes'];

        uasort(
            $sizeStats,
            function ($a, $b) {
                if ($a->bytes === $b->bytes) {
                    return 0;
                }
                return $a->bytes < $b->bytes ? 1 : -1;
            }
        );

        if (!empty($attachmentMetaData['sizes'])) {

            $skipped = $this->getSkippedImages($imageID, $sizeStats, $attachmentMetaData);

            if (!empty($skipped)) {

                foreach ($skipped as $imageData) {

                    $skipClass = 'size_limit' === $imageData['reason'] ? ' error' : '';

                    $stats_table     .= '<tr>
							<td>' . strtoupper($imageData['size']) . '</td>
							<td class="wpfiles-skipped' . $skipClass . '">' . $this->skipReason($imageData['reason']) . '</td>
						</tr>';
                }
            }
        }

        foreach ($sizeStats as $sizeKey => $sizeValue) {

            $dimensions = '';

            if (!empty($attachmentMetaData['sizes']) && !empty($attachmentMetaData['sizes'][$sizeKey])) {

                $dimensions = $attachmentMetaData['sizes'][$sizeKey]['width'] . 'x' . $attachmentMetaData['sizes'][$sizeKey]['height'];
            }

            $dimensions = !empty($dimensions) ? sprintf(' <br /> (%s)', $dimensions) : '';

            if ($sizeValue->bytes > 0) {

                $percent = round($sizeValue->percent, 1);

                $percent = $percent > 0 ? ' ( ' . ceil($percent) . '% )' : '';

                $stats_table  .= '<tr>
						<td>' . strtoupper($sizeKey) . $dimensions . '</td>
						<td>' . size_format($sizeValue->bytes, 1) . $percent . '</td>
					</tr>';
            }
        }

        $stats_table .= '</tbody>
			</table>
		</div>';

        return $stats_table;
    }

    /**
     * Show recompress option according to settings
     * @since 1.0.0
     * @param string $id              
     * @param array $compressedData  
     * @param array $attachmentData
     * @return bool
     */
    private function showRecompress($id = '', $compressedData = array(), $attachmentData = array())
    {
        if ($this->settings['compress_original_image'] && Wp_Files_Subscription::is_pro()) {

            if (!empty($compressedData) && empty($compressedData['sizes']['full'])) {
                return true;
            }
        }

        if ($this->resize->shouldResize($id, $attachmentData)) {
            return true;
        }

        if ($this->settings['strip_exif']) {
            if (isset($compressedData['stats']['keep_exif']) && $compressedData['stats']['keep_exif']) {
                return true;
            }
        }

        

        

        $imageSizes = $this->settings['image_sizes'] == "custom" && count((array)$this->settings['image_manual_sizes']) > 0 ? $this->settings['image_manual_sizes'] : [];

        if (empty($imageSizes)) {
            $imageSizes = array_keys(Wp_Files_Helper::getImageDimensions());
        }

        if (has_filter('wp_image_editors', 'photon_subsizes_override_image_editors')) {
            return false;
        }

        $compressedImageSizes = isset($compressedData['sizes']) && is_array($compressedData['sizes']) ? count($compressedData['sizes']) : 0;

        if (is_array($imageSizes) && count($imageSizes) > $compressedImageSizes && isset($attachmentData['sizes']) && count($attachmentData['sizes']) !== $compressedImageSizes) {

            foreach ($imageSizes as $imageSize) {

                if (isset($compressedData['sizes'][$imageSize])) {
                    continue;
                }

                if (isset($attachmentData['sizes'][$imageSize])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Skipped images
     * @since 1.0.0
     * @param int   $imageID             
     * @param array $sizeStats           
     * @param array $attachmentMetaData 
     * @return array
     */
    private function getSkippedImages($imageID, $sizeStats, $attachmentMetaData)
    {
        $skipped = array();

        $mediaSize = get_intermediate_image_sizes();

        $fullImage = get_attached_file($imageID);

        if (!array_key_exists('full', $sizeStats) && !Wp_Files_Subscription::is_pro()) {

            $skipped[] = array(
                'size'   => 'full',
                'reason' => 'large_size',
            );

            $fileSize = file_exists($fullImage) ? filesize($fullImage) : '';

            if (empty($skipped) && !empty($fileSize) && ($fileSize / WP_FILES_MAX_FREE_BYTES) > 1) {

                $skipped[] = array(
                    'size'   => 'full',
                    'reason' => 'size_limit',
                );
            }
        }

        if (is_array($mediaSize)) {

            foreach ($mediaSize as $size) {

                if (array_key_exists($size, $attachmentMetaData['sizes']) && !array_key_exists($size, $sizeStats) && !empty($size['file'])) {

                    $imagePath   = path_join(dirname($fullImage), $size['file']);

                    $imageSize = file_exists($imagePath) ? filesize($imagePath) : '';

                    if (!empty($imageSize) && ($imageSize / WP_FILES_MAX_FREE_BYTES) > 1) {

                        $skipped[] = array(
                            'size'   => 'full',
                            'reason' => 'size_limit',
                        );
                    }

                }

            }

        }

        return $skipped;
    }

    /**
     * Skip image reason
     * @since 1.0.0
     * @param string $messageId
     * @return bool
     */
    public function skipReason($messageId)
    {
        $count = count(get_intermediate_image_sizes());

        $compressionOriginalText = sprintf(
            esc_html__('When you upload any image to WP it creates %s image sizes that are commonly used in your site. WP also saves the original full size image, these are not usually used on your site we don not compress them. Pro users can change this.', 'wpfiles'),
            $count
        );

        $skipMessage = array(
            'large_size' => $compressionOriginalText,
            'size_limit' => esc_html__("Image could not be compressed as it exceeded the 5Mb size limit, Pro users can compress images without any size limit", 'wpfiles'),
        );

        $skipReason = '';

        if (!empty($skipMessage[$messageId])) {

            $skipReason = '<a href="'.WP_FILES_GO_URL.'/pricing?utm_source=wpfiles&utm_medium=plugin&utm_campaign=wpfiles_medialibrary_savings" target="_blank">
				<span class="wf-badge wf-badge--warning mt-0" wf-tooltip-left="' . $skipMessage[$messageId] . '">' . esc_html__('PRO', 'wpfiles') .  '</span></a>';
        }

        return $skipReason;
    }

    /**
     * Show restore option
     * @since 1.0.0
     * @param int $imageID  
     * @param string|array $attachmentData
     * @return bool
     */
    private function showRestoreOption($imageID, $attachmentData)
    {
        if (empty($attachmentData)) {
            return false;
        }

        $file = get_attached_file($imageID);

        $backupSizes = get_post_meta($imageID, '_wp_attachment_backup_sizes', true);

        if (!empty($backupSizes) && (!empty($backupSizes['compress-full']) || !empty($backupSizes['compression_png_path']))) {
            
            $backup = !empty($backupSizes['compression_png_path']) ? $backupSizes['compression_png_path'] : '';

            $backup = empty($backup) && !empty($backupSizes['compress-full']) ? $backupSizes['compress-full'] : $backup;
            
            $backup = !empty($backup['file']) ? $backup['file'] : '';

        }

        if (empty($backup)) {
            $backup = $this->getImageBackupPath($file);
        } else {
            $backup = str_replace(wp_basename($file), wp_basename($backup), $file);
        }

        $fileExists = apply_filters('wpfiles_backup_exists', file_exists($backup), $imageID, $backup);

        if ($fileExists) {
            return true;
        }

        $pngJpgSaving = get_post_meta($imageID, WP_FILES_PREFIX . 'pngjpg_savings', true);

        if (!empty($pngJpgSaving)) {

            $backup = get_post_meta($imageID, WP_FILES_PREFIX . 'original_file', true);

            $backup = Wp_Files_Helper::originalFilePath($backup);

            if (!empty($backup) && is_file($backup)) {
                return true;
            }

        }

        return false;
    }

    /**
     * Generates recompress link
     * @since 1.0.0
     * @param int $imageID 
     * @param string $type  
     * @return bool|string
     */
    public static function getRecompressLink($imageID, $type = 'wp')
    {
        if (empty($imageID)) {
            return false;
        }

        $class  = 'wpfiles-action wpfiles-title wpf-tooltip wpf-tooltip-constrained';

        $class .= 'wp' === $type ? ' wpfiles-recompress' : ' wpfiles-recompress';

        return sprintf(
            '<a href="#" wf-tooltip="%s" data-id="%d" data-nonce="%s" class="%s">%s</a>',
            esc_html__('Compress image', 'wpfiles'),
            $imageID,
            wp_create_nonce('wpfiles-recompress-' . $imageID),
            $class,
            esc_html__('Recompress', 'wpfiles')
        );

    }

    /**
     * Super Compression link.
     * @since 1.0.0
     * @param int $id
     * @param array $compressedData
     * @return string
     */
    private function getSuperCompressionLink($id, $compressedData)
    {
        if (!Wp_Files_Subscription::is_pro() || empty($compressedData['stats'])) {
            return '';
        }

        if (isset($compressedData['stats']['lossy']) && $compressedData['stats']['lossy']) {
            return '';
        }

        if ('image/gif' === get_post_mime_type($id) || !$this->settings['super_compression']) {
            return '';
        }

        return "<a href='#' class='wpfiles-send' data-id='{$id}'>" . __('Super Compress', 'wpfiles') . '</a>';
    }

    /**
     * Get a restore link
     * @since 1.0.0
     * @param int $imageID
     * @param string $type
     * @return bool|string
    */
    public static function getRestoreLink($imageID, $type = 'wp')
    {
        if (empty($imageID)) {
            return false;
        }

        $class  = 'wpfiles-action wpfiles-title wpf-tooltip';

        $class .= 'wp' === $type ? ' wpfiles-restore' : ' wpfiles-restore';

        return sprintf(
            '<a href="#" wf-tooltip="%s" data-id="%d" data-nonce="%s" class="%s">%s</a>',
            esc_html__('Restore original image', 'wpfiles'),
            $imageID,
            wp_create_nonce('wpfiles-restore-' . $imageID),
            $class,
            esc_html__('Restore', 'wpfiles')
        );
    }

    /**
     * Restore image
     * @since 1.0.0
     * @param object $attachment
     * @param bool $response
     * @return bool
    */
    public function restoreImage($attachment, $response)
    {
        if (empty($attachment)) {
            if (empty(sanitize_text_field($_POST['attachment_id']))) {
                return array(
                    'success' => false,
                    'error' => esc_html__('Error in processing', 'wpfiles'),
                );
            }
        }


        $restored    = false;

        $restorePng = false;

        $id = empty($attachment) ? absint((int) sanitize_text_field($_POST['attachment_id'])) : $attachment;

        if (!wp_attachment_is_image($id)) {
            return false;
        }

        set_transient( 'wpfiles-restore-' . $id, 1, HOUR_IN_SECONDS );

        
        
        $filePath = get_attached_file($id);

        if (false !== strpos($filePath, '-scaled.') && function_exists('wp_get_original_image_path')) {
            $filePath = wp_get_original_image_path($id, true);
        }

        $backupSizes = get_post_meta($id, '_wp_attachment_backup_sizes', true);

        if (!empty($backupSizes)) {

            

            if (empty($backupPath)) {

                if (!empty($backupSizes[$this->backupKey])) {
                    $backupPath = $backupSizes[$this->backupKey];
                } else {
                    $backupPath = $this->getImageBackupPath($filePath);
                }
            }

            $backupPath = is_array($backupPath) && !empty($backupPath['file']) ? $backupPath['file'] : $backupPath;

            $isBackupFile = false === strpos($backupPath, '.bak');

            if ($isBackupFile) {
                $backupFullPath = $backupPath;
            } else {
                $backupFullPath = str_replace(wp_basename($filePath), wp_basename($backupPath), $filePath);
            }
        }

        if (!empty($backupFullPath)) {

            if ($restorePng) {

                $restored = $this->restorePng($id, $backupFullPath, $filePath);

                if ($restored) {
                    $this->removeFromBackupSizes($id, 'compression_png_path', $backupSizes);
                }

            } else {

                if (!$isBackupFile) {
                    $restored = @copy($backupFullPath, $filePath);
                } else {
                    $restored = true;
                }

                if ($restored) {
                    $this->removeFromBackupSizes($id, '', $backupSizes);

                    @unlink($backupFullPath);
                }
            }
        } elseif (file_exists($filePath . '_backup')) {
            $restored = @copy($filePath . '_backup', $filePath);
        }

        if (!$restorePng) {

            $metadata = wp_generate_attachment_metadata($id, $filePath);

            if (!empty($metadata) && !is_wp_error($metadata)) {
                wp_update_attachment_metadata($id, $metadata);
            }

        }

        if ($restored) {

            delete_post_meta($id, Wp_Files_Compression::$compressedMetaKey);

            delete_post_meta($id, self::$watermarkMetaKey);

            delete_post_meta($id, WP_FILES_PREFIX . 'pngjpg_savings');

            delete_post_meta($id, WP_FILES_PREFIX . 'original_file');

            delete_post_meta($id, WP_FILES_PREFIX . 'resize_savings');

            //Update restore at
            update_post_meta($id, self::$restoreAt, time());

            $this->removeFromImagesWithBackupsCacheList($id);

            $html = $this->generateHtml($id);

            delete_transient("wpfiles-restore-$id");

            Wp_Files_Stats::removeFromCompressedList($id);

            Wp_Files_Stats::removeFromWatermarkedList($id);

            if (!$response) {
                return true;
            }

            $size = file_exists($filePath) ? filesize($filePath) : 0;

            if ($size > 0) {
                $updateSize = size_format($size, 0);
            }

            return array(
                'stats'    => $html,
                'new_size' => isset($updateSize) ? $updateSize : 0,
            );
        }

        delete_transient("wpfiles-restore-$id");

        if ($response) {
            return array(
                'success' => false,
                'error' => esc_html__('Unable to restore image', 'wpfiles')
            );
        }

        return false;
    }

    
    
    /**
     * Auto compression [Check is enabled]
     * @since 1.0.0
     * @return int|mixed
     */
    public function isAutoCompressionEnabled()
    {
        $autoCompression = $this->settings['automatic_compression'];

        if (!isset($autoCompression)) {
            $autoCompression = 1;
        }

        return $autoCompression;
    }

    /**
     * Remove specific backups
     * @since 1.0.0
     * @param string $id  
     * @param string $backupKey    
     * @param array  $backupSizes
     */
    private function removeFromBackupSizes($id = '', $backupKey = '', $backupSizes = array())
    {
        $backupSizes = empty($backupSizes) ? get_post_meta($id, '_wp_attachment_backup_sizes', true) : $backupSizes;

        $backupKey   = empty($backupKey) ? $this->backupKey : $backupKey;

        if (empty($backupSizes) || !isset($backupSizes[$backupKey])) {
            return;
        }

        unset($backupSizes[$backupKey]);

        update_post_meta($id, '_wp_attachment_backup_sizes', $backupSizes);
        
    }

    /**
     * Removes an image from cached list with backup.
     * @since 1.0.0
     * @param integer $id
     * @return void
     */
    private function removeFromImagesWithBackupsCacheList($id)
    {
        $images        = wp_cache_get('images_with_backups', WP_FILES_CACHE_PREFIX);

        $id = strval($id);

        if (!empty($images) && in_array($id, $images, true)) {

            $key = array_search($id, $images, true);

            if (false !== $key) {

                unset($images[$key]);

                wp_cache_set('images_with_backups', array_values($images), WP_FILES_CACHE_PREFIX);
            }
        }
    }

    /**
     * Delete all backup files
     * @since 1.0.0
     * @param int $imageID 
     */
    public function deleteBackupFiles($imageID)
    {
        $compressedMeta = get_post_meta($imageID, Wp_Files_Compression::$compressedMetaKey, true);

        if (empty($compressedMeta)) {
            return;
        }

        $meta = wp_get_attachment_metadata($imageID);

        $file = get_attached_file($imageID);

        $backupName = $this->getImageBackupPath($file);

        @unlink($backupName);

        $this->removeFromImagesWithBackupsCacheList($imageID);

        if (!empty($meta) && !empty($meta['sizes'])) {

            foreach ($meta['sizes'] as $size) {

                if (empty($size['file'])) {
                    continue;
                }

                $imageSizePath  = path_join(dirname($file), $size['file']);

                $imageBackupPath = $this->getImageBackupPath($imageSizePath);

                @unlink($imageBackupPath);
            }

        }
    }

    /**
     * Add watermark
     * @since 1.0.0
     * @param int  $attachment_id
     * @return array|string
     */
    public function addWatermark($attachment_id)
    {
        if (get_transient("watermark-in-progress-{$attachment_id}") || get_transient("wpfiles-restore-{$attachment_id}")) {

            if (!wp_attachment_is_image($attachment_id) || !in_array(get_post_mime_type($attachment_id), Wp_Files_Compression::$mimeTypes, true)) {
                return [
                    'success' => false,
                    'error' => __('Could not watermark', 'wpfiles')
                ];
            }

            return [
                'success' => false,
                'error' => __('Invalid request', 'wpfiles')
            ];
        }

        set_transient( 'watermark-in-progress-' . $attachment_id, 1, HOUR_IN_SECONDS );

        $attachment_id = absint((int) $attachment_id);

        $attachmentFilePath = get_attached_file($attachment_id);

        Wp_Files_Helper::checkAnimatedStatus($attachmentFilePath, $attachment_id);

        $this->createBackup($attachmentFilePath, $attachment_id); 

        $originalMeta = !empty($_POST['metadata']) ? Wp_Files_Helper::formatMetaFromPost(Wp_Files_Helper::sanitizeArray($_POST['metadata'])) : '';

        $originalMeta = empty($originalMeta) ? wp_get_attachment_metadata($attachment_id) : $originalMeta;

        $watermark = $this->processWatermark($originalMeta, $attachment_id);

        wp_update_attachment_metadata($attachment_id, $originalMeta);

        delete_transient('watermark-in-progress-' . $attachment_id);

        $this->updateRewatermarkList($attachment_id);

        Wp_Files_Stats::addToWatermarkedList($attachment_id);

        if(empty($watermark)) {
            return array(
                'success' => false,
                'error'    => __('Invalid image', 'wpfiles'),
            );
        } else if (is_wp_error($watermark)) {
            return array(
                'success' => false,
                'error' => $watermark->get_error_message()
            );
        }

        return $watermark;
    }

    /**
     * Process watermark request
     * @since 1.0.0
     * @param array $meta 
     * @param null|int $id 
     * @return mixed
     */
    public function processWatermark($meta, $id = null)
    {
        $errors = new WP_Error();

        $stats_array  = array(
            'sizes' => array(),
        );

        if ($id && false === wp_attachment_is_image($id)) {
            return $meta;
        }

        $this->attachment_id = $id;

        $this->mediaType    = 'wp';

        $attachmentFilePath = Wp_Files_Helper::getAttachedFile($id);

        if (!empty($meta['sizes']) && !has_filter('wp_image_editors', 'photon_subsizes_override_image_editors')) {

            foreach ($meta['sizes'] as $sizeKey => $sizeData) {

                if ('full' !== $sizeKey && $this->skipWatermarkImageSize($this->settings, $sizeKey)) {
                    continue;
                }

                $attachment_file_path_size = path_join(dirname($attachmentFilePath), $sizeData['file']);

                $ext = Wp_Files_Helper::getFileMimeType($attachment_file_path_size);

                if ($ext) {
                    $valid_mime = array_search(
                        $ext,
                        array(
                            'jpg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                        ),
                        true
                    );

                    if (false === $valid_mime) {
                        continue;
                    }
                }

                if (!apply_filters('wp_files_media_image', true, $sizeKey)) {
                    continue;
                }

                $response = $this->startWatermarked($attachment_file_path_size);

                if (is_wp_error($response)) {
                    return $response;
                }

                $stats_array['sizes'][$sizeKey] = 1;
            }
        }

        if ($this->settings['compress_original_image']) {

            $watermark_full_image = apply_filters('wp_files_media_image', true, 'full');
    
            if ($watermark_full_image) { 
    
                $full_image_response = $this->startWatermarked($attachmentFilePath);
    
                if (is_wp_error($full_image_response)) {
                    return $full_image_response;
                }
    
                $stats_array['sizes']['full'] = 1;
            }
            
        }

        $has_errors = (bool) count($errors->get_error_messages());

        if (!$has_errors) {
            $stats_array['auto_watermark'] = $this->settings['auto_watermark'];
            $stats_array['watermark_type'] = $this->settings['watermark_type'];
            $stats_array['watermark_attachment_url'] = $this->settings['watermark_attachment_url'];
            $stats_array['watermark_text'] = $this->settings['watermark_text'];
            $stats_array['watermark_font'] = $this->settings['watermark_font'];
            $stats_array['watermark_size'] = $this->settings['watermark_size'];
            $stats_array['watermark_color'] = $this->settings['watermark_color'];
            $stats_array['watermark_opacity'] = $this->settings['watermark_opacity'];
            $stats_array['watermark_position'] = $this->settings['watermark_position'];
            $stats_array['watermark_x_axis'] = $this->settings['watermark_x_axis'];
            $stats_array['watermark_y_axis'] = $this->settings['watermark_y_axis'];
            $stats_array['watermark_scale_value'] = $this->settings['watermark_scale_value'];
            $stats_array['watermark_image_sizes'] = $this->settings['watermark_image_sizes'];
            $stats_array['watermark_image_sizes_manual'] = $this->settings['watermark_image_sizes_manual'];
            $stats_array['watermark_variant'] = $this->settings['watermark_variant'];
            $stats_array['watermark_fill'] = $this->settings['watermark_fill'];
            $stats_array['watermark_rounded_corner'] = $this->settings['watermark_rounded_corner'];
            $stats_array['watermark_text_padding'] = $this->settings['watermark_text_padding'];
            $stats_array['watermark_bg_color'] = $this->settings['watermark_bg_color'];
            $stats_array['watermark_stroke_color'] = $this->settings['watermark_stroke_color'];
            $stats_array['watermark_stroke_width'] = $this->settings['watermark_stroke_width'];
            $stats_array['timestamp'] = time();

            //Delete restore at
            delete_post_meta($id, self::$restoreAt);
            
            //Update post meta
            update_post_meta($id, self::$watermarkMetaKey, $stats_array);
        }

        if (!empty($response)) {
            unset($response);
        }

        if (count($stats_array['sizes']) > 1) {
            return count($stats_array['sizes']). ' ' . __('images were watermarked successfully', 'wpfiles');
        } else {
            return count($stats_array['sizes']). ' ' . __('image was watermarked successfully', 'wpfiles');
        }
    }

    /**
     * Start processing of watermark
     * @since 1.0.0
     * @param string $filePath     
     * @return array|bool|WP_Error
     */
    public function startWatermarked($filePath = '')
    {
        $data = array();
        
        $errors   = new WP_Error();

        $directoryName = trailingslashit(dirname($filePath));

        if (empty($filePath)) {
            $errors->add('empty_path', __('File does not exist', 'wpfiles'));
        } elseif (!file_exists($filePath) || !is_file($filePath)) {
            $errors->add('file_not_found', sprintf(__('Could not find %s', 'wpfiles'), $filePath));
        } elseif (!is_writable($directoryName)) {
            $errors->add('not_writable', sprintf(__('%s permission issue', 'wpfiles'), $directoryName));
        }

        $fileSize = file_exists($filePath) ? filesize($filePath) : '';

        if (0 === (int) $fileSize) {
            $errors->add('image_not_found', sprintf(__('(%1$s), image not found', 'wpfiles'), size_format($fileSize, 1)));
        }

        if (count($errors->get_error_messages())) {
            return $errors;
        }

        clearstatcache();

        $permission = fileperms($filePath) & 0777;

        wp_raise_memory_limit('image');

        $response = $this->compositeWatermark($filePath);

        unset($args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['status_code']) && $response['status_code'] != 200) {
            $errors->add('false_response', $response['response']['error']['message']);
        }

        if ($response && isset($response['status_code']) && $response['status_code'] == 200) {

            $image     = base64_decode($response['response']['data']['image']);

            $data['success']     = true;

            $data['data']['image'] = $image;

            unset($image);

        } else {
            $errors->add('false_response', !empty($response['response']['data']) ? $response['response']['data'] : __("Image could not be watermarked", 'wpfiles'));
        }

        unset($result);

        unset($response);

        $response = $data;

        if (empty($response['data'])) {
            $errors->add('no_data', __('API error', 'wpfiles'));
        }

        if (count($errors->get_error_messages())) {
            return $errors;
        }

        $temporaryFile = $filePath . '.tmp';

        file_put_contents($temporaryFile, $data['data']['image']);

        $success = @rename($temporaryFile, $filePath);

        if (file_exists($temporaryFile)) {
            @unlink($temporaryFile);
        }

        if (!$success) {
            @copy($temporaryFile, $filePath);
            @unlink($temporaryFile);
        }

        if (empty($permission) || !$permission) {

            $stats  = stat(dirname($filePath));

            $permission = $stats['mode'] & 0000666;

        }

        @chmod($filePath, $permission);

        return $response;
    }

    /**
     * Composite watermarked request
     * @since 1.0.0
     * @param string $filePath    
     * @return array|bool|WP_Error
     */
    public function compositeWatermark($filePath)
    {
        $errors   = new WP_Error();
        
        try {

            $watermark_image = get_option(WP_FILES_PREFIX . 'last-watermark-save', 0);

            $watermark_path = ($this->settings['watermark_type'] == "text" ? WP_FILES_PLUGIN_DIR . 'admin/images/watermark/user/'.$watermark_image : get_attached_file($this->settings['watermark_attachment_id']));

            if(file_exists($watermark_path)) {

                $watermark_ext = Wp_Files_Helper::getFileMimeType($watermark_path);

                if ($watermark_ext) {

                    $valid_mime = array_search(
                        $watermark_ext,
                        array(
                            'png' => 'image/png',
                            'jpg' => 'image/jpeg',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp'
                        ),
                        true
                    );

                    if ($valid_mime) {
                        // Imagick extension
                        if ( $this->extension === 'imagick') {
    
                            // create image resource
                            $image = new Imagick( $filePath );
    
                            $image->setImageCompressionQuality(90);
    
                            // create watermark resource
                            $watermark = new Imagick( $watermark_path );
    
                            // alpha channel exists?
                            if ( $watermark->getImageAlphaChannel() > 0 ) {
                                $watermark->evaluateImage( Imagick::EVALUATE_MULTIPLY, round( (float) $this->settings['watermark_opacity'], 2 ), Imagick::CHANNEL_ALPHA );
                            } else {
                                // no alpha channel
                                $watermark->setImageOpacity( round( (float) $this->settings['watermark_opacity'], 2 ) );
                            }
    
                            // set image output to progressive
                            // if ( $options['watermark_image']['jpeg_format'] === 'progressive' )
                            //     $image->setImageInterlaceScheme( Imagick::INTERLACE_PLANE );
    
                            // get image dimensions
                            $image_dim = $image->getImageGeometry();
    
                            // get watermark dimensions
                            $watermark_dim = $watermark->getImageGeometry();
    
                            // calculate watermark new dimensions
                            list( $width, $height ) = $this->calculateWatermarkDimensions( $image_dim['width'], $image_dim['height'], $watermark_dim['width'], $watermark_dim['height'] );
    
                            // resize watermark
                            $watermark->resizeImage( $width, $height, imagick::FILTER_CATROM, 1 );
    
                            // calculate image coordinates
                            list( $dest_x, $dest_y ) = $this->calculateImageCoordinates( $image_dim['width'], $image_dim['height'], $width, $height );
    
                            // combine two images together
                            $image->compositeImage( $watermark, Imagick::COMPOSITE_DEFAULT, (int)$dest_x, (int)$dest_y, Imagick::CHANNEL_ALL );
    
                            $imgBuff = $image->getimageblob();
    
                            $image->clear();
    
                            return [
                                'status_code' => 200,
                                'response' => array(
                                    "data" => array(
                                        "image" => base64_encode($imgBuff)
                                    )
                                ),
                            ];
    
                        } else if($this->extension === 'gd') {
                            
                            $mime = wp_check_filetype( $filePath );
    
                            $image = $this->getImageResource( $filePath, $mime['type'] );
    
                            if ( $image !== false ) {
    
                                $watermark_file_info = getimagesize( $watermark_path );
    
                                switch ( $watermark_file_info['mime'] ) {
                                    case 'image/jpeg':
                                    case 'image/pjpeg':
                                        $watermark = imagecreatefromjpeg( $watermark_path );
                                        break;
    
                                    case 'image/gif':
                                        $watermark = imagecreatefromgif( $watermark_path );
                                        break;
    
                                    case 'image/webp':
                                        $watermark = imagecreatefromwebp( $watermark_path );
                                        break;
                                        
                                    case 'image/png':
                                        $watermark = imagecreatefrompng( $watermark_path );
                                        break;
    
                                    default:
                                        return false;
                                }
    
                                $image_width = imagesx( $image );
    
                                $image_height = imagesy( $image );
    
                                list( $w, $h ) = $this->calculateWatermarkDimensions( $image_width, $image_height, imagesx( $watermark ), imagesy( $watermark ) );
    
                                list( $dest_x, $dest_y ) = $this->calculateImageCoordinates( $image_width, $image_height, $w, $h );
    
                                $this->imagecopymergeAlpha( $image, $this->resizeWatermark( $watermark, $w, $h, $watermark_file_info ), $dest_x, $dest_y, 0, 0, $w, $h, round( (float) $this->settings['watermark_opacity'] * 100, 2 ) );
                            
                                ob_start(); 
                                switch ( $mime['type'] ) {
                                    case 'image/jpeg':
                                    case 'image/pjpeg':
                                        imagejpeg($image, null);
                                        break;
                                    case 'image/png':
                                        imagealphablending($image, false);
                                        imagesavealpha($image,true);
                                        imagepng($image,null);
                                        break;
                                    case 'image/gif':
                                        imagegif($image,null);
                                        break;
                                }
                                $data = ob_get_contents();
                                ob_end_clean();
    
                                // clear watermark memory
                                imagedestroy( $image );
    
                                $image = null;
                                
                                return [
                                    'status_code' => 200,
                                    'response' => array(
                                        "data" => array(
                                            "image" => base64_encode($data)
                                        )
                                    ),
                                ];
    
                            } else {
                                $errors->add('false_response', __('Invalid image', 'wpfiles'));
                            }
    
                        } else {
                            $errors->add('false_response', __('No image library found on server', 'wpfiles'));
                        }
                    } else {
                        $errors->add('false_response', __('Invalid watermark type', 'wpfiles'));
                    }

                } else {
                    $errors->add('false_response', __('Invalid watermark type', 'wpfiles'));
                }
                
            } else {
                $errors->add('false_response', __('Watermark is not yet configured! Please set watermark first', 'wpfiles'));
            }

        } catch(Exception $e) {
            $errors->add('false_response', $e->getMessage());
        } 

        if (count($errors->get_error_messages())) {
            return $errors;
        }
    }

    /**
	 * Composite watermark image.
     * @since 1.0.0
	 * @param resource $dst_im
	 * @param resource $src_im
	 * @param int $dst_x
	 * @param int $dst_y
	 * @param int $src_x
	 * @param int $src_y
	 * @param int $src_w
	 * @param int $src_h
	 * @param int $pct
	 * @return void
	 */
	private function imagecopymergeAlpha( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct ) {
		// create a cut resource
		$cut = imagecreatetruecolor( $src_w, $src_h );

		// copy relevant section from background to the cut resource
		imagecopy( $cut, $dst_im, 0, 0, (int)$dst_x, (int)$dst_y, (int)$src_w, (int)$src_h );

		// copy relevant section from watermark to the cut resource
		imagecopy( $cut, $src_im, 0, 0, (int)$src_x, (int)$src_y, (int)$src_w, (int)$src_h );

		// insert cut resource to destination image
		imagecopymerge( $dst_im, $cut, (int)$dst_x, (int)$dst_y, 0, 0, (int)$src_w, (int)$src_h, (int)$pct );
	}

    /**
	 * Resize watermark image.
     * @since 1.0.0
	 * @param resource $image 
	 * @param int $width
	 * @param int $height
	 * @param array	$info
	 * @return resource
	 */
	private function resizeWatermark( $image, $width, $height, $info ) {
        try {
            $new_image = imagecreatetruecolor( $width, $height );

            // check if this image is PNG, then set if transparent
            if ( $info[2] === 3 ) {
                imagealphablending( $new_image, false );
                imagesavealpha( $new_image, true );
                imagefilledrectangle( $new_image, 0, 0, $width, $height, imagecolorallocatealpha( $new_image, 255, 255, 255, 127 ) );
            }

            imagecopyresampled( $new_image, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1] );

            return $new_image;
        } catch (\Throwable $th) {
            return $image;
        }
	}

    /**
	 * Get image resource accordingly to mimetype.
	 * @since 1.0.0
	 * @param string $filepath
	 * @param string $mimeType
	 * @return resource
	 */
	private function getImageResource( $filepath, $mimeType ) {
        
        try {
            switch ( $mimeType ) {
                case 'image/jpeg':
                case 'image/pjpeg':
                    $image = imagecreatefromjpeg( $filepath );
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif( $filepath );
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp( $filepath );
                    break;
                case 'image/png':
                    $image = imagecreatefrompng( $filepath );
                    if ( is_resource( $image ) )
                        imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
                    break;
                default:
                    $image = false;
            }

            if ( is_resource( $image ) ) {
                imagealphablending( $image, false );
                imagesavealpha( $image, true );
            }
        } catch(Exception $e) {
            $image = false;
        } 

		return $image;

	}

    /**
	 * Calculate image coordinates for watermark.
     * @since 1.0.0
	 * @param int $image_width Image width
	 * @param int $image_height	Image height
	 * @param int $watermark_width Watermark width
	 * @param int $watermark_height	Watermark height
	 * @return array
	 */
	private function calculateImageCoordinates( $image_width, $image_height, $watermark_width, $watermark_height ) {
		switch ( $this->settings['watermark_position'] ) {
			case 'top-left':
				$dest_x = $dest_y = 0;
				break;

			case 'top-center':
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = 0;
				break;

			case 'top-right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = 0;
				break;

			case 'mid-left':
				$dest_x = 0;
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
				break;

			case 'mid-right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
				break;

			case 'bottom-left':
				$dest_x = 0;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom-center':
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom-right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'mid-center':
			default:
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
		}

		// $dest_x += (int) ( $image_width * $this->settings['watermark_x_axis'] / 100 );
		// $dest_y += (int) ( $image_height * $this->settings['watermark_y_axis'] / 100 );

        $dest_x += (int) $this->settings['watermark_x_axis'];
		$dest_y += (int) $this->settings['watermark_y_axis'];

		return array( $dest_x, $dest_y );
	}
    
    /**
	 * Calculate watermark dimensions.
     * @since 1.0.0
	 * @param int $image_width Image width
	 * @param int $image_height Image height
	 * @param int $watermark_width Watermark width
	 * @param int $watermark_height	Watermark height
	 * @return array
	 */
	private function calculateWatermarkDimensions( $image_width, $image_height, $watermark_width, $watermark_height ) {
		// scale
		if ( $this->settings['watermark_scale_value'] ) {
			$ratio = $image_width * $this->settings['watermark_scale_value'] / 100 / $watermark_width;

			$width = (int) ( $watermark_width * $ratio );
			$height = (int) ( $watermark_height * $ratio );

			// if watermark scaled height is bigger then image watermark
			if ( $height > $image_height ) {
				$width = (int) ( $image_height * $width / $height );
				$height = $image_height;
			}
		} else {
            // original
			$width = $watermark_width;
			$height = $watermark_height;
		}

		return array( $width, $height );
	}

    /**
     * Check which extension is available and set it.
     * @since 1.0.0
     * @access public
     */
	public function checkExtensions() {

		$ext = null;

		if ( $this->checkImagick() ) {
			$this->extensions['imagick'] = 'ImageMagick';
			$ext = 'imagick';
		}

		if ( $this->checkGd() && !$ext) {
			$this->extensions['gd'] = 'GD';

			if ( is_null( $ext ) )
				$ext = 'gd';
		}

		$this->extension = $ext;	
	}

    /**
     * Check whether ImageMagick extension is available.
     * @since 1.0.0
     * @access public
     */
	public function checkImagick() {

		// check Imagick's extension and classes
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) || ! class_exists( 'ImagickPixel', false ) ) {
            return false;
        }

		// check version
		if ( version_compare( phpversion( 'imagick' ), '2.2.0', '<' ) ) {
            //return false;
        }

		// check for deep requirements within Imagick
		if ( ! defined( 'imagick::COMPOSITE_OVERLAY' ) || ! defined( 'Imagick::INTERLACE_PLANE' ) || ! defined( 'imagick::FILTER_CATROM' ) || ! defined( 'Imagick::CHANNEL_ALL' ) )
			return false;

		// check methods
		if ( array_diff( array( 'clear', 'destroy', 'valid', 'getimage', 'writeimage', 'getimagegeometry', 'getimageformat', 'setimageformat', 'setimagecompression', 'setimagecompressionquality', 'scaleimage' ), get_class_methods( 'Imagick' ) ) )
			//return false;

		return true;

	}

    /**
     * Check whether GD extension is available.
     * @since    1.0.0
     * @access   public
     */
	public function checkGd( $args = array() ) {
		// check extension
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) )
			return false;

		return true;
	}

    /**
     * Skip watermark image size
     * @since 1.0.0
     * @param string $size
     * @return bool true/false
     */
    public static function skipWatermarkImageSize($settings, $size = '')
    {
        if (empty($size)) {
            return false;
        }

        if ("all" === $settings['watermark_image_sizes'] || ("custom" === $settings['watermark_image_sizes'] && count((array)$settings['watermark_image_sizes_manual']) == 0)) {
            return false;
        }

        return is_array($settings['watermark_image_sizes_manual']) && !in_array($size, $settings['watermark_image_sizes_manual']);
    }

    /**
     * Auto watermark
     * @since 1.0.0
     * @return int|mixed
     */
    public function isAutoWatermarkEnabled()
    {
        $autoWatermark = $this->settings['auto_watermark'];

        if (!isset($autoWatermark)) {
            $autoWatermark = 1;
        }

        return $autoWatermark;
    }

    /**
     * Update rewatermark list
     * @since 1.0.0
     * @param string $attachment_id
     * @param string $key
     */
    public function updateRewatermarkList($attachment_id, $key = 'wpfiles-rewatermark-list')
    {

        $rewatermarkList = get_option($key);

        if (!empty($rewatermarkList) && count($rewatermarkList) > 0) {

            $key = array_search($attachment_id, $rewatermarkList);

            if ($rewatermarkList) {
                unset($rewatermarkList[$key]);
            }

            $rewatermarkList = array_values($rewatermarkList);
        }

        if (empty($rewatermarkList) || 0 === count($rewatermarkList)) {
            delete_option($key);
        } else {
            update_option($key, $rewatermarkList, false);
        }

    }

    /**
     * Return compressed attachment
     * @since 1.0.0
     * @param int $id
     * @return array
     */
    public static function getCompressedAttachment($id, $column = null)
    {
        $compressedData = get_post_meta($id, Wp_Files_Compression::$compressedMetaKey, true);

        if($compressedData && isset($compressedData['stats']) && is_array($compressedData['stats'])) {
            if($column && isset($compressedData['stats'][$column])) {
                return $compressedData['stats'][$column];
            } else if(!$column) {
                return $compressedData['stats'];
            }
        }

        return null;
    }

    /**
     * Get the watermark status for attachment.
     * @since 1.0.0
     * @param int $id
     * @return string
     */
    public function attachmentWatermarkStatus($id)
    {
        $watermark_data = get_post_meta($id, Wp_Files_Compression::$watermarkMetaKey, true);

        if($watermark_data && is_array($watermark_data)) {
            return $watermark_data;
        } else {
            return null;
        }
    }
}
