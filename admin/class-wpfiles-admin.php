<?php
/**
 * The admin-specific functionality of the plugin.
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/admin
 */

/**
 * The admin-specific functionality of the plugin.
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 * @since 1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/admin
 */
class Wp_Files_Admin
{

	/**
	 * Traits to extend admin class functionalities.
	 * @since 1.0.0
	*/
	use Wp_Files_Notice_Hooks, Wp_Files_General_Hooks, Wp_Files_Crons_hooks;

	/**
	 * The ID of this plugin.
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The media controller
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $media_controller    
	 */
	private $media_controller;

	/**
	 * Pages
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $pages    
	 */
	private $pages = [];

	/**
	 * Pages
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed    $pageId    
	 */
	private $pageId = null;

	/**
	 * Import media plugin controller
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed    $import_media_plugin_controller    
	 */
	private $import_media_plugin_controller;

	/**
	 * Compression controller
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $compression_controller    
	 */
	private $compression_controller;

	/**
	 * Settings controller
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $settings_controller    
	 */
	private $settings_controller;

	/**
	 * Settings
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $settings    
	 */
	private $settings;

	/**
	 * Wordpress media library
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $wp_media_library    
	 */
	private $wp_media_library;

	/**
	 * Gutenberg support
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $gutenberg    
	 */
	private $gutenberg;

	/**
	 * Page builder support
	 * @since    1.0.0
	 * @access   private
	 * @var      mixed $page_builder    
	 */
	private $page_builder;

	/**
	 * Allowed file extensions.
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $allowed_file_extensions 
	 */
	private $allowed_file_extensions;

	/**
	 * Initialize the class and set its properties.
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 * @return void
	 */

	/**
     * Folder table
     * @since 1.0.5
     * @var string $folder_table
    */
    private static $folder_table = 'wpf';

    /**
     * Relation table
     * @since 1.0.5
     * @var string $relation_table
    */
    private static $relation_table = 'wpf_attachment_folder';

	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->settings = Wp_Files_Settings::loadSettings();
		$this->media_controller = new Wp_Files_Media_Controller();
		$this->import_media_plugin_controller = new Wp_Files_Import_Controller();
		$this->compression_controller = new Wp_Files_Compression_Controller();
		$this->settings_controller = new Wp_Files_Settings_Controller();
		$this->stats = new Wp_Files_Stats($this->settings);
		$this->compression = new Wp_Files_Compression($this->settings);
	}

	/**
	 * Register the stylesheets for the admin area.
	 * @since    1.0.0
	 * @param $screen
	 * @return void
	 */
	public function enqueue_styles($screen)
	{
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Files_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Files_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if (function_exists('get_current_screen')) {
			if ($screen == "upload.php") {
				wp_enqueue_style($this->plugin_name.'-global', WP_FILES_PLUGIN_URL . 'admin/css/wpfiles-admin.min.css', array(), $this->version, 'all');
				wp_enqueue_style($this->plugin_name.'-media-modal', WP_FILES_PLUGIN_URL . 'admin/css/wpfiles-media-modal.min.css', array(), $this->version, 'all');
			} else if (false !== strpos($screen, 'wpfiles_page') || false !== strpos($screen, 'page_wpfiles')) {
				wp_enqueue_style($this->plugin_name.'-admin-settings', WP_FILES_PLUGIN_URL . 'admin/css/wpfiles-admin-settings.min.css', array(), $this->version, 'all');
			}
			wp_enqueue_style($this->plugin_name.'-loader', WP_FILES_PLUGIN_URL . 'admin/css/wpfiles-admin-loader.min.css', array(), $this->version, 'all');
			wp_enqueue_style($this->plugin_name.'-notices', WP_FILES_PLUGIN_URL . 'admin/css/wpfiles-notices.min.css', array(), $this->version, 'all');
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 * @since  1.0.0
	 * @param $screen
	 * @return void
	 */
	public function enqueue_scripts($screen)
	{
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Files_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Files_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if (function_exists('get_current_screen')) {

			$script_data = Wp_Files_Settings::getLocalizeScript($screen, $this->settings);

			wp_enqueue_script($this->plugin_name.'-global', WP_FILES_PLUGIN_URL . 'admin/js/wpfiles-admin.min.js', array('jquery'), $this->version, true);

			//Support WPFiles for wordpress media library
			if(in_array($screen, ["upload.php", "media-new.php", "plugins.php"]) || false !== strpos($screen, 'wpfiles_page') || false !== strpos($screen, 'page_wpfiles')) {
				$script_data['screen'] = $screen;
				if(in_array($screen, ["upload.php", "media-new.php"]) && !get_option(WP_FILES_PREFIX.'install-hide')) {
					wp_redirect( admin_url( 'admin.php?page=wpfiles' ) );
				}
			} else {
				if($this->settings['thirdparty_compatibility'] == 1) {
					if(in_array($screen, ["post-new.php", "post.php"]) && $this->settings['woocommerce_support'] == 1 && class_exists( 'WooCommerce' )) {
						$product = wc_get_product( sanitize_text_field($_GET['post']) );
						if($product || (isset($_GET['post_type']) && $_GET['post_type'] == 'product')) {
							if($this->settings['woocommerce_editor_support']) {
								$screen = "media-page-builder-pro";
								$script_data['screen'] = "media-page-builder-pro";
								$script_data['plugin'] = "third_party_woocommerce";
							}
						} else if(is_plugin_active( 'classic-editor/classic-editor.php' ) || is_plugin_active_for_network( 'classic-editor/classic-editor.php' )) {
							if($this->settings['class_editor_support']) {
								$screen = "media-page-builder-pro";
								$script_data['screen'] = "media-page-builder-pro";
								$script_data['plugin'] = "third_party_class_editor";
							}
						} else if($this->settings['gutenberg_editor_support']) {
							//Gutenberg free support
							$screen = "media-page-builder";
							$script_data['screen'] = "media-page-builder";
						}
					} else if(is_plugin_active( 'classic-editor/classic-editor.php' ) || is_plugin_active_for_network( 'classic-editor/classic-editor.php' )) {
						if($this->settings['class_editor_support']) {
							$screen = "media-page-builder-pro";
							$script_data['screen'] = "media-page-builder-pro";
							$script_data['plugin'] = "third_party_class_editor";
						}
					} else if($this->settings['gutenberg_editor_support']) {
						//Gutenberg free support
						$screen = "media-page-builder";
						$script_data['screen'] = "media-page-builder";
					}
				}
			}

			wp_localize_script($this->plugin_name.'-global', 'appLocalizer', apply_filters('appLocalizer', $script_data));
			
			self::enqueueScreenAssets($screen, $script_data, $this->plugin_name, $this->version);
		}
	}

	/**
	 * Register the JavaScript for the screens assets
	 * @since  1.0.0
	 * @param $screen
	 * @param $data
	 * @param $plugin_name
	 * @param $version
	 * @return void
	 */
	public static function enqueueScreenAssets($screen, $data, $plugin_name, $version) {

		$capability = is_multisite() ? 'manage_network' : 'manage_options';

		//Users permissions to access WPFiles screens only
		if(current_user_can( $capability ) || current_user_can( 'upload_files' )) {

			$js_to_load = WP_FILES_PLUGIN_URL . 'admin/js/app.js';
			
			if (in_array($screen, ["upload.php", "media-page-builder", "media-page-builder-pro", "media-new.php", "plugins.php"])) {

				wp_enqueue_script($plugin_name, $js_to_load, array('jquery', 'media', 'media-grid', 'media-editor', 'media-views', 'wp-element'), $version, true);

				wp_localize_script($plugin_name, 'appLocalizer', apply_filters('appLocalizer', $data));

			} else if (false !== strpos($screen, 'wpfiles_page') || false !== strpos($screen, 'page_wpfiles')) {

				wp_enqueue_script($plugin_name, $js_to_load, array('jquery', 'media', 'media-grid', 'media-editor', 'media-views', 'wp-element'), $version, true);

				wp_localize_script($plugin_name, 'appLocalizer', apply_filters('appLocalizer', $data));

				wp_enqueue_media(); //Wordpress media api
			}

		}
		
	}
}
