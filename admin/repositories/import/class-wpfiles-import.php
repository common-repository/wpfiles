<?php
/**
 * In this class, you will find all import/export related to WPFiles.
 */
class Wp_Files_Import
{
    /**
     * Class instance
     * @since 1.0.0
     * @var object $instance
     */
    protected static $instance = null;

    /**
     * Folder table
     * @since 1.0.0
     * @var string $options
     */
    private static $options = 'options';

    /**
     * Return class instance
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
     * Return Filebird folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function FilebirdFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fbv';

        $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));

        if (!$wpdb->get_var($query) == $table) {
            return array();
        }

        $folders = $wpdb->get_results($wpdb->prepare('select r.id as id, r.name as title, r.parent as parent from %1$s as r where r.parent = %2$d order by r.ord', $table, $parent));

        if ($flat) {
            foreach ($folders as $k => $folder) {
                $children = self::FilebirdFolders($folder->id, $flat);
                foreach ($children as $i => $v) {
                    $folders[] = $v;
                }
            }
        } else {
            foreach ($folders as $i => $folder) {
                $folders[$i]->children = self::FilebirdFolders($folder->id, $flat);
            }
        }
        return $folders;
    }

    /**
     * Return Wpmlf media library folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function wpmlfFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mgmlp_folders';

        $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));

        if (!$wpdb->get_var($query) == $table) {
            return array();
        }

        $folders = $wpdb->get_results($wpdb->prepare('select p.ID as id, p.post_title as title, mlf.folder_id as parent from %1$s as p LEFT JOIN %2$s as mlf ON(p.ID = mlf.post_id) where p.post_type = \'mgmlp_media_folder\' and mlf.folder_id = \'%3$s\' order by mlf.folder_id', $wpdb->posts, $wpdb->prefix . 'mgmlp_folders', $parent));

        if ($flat) {
            foreach ($folders as $k => $folder) {
                $children = self::wpmlfFolders($folder->id, $flat);
                foreach ($children as $i => $v) {
                    $folders[] = $v;
                }
            }
        } else {
            foreach ($folders as $i => $folder) {
                $folders[$i]->children = self::wpmlfFolders($folder->id, $flat);
            }
        }
        return $folders;
    }

    /**
     * Return Enhanced media library folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function enhancedFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $folders = $wpdb->get_results($wpdb->prepare('SELECT t.term_id as id, t.name as title, tt.term_taxonomy_id FROM %1$s as t  INNER JOIN %2$s as tt ON (t.term_id = tt.term_id) WHERE tt.taxonomy = \'media_category\' AND tt.parent = %3$d', $wpdb->terms, $wpdb->term_taxonomy, $parent));

        foreach ($folders as $k => $folder) {
            $folders[$k]->parent = $parent;
        }

        if ($flat) {
            foreach ($folders as $k => $folder) {
                $children = self::enhancedFolders($folder->id, $flat);
                foreach ($children as $k2 => $v2) {
                    $folders[] = $v2;
                }
            }
        } else {
            foreach ($folders as $k => $folder) {
                $folders[$k]->children = self::enhancedFolders($folder->id, $flat);
            }
        }

        return $folders;
    }

    /**
     * Return Real media library folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function realMediaFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'realmedialibrary';

        $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));

        if (!$wpdb->get_var($query) == $table) {
            return array();
        }

        $folders = $wpdb->get_results($wpdb->prepare('select r.id as id, r.name as title, r.parent as parent from %1$s as r where r.parent = %2$d order by r.ord', $table, $parent));

        if ($flat) {
            foreach ($folders as $j => $folder) {
                $children = self::realMediaFolders($folder->id, $flat);
                foreach ($children as $i => $v) {
                    $folders[] = $v;
                }
            }
        } else {
            foreach ($folders as $i => $folder) {
                $folders[$i]->children = self::realMediaFolders($folder->id, $flat);
            }
        }

        return $folders;
    }

    /**
     * Return Wpmf media library folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function wpmfFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $folders = $wpdb->get_results($wpdb->prepare('SELECT t.term_id as id, t.name as title, tt.term_taxonomy_id FROM %1$s as t  INNER JOIN %2$s as tt ON (t.term_id = tt.term_id) WHERE tt.taxonomy = \'wpmf-category\' AND tt.parent = %3$d', $wpdb->terms, $wpdb->term_taxonomy, $parent));

        foreach ($folders as $i => $folder) {
            $folders[$i]->parent = $parent;
        }

        if ($flat) {
            foreach ($folders as $j => $folder) {
                $children = self::wpmfFolders($folder->id, $flat);
                foreach ($children as $i => $v) {
                    $folders[] = $v;
                }
            }
        } else {
            foreach ($folders as $i => $folder) {
                $folders[$i]->children = self::wpmfFolders($folder->id, $flat);
            }
        }

        return $folders;
    }

    /**
     * Return Happy Files folders
     * @since 1.0.0
     * @param  mixed $parent
     * @param  mixed $flat
     * @return Array
     */
    public static function HappyFilesFolders($parent = 0, $flat = false)
    {
        global $wpdb;

        $folders = $wpdb->get_results($wpdb->prepare('SELECT t.term_id as id, t.name as title, tt.term_taxonomy_id FROM %1$s as t  INNER JOIN %2$s as tt ON (t.term_id = tt.term_id) WHERE tt.taxonomy = \'happyfiles_category\' AND tt.parent = %3$d', $wpdb->terms, $wpdb->term_taxonomy, $parent));

        foreach ($folders as $i => $folder) {
            $folders[$i]->parent = $parent;
        }

        if ($flat) {
            foreach ($folders as $j => $folder) {
                $children = self::HappyFilesFolders($folder->id, $flat);
                foreach ($children as $i => $v) {
                    $folders[] = $v;
                }
            }
        } else {
            foreach ($folders as $i => $folder) {
                $folders[$i]->children = self::HappyFilesFolders($folder->id, $flat);
            }
        }

        return $folders;
    }

    /**
     * Delete plugin data
     * @since 1.0.0
     * @param  mixed $plugin
     * @return Array
     */
    public static function deletePluginData($plugin)
    {
        global $wpdb;

		$plugin = isset( $_POST['plugin'] ) ? sanitize_text_field($_POST['plugin']) : false;

        if(in_array($plugin, ['filebird', 'realmedia', 'wpmlf'])) {

            $folders_deleted = false;

            $attachments_deleted = false;
    
            if ( $plugin === 'filebird' ) {
                $table = $wpdb->base_prefix . 'fbv';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $folders_deleted = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'fbv' );
                }

                $table = $wpdb->base_prefix . 'fbv_attachment_folder';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $attachments_deleted = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'fbv_attachment_folder' );
                }
            }

            if ( $plugin === 'realmedia' ) {

                $table = $wpdb->base_prefix . 'realmedialibrary';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $folders_deleted = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'realmedialibrary' );
                }

                $table = $wpdb->base_prefix . 'realmedialibrary_meta';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'realmedialibrary_meta' );
                }

                $table = $wpdb->base_prefix . 'realmedialibrary_posts';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $attachments_deleted = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'realmedialibrary_posts' );
                }
     
            }

            if ( $plugin === 'wpmlf' ) {
                $table = $wpdb->base_prefix . 'mgmlp_folders';
                $query      = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table));
                if ($wpdb->get_var($query) == $table) {
                    $folders_deleted = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'mgmlp_folders' );
                    $wpdb->query( 'DELETE FROM ' . $wpdb->posts . ' WHERE post_type = \'mgmlp_media_folder\'' );
                    Wp_Files_Helper::addOrUpdateOption('mgmlp_upload_folder_id', '0');
                }
            }
    
           return [
                'folders_deleted'     => $folders_deleted,
                'attachments_deleted' => $attachments_deleted,
                'message' => __("Data cleaned successfully", 'wpfiles')
            ];

        } else {

            $deleted = [];

            $folders = [];

            if($plugin == "happyfiles") {
                $folders = self::HappyFilesFolders(0, true);
            } else if($plugin == "enhanced") {
                $folders = self::enhancedFolders(0, true);
            } else if($plugin == "wpmlf") {
                $folders = self::wpmlfFolders(0, true);
            } else if($plugin == "wpmf") {
                $folders = self::wpmfFolders(0, true);
            }
            
            foreach ( $folders as $folder ) {

                $term_id = intval( $folder->id );
    
                if ( $term_id ) {
                    
                    $deleted[$term_id]['term_relationships'] = $wpdb->delete( $wpdb->prefix . 'term_relationships', ['term_taxonomy_id' => $term_id] );
                    $deleted[$term_id]['term_taxonomy'] = $wpdb->delete( $wpdb->prefix . 'term_taxonomy', ['term_id' => $term_id] );
                    $deleted[$term_id]['terms'] = $wpdb->delete( $wpdb->prefix . 'terms', ['term_id' => $term_id] );
    
                    // Delete termmeta data (= Category position)
                    if ( $plugin === 'folders' ) {
                        $deleted[$term_id]['termmeta'] = $wpdb->delete( $wpdb->prefix . 'termmeta', ['term_id' => $term_id] );
                    }
                }

            }
    
            return [
                'deleted' => $deleted,
                'post'    => Wp_Files_Helper::sanitizeArray($_POST),
                'message' => __("Data cleaned successfully", 'wpfiles')
            ];

        }
    }

    /**
     * Clean data before import
     * @since 1.0.5
     * @return void
     */
    public static function clean()
    {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM " . self::getTable(self::$options) . "
            WHERE
            option_name LIKE '%new_term_id_%';"
        );
    }

     /**
     * Get table
     * @since 1.0.5
     * @param $table
     * @return string
    */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
}
