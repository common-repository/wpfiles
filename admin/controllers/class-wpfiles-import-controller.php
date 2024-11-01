<?php
class Wp_Files_Import_Controller
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
     * Import & Export api routes
     * @since 1.0.0
     * @return void
     */
    public function routes()
    {
        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/media-plugins',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'index'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/media-plugin-import',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'startImport'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/import-insert-folder',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'addFolders'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/after-import',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'afterImport'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/import-wpfiles-content',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'import'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'settings/delete-plugin-data',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'deletePluginData'),
                'permission_callback' => function () {
                    $capability = is_multisite() ? 'manage_network' : 'manage_options';
                    return current_user_can($capability);
                }
            )
        );
    }

    /**
     * Return other plugins to be Import
     * @since 1.0.0
     * @return JSON
     */
    public function index()
    {
        $enhancedFolder = count(Wp_Files_Import::enhancedFolders(0, true));

        $wpmlfFolder = count(Wp_Files_Import::wpmlfFolders(0, true));

        $wpmfFolder = count(Wp_Files_Import::wpmfFolders(0, true));

        $realMediaFolder = count(Wp_Files_Import::realMediaFolders(-1, true));

        $filebirdFolder = count(Wp_Files_Import::FilebirdFolders(0, true));
        
        $happyFiles = count(Wp_Files_Import::HappyFilesFolders(0, true));

        wp_send_json_success([
            'countEnhancedFolder' => $enhancedFolder,
            'countWpmlfFolder' => $wpmlfFolder,
            'countWpmfFolder' => $wpmfFolder,
            'countRealMediaFolder' => $realMediaFolder,
            'countHappyFiles' => $happyFiles,
            'countFilebirdFolder' => $filebirdFolder,
        ]);
    }

    /**
     * Start import plugin process
     * @since 1.0.0
     * @param  mixed $request
     * @return JSON
     */
    public function startImport($request)
    {
        $site = sanitize_text_field($request->get_param('site'));

        $site = isset($site) ? sanitize_text_field($site) : '';

        $this->checkStatus($site);

        $folders = $this->getAllData($site, true);

        $count = count($folders);

        $folders = array_chunk($folders, 20);

        wp_send_json_success(array(
            'folders' => $folders,
            'count' => $count,
            'site' => $site
        ));
    }

    /**
     * Detect plugin somewhere is already imported
     * @since 1.0.0
     * @param  string $site
     * @return JSON
    */
    private function checkStatus($site)
    {
        //Clean data
        Wp_Files_Import::clean();

        if ($site == 'wpmlf') {
            if (get_option('wpfiles_updated_from_wpmlf', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        } elseif ($site == 'enhanced') {
            if (get_option('wpfiles_updated_from_enhanced', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        } elseif ($site == 'wpmf') {
            if (get_option('wpfiles_updated_from_wpmf', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        } elseif ($site == 'happyfiles') {
            if (get_option('wpfiles_updated_from_happyfiles', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        } elseif ($site == 'realmedia') {
            if (get_option('wpfiles_updated_from_realmedia', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        } elseif ($site == 'filebird') {
            if (get_option('wpfiles_updated_from_filebird', '0') == '1') {
                wp_send_json_success(array(
                    'message' => __('Already imported', 'wpfiles')
                ));
            }
        }
    }

    /**
     * Return relevant plugin folders
     * @since 1.0.0
     * @param  string $site
     * @param  boolean $flat
     * @return Array
    */
    public function getAllData($site, $flat = false)
    {
        $folders = array();

        if ($site == 'enhanced') {
            $folders = Wp_Files_Import::enhancedFolders(0, $flat);
        } elseif ($site == 'wpmlf') {
            $folders = Wp_Files_Import::wpmlfFolders(0, $flat);
        } elseif ($site == 'wpmf') {
            $folders = Wp_Files_Import::wpmfFolders(0, $flat);
        } elseif ($site == 'realmedia') {
            $folders = Wp_Files_Import::realMediaFolders(-1, $flat);
            foreach ($folders as $k => $folder) {
                $folders[$k]->parent = $folder->parent == '-1' ? 0 : $folder->parent;
            }
        } elseif ($site == 'filebird') {
            $folders = Wp_Files_Import::FilebirdFolders(0, $flat);
            foreach ($folders as $k => $folder) {
                $folders[$k]->parent = $folder->parent == '-1' ? 0 : $folder->parent;
            }
        } elseif ($site == 'happyfiles') {
            $folders = Wp_Files_Import::HappyFilesFolders(0, $flat);
        }

        return $folders;
    }

    /**
     * Add folders to our system
     * @since 1.0.0
     * @param  mixed $request
     * @return JSON
    */
    public function addFolders($request)
    {
        $site = sanitize_text_field($request->get_param('site'));

        $folders = Wp_Files_Helper::sanitizeArray($request->get_param('folders'));

        $site = isset($site) ? sanitize_text_field($site) : '';

        $folders = isset($folders) ? Wp_Files_Helper::sanitizeArray($folders) : '';

        foreach ((array)$folders as $k => $folder) {
            if (\is_array($folder)) {
                $folder = json_decode(json_encode($folder));
            }
            $new_parent = $folder->parent;
            if ($new_parent > 0) {
                $new_parent = get_option('new_term_id_' . $new_parent);
            }
            $inserted = Wp_Files_Media::save($folder->title, $new_parent);
            update_option('new_term_id_' . $folder->id, $inserted);
            $atts = $this->getAttachmentsOfFolder($site, $folder);
            Wp_Files_Media::attachFoldersToPosts($atts, $inserted);
        }

        wp_send_json_success();
    }

    /**
     * Return attachments of relevant folder
     * @since 1.0.0
     * @param  string $site
     * @param  mixed $folder
     * @return ARRAY
    */
    public function getAttachmentsOfFolder($site, $folder)
    {
        global $wpdb;

        $attachments = array();

        if (is_array($folder)) {
            $folder = json_decode(json_encode($folder));
        }

        if ($site == 'happyfiles') {
            $attachments = $wpdb->get_col($wpdb->prepare('SELECT object_id FROM %1$s WHERE term_taxonomy_id = %2$d', $wpdb->term_relationships, $folder->term_taxonomy_id));
        } elseif ($site == 'enhanced') {
            $attachments = $wpdb->get_col($wpdb->prepare('SELECT object_id FROM %1$s WHERE term_taxonomy_id = %2$d', $wpdb->term_relationships, $folder->term_taxonomy_id));
        } elseif ($site == 'realmedia') {
            $folder_table = $wpdb->prefix . 'realmedialibrary_posts';
            $attachments = $wpdb->get_col($wpdb->prepare('SELECT attachment FROM %1$s WHERE fid = %2$d', $folder_table, $folder->id));
        } elseif ($site == 'wpmlf') {
            $folder_table = $wpdb->prefix . 'mgmlp_folders';
            $sql = $wpdb->prepare("select ID from {$wpdb->prefix}posts 
                        LEFT JOIN $folder_table ON({$wpdb->prefix}posts.ID = $folder_table.post_id)
                        LEFT JOIN {$wpdb->prefix}postmeta AS pm ON (pm.post_id = {$wpdb->prefix}posts.ID) 
                        where post_type = 'attachment' 
                        and folder_id = %s
                        AND pm.meta_key = '_wp_attached_file' 
                        order by post_date desc", $folder->id);
            $attachments = $wpdb->get_col($sql);
        } elseif ($site == 'filebird') {
            $folder_table = $wpdb->prefix . 'fbv_attachment_folder';
            $attachments = $wpdb->get_col($wpdb->prepare('SELECT attachment_id FROM %1$s WHERE folder_id = %2$d', $folder_table, $folder->id));
        } elseif ($site == 'wpmf') {
            $attachments = $wpdb->get_col($wpdb->prepare('SELECT object_id FROM %1$s WHERE term_taxonomy_id = %2$d', $wpdb->term_relationships, $folder->term_taxonomy_id));
        }

        return $attachments;
    }

    /**
     * After import
     * @since 1.0.0
     * @param  mixed $request
     * @return JSON
    */
    public function afterImport($request)
    {
        $reload = false;

        $site = sanitize_text_field($request->get_param('site'));

        $count = sanitize_text_field($request->get_param('count'));

        $installation_step = sanitize_text_field($request->get_param('installation_step'));

        $site = isset($site) ? sanitize_text_field($site) : '';

        $count = isset($count) ? sanitize_text_field($count) : '';

        $this->deleteTermData($site);

        if ($site == 'wpmf') {
            update_option('wpfiles_updated_from_wpmf', '1');
        } elseif ($site == 'wpmlf') {
            update_option('wpfiles_updated_from_wpmlf', '1');
        } elseif ($site == 'realmedia') {
            update_option('wpfiles_updated_from_realmedia', '1');
        } elseif ($site == 'filebird') {
            update_option('wpfiles_updated_from_filebird', '1');
        } elseif ($site == 'happyfiles') {
            update_option('wpfiles_updated_from_happyfiles', '1');
        } elseif ($site == 'enhanced') {
            update_option('wpfiles_updated_from_enhanced', '1');
        }

        $mess = sprintf(__('Congratulations! We have successfully imported %d folders into WPFiles', 'wpfiles'), $count);

        if ($installation_step == "import-data") {
            Wp_Files_Settings::installationStep($installation_step);
            $reload = true;
        }

        //Clean data
        Wp_Files_Import::clean();
        
        wp_send_json_success(array(
            'message' => $mess,
            'reload' => $reload
        ));
    }

    /**
     * Delete some useless term meta data
     * @since 1.0.0
     * @return void
    */
    private function deleteTermData()
    {
        global $wpdb;
        $wpdb->delete($wpdb->termmeta, array('meta_key' => 'old_term_id'));
    }

    /**
     * Export WPFiles media folders and its assigned attachments
     * @since 1.0.0
     * @return void
    */
    public function export()
    {
        set_time_limit(0);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No action, nonce is not required
        if (!isset($_GET['action']) || $_GET['action'] !== 'wp_files_export') {
            return;
        }

        if (!defined('WXR_VERSION')) {
            define('WXR_VERSION', '1.2');
        }

        $export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : 'folders';

        $folders = Wp_Files_Tree::getAllData(null, true);

        $filename = 'WPFiles-.' . date('Y-m-d H:i:s') . '.xml';

        header('Content-Description: File Transfer');

        header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

        header('Content-Disposition: attachment; filename=' . $filename);

        echo '<?xml version="1.0" encoding="' . esc_html(get_bloginfo('charset')) . "\" ?>\n";
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Render to XML file
        ?>
        <?php the_generator('export'); ?>
        <rss version="2.0" xmlns:excerpt="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/">
            <channel>
                <title><?php bloginfo('name'); ?></title>
                <link><?php bloginfo('url'); ?></link>
                <description><?php bloginfo('description'); ?></description>
                <pubDate><?php echo date('D, d M Y H:i:s +0000'); ?></pubDate>
                <language><?php bloginfo('language'); ?></language>
                <wp:wxr_version><?php echo WXR_VERSION; ?></wp:wxr_version>
                <wp:base_site_url><?php echo Wp_Files_Helper::wxrSiteUrl(); ?></wp:base_site_url>
                <wp:base_blog_url><?php bloginfo('url'); ?></wp:base_blog_url>
                <?php
                if (in_array($export_type, ["folders", "all"])) {
                    //Folder hirerchcy
                    foreach ($folders as $folder) {
                        ?>
                        <folder>
                            <id><?php esc_html_e($folder['id'], 'wpfiles'); ?></id>
                            <text><?php esc_html_e($folder['text'], 'wpfiles'); ?></text>
                            <parent><?php esc_html_e($folder['parent'], 'wpfiles'); ?></parent>
                            <color><?php esc_html_e($folder['color'], 'wpfiles'); ?></color>
                            <starred><?php esc_html_e($folder['starred'], 'wpfiles'); ?></starred>
                        </folder>
                    <?php
                    }
                    //Folder relations
                    $relations = Wp_Files_Media::getFolderMediaRelations();

                    foreach ($relations as $attachment => $folder) {
                        ?>
                        <relation>
                            <folder><?php esc_html_e(implode(",", $folder), 'wpfiles'); ?></folder>
                            <attachment><?php esc_html_e(implode(",", $attachment), 'wpfiles'); ?></attachment>
                        </relation>
                <?php
                    }
                }
                if (in_array($export_type, ["settings", "all"])) {
                    //Pending for future
                }
        ?>
            </channel>
        </rss>
        <?php
        die();
    }

    /**
     * Import WPFiles media folders and its assigned attachments
     * @since 1.0.0
     * @param mixed $request
     * @return JSON
    */
    public function import($request)
    {
        try {
            $params = $request->get_file_params('file');

            if (isset($params['file']) and is_uploaded_file($params['file']['tmp_name'])) {
                $pathinfo = pathinfo($params['file']['name']);

                $extension = strtolower($pathinfo['extension']);

                $extensions_allowed = array("xml");

                $types_allowed = array("application/xml", "text/xml");

                if (!in_array($params['file']["type"], $types_allowed)) {
                    wp_send_json_error([
                        "success" => false,
                        "message" => __("Selected file type is not allowed", 'wpfiles')
                    ]);
                }

                if (in_array($extension, $extensions_allowed)) {
                    $import_data = $this->parse($params['file']["tmp_name"]);

                    Wp_Files_Media::importFolders($import_data);

                    wp_send_json_success([
                        "success" => false,
                        "message" => __("Imported successfully", 'wpfiles')
                    ]);
                } else {
                    wp_send_json_error([
                        "success" => false,
                        "message" => __("Not allowed", 'wpfiles')
                    ]);
                }
            } else {
                if (!is_uploaded_file($params['file']['tmp_name'])) {
                    wp_send_json_error([
                        "success" => false,
                        "message" => __("Please choose file to upload", 'wpfiles')
                    ]);
                }
            }
        } catch (Exception $e) {
            wp_send_json_error([
                "success" => false,
                "message" => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete plugin data
     * @since 1.0.0
     * @param mixed $request
     * @return JSON
    */
    public function deletePluginData($request)
    {
        $plugin = sanitize_text_field($request->get_param('plugin'));

        $response = Wp_Files_Import::deletePluginData($plugin);

        $installation_step = sanitize_text_field($request->get_param('installation_step'));

        if ($installation_step == "import-data") {
            Wp_Files_Settings::installationStep($installation_step);
            $response['reload'] = true;
        } else {
            $response['reload'] = false;
        }

        wp_send_json_success($response);
    }

    /**
     * Parse a WXR file
     * @since 1.0.0
     * @param string $file Path to WXR file for parsing
     * @return array Information gathered from the WXR file
     */
    public function parse($file)
    {
        $parser = new WXR_Parser();
        return $parser->parse($file);
    }
}
