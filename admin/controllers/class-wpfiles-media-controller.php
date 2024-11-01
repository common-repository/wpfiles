<?php

class Wp_Files_Media_Controller
{
    /**
     * Routes
     * @since 1.0.0
     * @return void
    */
    public function routes()
    {
        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'get-all-data',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'getAllData'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'get-trashed-folders',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'getTrashedFolders'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        

        

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'get-folders',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'getAllFolders'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'create-folder',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'createFolder'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'save-folder',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'saveFolder'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'delete-folder-file',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'deleteItems'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'add-to-folder',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'addToFolder'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'move-to-folder',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'moveToFolder'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'restore-items',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'restoreItems'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'empty-bin',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'emptyBin'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'move-to-uncategorized',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'moveToUncategorized'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'move-to-root',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'moveToRoot'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'fetch-attachments',
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'fetchAttachments'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'shortcut-folder',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'shortcutFolder'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        

        

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'find-posts',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'findPosts'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );

        register_rest_route(
            WP_FILES_REST_API_PREFIX,
            'attach-media-to-post',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'attachMediaToPost'),
                'permission_callback' => function () {
                    return current_user_can('upload_files');
                }
            )
        );
    }

    /**
     * Update get posts clauses
     * @since 1.0.0
     * @param $clauses
     * @param $query
     * @return array
    */
    public function customizedPostsClauses($clauses, $query)
    {
        global $wpdb;

        if ($query->get("post_type") !== "attachment") {
            return $clauses;
        }

        if (Wp_Files_Helper::hasListMode() && !isset($_GET['wpf'])) {
            return $clauses;
        }

        $folder = $query->get('wpf');

        if (isset($_GET['wpf']) || $folder !== '') {
            $folder = isset($_GET['wpf']) ? (int)sanitize_text_field($_GET['wpf']) : (int)$folder;

            $table_name = $wpdb->prefix . 'wpf_attachment_folder';

            if (in_array($folder, [-1, 1])) {
                $attachment_ids = Wp_Files_Media::getFoldersTrashedAttachments();
                if (!empty($attachment_ids)) {
                    $clauses['where'] .= " AND {$wpdb->posts}.ID NOT IN(" . implode(',', $attachment_ids) . ")";
                }
                if ($folder == 1) {
                    $attachment_ids = Wp_Files_Media::getStarredItems();
                    if (!empty($attachment_ids)) {
                        $clauses['where'] .= " AND {$wpdb->posts}.ID IN(" . implode(',', $attachment_ids) . ")";
                    } else {
                        $clauses['where'] .= " AND {$wpdb->posts}.ID < 0"; // Show no starred attachments
                    }
                }
                return $clauses;
            } elseif ($folder === 2) {
                $attachment_ids = Wp_Files_Media::getFoldersTrashedAttachments();
                $clauses['where'] = " AND {$wpdb->posts}.post_type = 'attachment' AND {$wpdb->posts}.post_status = 'trash'";
                if (!empty($attachment_ids)) {
                    $clauses['where'] .= " AND {$wpdb->posts}.ID NOT IN(" . implode(',', $attachment_ids) . ")";
                }
                return $clauses;
            } elseif ($folder === 0) {
                $clauses = Wp_Files_Media::getUserFolderMediaRelations($clauses);
            } else {
                $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$table_name} AS wpfa ON wpfa.attachment_id = {$wpdb->posts}.ID AND wpfa.folder_id = %d AND wpfa.deleted_at IS NULL", $folder);
                $clauses['where'] .= " AND wpfa.folder_id IS NOT NULL";
            }
        }

        return $clauses;
    }

    /**
     * Add attachment
     * @since 1.0.0
     * @param $post_id
     * @return void
    */
    public function addAttachment($post_id)
    {
        $folder = ((isset($_REQUEST['wpf'])) ? sanitize_text_field($_REQUEST['wpf']) : '');
        $path = ((isset($_REQUEST['path'])) ? sanitize_text_field($_REQUEST['path']) : '');
        if (in_array($folder, [-1, 0, 1, 2])) {
            $parent = 0;
        } else {
            $parent = $folder;
        }
        if ($path) {
            $path = explode('/', ltrim(rtrim($path, '/'), '/'));
            foreach ($path as $k => $v) {
                $parent = Wp_Files_Media::save($v, $parent);
            }
        }
        Wp_Files_Media::attachFoldersToPosts($post_id, $parent);
    }

    /**
     * Set attachment Args
     * @since 1.0.0
     * @param $query
     * @return array
    */
    public function setQueryAttachmentsArgs($query)
    {
        if (empty($_REQUEST['query'])) {
            if (isset($_REQUEST['wpf'])) {
                $folder = sanitize_text_field($_REQUEST['wpf']);

                if (is_array($folder)) {
                    $folder = array_map('intval', $folder);
                } else {
                    $folder = intval($folder);
                }

                $query['wpf'] = $folder;
            }

            $query['action'] = "query-attachments";

            $query['sortFolderBy'] = isset($_REQUEST['sortFolderBy']) ? sanitize_text_field($_REQUEST['sortFolderBy']) : "name_asc";

            $query['order'] = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : "DESC";

            $query['s'] = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : "";

            $query['orderby'] = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : "title";

            $query['ignore'] = isset($_REQUEST['ignore']) ? sanitize_text_field($_REQUEST['ignore']) : "";

            $query['posts_per_page'] = isset($_REQUEST['posts_per_page']) ? sanitize_text_field($_REQUEST['posts_per_page']) : 40;

            $query['trash'] = isset($_REQUEST['trash']) && sanitize_text_field($_REQUEST['trash']) ? true : false;

            if (isset($_REQUEST['year'])) {
                $query['year'] = sanitize_text_field($_REQUEST['year']);
            }

            if (isset($_REQUEST['monthnum'])) {
                $query['monthnum'] = sanitize_text_field($_REQUEST['monthnum']);
            }

            if (isset($_REQUEST['post_mime_type'])) {
                $query['post_mime_type'] = sanitize_text_field($_REQUEST['post_mime_type']);
            }

            if (isset($_REQUEST['author'])) {
                $query['author'] = sanitize_text_field($_REQUEST['author']);
            }

            update_option('wpf-query-params', $query);

            $query['paged'] = isset($_REQUEST['paged']) ? sanitize_text_field($_REQUEST['paged']) : 1;
        } elseif (isset($_REQUEST['query']['wpf'])) {
            $folder = sanitize_text_field($_REQUEST['query']['wpf']);

            if (is_array($folder)) {
                $folder = array_map('intval', $folder);
            } else {
                $folder = intval($folder);
            }

            $query['trash'] = isset($_REQUEST['query']['trash']) && sanitize_text_field($_REQUEST['query']['trash']) ? true : false;

            $query['wpf'] = $folder;

            if(isset($_REQUEST['query']) && is_array($_REQUEST['query'])) {
                update_option('wpf-query-params', $_REQUEST['query']);
            }
        }

        return $query;
    }

    /**
     * Get all data
     * @since 1.0.0
     * @return JSON
    */
    public static function getAllData()
    {
        try {
            $query = get_option('wpf-query-params') ? get_option('wpf-query-params') : [];
        } catch (\Throwable $th) {
            $query = [];
        }
        
        $option = 'reset';

        $sort = isset($_POST['sort']) && $_POST['sort'] ? sanitize_text_field($_POST['sort']) : (isset($query['sortFolderBy']) && $query['sortFolderBy'] ? $query['sortFolderBy'] : "name_asc");

        $language = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : null;

        $order = null;

        if (isset($sort) && \in_array(sanitize_text_field($sort), array('name_desc', 'reset', 'name_asc'))) {
            if (sanitize_text_field($sort) == 'name_asc') {
                $order = 'CAST(name as unsigned), name ASC';
                $option = sanitize_text_field($sort);
            } elseif (sanitize_text_field($sort) == 'name_desc') {
                $order = 'CAST(name as unsigned) DESC, name DESC';
                $option = sanitize_text_field($sort);
            }
            update_option('wpfiles_sort_folder', $option);
        } else {
            $wpfiles_sort_folder = get_option('wpfiles_sort_folder', 'reset');
            if ($wpfiles_sort_folder == 'reset') {
                $order = null;
            } elseif ($wpfiles_sort_folder == 'name_asc') {
                $order = 'name asc';
            } elseif ($wpfiles_sort_folder == 'name_desc') {
                $order = 'name desc';
            }
        }

        $tree = Wp_Files_Tree::getAllData($order, false);

        $foldersCount = is_null($language) ? Wp_Files_Tree::getFoldersCount() : Wp_Files_Tree::getFoldersCount($language);

        $total = is_null($language) ? Wp_Files_Tree::getFolderCount(-1) : Wp_Files_Tree::getFolderCount(-1, $language);

        $categorizedAttachmentsCount = Wp_Files_Media::getCategorizedAttachmentsCount(true);

        $total_uncategorized = ($total - $categorizedAttachmentsCount);

        $months = Wp_Files_Media::getMediaMonthsFilter();

        $response = array(
            'months' => $months,
            'mime_types' =>  Wp_Files_Media::getMimetypes(),
            'tree' => $tree,
            'totalFolders' => Wp_Files_Tree::getFolderChildrensIds($tree),
            'folder_count' => array(
                'total' => $total,
                'starred' => Wp_Files_Media::getStarredItemsCount(),
                'trashed' => (int)Wp_Files_Media::getTrashedItemsCount(true),
                'total_uncategorized' => $total_uncategorized,
                'folders' => $foldersCount,
            )
        );

        if (wp_is_json_request()) {
            wp_send_json_success($response);
        } else {
            return $response;
        }
    }

    /**
     * Get trashed folders
     * @since 1.0.0
     * @return JSON
    */
    public static function getTrashedFolders()
    {
        try {
            $query = get_option('wpf-query-params') ? get_option('wpf-query-params') : [];
        } catch (\Throwable $th) {
            $query = [];
        }

        $order = null;

        $option = 'reset';

        $sort = $_POST['sort'] ? sanitize_text_field($_POST['sort']) : (isset($query['sortFolderBy']) && $query['sortFolderBy'] ? $query['sortFolderBy'] : "name_asc");

        if (isset($sort) && \in_array(sanitize_text_field($sort), array('name_asc', 'name_desc', 'reset'))) {
            if (sanitize_text_field($sort) == 'name_asc') {
                $order = 'CAST(name as unsigned), name ASC';
                $option = sanitize_text_field($sort);
            } elseif (sanitize_text_field($sort) == 'name_desc') {
                $order = 'CAST(name as unsigned) DESC, name DESC';
                $option = sanitize_text_field($sort);
            }
            update_option('wpfiles_sort_folder', $option);
        } else {
            $wpfiles_sort_folder = get_option('wpfiles_sort_folder', 'reset');
            if ($wpfiles_sort_folder == 'reset') {
                $order = null;
            } elseif ($wpfiles_sort_folder == 'name_asc') {
                $order = 'name asc';
            } elseif ($wpfiles_sort_folder == 'name_desc') {
                $order = 'name desc';
            }
        }

        $response = Wp_Files_Media::getTrashedFolders($order);

        if (wp_is_json_request()) {
            wp_send_json_success($response);
        } else {
            return $response;
        }
    }

    /**
     * Get all folders
     * @since 1.0.0
     * @return JSON
    */
    public function getAllFolders()
    {
        $_folders = Wp_Files_Tree::getAllData(null, true, 0, true);

        $folders = array(
            array(
                'value' => 0,
                'label' => __('Please choose folder', 'wpfiles'),
                'disabled' => true
            )
        );
        foreach ($_folders as $k => $v) {
            $folders[] = array(
                'value' => $v['id'],
                'label' => $v['text']
            );
        }

        wp_send_json_success($folders);
    }

    /**
     * New folder
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function createFolder($request)
    {
        $name = sanitize_text_field($request->get_param('name'));

        $parent = sanitize_text_field($request->get_param('parent'));

        $parent = in_array($parent, [1, 2, 0]) ? "-1" : $parent;

        $name = isset($name) ? sanitize_text_field(wp_unslash($name)) : '';

        $parent = isset($parent) ? sanitize_text_field($parent) : '';

        if ($name != '' && $parent != '') {
            $insert = Wp_Files_Media::save($name, $parent, false);

            if ($insert !== false) {
                $message = sprintf(__('"%1$s" created successfully', 'wpfiles'), $name);

                wp_send_json_success(
                    array(
                        'mess' => $message
                    )
                );
            } else {
                wp_send_json_error(array('mess' => sprintf(__('A folder named "%1$s" already exist', 'wpfiles'), $name)));
            }
        } else {
            wp_send_json_error(array(
                'mess' => __('Folder name cannot be empty', 'wpfiles')
            ));
        }
    }

    /**
     * Save folder
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function saveFolder($request)
    {
        $id = sanitize_text_field($request->get_param('id'));

        $parent = sanitize_text_field($request->get_param('parent'));

        $name = sanitize_text_field($request->get_param('name'));

        $id = isset($id) ? sanitize_text_field($id) : '';

        $parent = isset($parent) ? intval(sanitize_text_field($parent)) : '';

        $name = isset($name) ? sanitize_text_field(wp_unslash($name)) : '';

        $folder = Wp_Files_Media::getFolderDetail($id);

        if (is_numeric($id) && is_numeric($parent) && $name != '') {
            $update = Wp_Files_Media::saveFolder($name, $parent, $id);

            if ($update === true) {
                $message = sprintf(__('"%1$s" renamed to "%2$s"', 'wpfiles'), $folder->name, $name);
                wp_send_json_success(
                    array(
                        'mess' => $message
                    )
                );
            } else {
                wp_send_json_error(array('mess' => sprintf(__('A folder named "%1$s" already exist', 'wpfiles'), $name)));
            }
        }

        wp_send_json_error();
    }

    /**
     * Delete items
     * @since 1.0.0
     * @param object $request
     * @return JSON
    */
    public function deleteItems($request)
    {
        $settings = (array) Wp_Files_Settings::loadSettings();
        $translation = Wp_Files_i18n::getTranslation();
        $trash = sanitize_text_field($request->get_param('trash'));
        $activeFolder = sanitize_text_field($request->get_param('activeFolder'));
        $selectionItems = Wp_Files_Helper::sanitizeArray($request->get_param('selectionItems'));
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $folders = $attachments = [];
        if (count($selectionItems) > 0) {
            foreach ($selectionItems as $item) {
                $parms = explode("-", $item);
                if (isset($parms[0]) && isset($parms[1]) && $parms[0] == "folder") {
                    Wp_Files_Media::destroyFolders($settings, $parms[1], $trash);
                    array_push($folders, $parms[1]);
                } elseif (isset($parms[0]) && isset($parms[1]) && $parms[0] == "attachment") {
                    Wp_Files_Media::deleteAttachment($settings, $parms[1], $trash, $activeFolder);
                    array_push($attachments, $parms[1]);
                }
            }
            if ($trash) {
                if (count($selectionItems) > 1) {
                    $message = sprintf(__('%1$s items were moved to %2$s', 'wpfiles'), count($selectionItems), $translation['trash_bin']);
                } else {
                    $message = sprintf(__('%1$s item was moved to %2$s', 'wpfiles'), count($selectionItems), $translation['trash_bin']);
                }
            } else {
                if (count($selectionItems) > 1) {
                    $message = sprintf(__('%1$s items were deleted forever', 'wpfiles'), count($selectionItems));
                } else {
                    $message = sprintf(__('%1$s item was deleted forever', 'wpfiles'), count($selectionItems));
                }
            }

            wp_send_json_success(
                array(
                    'mess' => $message
                )
            );
        }
        wp_send_json_error(array(
            'mess' => __('Can not delete folder, please try again later', 'wpfiles')
        ));
    }

    

    

    

    

    /**
     * Add all selected folder/files to new folder
     * @since 1.0.0
     * @param object $request
     * @return JSON
    */
    public function addToFolder($request)
    {
        $parent = sanitize_text_field($request->get_param('parent'));
        $name = sanitize_text_field($request->get_param('name'));
        $name = isset($name) ? sanitize_text_field(wp_unslash($name)) : '';
        $activeFolder = sanitize_text_field($request->get_param('activeFolder'));
        $activeFolder = isset($activeFolder) ? sanitize_text_field(wp_unslash($activeFolder)) : '';
        $selectionItems = $request->get_param('selectionItems');
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $folders = $attachments = [];
        $parent = isset($parent) && !in_array($parent, [-1, 0, 1, 2]) ? sanitize_text_field($parent) : 0;
        if (count($selectionItems) > 0) {
            $insert = Wp_Files_Media::save($name, $parent, false);
            if ($insert !== false) {
                Wp_Files_Media::pasteFolder($insert, $parent);
                foreach ($selectionItems as $item) {
                    $parms = explode("-", $item);
                    if (isset($parms[0]) && isset($parms[1]) && $parms[0] == "folder") {
                        Wp_Files_Media::pasteFolder($parms[1], $insert);
                        array_push($folders, $parms[1]);
                    } elseif (isset($parms[0]) && isset($parms[1]) && $parms[0] == "attachment") {
                        Wp_Files_Media::attachFoldersToPosts($parms[1], $insert);
                        array_push($attachments, $parms[1]);
                    }
                }
                if (count($selectionItems) > 1) {
                    $message = sprintf(__('%1$s items were moved to "%2$s"', 'wpfiles'), count($selectionItems), $name);
                } elseif (count($attachments) == 0) {
                    $folder = Wp_Files_Media::getFolderDetail($folders[0]);
                    $message = sprintf(__('"%1$s" was moved to "%2$s"', 'wpfiles'), $folder->name, $name);
                } else {
                    $message = sprintf(__('%1$s item was moved to "%2$s"', 'wpfiles'), count($selectionItems), $name);
                }
                wp_send_json_success(
                    array(
                        'mess' => $message
                    )
                );
            } else {
                wp_send_json_error(array('mess' => __('A folder with this name already exists. Please choose another one', 'wpfiles')));
            }
        } else {
            wp_send_json_error(array('mess' => __('Some error occurred, please try again', 'wpfiles')));
        }
    }

    /**
     * Move all selected folder/files to "Move to" folder
     * @since 1.0.0
     * @param object $request
     * @return JSON
    */
    public function moveToFolder($request)
    {
        $translation = Wp_Files_i18n::getTranslation();
        $moveTo = sanitize_text_field($request->get_param('moveTo'));
        $selectionItems = $request->get_param('selectionItems');
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $folders = $attachments = [];
        if (count($selectionItems) > 0) {
            if ($moveTo != '') {
                foreach ($selectionItems as $item) {
                    $parms = explode("-", $item);
                    if (isset($parms[0]) && isset($parms[1]) && $parms[0] == "folder") {
                        Wp_Files_Media::pasteFolder($parms[1], $moveTo);
                        array_push($folders, $parms[1]);
                    } elseif (isset($parms[0]) && isset($parms[1]) && $parms[0] == "attachment") {
                        Wp_Files_Media::attachFoldersToPosts($parms[1], $moveTo);
                        array_push($attachments, $parms[1]);
                    }
                }
                $folder_to = Wp_Files_Media::getFolderDetail($moveTo);
                if (count($selectionItems) > 1) {
                    $message = sprintf(__('%1$s items were moved to "%2$s"', 'wpfiles'), count($selectionItems), $folder_to ? $folder_to->name : $translation['root']);
                } elseif (count($attachments) == 0) {
                    $folder_from = Wp_Files_Media::getFolderDetail($folders[0]);
                    $message = sprintf(__('%1$s item was moved to "%2$s"', 'wpfiles'), $folder_from->name, $folder_to ? $folder_to->name : $translation['root']);
                } else {
                    $message = sprintf(__('%1$s item was moved to "%2$s"', 'wpfiles'), count($selectionItems), $folder_to ? $folder_to->name : $translation['root']);
                }
                wp_send_json_success(
                    array(
                        'mess' => $message
                    )
                );
            } else {
                wp_send_json_error(array(
                    'mess' => __('Some error occurred, please try again', 'wpfiles')
                ));
            }
        } else {
            wp_send_json_error(array('mess' => __('Some error occurred, please try again', 'wpfiles')));
        }
    }

    /**
     * Restore items
     * @since 1.0.0
     * @param object $request
     * @return JSON
    */
    public function restoreItems($request)
    {
        $selectionItems = $request->get_param('selectionItems');
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $folders = $attachments = [];
        if (count($selectionItems) > 0) {
            foreach ($selectionItems as $item) {
                $parms = explode("-", $item);
                if (isset($parms[0]) && isset($parms[1]) && $parms[0] == "folder") {
                    array_push($folders, $parms[1]);
                    Wp_Files_Media::restoreFolder($parms[1]);
                } elseif (isset($parms[0]) && isset($parms[1]) && $parms[0] == "attachment") {
                    $attachment = Wp_Files_Media::getAttachmentDetail($parms[1]);
                    array_push($attachments, $parms[1]);
                    Wp_Files_Media::restoreAttachment($parms[1]);
                }
            }
            if (count($selectionItems) > 1) {
                $message = sprintf(__('%1$s items were restored', 'wpfiles'), count($selectionItems));
            } elseif (count($attachments) == 0) {
                $folder = Wp_Files_Media::getFolderDetail($folders[0]);
                $message = sprintf(__('"%1$s" was restored', 'wpfiles'), $folder->name);
            } else {
                $message = __('1 item was restored', 'wpfiles');
            }
            wp_send_json_success(
                array(
                    'mess' => $message
                )
            );
        } else {
            wp_send_json_error(array('mess' => __('Some error occurred, please try again', 'wpfiles')));
        }
    }

    /**
     * Empty bin
     * @since 1.0.0
     * @return JSON
    */
    public function emptyBin()
    {
        Wp_Files_Media::emptyBin();
        wp_send_json_success(
            array(
                'mess' => __('Trash bin emptied successfully', 'wpfiles')
            )
        );
    }

    /**
     * Move to uncategorized
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function moveToUncategorized($request)
    {
        $translation = Wp_Files_i18n::getTranslation();
        $selectionItems = $request->get_param('selectionItems');
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $activeFolder = sanitize_text_field($request->get_param('activeFolder'));
        $activeFolder = isset($activeFolder) ? sanitize_text_field(wp_unslash($activeFolder)) : '';
        $attachments = $all_folders = [];
        if (count($selectionItems) > 0) {
            foreach ($selectionItems as $item) {
                $parms = explode("-", $item);
                if (isset($parms[0]) && isset($parms[1]) && ltrim($parms[0], '"') == "folder") {
                    $folders = array();
                    $folders = Wp_Files_Media::getAllChildsOfFolder(rtrim($parms[1], '"'), $folders);
                    foreach ($folders as $folder) {
                        $attachment_ids = Wp_Files_Media::getAttachmentsOfSelectedFolders([$folder]);
                        foreach ($attachment_ids as $attachment) {
                            Wp_Files_Media::attachFoldersToPosts($attachment_ids, 0);
                            $attachments = array_merge($attachments, $attachment_ids);
                        }
                    }
                    array_push($all_folders, $parms[1]);
                } elseif (isset($parms[0]) && isset($parms[1]) && ltrim($parms[0], '"') == "attachment") {
                    Wp_Files_Media::attachFoldersToPosts(rtrim($parms[1], '"'), 0);
                    array_push($attachments, $parms[1]);
                }
            }
            if (count($selectionItems) > 1) {
                $message = sprintf(__('%1$s items were moved to "%2$s"', 'wpfiles'), count($selectionItems), $translation['uncategorized']);
            } elseif (count($attachments) == 0) {
                $folder = Wp_Files_Media::getFolderDetail($all_folders[0]);
                $message = sprintf(__('"%1$s" was moved to "%2$s"', 'wpfiles'), $folder->name, $translation['uncategorized']);
            } else {
                $message = sprintf(__('%1$s item was moved to "%2$s"', 'wpfiles'), count($selectionItems), $translation['uncategorized']);
            }
            wp_send_json_success(
                array(
                    'mess' => $message
                )
            );
        } else {
            wp_send_json_error(array('mess' => __('Some error occurred, please try again', 'wpfiles')));
        }
    }

    /**
     * Move to root [Only folders]
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function moveToRoot($request)
    {
        $translation = Wp_Files_i18n::getTranslation();
        $selectionItems = $request->get_param('selectionItems');
        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';
        $selectionItems =  (array)array_filter($selectionItems, function ($item) {
            return str_contains($item, 'folder-');
        });
        $activeFolder = $request->get_param('activeFolder');
        $activeFolder = isset($activeFolder) ? sanitize_text_field(wp_unslash($activeFolder)) : '';
        $folders = [];
        if (count($selectionItems) > 0) {
            foreach ($selectionItems as $item) {
                $parms = explode("-", $item);
                if (isset($parms[0]) && isset($parms[1]) && ltrim($parms[0], '"') == "folder") {
                    Wp_Files_Media::pasteFolder(rtrim($parms[1], '"'), 0);
                    array_push($folders, $parms[1]);
                }
            }
            if (count($selectionItems) > 1) {
                $message = sprintf(__('%1$s items were moved to "%2$s"', 'wpfiles'), count($selectionItems), $translation['root']);
            } else {
                $folder = Wp_Files_Media::getFolderDetail($folders[0]);
                $message = sprintf(__('"%1$s" was moved to "%2$s"', 'wpfiles'), $folder->name, $translation['root']);
            }
            wp_send_json_success(
                array(
                    'mess' => $message
                )
            );
        } else {
            wp_send_json_error(array('mess' => __('Please select item first', 'wpfiles')));
        }
    }

    /**
     * Fetch attachments
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function fetchAttachments($request)
    {
        $selectionItems = $request->get_param('selectionItems');

        $selectionItems = isset($selectionItems) ? Wp_Files_Helper::sanitizeArray($selectionItems) : '';

        $attachments = [];

        if (count($selectionItems) > 0) {
            foreach ($selectionItems as $item) {
                $parms = explode("-", $item);
                if (isset($parms[0]) && isset($parms[1]) && $parms[0] == "folder") {
                    $folders = array();
                    $folders = Wp_Files_Media::getAllChildsOfFolder(rtrim($parms[1], '"'), $folders);
                    foreach ($folders as $folder) {
                        $attachment_ids = Wp_Files_Media::getAttachmentsOfSelectedFolders([$folder], true);
                        $attachments = array_merge($attachments, $attachment_ids);
                    }
                } elseif (isset($parms[0]) && isset($parms[1]) && $parms[0] == "attachment") {
                    $attachment = get_post($parms[1]);
                    if ($attachment) {
                        array_push($attachments, $attachment);
                    }
                }
            }

            wp_send_json_success(
                array(
                    'success' => true,
                    'attachments' => $attachments
                )
            );
        } else {
            wp_send_json_error(array('mess' => __('Some error occurred, please try again', 'wpfiles')));
        }
    }

    

    

    /**
     * Create shortcut folder
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function shortcutFolder($request)
    {
        $folderId = sanitize_text_field($request->get_param('folderId'));

        $folder = Wp_Files_Media::getFolderDetail($folderId);

        if ($folderId) {
            Wp_Files_Media::shortcutFolder($folderId);
            wp_send_json_success(
                array(
                    'mess' => sprintf(__('"%1$s" shortcut created', 'wpfiles'), $folder->name)
                )
            );
        }

        wp_send_json_error(array(
            'mess' => __('Some error occurred, please try again', 'wpfiles')
        ));
    }

    /**
     * Find posts
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function findPosts($request)
    {
        $query = sanitize_text_field($request->get_param('query'));
        $posts = Wp_Files_Media::findPosts($query);
        wp_send_json_success(
            array(
                'posts' => $posts
            )
        );
    }

    /**
     * Attach media to post
     * @since 1.0.0
     * @param $request
     * @return JSON
    */
    public function attachMediaToPost($request)
    {
        $id = sanitize_text_field($request->get_param('id'));
        $post_parent = sanitize_text_field($request->get_param('post_parent'));
        $response = Wp_Files_Media::attachMediaToPost($id, $post_parent);
        wp_send_json_success($response);
    }
}
