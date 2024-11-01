<?php

class Wp_Files_Settings_Controller
{
    /**
     * Class instance
     * @since 1.0.0
    */
    protected static $instance = null;

    /**
     * Return class instance only
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
     * Settings api routes
     * @since 1.0.0
     * @return void
     */
    public function routes()
    {
        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/load-settings',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'index'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/save-settings',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'saveSettings'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/save-color',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'saveColor'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/delete-color',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'deleteColor'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/recreate-tables',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'reCreateTable'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/clear-all-data',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'clearAllData'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/get-site-health',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'getSiteHealth'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/add-newsletter-email',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'addNewsletterEmail'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/submit-feedback',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'submitFeedback'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/update-site-status',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_api_status'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/fetch-cdn-status',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'fetchCdnStatus'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/purge-cache',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'purgeCache'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/load-google-fonts',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'loadGoogleFonts'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/dismiss-notice',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'dismissNotice'),
                'permission_callback' => '__return_true'
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/installation-step',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'installationStep'),
                'permission_callback' => '__return_true'
            )
        );
    }

    /**
     * Return WPFiles settings
     * @since 1.0.0
     * @return JSON
     */
    public function index()
    {
        $settings = (array) Wp_Files_Settings::loadSettings();

        $settings['exts'] = (array) Wp_Files_Settings::loadUploadFileTypes();

        $settings['image_all_sizes'] = Wp_Files_Helper::getImageDimensions();

        $settings['active_language'] = Wp_Files_Settings::getActiveTranslation();

        $settings['cpts'] = get_post_types(
            array(
                'public'   => true,
                '_builtin' => false,
            ),
            'objects',
            'and'
        );

        $settings['mime_types'] = get_allowed_mime_types();

        $settings['lazy_disable_classes'] = implode("\n", $settings['lazy_disable_classes']);

        $settings['lazy_disable_urls'] = implode("\n", $settings['lazy_disable_urls']);

        wp_send_json_success([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Save WPFiles settings
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function saveSettings($request)
    {
        $input = Wp_Files_Helper::sanitizeArray($request->get_param('settings'));

        $watermark_svg = sanitize_text_field($request->get_param('watermark_svg'));

        $input = isset($input) ? Wp_Files_Helper::sanitizeArray($input) : '';

        $settings = (array) Wp_Files_Settings::loadSettings();

        Wp_Files_Settings::saveSettings($input);

        if ($watermark_svg) {
            Wp_Files_Settings::saveWatermark($watermark_svg);
        }

        Wp_Files_Settings::saveUploadFileTypes($input);

        if (isset($settings['api_key']) && isset($input['api_key'])) {
            if ($settings['api_key'] != $input['api_key'] && $input['api_key']) {
                $stats = new Wp_Files_Stats($settings);
                //Update api status
                $stats->update_api_status();
            }
        }

        wp_send_json_success([
            'success' => true,
            "message" => __('Settings saved successfully', 'wpfiles')
        ]);
    }

    /**
     * Add/update folder color
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function saveColor($request)
    {
        $color = sanitize_text_field($request->get_param('color'));
        $action = sanitize_text_field($request->get_param('action'));
        if ($action == "update") {
            $id = sanitize_text_field($request->get_param('id'));
            Wp_Files_Settings::saveColor($color, $action, $id);
        } else {
            Wp_Files_Settings::saveColor($color, $action);
        }
        wp_send_json_success();
    }

    /**
     * Delete folder color
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function deleteColor($request)
    {
        $id = sanitize_text_field($request->get_param('id'));
        Wp_Files_Settings::deleteColor($id);
        wp_send_json_success();
    }

    /**
     * Check if required tables exist and create them if not.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function reCreateTable($request)
    {
        Wp_Files_Settings::reCreateTable();

        wp_send_json_success([
            'success' => true,
            "message" => __("Missing tables recreated successfully", 'wpfiles')
        ]);
    }

    /**
     * Wipe all data and reset to default factory settings.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function clearAllData($request)
    {
        $status = Wp_Files_Settings::clearAllData();

        if ($status) {
            wp_send_json_success(array(
                'success' => true,
                'message' => __('Successfully cleared', 'wpfiles')
            ));
        } else {
            wp_send_json_error(array(
                'success' => false,
                'message' => __('Please try again', 'wpfiles')
            ));
        }
    }

    /**
     * Monitor your system's status and see the details of key components.
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function getSiteHealth($request)
    {
        $response = Wp_Files_Settings::getSiteHealth();

        $text = Wp_Files_Settings::getSiteHealthCopyToClipboardData($response);

        wp_send_json_success([
            'success' => true,
            "response" => $response,
            "text" => $text
        ]);
    }

    /**
     * Save newsletter email in WPFiles account for marketing campaigns
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function addNewsletterEmail($request)
    {
        $email = sanitize_text_field($request->get_param('email'));

        //Remote api instance
        $api = new Wp_Files_Api(Wp_Files_Helper::getAccountApikey());

        $redirect = admin_url('admin.php');

        if (! is_email($email)) {
            wp_send_json_error([
                'message' => __('Invalid email address', 'wpfiles')
            ]);
        } else {
            $response = $api->addNewsLetterEmail($email, $redirect, true);

            if (is_wp_error($response)) {
                $code = is_numeric($response->get_error_code()) ? $response->get_error_code() : null;
                wp_send_json_error(
                    array(
                        'message' => $response->get_error_message(),
                    ),
                    $code
                );
            }

            $status = (object)json_decode($response['body']);

            // Too many requests.
            if (is_null($status) || wp_remote_retrieve_response_code($response) === 429) {
                wp_send_json_error(
                    array(
                        'message' =>  __('Too many requests, please try again in a moment', 'wpfiles')
                    ),
                    200
                );
            } else {
                if ($status->success) {
                    Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'newsletter-id', $status->data->id);
                    wp_send_json_success([
                        'message' => __("Great! Check your inbox to confirm your subscription", 'wpfiles')
                    ]);
                } else {
                    Wp_Files_Settings::dismissNotice(WP_FILES_PREFIX . 'newsletter-hide');
                    wp_send_json_error([
                        'message' => is_array($status->message) ? implode(' ', $status->message) : $status->message
                    ]);
                }
            }
        }
    }

    /**
     * Submit feedback when deactivate plugin
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function submitFeedback($request)
    {
        $name = sanitize_text_field($request->get_param('name'));

        $email = sanitize_text_field($request->get_param('email'));

        $feedback = sanitize_text_field($request->get_param('feedback'));

        $rating = sanitize_text_field($request->get_param('rating'));

        $type = sanitize_text_field($request->get_param('type'));

        $option = sanitize_text_field($request->get_param('option'));

        $response = Wp_Files_Settings::submitFeedback($rating);

        if ($response || $type == "feedback") {
            //Remote api instance
            $api = new Wp_Files_Api(Wp_Files_Helper::getAccountApikey());

            $response = $api->submitFeedback($email, $rating, $feedback, $name, $type, $option, true);

            if (is_wp_error($response)) {
                if ($type == "rating") {
                    Wp_Files_Settings::deleteFeedback();
                }
                $code = is_numeric($response->get_error_code()) ? $response->get_error_code() : null;
                wp_send_json_error(
                    array(
                        'message' => $response->get_error_message(),
                    ),
                    $code
                );
            }

            $status = (object)json_decode($response['body']);

            // Too many requests.
            if (is_null($status) || wp_remote_retrieve_response_code($response) === 429) {
                if ($type == "rating") {
                    Wp_Files_Settings::deleteFeedback();
                }
                wp_send_json_error(
                    array(
                        'message' =>  __('Too many requests, please try again in a moment', 'wpfiles')
                    ),
                    200
                );
            } else {
                if ($status->success) {
                    Wp_Files_Helper::addOrUpdateOption($type == "feedback" ? WP_FILES_PREFIX . 'feedback-id' : WP_FILES_PREFIX . 'rating-id', $status->data->id);
                    wp_send_json_success([
                        'message' => __("Feedback submitted successfully", 'wpfiles')
                    ]);
                } else {
                    if ($type == "rating") {
                        Wp_Files_Settings::deleteFeedback();
                    }
                    wp_send_json_error([
                        'message' => is_array($status->message) ? implode(' ', $status->message) : $status->message
                    ]);
                }
            }
        } else {
            wp_send_json_error(
                array(
                    'message' => __("Feedback already submitted", 'wpfiles')
                ),
                200
            );
        }
    }

    /**
     * Purge cdn cache
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function purgeCache($request)
    {
        //Remote api instance
        $api = new Wp_Files_Api(Wp_Files_Helper::getAccountApikey());

        $response = $api->purgeCache(true);

        if (is_wp_error($response)) {
            $code = is_numeric($response->get_error_code()) ? $response->get_error_code() : null;
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                $code
            );
        }

        $status = (object)json_decode($response['body']);

        // Too many requests.
        if (is_null($status) || wp_remote_retrieve_response_code($response) === 429) {
            wp_send_json_error(
                array(
                    'message' =>  __('Too many requests, please try again in a moment', 'wpfiles')
                ),
                200
            );
        } else {
            if ($status->success) {
                wp_send_json_success([
                    'message' => __("Cache purged successfully", 'wpfiles')
                ]);
            } else {
                wp_send_json_error([
                    'message' => $status->message
                ]);
            }
        }
    }

    /**
     * Load google fonts
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function loadGoogleFonts($request)
    {
        $key = WP_FILES_PREFIX . 'load-google-fonts';

        $fonts = wp_cache_get($key, WP_FILES_CACHE_PREFIX);

        if (!$fonts) {
            $fonts = file_get_contents(WP_FILES_PLUGIN_DIR.'admin/json/fonts.json');
            if ($fonts) {
                wp_cache_set($key, $fonts, WP_FILES_CACHE_PREFIX, 172800);
            } else {
                $fonts = [
                    'items' => []
                ];
            }
        }

        wp_send_json_success(json_decode($fonts));
    }

    /**
     * Request to update your account/api status
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function update_api_status($request)
    {
        $settings = (array) Wp_Files_Settings::loadSettings();

        if ($settings['api_key']) {
            $stats = new Wp_Files_Stats($settings);

            $response = $stats->update_api_status(true);

            if ($response->success) {
                wp_send_json_success([
                    "message" => __("API status updated successfully", 'wpfiles')
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response->message
                ]);
            }
        } else {
            wp_send_json_error([
                'message' => __("Please connect your WPFiles account first", 'wpfiles')
            ]);
        }
    }

    /**
     * Update your cdn status from WPFiles account
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function fetchCdnStatus($request)
    {
        $settings = (array) Wp_Files_Settings::loadSettings();

        //Remote api instance
        $api = new Wp_Files_Api($settings['api_key']);

        if ($settings['api_key']) {
            $response = $api->fetch_cdn_status(true);

            if (is_wp_error($response)) {
                $code = is_numeric($response->get_error_code()) ? $response->get_error_code() : null;
                wp_send_json_error(
                    array(
                        'message' => $response->get_error_message(),
                    ),
                    $code
                );
            }

            $result = (object)json_decode($response['body']);

            // Too many requests.
            if (is_null($result) || wp_remote_retrieve_response_code($response) === 429) {
                wp_send_json_error(
                    array(
                        'message' =>  __('Too many requests, please try again in a moment', 'wpfiles')
                    ),
                    200
                );
            } else {
                if ($result->success) {
                    //Save api status
                    Wp_Files_Settings::saveSiteStatus($settings, $result->data);

                    //Disable cdn module if suspended or inactive from WPFiles account
                    if ($settings['cdn'] && ((int)$result->data->website->cdn == 0 || (int)$result->data->cdn_active == 0)) {
                        Wp_Files_Settings::updateSetting(WP_FILES_PREFIX .'cdn', 0);
                    } else {
                        Wp_Files_Settings::updateSetting(WP_FILES_PREFIX .'cdn', 1);
                    }

                    wp_send_json_success($result->data);
                }
            }
        } else {
            wp_send_json_error([
                'message' => __("Please connect your WPFiles account first", 'wpfiles')
            ]);
        }
    }

    /**
     * This is generic function ti dismiss wordpress notices
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function dismissNotice($request)
    {
        $notice = sanitize_text_field($request->get_param('notice'));
        $parameter_1 = sanitize_text_field($request->get_param('parameter_1'));
        Wp_Files_Settings::dismissNotice($notice, $parameter_1);
        wp_send_json_success();
    }

    /**
     * Save plugin installation step
     * @since 1.0.0
     * @param $request
     * @return JSON
     */
    public function installationStep($request)
    {
        $step = sanitize_text_field($request->get_param('step'));
        Wp_Files_Settings::installationStep($step);
        wp_send_json_success();
    }
}
