<?php

class Wp_Files_Media
{
    /**
     * Folder table
     * @since 1.0.0
     * @var string $folder_table
     */
    private static $folder_table = 'wpf';

    /**
     * Relation table
     * @since 1.0.0
     * @var string $relation_table
     */
    private static $relation_table = 'wpf_attachment_folder';

    /**
     * Wp Posts table
     * @since 1.0.0
     * @var string $wp_posts
     */
    private static $wp_posts = 'posts';

    /**
     * Wp postmeta
     * @since 1.0.0
     * @var string $wp_postmeta
     */
    private static $wp_postmeta = 'postmeta';

    /**
     * All folders
     * @since 1.0.0
     * @param $select
     * @param $prepend_default
     * @param $order_by
     * @param $trash 
     * @return array
    */
    public static function getAllFolders($select = '*', $prepend_default = null, $order_by = null, $trash = false, $all = false)
    {
        global $wpdb;

        $where = array('created_by' => apply_filters('wpfiles_current_user_id', '0'));

        if (\is_null($order_by)) $order_by = 'ord+0, id, name';

        $order_by = apply_filters('wpf_order_by', $order_by);

        $conditions = array('1 = 1');

        foreach ($where as $field => $value) {
            $conditions[] = "`$field` = " . $value;
        }

        $conditions = implode(' AND ', $conditions);

        if(!$all) {
            if (!$trash) {
                $conditions .= ' AND deleted_at IS NULL';
            } else {
                $conditions .= ' AND deleted_at IS NOT NULL';
            }
        }

        $sql = "SELECT $select FROM " . self::getTable(self::$folder_table) . " WHERE " . $conditions . " ORDER BY " . $order_by;

        $folders = $wpdb->get_results($sql);

        if (is_array($prepend_default)) {
            $all = new \stdClass();
            $all->{$prepend_default[0]} = -1;
            $all->{$prepend_default[1]} = __('All Folders', 'wpfiles');

            $uncategorized = new \stdClass();
            $uncategorized->{$prepend_default[0]} = 0;
            $uncategorized->{$prepend_default[1]} = __('Uncategorized', 'wpfiles');

            array_unshift($folders, $all, $uncategorized);
        }
        return $folders;
    }

    /**
     * Get folder media relations
     * @since 1.0.0
     * @return mixed
    */
    public static function getFolderMediaRelations()
    {
        global $wpdb;

        $query = "SELECT `attachment_id`, GROUP_CONCAT(`folder_id`) as folders FROM `{$wpdb->prefix}wpf_attachment_folder` GROUP BY `attachment_id`";

        $relations = $wpdb->get_results($query);

        $res = array();

        foreach ($relations as $k => $v) {
            $res[$v->attachment_id] = array_map('intval', explode(',', $v->folders));
        }

        return $res;
    }

    /**
     * Paste folder
     * @since 1.0.0
     * @param $cutItemId
     * @param $pasteFolderId
     * @return void
    */
    public static function pasteFolder($cutItemId, $pasteFolderId)
    {
        global $wpdb;

        $folder = self::getFolderDetail($pasteFolderId);

        //Move it if container folder is not a shortcut
        if (($folder && $folder->shortcut == 0) || $pasteFolderId == 0) {
            $wpdb->update(
                self::getTable(self::$folder_table),
                array('parent' => $pasteFolderId),
                array('id' => $cutItemId),
                array('%d'),
                array('%d')
            );
        }
    }

    /**
     * Add folders to posts
     * @since 1.0.0
	 * @param $post_ids
	 * @param $folder_ids
	 * @param $has_action
	 * @param $product_id
     * @return void
    */
    public static function attachFoldersToPosts($post_ids, $folder_ids, $has_action = true, $product_id = 0)
    {
        global $wpdb;

        if (!is_array($post_ids)) $post_ids = array($post_ids);

        if (!is_array($folder_ids)) $folder_ids = array($folder_ids);

        foreach ($folder_ids as $k => $folder_id) {

            $folder = self::getFolderDetail($folder_id);

            $folder_id = ($folder && $folder->shortcut > 0 ? $folder->shortcut : $folder_id);

            foreach ($post_ids as $k2 => $post_id) {

                do_action('wpf_action_before_folder_media_relation', (int)$post_id, (int)$folder_id);
                
                if ($folder_id > 0) {
                    $wpdb->insert(
                        self::getTable(self::$relation_table),
                        array(
                            'attachment_id' => (int)$post_id,
                            'folder_id' => (int)$folder_id,
                            'product_id' => (int)$product_id
                        ),
                        array('%d', '%d', '%d')
                    );
                }

                if ($has_action === true) {
                    do_action('wpf_after_set_folder', $post_id, $folder_id);
                }
                
            }

        }

    }

    /**
     * Get detail of folder
     * @since 1.0.0
     * @param $name
     * @param $parent
     * @param $select
     * @return object
    */
    public static function detail($name, $parent, $select = 'id')
    {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `name` = "%2$s" AND `parent` = %3$d AND deleted_at IS NULL', self::getTable(self::$folder_table), $name, $parent);

        $user_has_own_folder = get_option('wpf_per_user_media', '0') === '1'
        ;
        if ($user_has_own_folder) {
            $query .= " AND `created_by` = " . get_current_user_id();
        } else {
            $query .= " AND `created_by` = 0";
        }

        return $wpdb->get_row($query);
    }

    /**
     * Save folder name
     * @since 1.0.0
     * @param $name
     * @param $pt
     * @param $fl_id
     * @return boolean
    */
    public static function saveFolder($name, $pt, $fl_id)
    {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` != %2$d AND `name` = "%3$s" AND `parent` = %4$d', self::getTable(self::$folder_table), $fl_id, $name, $pt);

        $folder = $wpdb->get_row($query);

        if (is_null($folder) || !$folder) {

            $wpdb->update(
                self::getTable(self::$folder_table),
                array('name' => $name),
                array('id' => $fl_id),
                array('%s'),
                array('%d')
            );

            return true;
        }

        return false;
    }

    /**
     * Delete folders recursively.
     * @since 1.0.0
     * @param array $settings
     * @param int $id
     * @param boolean $trash
     * @return void
    */
    public static function destroyFolders($settings, $id, $trash = false)
    {
        global $wpdb;
        
        if ($trash && $settings['trash_bin'] == 1) {

            // Move to trash
            $folder = $wpdb->get_row($wpdb->prepare('SELECT * FROM %1$s WHERE `id` = "%2$s"', self::getTable(self::$folder_table), $id));

            if ($folder) {
                $wpdb->update(
                    self::getTable(self::$folder_table),
                    array('deleted_at' => current_time('mysql')),
                    array('id' => (int)$id),
                    array('%s'),
                    array('%d')
                );
            }

            $folders = array();
            
            $folders = Wp_Files_Media::getAllChildsOfFolder($id, $folders);

            if ($settings['is_folder_media_deleted'] == 1) {

                if (count($folders) > 0) {

                    $attachments = Wp_Files_Media::getAttachmentsOfSelectedFolders($folders);
                    
                    $wpdb->query($wpdb->prepare('UPDATE %1$s SET deleted_at= "%2$s" WHERE folder_id IN(%3$s)', self::getTable(self::$relation_table), current_time('mysql'), implode(',', $folders)));
                    
                    //Delete from posts table
                    if(count($attachments)) {
                        $wpdb->query($wpdb->prepare('UPDATE %1$s SET post_status= "%2$s" WHERE ID IN(%3$s)', self::getTable(self::$wp_posts), 'trash', implode(',', $attachments)));
                    }

                }

            } else {

                //Only break relation with folder but available for just restorable to same folder
                if (count($folders) > 0) {
                    $wpdb->query($wpdb->prepare('UPDATE %1$s SET restore = 1 WHERE folder_id IN(%2$s)', self::getTable(self::$relation_table), implode(',', $folders)));
                }

            }

        } else {

            //Childs of relevent folder
            $folders = array();
            $folders = Wp_Files_Media::getAllChildsOfFolder($id, $folders);

            foreach (array_merge($folders, [$id]) as $folder_id) {

                if ($settings['is_folder_media_deleted'] == 1) {
                    //Folder attachments
                    $attachments = $wpdb->get_col("SELECT `attachment_id` FROM " . self::getTable(self::$relation_table) . " WHERE `folder_id` = '" . (int)$folder_id . "'");

                    foreach ($attachments as $k => $attachment) {
                        //Delete from system
                        wp_delete_attachment($attachment, true);
                    }
                }

                //Delete from folder table
                $wpdb->delete(self::getTable(self::$folder_table), array('id' => (int)$folder_id), array('%d'));

                //Delete from relation table
                $wpdb->delete(self::getTable(self::$relation_table), array('folder_id' => (int)$folder_id, array('%d')));    
            }
            
        }
    }

    /**
     * Create new folder
     * @since 1.0.0
     * @param $name
     * @param $parent
     * @param $shortcutOfFolder
     * @param $type
     * @return int
    */
    public static function createFolder($name, $parent = 0, $shortcutOfFolder = 0, $type = 0)
    {
        global $wpdb;

        $data = apply_filters('wpfiles_pre_creating_folders', array(
            'name' => $name,
            'parent' => $parent,
            'shortcut' => $shortcutOfFolder,
            'type' => $type
        ));

        $wpdb->insert(self::getTable(self::$folder_table), $data);

        return $wpdb->insert_id;
    }

    /**
     * Save folder & return
     * @since 1.0.0
     * @param $name
     * @param $parent
     * @param $return_id_if_exist
     * @return mixed
    */
    public static function save($name, $parent, $return_id_if_exist = true)
    {
        $parent = $parent == "-1" ? 0 : $parent;

        $check = self::detail($name, $parent);

        if (is_null($check)) {
            return self::createFolder($name, $parent);
        } else {
            return $return_id_if_exist ? (int)$check->id : false;
        }

    }

    /**
     * Delete all
     * @since 1.0.0
     * @return void
    */
    public static function destroyAll()
    {
        global $wpdb;

        $wpdb->query("DELETE FROM " . self::getTable(self::$relation_table));
        
        $wpdb->query("DELETE FROM " . self::getTable(self::$folder_table) . " WHERE id NOT IN(1,2)");
    }

    /**
     * Folder childrens
     * @since 1.0.0
     * @param object $folder_id
     * @param int $index
     * @return object
    */
    public static function getFolderChildrens($folder_id, $index = 0)
    {
        global $wpdb;

        $detail = null;

        if ($index == 0) {
            $detail = $wpdb->get_results("SELECT name, id FROM " . $wpdb->prefix . "wpf WHERE id = " . (int)$folder_id);
        }

        $children = $wpdb->get_results("SELECT name, id FROM " . $wpdb->prefix . "wpf WHERE parent = " . (int)$folder_id);

        foreach ($children as $k => $v) {
            $children[$k]->children = self::getFolderChildrens($v->id, $index + 1);
        }

        if ($detail != null) {
            $return = new \stdClass;
            $return->id = $detail[0]->id;
            $return->name = $detail[0]->name;
            $return->children = $children;
            return $return;
        }

        return $children;
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

    /**
     * Folder relation with user / media
     * @since 1.0.0
     * @param $clauses
     * @return array
    */
    public static function getUserFolderMediaRelations($clauses)
    {
        global $wpdb;

        $folder_attachments = $wpdb->prepare("SELECT attachment_id 
                            FROM {$wpdb->prefix}wpf_attachment_folder AS wpfa
                            JOIN {$wpdb->prefix}wpf AS wpf ON wpfa.folder_id = wpf.id
                            AND wpfa.deleted_at IS NULL
                            AND wpfa.restore = 0
                            GROUP BY attachment_id
                            HAVING FIND_IN_SET(%d, GROUP_CONCAT(created_by))", apply_filters('wpfiles_current_user_id', 0));

        $clauses['where'] .= " AND {$wpdb->posts}.ID NOT IN ($folder_attachments)";
        
        return $clauses;
    }

    

    

    

    /**
     * Delete attachment
     * @since 1.0.0
     * @param int $id
     * @param array $settings
     * @param boolean $trash
     * @param id $activeFolder
     * @return void
    */
    public static function deleteAttachment($settings, $id, $trash = false, $activeFolder = null)
    {
        global $wpdb;

        if ($trash && $settings['trash_bin'] == 1) {

            //Delete from posts table
            $wpdb->update(
                self::getTable(self::$wp_posts),
                array('post_status' => 'trash', 'post_modified' => current_time('mysql')),
                array('ID' => (int)$id),
                array('%s', '%s'),
                array('%d')
            );

            //Delete from folder relation table
            $wpdb->update(
                self::getTable(self::$relation_table),
                array('deleted_at' => current_time('mysql')),
                array('attachment_id' => (int)$id),
                array('%s'),
                array('%d')
            );

        } else {

            wp_delete_attachment($id, true);

            //Delete from relation table
            $wpdb->delete(self::getTable(self::$relation_table), array('attachment_id' => $id), array('%d'));

        }
    }

    /**
     * All childs of folder
     * @since 1.0.0
     * @param int $folder_id
     * @param array $returnArray
     * @return array
    */
    public static function getAllChildsOfFolder($folder_id, &$returnArray)
    {
        global $wpdb;

        $detail = null;

        $detail = $wpdb->get_results("SELECT id FROM " . $wpdb->prefix . "wpf WHERE id = " . (int)$folder_id);

        $children = $wpdb->get_results("SELECT id FROM " . $wpdb->prefix . "wpf WHERE parent = " . (int)$folder_id);

        foreach ($children as $k => $v) {
            self::getAllChildsOfFolder($v->id, $returnArray);
        }

        if ($detail != null) {
            array_push($returnArray, $detail[0]->id);
        }

        return $returnArray;
    }

    /**
     * Trashed Attachments(Folders)
     * @since 1.0.0
     * @param array $folder_ids
     * @return array
    */
    public static function getTrashedAttachmentsOfSelectedFolders($folder_ids)
    {

        global $wpdb;

        $query = "SELECT wpfa.attachment_id 
                    FROM {$wpdb->prefix}wpf_attachment_folder AS wpfa
                    JOIN {$wpdb->prefix}wpf AS wpf ON wpfa.folder_id = wpf.id
                    JOIN {$wpdb->posts} AS posts ON posts.ID = wpfa.attachment_id
                    WHERE wpfa.deleted_at IS NOT NULL
                    AND folder_id IN(" . implode(",", $folder_ids) . ")
                    AND posts.post_status = 'trash'
                    AND posts.post_type = 'attachment'
                    GROUP BY wpfa.attachment_id";

        $attachments = $wpdb->get_col($query);
        
        return $attachments;
    }

    /**
     * Folders Trashed Attachments 
     * @since 1.0.0
     * @param $count
     * @return mixed
    */
    public static function getFoldersTrashedAttachments($count = false)
    {
        $ids = array();

        $folders = Wp_Files_Media::getTrashedFolders();

        foreach ($folders as $key => $folder) {
            $child_folders = array();
            $folders = Wp_Files_Media::getAllChildsOfFolder($folder->id, $child_folders);
            if(count($folders) > 0) {
                $ids = array_merge($ids, $folders);
            } else {
                $ids[] = $folder->id;
            }
        }

        if(count($ids) > 0) {

            $ids = implode(',', $ids);

            global $wpdb;
    
            $query = "SELECT wpfa.attachment_id 
                        FROM {$wpdb->prefix}wpf_attachment_folder AS wpfa
                        JOIN {$wpdb->prefix}wpf AS wpf ON wpfa.folder_id = wpf.id
                        JOIN {$wpdb->posts} AS posts ON posts.ID = wpfa.attachment_id
                        WHERE wpfa.deleted_at IS NOT NULL
                        AND wpf.id IN ($ids)
                        AND posts.post_status = 'trash'
                        AND posts.post_type = 'attachment'
                        GROUP BY wpfa.attachment_id";
    
            $attachments = $wpdb->get_col($query);
            
            if($count) {
                return count($attachments);
            }

            return $attachments;
        } else {
            return [];
        }
    }

    /**
     * Restore attachment
     * @since 1.0.0
     * @param int $id
     * @return void
    */
    static public function restoreAttachment($id)
    {
        global $wpdb;

        //Restore from posts table if deleted
        $wpdb->update(
            self::getTable(self::$wp_posts),
            array('post_status' => 'inherit'),
            array('ID' => (int)$id),
            array('%s'),
            array('%d')
        );

        //Restore from relation table if deleted 
        $wpdb->update(
            self::getTable(self::$relation_table),
            array('deleted_at' => NULL, 'restore' => 0),
            array('attachment_id' => (int)$id),
            array('%s', '%d'),
            array('%d')
        );
    }

    /**
     * Restore folder 
     * @since 1.0.0
     * @param int $id
     * @return void
    */
    static public function restoreFolder($id)
    {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` = "%2$s"', self::getTable(self::$folder_table), $id);

        $folder = $wpdb->get_row($query);

        if (!is_null($folder) || $folder) {

            $wpdb->update(
                self::getTable(self::$folder_table),
                array('deleted_at' => NULL),
                array('id' => (int)$id),
                array('%s'),
                array('%d')
            );

            //Childs of relevent folder
            $folders = array();
            $folders = Wp_Files_Media::getAllChildsOfFolder($id, $folders);

            $attachments = self::getTrashedAttachmentsOfSelectedFolders($folders);

            //Restore its attachments
            if (count($folders) > 0) {
                $wpdb->query($wpdb->prepare('UPDATE %1$s SET restore = 0, deleted_at = NULL WHERE folder_id IN(%2$s)', self::getTable(self::$relation_table), implode(',', $folders)));
            }

            //Restore from posts table
            if(count($attachments)) {
                $wpdb->query($wpdb->prepare('UPDATE %1$s SET post_status= "%2$s" WHERE ID IN(%3$s)', self::getTable(self::$wp_posts), 'inherit', implode(',', $attachments)));
            }
            
        }
    }

    /**
     * Empty bin 
     * @since 1.0.0
     * @return void
    */
    public static function emptyBin()
    {
        global $wpdb;

        $settings = (array) Wp_Files_Settings::loadSettings();

        $folders = $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s WHERE `id` NOT IN(%2$s) AND deleted_at IS NOT NULL', self::getTable(self::$folder_table), implode(',', ['1', '2'])));

        foreach ($folders as $folder) {

            $id = $folder->id;

            //Childs of relevent folder
            $folders = array();
            $folders = Wp_Files_Media::getAllChildsOfFolder($id, $folders);

            foreach (array_merge($folders, [$id])  as $folder_id) {

                if ($settings['is_folder_media_deleted'] == 1) {
                    //Folder attachments
                    $attachments = $wpdb->get_col("SELECT `attachment_id` FROM " . self::getTable(self::$relation_table) . " WHERE `folder_id` = '" . (int)$folder_id . "'");

                    foreach ($attachments as $k => $attachment) {
                        //Delete from system
                        wp_delete_attachment($attachment, true);
                    }
                }

                //Delete from folder table
                $wpdb->delete(self::getTable(self::$folder_table), array('id' => (int)$folder_id), array('%d'));

                //Delete from relation table
                $wpdb->delete(self::getTable(self::$relation_table), array('folder_id' => (int)$folder_id, array('%d')));
            }
        }
        //End

        //Attachments
        $attachments = $wpdb->get_results($wpdb->prepare('SELECT * FROM %1$s WHERE `post_type` = "attachment" AND `post_status` = "trash"', self::getTable(self::$wp_posts)));

        foreach ($attachments as $attachment) {
            Wp_Files_Media::deleteAttachment($settings, $attachment->ID, false);
        }
        //End
    }

    /**
     * Get categorized attachments count
     * @since 1.0.0
     * @param $count
     * @return mixed
    */
    public static function getCategorizedAttachmentsCount($count = false)
    {
        global $wpdb;

        $query = "SELECT wpfa.attachment_id 
                    FROM {$wpdb->prefix}wpf_attachment_folder AS wpfa
                    JOIN {$wpdb->prefix}wpf AS wpf ON wpfa.folder_id = wpf.id
                    JOIN {$wpdb->posts} AS posts ON posts.ID = wpfa.attachment_id
                    WHERE wpfa.deleted_at IS NULL
                    AND wpfa.restore = 0
                    AND (posts.post_status = 'inherit' OR posts.post_status = 'private')
                    AND posts.post_type = 'attachment'
                    GROUP BY wpfa.attachment_id";

        $attachments = $wpdb->get_col($query);

        if ($count) {
            return count($attachments);
        }

        return $attachments;
    }

    /**
     * Trashed folders
     * @since 1.0.0
     * @param $order_by
     * @return array
    */
    public static function getTrashedFolders($order_by = null)
    {
        global $wpdb;

        if (\is_null($order_by)) $order_by = 'name';

        $order_by = apply_filters('wpf_order_by', $order_by);

        $sql = "SELECT *, name as text FROM " . self::getTable(self::$folder_table) . " WHERE deleted_at IS NOT NULL  ORDER BY " . $order_by;

        $folders = $wpdb->get_results($sql);

        return $folders;
    }

    /**
     * Starred items count
     * @since 1.0.0
     * @return int
    */
    public static function getStarredItemsCount()
    {
        global $wpdb;

        $folders = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %1$s where starred = 1 AND deleted_at IS NULL', self::getTable(self::$folder_table)));

        $attachments = $wpdb->get_var("SELECT COUNT(*) FROM " . self::getTable(self::$wp_postmeta) . " AS postmeta
        JOIN {$wpdb->posts} AS posts ON posts.ID = postmeta.post_id WHERE (posts.post_status = 'inherit' OR posts.post_status = 'private') AND postmeta.meta_key = 'wpfiles-starred' AND postmeta.post_id IS NOT NULL");

        return $attachments + $folders;
    }

    /**
     * Return starred items
     * @since 1.0.0
     * @return array
    */
    public static function getStarredItems()
    {
        global $wpdb;

        $attachments = (array) $wpdb->get_col($wpdb->prepare('SELECT post_id FROM %1$s where meta_key = "wpfiles-starred" AND post_id IS NOT NULL', self::getTable(self::$wp_postmeta)));
    
        return $attachments;
    }

    /**
     * Trashed items (Folders / Individual attachments)
     * @since 1.0.0
     * @param $count
     * @return int
    */
    public static function getTrashedItemsCount($count = false)
    {
        global $wpdb;

        $trashed_folders = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %1$s where deleted_at IS NOT NULL', self::getTable(self::$folder_table)));

        $trashed_attachments = $wpdb->get_col($wpdb->prepare('SELECT ID FROM %1$s where post_status = "trash" and post_type = "attachment"', self::getTable(self::$wp_posts)));
        
        $trashed_folders_attachments = array_intersect(self::getFoldersTrashedAttachments(), $trashed_attachments);

        return (int)($trashed_folders + (count($trashed_attachments) - count($trashed_folders_attachments)));
    }

    /**
     * Folder detail
     * @since 1.0.0
     * @param $id
     * @return object
    */
    public static function getFolderDetail($id)
    {
        global $wpdb;
        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` = "%2$s"', self::getTable(self::$folder_table), $id);
        return $wpdb->get_row($query);
    }

    /**
     * Attachment detail
     * @since 1.0.0
     * @param $id
     * @return object
    */
    public static function getAttachmentDetail($id)
    {
        global $wpdb;
        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `ID` = "%2$s"', $wpdb->posts, $id);
        return $wpdb->get_row($query);
    }

    /**
     * Import folders
     * @since 1.0.0
     * @param $folders
     * @return void
    */
    public static function importFolders($folders)
    {
        global $wpdb;

        //Folders
        if (isset($folders['folders']) && count($folders['folders']) > 0) {
            foreach ($folders['folders'] as $folder) {
                $query = "SELECT * FROM " . self::getTable(self::$folder_table) . " WHERE `id` = '" . (int)$folder['id'] . "' AND `name` = '" . (string)$folder['text'] . "' AND `parent` = '" . (int)$folder['parent'] . "'";
                $response = $wpdb->get_row($query);
                if (is_null($response)) {
                    $data = apply_filters('wpfiles_pre_creating_folders', array(
                        'id' => (int)$folder['id'],
                        'name' => (string)$folder['text'],
                        'parent' => (int)$folder['parent'],
                        'starred' => (int)$folder['starred'],
                        'color' => $folder['color'],
                        'type' => 0
                    ));
                    $wpdb->insert(self::getTable(self::$folder_table), $data);
                }
            }
        }

        //Relations
        if (isset($folders['relations']) && count($folders['relations']) > 0) {
            foreach ($folders['relations'] as $relation) {
                $query = "SELECT * FROM " . self::getTable(self::$relation_table) . " WHERE `folder_id` = '" . (int)$relation['folder_id'] . "' AND `attachment_id` = '" . (int)$relation['attachment_id'] . "'";
                $response = $wpdb->get_row($query);
                if (is_null($response)) {
                    $data = apply_filters('wpfiles_pre_creating_folders', array(
                        'folder_id' => (int)$relation['folder_id'],
                        'attachment_id' => (int)$relation['attachment_id'],
                    ));
                    $wpdb->insert(self::getTable(self::$relation_table), $data);
                }
            }
        }
    }

    /**
     * Attachments(Folders)
     * @since 1.0.0
     * @param $folder_ids
     * @param $detail
     * @return array
    */
    public static function getAttachmentsOfSelectedFolders($folder_ids, $detail = false)
    {
        global $wpdb;
        $results = $wpdb->get_results("SELECT posts.* FROM " . self::getTable(self::$relation_table) . " AS wpfa
                                    JOIN {$wpdb->prefix}wpf AS wpf ON wpfa.folder_id = wpf.id
                                    JOIN {$wpdb->posts} AS posts ON posts.ID = wpfa.attachment_id 
                                    WHERE wpfa.deleted_at IS NULL
                                    AND wpfa.restore = 0 
                                    AND wpfa.folder_id IN(" . implode(",", $folder_ids) . ") 
                                    AND posts.post_type = 'attachment'
                                    AND (posts.post_status = 'inherit' OR posts.post_status = 'private')
                                    GROUP BY wpfa.attachment_id");
        $res = array();
        foreach ($results as $k => $v) {
            $res[] = $detail ? $v : $v->ID;
        }
        return $res;
    }

    /**
     * Folder attachment detail
     * @since 1.0.0
     * @param $folder_id
     * @param $attachment_id
     * @return object
    */
    public static function getFolderAttachmentDetail($folder_id, $attachment_id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM " . self::getTable(self::$relation_table) . " WHERE `folder_id` = " . (int)$folder_id . " AND `attachment_id` = " . (int)$attachment_id . "");
    }

    /**
     * Detect if folder has able to create shortcut
     * @since 1.0.0
     * @param $folder_id
     * @return object
    */
    public static function folderShortcutable($folder_id)
    {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `id` = %2$d AND `shortcut` = %3$d', self::getTable(self::$folder_table), $folder_id, 0);

        return $wpdb->get_row($query);
    }

    /**
     * Detect if Woocommerce folder has able to create shortcut
     * @since 1.0.0
     * @param $product_id
     * @return object
    */
    public static function woocommerceFolderShortcutable($product_id)
    {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `product_id` = %2$d AND `shortcut` = %3$d', self::getTable(self::$folder_table), $product_id, 0);

        return $wpdb->get_row($query);
    }

    /**
     * Verify if attachment has linked to any folder
     * @since 1.0.0
     * @param $attachment_id
     * @param $post_id
     * @return object
    */
    public static function attachment_has_link($attachment_id, $post_id)
    {
        global $wpdb;

        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpf_attachment_folder WHERE product_id = " . (int)$post_id . " AND attachment_id = " . (int)$attachment_id . "");
    }

    /**
     * Create shortcut of relevant folder
     * @since 1.0.0
     * @param $folder_id
     * @return int
    */
    public static function shortcutFolder($folder_id)
    {
        global $wpdb;

        $action_folder = self::getFolderDetail($folder_id);

        if (!is_null($action_folder)) {

            if ($action_folder->shortcut == 0) {

                $count_shortcuts = $wpdb->get_row($wpdb->prepare('SELECT count(*) as count FROM %1$s WHERE `shortcut` = "%2$s"', self::getTable(self::$folder_table), $action_folder->id));

                $name = $action_folder->name . ' shortcut' . ($count_shortcuts->count == 0 ? '' : ' ' . ($count_shortcuts->count + 1));

                return self::createFolder($name, $action_folder->parent, $action_folder->id, $action_folder->type);
            } else {

                $folder = self::getFolderDetail($action_folder->shortcut);

                if ($folder) {
                    $count_shortcuts = $wpdb->get_row($wpdb->prepare('SELECT count(*) as count FROM %1$s WHERE `shortcut` = "%2$s"', self::getTable(self::$folder_table), $folder->id));

                    $name = $folder->name . ' shortcut' . ($count_shortcuts->count == 0 ? '' : ' ' . ($count_shortcuts->count + 1));

                    return self::createFolder($name, $action_folder->parent, $folder->id, $folder->type);
                }
            }
        }
    }

    /**
     * Fetch attachment relation
     * @since 1.0.0
     * @param $id
     * @return object
    */
    public static function getAttachmentRelation($id)
    {
        global $wpdb;
        $query = $wpdb->prepare('SELECT * FROM %1$s WHERE `attachment_id` = "%2$s"', self::getTable(self::$relation_table), $id);
        return $wpdb->get_row($query);
    }

    

    

    

    /**
     * Get media months filter
     * @since 1.0.0
     * @return object
    */
    public static function getMediaMonthsFilter()
    {
        global $wpdb;
        $query = $wpdb->prepare('SELECT YEAR(post_date) as year, MONTH(post_date) as month, MONTHNAME(post_date) as month_name FROM %1$s WHERE post_date > now() - INTERVAL 12 month GROUP BY month ORDER BY post_date DESC', self::getTable(self::$wp_posts));
        return $wpdb->get_results($query);
    }

    /**
     * Find posts
     * @since 1.0.0
     * @param $query
     * @return array
    */
    public static function findPosts($query)
    {
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);

        $s    = wp_unslash($query);
        $args = array(
            'post_type'      => array_keys($post_types),
            'post_status'    => 'any',
            'posts_per_page' => 50,
        );

        if ('' !== $s) {
            $args['s'] = $s;
        }

        $posts = get_posts($args);

        $alt  = '';

        foreach ($posts as $key => $post) {
            $title = trim($post->post_title) ? $post->post_title : __('(no title)');
            $alt   = ('alternate' === $alt) ? '' : 'alternate';

            switch ($post->post_status) {
                case 'publish':
                case 'private':
                    $stat = __('Published');
                    break;
                case 'future':
                    $stat = __('Scheduled');
                    break;
                case 'pending':
                    $stat = __('Pending Review');
                    break;
                case 'draft':
                    $stat = __('Draft');
                    break;
            }

            if ('0000-00-00 00:00:00' === $post->post_date) {
                $time = '';
            } else {
                $time = mysql2date(__('Y/m/d'), $post->post_date);
            }

            $posts[$key]->title = $title;
            $posts[$key]->alt = $alt;
            $posts[$key]->time = $time;
            $posts[$key]->stat = $stat;
            $posts[$key]->type = $post_types[$post->post_type]->labels->singular_name;
        }

        return $posts;
    }

    /**
     * Attach media to post
     * @since 1.0.0
     * @param $id
     * @param $post_parent
     * @return void
    */
    public static function attachMediaToPost($id, $post_parent)
    {
        $attachment = array(
            'ID' => $id,
            'post_parent' => $post_parent,
        );

        return wp_update_post($attachment);
    }

    /**
     * Get mime types
     * @since 1.0.0
     * @return array
    */
    public static function getMimetypes()
    {
        $post_mime_types = get_post_mime_types();

        $types = array();

        if (count($post_mime_types) > 0) {
            foreach ($post_mime_types as $key => $post_mime_type) {
                if (isset($post_mime_type[0])) {
                    $types[] = array(
                        'id' => $key,
                        'value' => $post_mime_type[0],
                    );
                }
            }
        }

        return $types;
    }
}
