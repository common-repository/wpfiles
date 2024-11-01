<?php
class Wp_Files_Tree
{

    private static $wpf_colors = 'wpf_colors';

    /**
     * Folder count
     * @since 1.0.0
     * @param $folder_id
     * @param $lang
     * @return mixed
    */
    public static function getFolderCount($folder_id, $lang = null)
    {
        global $wpdb;

        if ($folder_id == 1) {
            $select = "SELECT COUNT(*) FROM {$wpdb->prefix}wpf WHERE starred = 1";
            return (int)$wpdb->get_var($select);
        } else {
            $select = "SELECT COUNT(*) FROM {$wpdb->posts} as posts WHERE ";

            $where = array("post_type = 'attachment'");

            $where[] = "(posts.post_status = 'inherit' OR posts.post_status = 'private')";

            // For specific folder
            if ($folder_id > 0) {
                $post__in = $wpdb->get_col("SELECT `attachment_id` FROM {$wpdb->prefix}wpf_attachment_folder WHERE `folder_id` = " . (int)$folder_id . " AND deleted_at IS NULL");
                if (count($post__in) == 0) {
                    $post__in = array(0);
                }
                $where[] = "(ID IN (" . implode(', ', $post__in) . "))";
            } elseif ($folder_id == 0) {
                return 0; //return 0 if this is uncategorized folder
            }

            $where = apply_filters('wpfiles_extend_fetch_count_where_query', $where);

            $query = $select . implode(' AND ', $where);

            return (int)$wpdb->get_var($query);
        }
    }

    /**
     * Get all data
     * @since 1.0.0
     * @param $order_by
     * @param $flat
     * @param $level
     * @param $show_level
     * @param $trash
     * @return array
    */
    public static function getAllData($order_by = null, $flat = false, $level = 0, $show_level = false, $trash = false, $all = false)
    {
        $folders_from_db = Wp_Files_Media::getAllFolders('*', null, $order_by, $trash, $all);

        $default_folders = array();

        $tree = self::getTreeHirerchy($folders_from_db, 0, $default_folders, $flat, $level, $show_level);

        return $tree;
    }

    /**
     * Folders tree hierarchy
     * @since 1.0.0
     * @param $data
     * @param $parent
     * @param $default
     * @param $flat
     * @param $level
     * @param $show_level
     * @return array
    */
    private static function getTreeHirerchy($data, $parent = 0, $default = null,  $flat = false, $level = 0, $show_level = false)
    {
        global $wpdb;

        $tree = is_null($default) ? array() : $default;

        foreach ($data as $k => $v) {
            if ($v->parent == $parent) {
                $children = self::getTreeHirerchy($data, $v->id, null, $flat, $level + 1, $show_level);

                $color = null;
                
                //Find color 
                if($v->color) {
                    $color = $wpdb->get_row("SELECT * FROM " . self::getTable(self::$wpf_colors) . " WHERE `id` = '" . (int)$v->color . "'");
                }
                
                $f = array(
                    'id' => (int)$v->id,
                    'text' => $show_level ? str_repeat('-', $level) . $v->name : $v->name,
                    'parent' => (int)$parent,
                    'color' => isset($color) && $color ? $color->color : '',
                    'starred' => $v->starred,
                    'type' => $v->type,
                    'shortcut' => $v->shortcut,
                    'li_attr' => array("data-count" => 0, "data-parent" => (int)$parent),
                    'count' => 0
                );

                if ($flat === true) {
                    $tree[] = $f;
                    foreach ($children as $k2 => $v2) {
                        $tree[] = $v2;
                    }
                } else {
                    $f['children'] = $children;
                    $tree[] = $f;
                }
            }
        }
        
        return $tree;
    }

    /**
     * Get all folders with count
     * @since 1.0.0
     * @param $lang
     * @return array
    */
    public static function getFoldersCount($lang = null)
    {
        global $wpdb;
        $query = $wpdb->prepare("SELECT wpfa.folder_id, count(wpfa.attachment_id) as count FROM {$wpdb->prefix}wpf_attachment_folder as wpfa 
        INNER JOIN {$wpdb->prefix}wpf as wpf ON wpf.id = wpfa.folder_id 
        INNER JOIN {$wpdb->posts} as posts ON wpfa.attachment_id = posts.ID  
        WHERE (posts.post_status = 'inherit' OR posts.post_status = 'private') 
        AND (posts.post_type = 'attachment') 
        AND wpf.created_by = %d 
        AND wpfa.deleted_at IS NULL
        GROUP BY wpfa.folder_id", apply_filters('wpfiles_current_user_id', '0'));
        $query = apply_filters('wpf_all_folders_and_count', $query, $lang);
        $results = $wpdb->get_results($query);
        $return = array();
        if (is_array($results)) {
            foreach ($results as $k => $v) {
                $return[$v->folder_id] = $v->count;
            }
        }
        return $return;
    }
    
    /**
     * All folder ids
     * @since 1.0.0
     * @param $order_by
     * @return array
    */
    public static function getAllFoldersId($order_by = null)
    {
        return Wp_Files_Media::getAllFolders('id, name', null, $order_by);
    }

    /**
     * Folder children's posts count
     * @since 1.0.0
     * @param $folder_id
     * @param $count
     * @param $lang
     * @return array
    */
    public static function getFolderChildrensPostsCount($folder_id, &$count, $lang = null)
    {
        global $wpdb;

        $children = $wpdb->get_results("SELECT name, id FROM " . $wpdb->prefix . "wpf WHERE parent = " . (int)$folder_id);

        foreach ($children as $k => $v) {
            $count = $count + Wp_Files_Tree::getFolderCount($v->id, $count, $lang);
            self::getFolderChildrensPostsCount($v->id, $count, $lang);
        }

        return $count;
    }

    /**
     * Root to folder path
     * @since 1.0.0
     * @param $children
     * @param $folderId
     * @param $node_path_storage
     * @param $node_path
     * @param $path
     * @return array
    */
    public static function getRootToFolderPath($children, $folderId, &$node_path_storage, $node_path, &$path)
    {
        $original_node_path = $node_path;
        foreach ($children as $child) {
            $node_path = $original_node_path . '/' . $child['text'];
            array_push($node_path_storage, $node_path);
            if ($child['id'] == $folderId) {
                $find = array_filter($node_path_storage, function ($row) use ($node_path) {
                    return $row == $node_path;
                });
                if (!empty($find)) {
                    $path = array_values($find)[0];
                    return $path;
                }
            } else if (!empty($child['children'])) {
                self::getRootToFolderPath($child['children'], $folderId, $node_path_storage, $node_path, $path);
            }
        }
    }

    /**
     * Get folder children's ids
     * @since 1.0.0
     * @param $tree
     * @param $folders
     * @return array
    */
    public static function getFolderChildrensIds($tree, &$folders = array())
    {
        foreach ($tree as $row) {
            array_push($folders, $row['id']);
            if (isset($row['children']) && count($row['children']) > 0) {
                self::getFolderChildrensIds($row['children'], $folders);
            }
        }

        return $folders;
    }

    /**
     * Recursive array search 
     * @since 1.0.0
     * @param $array
     * @param $value
     * @return mixed
    */
    public static function recursiveArraySearch($array, $value)
    {
        foreach ($array as $key => $item) {
            if ($item['id'] == $value) {
                return $item;
            } else {
                if (isset($item['children']) && count($item['children']) > 0) {
                    $keyFound = self::recursiveArraySearch($item['children'], $value);
                    if ($keyFound != false) {
                        return $keyFound;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Update folders tree order
     * @since 1.0.7
     * @param $folders
     * @param $action
     * @return array
    */
    public static function updateTreeOrder($folders, $action)
    {
        $array = array();

        foreach ($folders as $key => $folder) {
            if($folder['id'] == 1 && $action == "move-starred-top" && count($array) > 0) {
                $array = array_merge([$folder], $array);
            }  else {
                $array[] = $folder;
            }
        }

        return $array;
    }

    /**
     * Get table
     * @since 1.0.0
     * @param $table
     * @return string
    */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
}
