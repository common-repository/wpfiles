<?php
trait Wp_Files_General_Hooks {

    /**
     * Media routes / Gutenberg routes
     * @since 1.0.0
     * @return void
    */
    public function media_routes()
	{
		//Media routes
		$this->media_controller->routes();

		
	}

	/**
     * When add attachment
     * @since 1.0.0
	 	 * @param $post_id
     * @return void
    */
	public function addAttachment($post_id)
	{
		$this->media_controller->addAttachment($post_id);
	}

	/**
     * When delete attachment
     * @since 1.0.0
	 	 * @param $post_id
     * @return void
    */
	public function deleteAttachment($post_id) {}

	/**
     * Validate optimize api endpoint
	   * Fix SSL CA Certificate issue.
     * @since 1.0.0
     * @return void
    */
	public function validate_optimize_api_endpoint() {
		
		// Hostgator issue
		$use_http = wp_cache_get(WP_FILES_PREFIX . 'use_http', WP_FILES_CACHE_PREFIX);

		if (!$use_http) {
			$use_http = $this->settings['use_http'];
			wp_cache_add(WP_FILES_PREFIX . 'use_http', $use_http, WP_FILES_CACHE_PREFIX);
		}

		if ($use_http) {
			define('WP_FILES_API_HTTP', 'https://optimize.wpfiles.io');
		}
	}
	
    /**
     * Set attachment Args
     * @since 1.0.0
	   * @param $query
     * @return array
    */
	public function setQueryAttachmentsArgs($query)
	{
		return $this->media_controller->setQueryAttachmentsArgs($query);
	}

	/**
     * Filters all query clauses at once, for convenience.
     * @since 1.0.0
	   * @param $clauses
	   * @param $query
     * @return array
    */
	public function customizedPostsClauses($clauses, $query)
	{
		return $this->media_controller->customizedPostsClauses($clauses, $query);
	}

	/**
     * Wordpress admin menus
     * @since 1.0.0
     * @return void
    */
    public function settingMenus()
	{
		global $submenu;

		$role = Wp_Files_Helper::getCurrentUserRole();

		if(!$this->settings['access_setting'] || is_super_admin() || ($this->settings['access_setting'] && $role == "administrator")) {
			
			if(get_option('wpfiles-install-hide')) {
				$this->pages['wpfiles'] = new Wp_Files_Menus("wpfiles", __('WPFiles', 'wpfiles'));
				$this->pages['wpfiles-modules'] = new Wp_Files_Menus("wpfiles", __('Modules', 'wpfiles'), "wpfiles", "wpfiles-modules");
				$this->pages['wpfiles-settings'] = new Wp_Files_Menus("wpfiles-settings", __('Settings', 'wpfiles'), "wpfiles", "wpfiles-settings");
			}
	
			if(!get_option('wpfiles-install-hide')) {
				$this->pages['wpfiles'] = new Wp_Files_Menus("wpfiles", __('WPFiles', 'wpfiles'));
				$this->pages['wpfiles-install'] = new Wp_Files_Menus("wpfiles", __('Install', 'wpfiles'), "wpfiles", "wpfiles-install");
			}
			
			//Show pro menu
			if(!Wp_Files_Subscription::is_pro($this->settings)) {
				$this->pages['wpfiles-pro'] = new Wp_Files_Menus("wpfiles-pro", '<span style="color: #00ffbf;">' . __('WPFiles Pro', 'wpfiles') . '<i class="dashicons dashicons-star-filled" style="font-size:12px;vertical-align:-2px;height:10px;"></i></span>', "wpfiles", "wpfiles-pro");
			}
	
			$submenu['wpfiles'][] = [ esc_html__( 'Help &amp; Support', 'wpfiles' ) . '<i class="dashicons dashicons-external" style="font-size:12px;vertical-align:-2px;height:10px;"></i>', 'level_1', WP_FILES_GO_URL.'/support' ];
	
			//screens
			if (count($this->pages) > 0) {
				foreach ($this->pages as $key => $page) {
					$GLOBALS[$page->page_id] = $page->page_id;
				}
			}
		}
	}

	/**
	 * adminHead
	 * @since 1.0.0
	 * @return void
	 */
	public function adminHead()
	{
		$this->loadTemplate('layout/head');
	}
	
	/**
     * Settings routes
     * @since 1.0.0
     * @return void
    */
	public function settings_routes()
	{
		$this->import_media_plugin_controller->routes();

		$this->settings_controller->routes();
	}

	/**
     * Export WPFiles content
     * @since 1.0.0
     * @return void
    */
	public function exportWpFilesContent()
	{
		$this->import_media_plugin_controller->export();
	}

	/**
     * Compression routes
     * @since 1.0.0
     * @return void
    */
    public function compression_routes()
	{
		$this->compression_controller->routes();
	}

	/**
     * Add button to media-new.php in WP admin
     * @since 1.0.0
     * @return void
    */
	public function upload_ui_media_new()
	{
		if ( is_admin() && function_exists('get_current_screen') && get_current_screen() && get_current_screen()->base === 'media' ) {
			echo '
			<p>' . esc_attr(__("Optional: Assign a category to your uploaded file(s):", 'wpfiles')) . '</p>
			<div id="wp-media-new-screen-select-folders"></div><br>';
		}
	}

	/**
     * Before upload media
     * @since 1.0.0
	   * @param $file
     * @return mixed
    */
	public function uploadPreFilter($file)
	{
		if(function_exists('get_current_screen') && get_current_screen() && get_current_screen()->base === 'async-upload') {
			if(isset($_REQUEST['wpf'])) {
				$result = $this->validate_file( $file['name'] );
	
				if ( is_wp_error( $result ) ) {
					$file['error'] = $result->get_error_message();
				}
		
				$file_size = @filesize($file['tmp_name']);
		
				$max_file_upload = wp_max_upload_size();
		
				if(isset($file_size) && $file_size > 0 && isset($max_file_upload) && $max_file_upload > 0 && $file_size > $max_file_upload) {
					$file['error'] = __("You have reached your maximum file upload size limit", 'wpfiles');
				}
		
				$file['wpf'] = isset($_REQUEST['wpf']) ? sanitize_text_field($_REQUEST['wpf']) : -1;
		
				//Prevent to upload duplicate in case of queue process
				$upload_ids = get_transient('upload-ids');
		
				if(isset($_REQUEST['hash']) && $_REQUEST['hash']) {
					if(!empty($upload_ids)) {
						if(in_array($_REQUEST['hash'], $upload_ids)) {
							wp_send_json_success(array(
								'message' => __('Already uploaded', 'wpfiles')
							));
						} else {
							$upload_ids[] = sanitize_text_field($_REQUEST['hash']);
							set_transient('upload-ids', $upload_ids, 3600 );
						}
					} else {
						$upload_ids = [sanitize_text_field($_REQUEST['hash'])];
						set_transient('upload-ids', $upload_ids, 3600 );
					}
				}
			} else {
				$file['error'] = __('Disable default wordpress drag & drop', 'wpfiles');
			}
		}

		return $file;
	}

	/**
	 * Validate File
	 * @since 1.0.0
	 * @param string $file_path
	 * @param array $file_extensions Optional
	 * @return bool|\WP_Error
	 *
	*/
	private function validate_file( $file_path, $file_extensions = [] ) {

		$file_extension = pathinfo( $file_path, PATHINFO_EXTENSION );

		$allowed_file_extensions = $this->get_allowed_file_extensions();

		if ( $file_extensions ) {
			$allowed_file_extensions = array_intersect( $allowed_file_extensions, $file_extensions );
		}

		$file_extensions = [];

		if(count($allowed_file_extensions) > 0) {
			foreach($allowed_file_extensions as $file_ext) {
				if(str_contains($file_ext, '|')) {
					foreach(explode("|", $file_ext) as $ext) {
						$file_extensions[] = strtolower(trim($ext));
					}
				} else {
					$file_extensions[] = strtolower(trim($file_ext));
				}
			}
		}

		// Check if the file type (extension) is in the allowed extensions list. If it is a non-standard file type (not
		// enabled by default in WordPress) and unfiltered file uploads are not enabled, it will not be in the allowed
		// file extensions list.
		if ( ! in_array( $file_extension, $file_extensions ) ) {
			return new \WP_Error( 403, __('Uploading this file type is not allowed', 'wpfiles'));
		}

		return true;
	}

	/**
	 * Get Allowed File Extensions
	 * Retrieve an array containing the list of file extensions allowed for upload.
	 * @since 1.0.0
	 * @return array file extension/s
	 */
	private function get_allowed_file_extensions() {
		if ( ! $this->allowed_file_extensions ) {
			$this->allowed_file_extensions = array_keys( get_allowed_mime_types() );
		}

		return $this->allowed_file_extensions;
	}

    /**
     * Set current page id
     * @since 1.0.0
     * @return int
    */
	public function getPageId()
	{
		if (null == $this->pageId) {
			$this->pageId = WP_FILES_VERSION . '-settings';
		}

		return $this->pageId;
	}

	/**
     * Fires as an admin screen or script is being initialized.
     * @since 1.0.0
     * @return void
    */
	public function admin_init()
	{
		//Wordpress media library
		$this->wp_media_library = new Wp_Files_Media_Library($this->settings);
		$this->wp_media_library->init_ui(); // Init media library UI.

		if($this->settings['disable_wp_compression']) {
			// Disable WordPress image compression
			add_filter( 'wp_editor_set_quality', function( $arg ) {
				return 100;
			});
		}

		//Check schedules
		self::check_schedules();
	}

	/**
     * Fires after WordPress has finished loading but before any headers are sent.
     * @since 1.0.0
     * @return void
    */
	public function init()
	{
		
	}

	/**
     * Load template
     * @since 1.0.0
	   * @param $template
	   * @param $lib_params
     * @return void
    */
    public function loadTemplate($template, $lib_params = array())
    {
        ob_start();
        $params = $lib_params;
        include_once WP_FILES_PLUGIN_DIR . 'admin/partials/' . $template . ".php";
        $template = ob_get_contents();
        ob_end_clean();
        echo $template;
    }

	/**
	 * Redirect to installer page if activating plugin first time
	 * @since 1.0.0
	 * @return void
	 */
	public function redirect_after_installation()
    {
		if (get_option('wpfiles-activation-redirect', false)) {
			delete_option('wpfiles-activation-redirect');
			wp_redirect( admin_url( 'admin.php?page=wpfiles' ) );
		}
	}

	/**
	 * Save usage tracking.
	 * @since 1.0.0
	 * @return void
	 */
	public function save_usage_tracking() {
		update_option(WP_FILES_PREFIX.'usage_tracking', 1);
	}

	/**
	 * Get table
	 * @since 1.0.0
	 * @param $table
	 * @return void
	 */
    private static function getTable($table)
    {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
	
	/**
	 * Fires once activated plugins have loaded
	 * @since 1.0.0
	 * @return void
	 */
	public function plugins_loaded() {

		//Manage plugin updates
		$this->plugin_update = new Wp_Files_Plugin_Update($this->settings);
		$this->plugin_update->init();

		
	}

	/**
	 * Extend posts query for posts count
	 * @since 1.0.0
	 * @param $where
	 * @return array
	 */
	public function extendFetchCountWhereQuery($where)
    {
        global $wpdb;
        
		if (function_exists('picu_exclude_collection_images_from_library')) {
            $where[] = " posts.post_parent NOT IN (SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type = 'picu_collection') ";
        }
		
        if (function_exists('_is_elementor_installed')) {
            $where[] = " posts.ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_is_screenshot') ";
        }

        return $where;
    }

	/**
	 * Filters the array of row meta for each plugin in the Plugins list table.
	 * @since 1.0.0
	 * @param $links
	 * @param $file
	 * @return array
	 */
	public function plugin_row_meta($links, $file)
    {
		if ( strpos( $file, 'wpfiles.php' ) !== false ) {
			
			$links[] = '<a href="'.WP_FILES_GO_URL.'/help" target="_blank">'. __("Docs", "wpfiles") .'</a>';
			
			$links[] = '<a href="'.WP_FILES_GO_URL.'/support" target="_blank">'. __("Support", "wpfiles") .'</a>';
		}

		return $links;
	}

	/**
	 * Filters the list of action links displayed for a specific plugin in the Plugins list table.
	 * @since 1.0.0
	 * @param $links
	 * @return array
	 */
	public function plugin_action_links($links)
    {

		$settings = (array) Wp_Files_Settings::loadSettings();
		
		$settingsLinks = array(
			'<a href="' . admin_url( 'admin.php?page=wpfiles-settings' ) . '">'. __("Settings", "wpfiles") .'</a>',
		);

		//Show for only Free

		if(Wp_Files_Subscription::is_active($settings) && isset($settings['site_status']['website']) && is_array($settings['site_status']['website']) && $settings['site_status']['website']['was_pro'] == 1 && $settings['site_status']['is_free'] == 1 && isset($settings['site_status']['is_trial_used']) && $settings['site_status']['is_trial_used'] > 0) {
			$go_pro = __( 'Renew subscription', 'wpfiles' );
		} else if(Wp_Files_Subscription::is_active($settings) && isset($settings['site_status']['is_trial_used']) && $settings['site_status']['is_trial_used'] > 0) {	
			$go_pro = __( 'Upgrade to Pro', 'wpfiles' );
		} else {
			$go_pro = __( 'Start free trial', 'wpfiles' );
		}

		
		if((is_plugin_active( 'wpfiles/wpfiles.php' ) && WP_FILES_BASENAME == 'wpfiles/wpfiles.php') || !Wp_Files_Subscription::is_pro($this->settings)) {
			$links[] = '<a target="_blank" href="'.WP_FILES_GO_URL.'/pricing" style="color: #cf37e6;font-weight: 600;">' . $go_pro . '</a>';
		}
		
		return array_merge( $settingsLinks, $links );
	}
}
?>
