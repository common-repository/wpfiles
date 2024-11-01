<?php
class Wp_Files_Directory
{
    /**
     * Flag to check if dir compression table exist.
     * @since 1.0.0
     * @var $table_exist
     */
    public static $table_exist;

    /**
     * Total Stats for the image optimization.
     * @since 1.0.0
     * @var $stats
     */
    public $stats;

    /**
     * Directory scanner.
     * @since 1.0.0
     * @var
     */
    public $scanner;

    /**
     * Contains a list of optimized images.
     * @since 1.0.0
     * @var $optimized_images
     */
    public $optimized_images;

    /**
     * settings
     * @since 1.0.0
     * @var object $settings
     */
    private $settings;

    /**
     * compression
     * @since 1.0.0
     * @var object $compression
     */
    private $compression;

    /**
     * Constructor
     * @since 1.0.0
     * @param object $settings
     */
    public function __construct($settings = null)
    {
        $this->settings = $settings;

        $this->compression = new Wp_Files_Compression($settings);

        $this->compression->init($this->settings);

        if (!$this->scanner) {
            $this->scanner = new Wp_Files_Directory_Scanner();
        }

        if (!is_admin()) {
            return;
        }
    }

    /**
     * Directory/File list
     * @since 1.0.0
     * @param $request 
     * @return JSON
     */
    public function loadDirectories($request)
    {
        if (!current_user_can('upload_files') || !is_user_logged_in()) {
            wp_send_json_error(__('Not authenticated', 'wpfiles'));
        }

        $directory = $request->get_param('dir');

        $directory = isset($directory) ? sanitize_text_field($directory) : '';

        $dir_tree = $this->getDirectoryTree($directory);

        if (!is_array($dir_tree)) {
            wp_send_json_error(__('Not authenticated', 'wpfiles'));
        }

        wp_send_json($dir_tree);
    }

    /**
     * Directory tree data
     * @since 1.0.0
     * @param string $directory
     * @return array|bool
     */
    private function getDirectoryTree($directory = null)
    {
        $root = realpath($this->getRootPath());

        $attachmentDirectory = strlen($directory) >= 1 ? path_join($root, $directory) : $root . $directory;

        if (!$root || false === $attachmentDirectory || 0 !== strpos($attachmentDirectory, $root)) {
            return false;
        }

        $supportedImage = array(
            'gif',
            'jpg',
            'jpeg',
            'png',
        );

        if (file_exists($attachmentDirectory) && is_dir($attachmentDirectory)) {

            $files = scandir($attachmentDirectory);

            if (!empty($files)) {
                $files = preg_grep('/^([^.])/', $files);
            }

            $returnDirectory = substr($attachmentDirectory, strlen($root));

            natcasesort($files);

            if (count($files) !== 0 && !$this->skipDirectory($attachmentDirectory)) {

                $tree = array();

                foreach ($files as $file) {

                    $htmlRel  = htmlentities(ltrim(path_join($returnDirectory, $file), '/'));

                    $htmlName = htmlentities($file);

                    $extension       = preg_replace('/^.*\./', '', $file);

                    $filePath = path_join($attachmentDirectory, $file);

                    if (!file_exists($filePath) || '.' === $file || '..' === $file) {
                        continue;
                    }

                    if (!is_dir($filePath) && (!in_array($extension, $supportedImage, true) || $this->isMediaLibraryFile($filePath))) {
                        continue;
                    }

                    $skipPath = $this->skipDirectory($filePath);

                    $tree[] = array(
                        'title'        => $htmlName,
                        'key'          => $htmlRel,
                        'folder'       => is_dir($filePath),
                        'lazy'         => !$skipPath,
                        'checkbox'     => true,
                        'unselectable' => $skipPath, 
                    );
                }

                return $tree;
            }
        }

        return array();
    }

    /**
     * Root path 
     * @since 1.0.0
     * @return string
     */
    public function getRootPath()
    {
        if (is_main_site()) {

            $contentPath = explode('/', wp_normalize_path(WP_CONTENT_DIR));

            $rootPath = explode('/', get_home_path());

            $end = min(count($contentPath), count($rootPath));

            $i = 0;

            $commonPath = array();

            while ($contentPath[$i] === $rootPath[$i] && $i < $end) {
                $commonPath[] = $contentPath[$i];
                $i++;
            }

            return implode('/', $commonPath);

        }

        $uploadDir = wp_upload_dir();

        return $uploadDir['basedir'];
    }

    /**
     * Skip directory
     * @since 1.0.0
     * @param string $path
     * @return bool
    */
    public function skipDirectory($path)
    {
        $adminDirectory = $this->getAdminPath();

        $includedDirectory = ABSPATH . WPINC;

        $uploadDirectory = wp_upload_dir();

        $baseDirectory   = $uploadDirectory['basedir'];

        $skip = false;

        if ($path && false !== strpos($path, $baseDirectory . '/sites')) {

            $pathArray = explode('/', str_replace($baseDirectory . '/sites' . '/', '', $path));

            if (
                is_array($pathArray) && count($pathArray) > 1
                && is_numeric($pathArray[1]) && $pathArray[1] > 1900 && $pathArray[1] < 2100 
            ) {
                $skip = true;
            }

        } elseif ($path && false !== strpos($path, $baseDirectory)) {

            $pathArray = explode('/', str_replace($baseDirectory . '/', '', $path));

            if (count($pathArray) >= 1 && is_numeric($pathArray[0]) && $pathArray[0] > 1900 && $pathArray[0] < 2100 && (1 === count($pathArray) || (is_numeric($pathArray[1]) && $pathArray[1] > 0 && $pathArray[1] < 13))
            ) {
                $skip = true;
            }

        } elseif ($path && (false !== strpos($path, $adminDirectory)) || false !== strpos($path, $includedDirectory)) {
            $skip = true;
        }

        $skip = apply_filters('wp_files_skip_folder', $skip, $path);

        return $skip;
    }

    /**
     * Admin directory.
     * @since 1.0.0
     * @return string
     */
    private function getAdminPath()
    {
        $adminPath = rtrim(str_replace(get_bloginfo('url') . '/', ABSPATH, get_admin_url()), '/');

        $adminPath = apply_filters('wp_files_get_admin_path', $adminPath);

        return $adminPath;
    }

    /**
     * Check if the attachment file is media library file
     * @since 1.0.0
     * @param string $path 
     * @return bool
     */
    private function isMediaLibraryFile($path)
    {
        $uploadDirectory  = wp_upload_dir();

        $uploadPath = $uploadDirectory['path'];

        $baseDirectory = dirname($path);

        if ($baseDirectory === $uploadPath) {
            return true;
        }

        return false;
    }

    /**
     * Image list
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function imageList($request)
    {
        if (!current_user_can('upload_files')) {
            $this->returnError(__('Not authenticated', 'wpfiles'));
        }

        $compressionPath = Wp_Files_Helper::sanitizeTextOrArrayField($request->get_param('compression_path'));

        if (empty($compressionPath)) { 
            $this->returnError(__('Selected directory is empty', 'wpfiles'));
        }

        try {
            $files = $this->getImageList($compressionPath);
        } catch (\Exception $e) {
            $this->returnError($e->getMessage());
        }

        if (empty($files)) {
            $this->returnError(__('No images found in the selected directory', 'wpfiles'));
        }

        wp_send_json_success(count($files));
    }

    /**
     * Return the image list 
     * @since 1.0.0 
     * @param string|array $paths 
     * @return array
     * @throws \Exception
     */
    private function getImageList($paths = '')
    {
        if (!is_array($paths)) {
            $this->returnError(__('Some problem occurred with the selected directories', 'wpfiles'));
        }

        $count     = 0;

        $images    = array();

        $values    = array();
        
        $timestamp = gmdate('Y-m-d H:i:s');

        wp_raise_memory_limit('image');

        ini_set('memory_limit', '-1');

        $validatedDirectories = array();

        foreach ($paths as $relativePath) {

            $path = trim($this->getRootPath() . '/' . $relativePath);

            if (stripos($path, 'phar://') !== false) {
                continue;
            }

            if (!is_dir($path) && !$this->isMediaLibraryFile($path) && !strpos($path, '.bak')) {

                if (!$this->isImage($path)) {
                    continue;
                }

                if (in_array($path, $images, true)) {
                    continue;
                }

                if (!in_array(dirname($relativePath), $validatedDirectories, true)) {
                    if (!$this->validatePath(dirname($relativePath))) {
                        continue;
                    }
                    $validatedDirectories[] = dirname($relativePath);
                }

                $images[] = $path;

                $images[] = md5($path);

                $images[] = @filesize($path);  

                $images[] = @filectime($path);

                $images[] = $timestamp;

                $values[] = '(%s, %s, %d, %d, %s)';

                $count++;

                if ($count >= 5000) {
                    $count = 0;
                    $this->storeImages($values, $images);
                    $images = $values = array();
                }

                continue;
            }

            $baseDirectory = realpath(rawurldecode($path));

            if (!$baseDirectory) {
                $this->returnError(__('Not authenticated', 'wpfiles'));
            }

            if (!in_array($relativePath, $validatedDirectories, true)) {
                if (!$this->validatePath($relativePath)) {
                    continue;
                }
                $validatedDirectories[] = $relativePath;
            }

            $filteredDir = new Wp_Files_Iterator(new RecursiveDirectoryIterator($baseDirectory));

            $iterator = new RecursiveIteratorIterator($filteredDir, RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($iterator as $file) {

                if (basename($file) === '..' || basename($file) === '.') {
                    continue;
                }

                if (!$file->isFile()) {
                    continue;
                }

                $filePath = $file->getPathname();

                if ($this->isImage($filePath) && !$this->isMediaLibraryFile($filePath) && strpos($file, '.bak') === false) {
                    $images[] = $filePath;
                    $images[] = md5($filePath);
                    $images[] = $file->getSize();
                    $images[] = @filectime($filePath); 
                    $images[] = $timestamp;
                    $values[] = '(%s, %s, %d, %d, %s)';
                    $count++;
                }

                if ($count >= 5000) {
                    $count = 0;
                    $this->storeImages($values, $images);
                    $images = $values = array();
                }
            }
        }

        if (empty($images) || 0 === $count) {
            return array();
        }

        $this->storeImages($values, $images);

        return $this->getScannedImages();
    }

    /**
     * Is image
     * @since 1.0.0
     * @param string $path
     * @return bool
     */
    private function isImage($path)
    {
        if (!file_exists($path) || !$this->isImageFromExtension($path)) {
            return false;
        }

        if (false !== stripos($path, 'phar://')) {
            return false;
        }

        $b = @getimagesize($path);

        if (!$b || empty($b)) {
            return false;
        }

        $imageType = $b[2];

        if (in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF))) {
            return true;
        }

        return false;
    }

    /**
     * Validate attachment extension
     * @since 1.0.0
     * @param string $path  
     * @return bool 
     */
    private function isImageFromExtension($path)
    {
        $supportedImage = array('gif', 'jpg', 'jpeg', 'png');
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)); 

        if (in_array($extension, $supportedImage, true)) {
            return true;
        }

        return false;
    }

    /**
     * Image ids and path for last scanned images
     * @since 1.0.0
     * @return array
     */
    public function getScannedImages()
    {
        global $wpdb;

        $results = $wpdb->get_results("SELECT id, path, orig_size FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images WHERE last_scan = (SELECT MAX(last_scan) FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images )  GROUP BY id ORDER BY id", ARRAY_A);

        if (is_wp_error($results)) {
            error_log(sprintf(__('WPFiles Query Error in %s at %s: %s', 'wpfiles'), __FILE__, __LINE__, $results->get_error_message()));
            $results = array();
        }

        return $results;
    }

    /**
     * Store images
     * @since 1.0.0
     * @param array $values
     * @param array $images
     */
    private function storeImages($values, $images)
    {
        global $wpdb;

        $query = $this->makeQuery($values, $images);

        $wpdb->query($query);
    }

    /**
     * Build query
     * @since 1.0.0
     * @param array $values 
     * @param array $images  
     * @return bool|string
     */
    private function makeQuery($values, $images)
    {
        if (empty($images) || empty($values)) {
            return false;
        }

        global $wpdb;

        $values = implode(',', $values);

        $query = "INSERT INTO {$wpdb->prefix}wpf_dir_optimize_watermark_images (path, path_hash, orig_size, file_time, last_scan) VALUES $values ON DUPLICATE KEY UPDATE image_size = IF( file_time < VALUES(file_time), NULL, image_size ), file_time = IF( file_time < VALUES(file_time), VALUES(file_time), file_time ), last_scan = VALUES( last_scan )";
        
        $query = $wpdb->prepare($query, $images); 

        return $query;
    }

    /**
     * Validate path
     * @since 1.0.0
     * @param string $pathToCheck
     * @return bool
     */
    private function validatePath($pathToCheck)
    {
        $isValid = true;

        while ($isValid && dirname($pathToCheck) !== $pathToCheck) {

            $pathContents = $this->getDirectoryTree($pathToCheck);

            if (empty($pathContents)) {
                return false;
            }

            $isValid = false;

            foreach ($pathContents as $treeData) {
                if (false !== strpos($treeData['key'], $pathToCheck) && !$treeData['unselectable']) {
                    $isValid = true;
                    break;
                }
            }

            if (!$isValid) {
                $pathToCheck = dirname($pathToCheck);
            } else {
                break;
            }
        }

        return $isValid;
    }

    /**
     * Return error
     * @since 1.0.0
     * @param string $message
     * @return JSON
     */
    private function returnError($message)
    {
        wp_send_json_error(
            array(
                'message' => sprintf('<p>%s</p>', esc_html($message)),
            )
        );
    }

    /**
     * Directory Compression: Start compression.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function initScan($request)
    {
        $this->scanner->initScan();

        wp_send_json_success();
    }

    /**
     * Directory Compression: verify directory image
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function verifyDirectoryImage($request)
    {
        $urls         = $this->getScannedImages();

        $currentStep = absint(sanitize_text_field($request->get_param('step')));

        $this->scanner->updateCurrentStep($currentStep);

        if (isset($urls[$currentStep])) {
            $this->optimizeImage((int) $urls[$currentStep]['id']);
        }

        wp_send_json_success();
    }

    /**
     * optimize image
     * @since 1.0.0
     * @param int $id 
     * @return JSON
     */
    private function optimizeImage($id)
    {
        global $wpdb;

        $error = '';

        if ($id < 1) {
            $error = esc_html__('Incorrect attachment id', 'wpfiles');
            wp_send_json_error($error);
        }

        if (!Wp_Files_Subscription::is_pro()) {

            $shouldContinue = Wp_Files_Compression::checkBulkLimit(false, 'directory_bulk_limit');

            if (!$shouldContinue) {
                wp_send_json_error(
                    array(
                        'message'    => sprintf(
                            __(' Upgrade to Pro for bulk compression with no limit. Free users can compress %1$d attachments per click', 'wpfiles'),
                            Wp_Files_Compression::$maximumFreeBulk
                        ),
                        'error'    => 'dir_compression_limit_exceeded',
                        'continue' => false,
                    )
                );
            }
        }

        $scannedImages = $this->getUncompressedImages();

        $image = $this->getImage($id, '', $scannedImages);

        if (empty($image)) {
            wp_send_json_success(array('skipped' => true));
        }

        $path = $image['path'];

        if (false !== stripos($path, 'phar://')) {
            wp_send_json_error(
                array(
                    'error' => esc_html_e('Phar PHP Object Injection detected', 'wpfiles'),
                    'image' => array(
                        'id' => $id,
                    ),
                )
            );
        }

        $results = $this->compression->startCompression($path);

        if (is_wp_error($results)) {
            $error = $results->get_error_message();
        } elseif (empty($results['data'])) {
            $error = esc_html__("Image could not be compressed", 'wpfiles');
        }

        if (!empty($error)) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wpf_dir_optimize_watermark_images SET error=%s WHERE id=%d LIMIT 1",
                    $error,
                    $id
                )
            ); 

            wp_send_json_error(
                array(
                    'error' => $error,
                    'image' => array(
                        'id' => $id,
                    ),
                )
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wpf_dir_optimize_watermark_images SET error=NULL, image_size=%d, file_time=%d, lossy=%d, meta=%d WHERE id=%d LIMIT 1",
                $results['data']->after_size,
                @filectime($path),
                Wp_Files_Subscription::is_pro() && $this->settings['super_compression'],
                $this->settings['strip_exif'],
                $id
            )
        );

        Wp_Files_Compression::updateCompressionCount('directory_bulk_limit');
    }

    /**
     * Get uncompressed images.
     * @since 1.0.0
     * @return array
     */
    public function getUncompressedImages()
    {
        global $wpdb;

        $condition = '(image_size IS NULL || image_size = 0)';

        if (Wp_Files_Subscription::is_pro() && $this->settings['super_compression']) {
            $condition .= ' OR lossy <> 1';
        }

        if ($this->settings['strip_exif']) {
            $condition .= ' OR meta <> 1';
        }

        $results = $wpdb->get_results("SELECT id, path, orig_size FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images WHERE {$condition} && last_scan = (SELECT MAX(last_scan) FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images )  GROUP BY id ORDER BY id", ARRAY_A);

        if (is_wp_error($results)) {
            error_log(sprintf(__('WPFiles Query Error in %s at %s: %s', 'wpfiles'), __FILE__, __LINE__, $results->get_error_message()));
            $results = array();
        }

        return $results;
    }

    /**
     * Get image
     * @param string $id     
     * @param string $path    
     * @param array  $images  
     * @return array  
     */
    private function getImage($id, $path, $images)
    {
        foreach ($images as $key => $val) {
            if (!empty($id) && (int) $val['id'] === $id) {
                return $images[$key];
            } elseif (!empty($path) && $val['path'] === $path) {
                return $images[$key];
            }
        }

        return array();
    }

    /**
     * Directory Compression: Finish compression.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function directoryCompressionFinish($request)
    {
        $items = (int)absint(sanitize_text_field($request->get_param('items')));

        $failed = (int)absint(sanitize_text_field($request->get_param('failed')));

        $skipped = (int)absint(sanitize_text_field($request->get_param('skipped')));

        if ($failed > 0) {
            set_transient('wpfiles-dir-scan-failed-items', $failed, 60 * 5);
        }

        if ($skipped > 0) {
            set_transient('wpfiles-dir-scan-skipped-items', $skipped, 60 * 5);
        }

        set_transient('wpfiles-show-dir-scan-notice', $items, 60 * 5);

        $this->scanner->resetScan();

        wp_send_json_success();
    }

    /**
     * Directory Compression: Cancel compression.
     * @since 1.0.0
     * @return JSON
     */
    public function directoryCompressionCancel()
    {
        $this->scanner->resetScan();

        wp_send_json_success();
    }

    /**
     * Combine stats
     * @since 1.0.0
     * @param array $stats  
     * @return array
     */
    public function combineStats($stats_instance)
    {
        $dasharray = 125.663706144;

        /**************Compression****************/
        // Get the total/Compressed attachment count.
        $total_attachments = $stats_instance->count_of_attachments_for_compression + $stats_instance->dir_compression_stats['total'];
        $total_images      = $stats_instance->stats['total_images'] + $stats_instance->dir_compression_stats['total'];

        $compressed     = $stats_instance->compressed_count + $stats_instance->dir_compression_stats['optimized'];
        $savings     = !empty($stats_instance->stats) ? $stats_instance->stats['bytes'] + $stats_instance->dir_compression_stats['bytes'] : $stats_instance->dir_compression_stats['bytes'];
        /**************Compression****************/

        /**************Watermark****************/
        $total_watermark_attachments = $stats_instance->count_of_attachments_for_watermark + $stats_instance->dir_watermark_stats['total'];
        $watermarked_count     = $stats_instance->watermarked_count + $stats_instance->dir_watermark_stats['total'];
        /**************Watermark****************/

        $size_before = ! empty( $stats_instance->stats ) ? $stats_instance->stats['size_before'] + $stats_instance->dir_watermark_stats['orig_size'] : $stats_instance->dir_watermark_stats['orig_size'];

        $percent     = $size_before > 0 ? ( $savings / $size_before ) * 100 : 0;

        // Store the stats in array.
        return array(
            'total_count'   => $total_attachments,
            'compressed_count' => $compressed,
            'savings'       => size_format($savings),
            'saving_bytes'       => $savings,
            'bandwidth_saved'       => size_format($savings * 10000),
            'percent'       => round($percent, 2),
            'image_count'   => $total_images,
            'total_watermark_attachments'   => $total_watermark_attachments,
            'watermarked_count'   => $watermarked_count,
            'dash_offset'   => $total_attachments > 0 ? $dasharray - ($dasharray * ($compressed / $total_attachments)) : $dasharray,
            'tooltip_text'  => !empty($total_images) ? sprintf(__("You have compressed %d images in total", 'wpfiles'), $total_images) : '',
        );
    }

    /**
     * Should continue
     * @since 1.0.0
     * @return bool
     */
    public static function shouldContinue()
    {
        if (defined('DOING_AJAX') && DOING_AJAX && isset($_SERVER['HTTP_REFERER']) && preg_match('#^' . network_admin_url() . '#i', wp_unslash($_SERVER['HTTP_REFERER']))) {
            return true;
        }

        if ((!is_main_site() || !is_network_admin()) && is_multisite()) {
            return false;
        }

        return true;
    }

    /**
     * Total stats
     * @since 1.0.0
     * @param bool $forceUpdate 
     * @return array Total stats.
     */
    public function totalStats($forceUpdate = false, $type = "compression")
    {
        $cacheKey = $type == "compression" ? WP_FILES_PREFIX . 'dir_total_compression_stats' : WP_FILES_PREFIX . 'dir_total_watermark_stats';
        
        if (!$forceUpdate) {
            $totalStats = wp_cache_get($cacheKey, WP_FILES_CACHE_PREFIX);
            if (false !== $totalStats) {
                return $totalStats;
            }
        }

        global $wpdb;

        $offset    = 0;

        $compressed = 0;

        $limit     = 1000;

        $images    = array();

        $continue  = true;

        while ($continue) {
            if($type == "compression") {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT path, image_size, orig_size FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images WHERE image_size != 0 ORDER BY `id` LIMIT %d, %d",
                        $offset,
                        $limit
                    ),
                    ARRAY_A
                ); 
            } else {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT path, image_size, orig_size FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images WHERE watermark = 1 ORDER BY `id` LIMIT %d, %d",
                        $offset,
                        $limit
                    ),
                    ARRAY_A
                ); 
            }

            if (!$results) {
                break;
            }

            $images  = array_merge($images, $results);
            $offset += $limit;
        }

        if($type == "compression") {

            $this->stats = array(
                'path'       => '',
                'image_size' => 0,
                'orig_size'  => 0,
            );

            if (!empty($images)) {
                foreach ($images as $im) {
                    foreach ($im as $key => $val) {
                        if ('path' === $key) {
                            $this->optimized_images[$val] = $im;
                            continue;
                        }
                        $this->stats[$key] += (int) $val;
                    }
                    $compressed++;
                }
            }

            if (!empty($this->stats) && !empty($this->stats['orig_size'])) {
                $this->stats['percent'] = (isset($this->stats['bytes']) && $this->stats['bytes'] > 0 ? number_format_i18n((($this->stats['bytes'] / $this->stats['orig_size']) * 100), 1) : 0);
                $this->stats['bytes']   = (isset($this->stats['orig_size']) && $this->stats['orig_size'] > $this->stats['image_size']) ? $this->stats['orig_size'] - $this->stats['image_size'] : 0;
                $this->stats['human'] = size_format($this->stats['bytes'], 1);
            } else {
                $this->stats['percent'] = 0;
                $this->stats['bytes'] = 0;
                $this->stats['human'] = '0 b';
            }

            $this->stats['optimized'] = $compressed;

            $this->stats['total']     = count($images);

        } else {
            $this->stats['total']     = count($images);
        }

        wp_cache_set($cacheKey, $this->stats, WP_FILES_CACHE_PREFIX);

        return $this->stats;
    }

    /*****************************************/
    /***************Watermark*****************/
    /*****************************************/

    /**
     * Get only images that need watermarking.
     * @since 1.0.0
     * @return array Array of images that require watermarking.
     */
    public function getUnwatermarkedImages()
    {
        global $wpdb;

        $condition = 'image_size IS NULL';

        $condition .= ' OR watermark <> 1';

        $results = $wpdb->get_results("SELECT id, path, orig_size FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images WHERE {$condition} && last_scan = (SELECT MAX(last_scan) FROM {$wpdb->prefix}wpf_dir_optimize_watermark_images )  GROUP BY id ORDER BY id", ARRAY_A); // Db call ok; no-cache ok.

        if (is_wp_error($results)) {
            error_log(sprintf(__('WPFiles Query Error in %s at %s: %s', 'wpfiles'), __FILE__, __LINE__, $results->get_error_message()));
            $results = array();
        }

        return $results;
    }

    /**
     * Directory Watermarking: Start watermarking.
     * @since 1.0.0
     * @return JSON
     */
    public function directoryWatermarkStart($request)
    {
        $this->scanner->initScan();

        wp_send_json_success();
    }

    /**
     * Directory Watermarking: Watermark step.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function directoryWatermarkCheckStep($request)
    {
        $urls         = $this->getScannedImages();

        $currentStep = absint(sanitize_text_field($request->get_param('step')));

        $this->scanner->updateCurrentStep($currentStep);

        if (isset($urls[$currentStep])) {
            $this->watermarkImage((int) $urls[$currentStep]['id']);
        }

        wp_send_json_success();
    }

    /**
     * Start watermarking
     * @since 1.0.0
     * @param int $id 
     * @return JSON
     */
    private function watermarkImage($id)
    {
        global $wpdb;

        $error = '';

        if ($id < 1) {
            $error = esc_html__('Incorrect attachment id', 'wpfiles');
            wp_send_json_error($error);
        }

        $scannedImages = $this->getUnwatermarkedImages();

        $image          = $this->getImage($id, '', $scannedImages);

        if (empty($image)) {
            wp_send_json_success(array('skipped' => true));
        }

        $path = $image['path'];

        if (false !== stripos($path, 'phar://')) {
            wp_send_json_error(
                array(
                    'error' => esc_html_e('Potential Phar PHP Object Injection detected', 'wpfiles'),
                    'image' => array(
                        'id' => $id,
                    ),
                )
            );
        }

        $results = $this->compression->startWatermarked($path);

        if (is_wp_error($results)) {
            $error = $results->get_error_message();
        } elseif (empty($results['data'])) {
            $error = esc_html__("Image could not be optimized", 'wpfiles');
        }

        if (!empty($error)) {

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wpf_dir_optimize_watermark_images SET error=%s WHERE id=%d LIMIT 1",
                    $error,
                    $id
                )
            ); 

            wp_send_json_error(
                array(
                    'error' => $error,
                    'image' => array(
                        'id' => $id,
                    ),
                )
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wpf_dir_optimize_watermark_images SET error=NULL, image_size=%d, file_time=%d, watermark=%d WHERE id=%d LIMIT 1",
                0,
                @filectime($path),
                1,
                $id
            )
        );
        
    }
}
