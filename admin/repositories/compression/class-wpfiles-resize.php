<?php

/**
 * Class that contain every thing about resize
 */
class Wp_Files_Resize
{
    /**
     * Resizing
     * @since 1.0.0
     * @var bool
     */
    public $resizeEnabled = false;

    /**
     * Width of resizing attachments
     * @since 1.0.0
     * @var int
     */
    public $maximumWidth = 0;

    /**
     * Height of resizing attachments
     * @since 1.0.0
     * @var int
     */
    public $maximumHeight = 0;

    /**
     * settings
     * @since 1.0.0
     * @var object $settings
     */
    private $settings;

    /**
     * Constructor
     * @since 1.0.0
     * @var object $settings
     */
    public function __construct($settings)
    {
        $this->settings = $settings;

        $this->initialize();

        $this->maybeDisableModule();
    }

    /**
     * Auto resizing
     * @since 1.0.0
     * @param int $attachmentID 
     * @param mixed $attachmentMeta 
     * @return mixed 
     */
    public function autoResize($attachmentID, $attachmentMeta)
    {
        if (empty($attachmentID) || !wp_attachment_is_image($attachmentID)) {
            return $attachmentMeta;
        }

        $savings = array(
            'size_before' => 0,
            'bytes'       => 0,
            'size_after'  => 0,
        );

        if (!$this->shouldResize($attachmentID, $attachmentMeta)) {
            return $attachmentMeta;
        }

        $filePath = Wp_Files_Helper::getAttachedFile($attachmentID);

        $originalFileSize = filesize($filePath);

        $resize = $this->performResize($filePath, $originalFileSize, $attachmentID, $attachmentMeta);

        if (!$resize || $resize['filesize'] >= $originalFileSize) {
            update_post_meta($attachmentID, WP_FILES_PREFIX . 'resize_savings', $savings);
            return $attachmentMeta;
        }

        $replaced = $this->replaceOriginalImage($filePath, $resize, $attachmentMeta);

        if ($replaced) {

            clearstatcache();

            $fileSize = filesize($filePath);

            $savings['bytes']       = $originalFileSize > $fileSize ? $originalFileSize - $fileSize : 0;
            
            $savings['size_before'] = $originalFileSize;
            
            $savings['size_after']  = $fileSize;

            if (!empty($savings)) {
                update_post_meta($attachmentID, WP_FILES_PREFIX . 'resize_savings', $savings);
            }

            $attachmentMeta['width']  = !empty($resize['width']) ? $resize['width'] : $attachmentMeta['width'];
            
            $attachmentMeta['height'] = !empty($resize['height']) ? $resize['height'] : $attachmentMeta['height'];

            do_action('wp_files_image_resized', $attachmentID, $savings);
        }

        return $attachmentMeta;
    }

    /**
     * Start resize
     * @since 1.0.0
     * @param string $filePath 
     * @param int    $originalFileSize 
     * @param int    $attachmentID 
     * @param array  $attachmentMeta 
     * @param bool   $unlink 
     * @return array|bool|false 
     */
    public function performResize($filePath, $originalFileSize, $attachmentID, $attachmentMeta = array(), $unlink = true)
    {
        $sizes = apply_filters(
            'wp_files_resize_sizes',
            array(
                'width'  => $this->maximumWidth,
                'height' => $this->maximumHeight,
            ),
            $filePath,
            $attachmentID
        );

        $data = image_make_intermediate_size($filePath, $sizes['width'], $sizes['height']);

        if (empty($data['file']) || is_wp_error($data)) {
            
            if ($this->tryGdFallback()) {
                $data = image_make_intermediate_size($filePath, $sizes['width'], $sizes['height']);
            }

            if (empty($data['file']) || is_wp_error($data)) {
                return false;
            }
        }

        $resizePath = path_join(dirname($filePath), $data['file']);
       
        if (!file_exists($resizePath)) {
            return false;
        }

        $data['file_path'] = $resizePath;

        $fileSize = filesize($resizePath);

        $data['filesize'] = $fileSize;

        if ($fileSize > $originalFileSize) {

            if ($unlink) {
                $this->maybeUnlink($resizePath, $attachmentMeta);
            }

        }

        return $data;
    }

    /**
     * Replace original file
     * @since 1.0.0
     * @param string $filePath 
     * @param mixed  $resized  
     * @param array  $meta 
     * @return bool
     */
    private function replaceOriginalImage($filePath, $resized, $meta = array())
    {
        $replaced = @copy($resized['file_path'], $filePath);

        $this->maybeUnlink($resized['file_path'], $meta);

        return $replaced;
    }

    /**
     * May be unlink
     * @since 1.0.0
     * @param string $path
     * @param array $attachmentMeta
     * @return bool
     */
    private function maybeUnlink($path, $attachmentMeta)
    {
        if (empty($path)) {
            return true;
        }

        if (empty($attachmentMeta['sizes'])) {
            @unlink($path);
        }

        $unlink = true;

        $pathParts = pathinfo($path);

        $filename   = !empty($pathParts['basename']) ? $pathParts['basename'] : $pathParts['filename'];
        
        if (!empty($attachmentMeta['sizes'])) {
            foreach ($attachmentMeta['sizes'] as $imageSize) {
                if (false === strpos($imageSize['file'], $filename)) {
                    continue;
                }
                $unlink = false;
            }
        }

        if ($unlink) {
            @unlink($path);
        }

        return true;
    }

    /**
     * Try to fallback to GD.
     * @since 1.0.0
     * @return mixed
     */
    private function tryGdFallback()
    {
        if (!function_exists('gd_info')) {
            return false;
        }

        return add_filter(
            'wp_image_editors',
            function ($editors) {
                $editors = array_diff($editors, array('WP_Image_Editor_GD'));
                array_unshift($editors, 'WP_Image_Editor_GD');
                return $editors;
            }
        );
    }

    /**
     * Should resize
     * @since 1.0.0
     * @param string $attachmentID 
     * @param string $attachmentMeta
     * @return bool
    */
    public function shouldResize($attachmentID = '', $attachmentMeta = '')
    {
        if ($this->settings['image_resizing'] == "default" || (0 === $this->maximumWidth && 0 === $this->maximumHeight)) {
            return false;
        }

        $shouldResize = $this->checkShouldResize($attachmentID, $attachmentMeta);

        return apply_filters('wp_files_resize_uploaded_image', $shouldResize, $attachmentID, $attachmentMeta);
    }

    /**
     * Checks should resize
     * @since 1.0.0
     * @param string $attachmentID 
     * @param string $attachmentMeta 
     * @return bool
     */
    private function checkShouldResize($attachmentID = '', $attachmentMeta = '')
    {

        if (!Wp_Files_Helper::fileExists($attachmentID)) {
            return false;
        }

        $filePath = get_attached_file($attachmentID);

        if (!empty($filePath)) {
            if (strpos($filePath, 'noresize') !== false) {
                return false;
            }
        }

        $attachmentMeta = empty($attachmentMeta) ? wp_get_attachment_metadata($attachmentID) : $attachmentMeta;

        if (empty($attachmentMeta['width']) || empty($attachmentMeta['height'])) {
            return false;
        }

        $imagesize = array($attachmentMeta['width'], $attachmentMeta['height']);

        $threshold = (int) apply_filters('bigImageSizeThreshold', 2560, $imagesize, $filePath, $attachmentID);

        if (!$threshold) {
            return false;
        }

        $mime = get_post_mime_type($attachmentID);

        if ('image/gif' === $mime) {
            $animated = get_post_meta($attachmentID, WP_FILES_PREFIX . 'animated');

            if ($animated) {
                return false;
            }
        }

        $supported = in_array($mime, Wp_Files_Compression::$mimeTypes, true);

        $supported = apply_filters('wp_files_recompress_mime_supported', $supported, $mime);

        if (!empty($mime) && !$supported) {
            return false;
        }

        $oldWidth  = $attachmentMeta['width'];

        $oldHeight = $attachmentMeta['height'];

        $maximumWidth  = !empty($this->settings['image_resizing_width']) ? $this->settings['image_resizing_width'] : 0;

        $maximumHeight = !empty($this->settings['image_resizing_height']) ? $this->settings['image_resizing_height'] : 0;

        if (($oldWidth > $maximumWidth && $maximumWidth > 0) || ($oldHeight > $maximumHeight && $maximumHeight > 0)) {
            return true;
        }

        return false;
    }

    /**
     * Initialize
     * @since 1.0.0
     * @param bool 
     */
    public function initialize($skipCheck = false)
    {
        if (!is_user_logged_in() || (!is_admin() && !$skipCheck)) {
            return;
        }

        $this->resizeEnabled = $this->settings['image_resizing'] == "custom" && $this->settings['image_resizing_width'] && $this->settings['image_resizing_height'] ? true : false;

        $this->maximumWidth = !empty($this->settings['image_resizing_width']) ? $this->settings['image_resizing_width'] : 0;

        $this->maximumHeight = !empty($this->settings['image_resizing_height']) ? $this->settings['image_resizing_height'] : 0;

        $current_screen = function_exists('get_current_screen') ? get_current_screen() : false;

        if (!empty($current_screen) && !$skipCheck) {
            if (!in_array($current_screen->base, Wp_Files_Compression::$externalPages, true) && false === strpos($current_screen->base, 'page_compression')) {
                return;
            }
        }
    }

    /**
     * We do not need this module on WordPress 5.3+
     * @since 1.0.0
     * @return void
     */
    public function maybeDisableModule()
    {
        global $wp_version;
        $this->resizeEnabled = version_compare($wp_version, '5.3.0', '<');
    }

    /**
     * Return Filename.
     * @since 1.0.0
     * @param string $filename
     * @return string
     */
    public function file_name($filename)
    {
        if (empty($filename)) {
            return $filename;
        }

        return $filename . 'tmp';
    }
}
