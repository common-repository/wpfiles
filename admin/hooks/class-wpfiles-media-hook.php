<?php
/**
 * Class to manage all WPFiles specific media screen related hooks
 */
class Wp_Files_Media_Hook
{
    /**
     * Class instance
     * @since 1.0.0
     * @var object $instance
    */
    protected static $instance = null;

    /**
     * To detect User wise media | User Folders
     * @since 1.0.0
     * @var boolean $is_enabled
    */
    private $is_enabled;

    /**
     * Current user media
     * @since 1.0.0
     * @var int $current_user_id
    */
    private $current_user_id;

    /**
     * Return class instance
     * @since 1.0.0
     * @return object
    */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
            self::$instance->doHooks();
        }
        return self::$instance;
    }

    /**
     * Hooks
     * @since 1.0.0
     * @return void
    */
    private function doHooks()
    {
        $this->current_user_id = get_current_user_id();

        $this->is_enabled = $this->isEnabled();

        if ($this->is_enabled) {

            add_filter('wpfiles_pre_creating_folders', array($this, 'filterAttributesBeforeInsertingFolder'));

            add_filter('wpfiles_current_user_folders_where', array($this, 'currentUserFolders'), 10, 2);

            add_filter('wpfiles_current_user_id', array($this, '_current_user_id'));
        }

        add_action('wpf_action_before_folder_media_relation', array($this, 'actionBeforeFolderMediaRelation'), 10, 2);
    }

    /**
     * Current user folders query
     * @since 1.0.0
     * @param $where
     * @param $folder_table
     * @return string
    */
    public function currentUserFolders($where, $folder_table)
    {
        return sprintf('`folder_id` IN (SELECT `id` FROM %1$s WHERE `created_by` = %2$d)', $folder_table, $this->current_user_id);
    }

    /**
     * Current user id
     * @since 1.0.0
     * @return int
    */
    public function _current_user_id()
    {
        return $this->current_user_id;
    }

    /**
     * Apply "User Folders" settings before inserting into folders 
     * @since 1.0.0
     * @param $post_id
     * @param $folder_id
     * @return object
    */
    public function actionBeforeFolderMediaRelation($post_id, $folder_id)
    {
        global $wpdb;
        
        if ($this->is_enabled) {
            $query = sprintf('DELETE FROM %1$swpf_attachment_folder WHERE `attachment_id` = %2$d AND `folder_id` IN (SELECT `id` FROM %1$swpf WHERE `created_by` = %3$d)', $wpdb->prefix, $post_id, $this->current_user_id);
            $wpdb->query($wpdb->prepare($query));
        } else {
            $wpdb->query(sprintf('DELETE FROM %1$swpf_attachment_folder WHERE `attachment_id` = %2$d AND `folder_id` IN (SELECT `id` FROM %1$swpf WHERE `created_by` = 0)', $wpdb->prefix, $post_id));
        }
    }

    /**
     * User Folders settings
     * @since 1.0.0
     * @param $data
     * @return array
    */
    private function isEnabled()
    {
        // For now this feature is disabled
        return false;
    }

    /**
     * Filter before data inserted in DB
     * @since 1.0.0
     * @param $data
     * @return array
    */
    public function filterAttributesBeforeInsertingFolder($data)
    {
        $data['created_by'] = $this->current_user_id;
        
        return $data;
    }
}
