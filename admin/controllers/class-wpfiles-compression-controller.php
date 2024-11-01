<?php
/**
 * Compression/Watermark handle requests
*/
class Wp_Files_Compression_Controller
{
    /**
     * Routes
     * @since    1.0.0
     * @return   void
    */
    public function routes()
    {
        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/process-request',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'processCompressionRequest'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/scan-images',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'scanImages'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/compress-one',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'compressOne'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/recompress-image',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'recompressImage'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/restore-image',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'restoreImage'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/ignore-bulk-image',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'ignoreBulkImage'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/delete-recompress-list',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'deleteRecompressList'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/get-stats',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'getStats'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/remove-from-skip-list',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'removeFromSkipList'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/load-directories',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'loadDirectories'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/image-list',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'imageList'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/init-scan',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'initScan'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/verify-directory-image',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'verifyDirectoryImage'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/directory-compression-finish',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'directoryCompressionFinish'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/directory-compression-cancel',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'directoryCompressionCancel'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/get-dir-compression-stats',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'getDirCompressionStats'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/get-all-stats',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'getAllStats'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/add-watermark',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'addWatermark'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/directory-watermark-start',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'directoryWatermarkStart'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/directory-watermark-check-step',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'directoryWatermarkCheckStep'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/scan-watermark-images',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'scanWatermarkImages'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'compression/delete-rewatermark-list',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'deleteRewatermarkList'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );
    }

    /**
     * Bulk compression
     * @since    1.0.0
     * @return   json
    */
    public function processCompressionRequest($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->processCompressionRequest($request);

        wp_send_json_success(array());
    }

    /**
     * Scan images that need to compress
     * @since    1.0.0
     * @return   json
    */
    public function scanImages($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->scanImages($request);

        wp_send_json_success(array());
    }

    /**
     * Compress one image
     * @since    1.0.0
     * @return   json
    */
    public function compressOne($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->compressOne($request);

        wp_send_json_success(array());
    }

    /**
     * Recompress image
     * @since    1.0.0
     * @return   json
    */
    public function recompressImage($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->recompressImage($request);

        wp_send_json_success(array());
    }

    /**
     * Restore image
     * @since 1.0.0
     * @return json
    */
    public function restoreImage($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->restoreImage($request);

        wp_send_json_success(array());
    }

    /**
     * Ignore bulk image
     * @since    1.0.0
     * @return   json
    */
    public function ignoreBulkImage($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->ignoreBulkImage($request);

        wp_send_json_success(array());
    }

    /**
     * Delete recompress list
     * @since    1.0.0
     * @return   json
    */
    public function deleteRecompressList($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->deleteRecompressList($request);

        wp_send_json_success(array());
    }

    /**
     * Get stats
     * @since    1.0.0
     * @return   json
    */
    public function getStats($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->getStats($request);

        wp_send_json_success(array());
    }

    /**
     * Remove from skip list
     * @since    1.0.0
     * @return   json
    */
    public function removeFromSkipList($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->removeFromSkipList($request);

        wp_send_json_success(array());
    }

    

    /**
     * Directory listing
     * @since    1.0.0
     * @return   json
    */
    public function loadDirectories($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->loadDirectories($request);

        wp_send_json_success(array());
    }

    /**
     * Directory image listing
     * @since    1.0.0
     * @return   json
    */
    public function imageList($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->imageList($request);

        wp_send_json_success(array());
    }

    /**
     * Directory compression start
     * @since    1.0.0
     * @return   json
    */
    public function initScan($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->initScan($request);

        wp_send_json_success(array());
    }

    /**
     * Verify directory image
     * @since    1.0.0
     * @return   json
    */
    public function verifyDirectoryImage($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->verifyDirectoryImage($request);

        wp_send_json_success(array());
    }

    /**
     * Directory compression finish
     * @since    1.0.0
     * @return   json
    */
    public function directoryCompressionFinish($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->directoryCompressionFinish($request);

        wp_send_json_success(array());
    }

    /**
     * Directory compression cancel
     * @since    1.0.0
     * @return   json
    */
    public function directoryCompressionCancel($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->directoryCompressionCancel($request);

        wp_send_json_success(array());
    }

    /**
     * Directory compression stats
     * @since    1.0.0
     * @return   json
    */
    public function getDirCompressionStats($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->getDirCompressionStats($request);

        wp_send_json_success(array());
    }

    /**
     * All stats
     * @since    1.0.0
     * @return   json
    */
    public function getAllStats($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $result = $compression->getAllStats();

        wp_send_json_success($result);
    }

    /**
     * Add watermark
     * @since    1.0.0
     * @return   json
    */
    public function addWatermark($request)
    {
        $compression = new Wp_Files_Compression_Requests();

        $compression->addWatermark(sanitize_text_field($request->get_param('attachment_id')));

        wp_send_json_success();
    }

    /**
     * Directory watermark start
     * @since    1.0.0
     * @return   json
    */
    public function directoryWatermarkStart($request)
    {
        $watermark = new Wp_Files_Compression_Requests();

        $watermark->directoryWatermarkStart($request);

        wp_send_json_success(array());
    }

    /**
     * Watermark check step
     * @since    1.0.0
     * @return   json
    */
    public function directoryWatermarkCheckStep($request)
    {
        $watermark = new Wp_Files_Compression_Requests();

        $watermark->directoryWatermarkCheckStep($request);

        wp_send_json_success(array());
    }

    /**
     * Scan watermark images
     * @since    1.0.0
     * @return   json
    */
    public function scanWatermarkImages($request)
    {
        $watermark = new Wp_Files_Compression_Requests();

        $watermark->scanWatermarkImages($request);

        wp_send_json_success(array());
    }

    /**
     * Delete rewatermark list
     * @since    1.0.0
     * @return   json
    */
    public function deleteRewatermarkList($request)
    {
        $watermark = new Wp_Files_Compression_Requests();

        $watermark->deleteRewatermarkList($request);

        wp_send_json_success(array());
    }
}
