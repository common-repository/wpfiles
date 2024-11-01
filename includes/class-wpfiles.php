<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */

/**
 * The core plugin class.
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/includes
 */
class WPFiles
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wp_Files_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 * @since    1.0.0
	 * @return void
	 */
	public function __construct()
	{
		if (defined('WP_FILES_VERSION')) {
			$this->version = WP_FILES_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$this->plugin_name = WP_FILES_PLUGIN_NAME;

		$this->load_dependencies();

		$this->set_locale();

		$this->define_admin_hooks();

		$this->define_public_hooks();

		//WPFiles media related hooks
		Wp_Files_Media_Hook::getInstance();
	}

	/**
	 * Load the required dependencies for this plugin.
	 * Include the following files that make up the plugin:
	 * - Wp_Files_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_Files_i18n. Defines internationalization functionality.
	 * - Wp_Files_Admin. Defines all hooks for the admin area.
	 * - Wp_Files_Public. Defines all hooks for the public side of the site.
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function load_dependencies()
	{

		//Extend wordpress Media List 
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		if ( ! class_exists( 'WP_Media_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-media-list-table.php' );
		}

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wpfiles-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wpfiles-i18n.php';

		/**
		 * The class/traits responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/traits/class-wpfiles-notice-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/traits/class-wpfiles-general-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/traits/crons/class-wpfiles-crons-hooks.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wpfiles-public.php';

		//Wordpress related Api/Hooks
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/media_library/class-wpfiles-media-library.php';

		//Helpers
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/helpers/class-wpfiles-helper.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/helpers/class-wpfiles-iterator.php';

		//Media
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/class-wpfiles-tree.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/media/class-wpfiles-media.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/controllers/class-wpfiles-media-controller.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/hooks/class-wpfiles-media-hook.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/page-builder/class-wpfiles-page-builder.php';

		//Settings
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/class-wpfiles-menus.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/import/class-wpfiles-import.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/controllers/class-wpfiles-import-controller.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/controllers/class-wpfiles-settings-controller.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/class-wpfiles-settings.php';

		//Wordpress WXR libraries
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/import/parsers/class-wxr-parser.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/import/parsers/class-wxr-parser-simplexml.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/import/parsers/class-wxr-parser-xml.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/import/parsers/class-wxr-parser-regex.php';

		//Cdn && LazyLoad
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/controllers/class-wpfiles-cdn-controller.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/cdn/class-wpfiles-cdn-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/cdn/class-wpfiles-cdn-utilities.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/cdn/class-wpfiles-cdn.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/lazyload/class-wpfiles-lazyload-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/lazyload/class-wpfiles-lazyload-utilities.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/lazyload/class-wpfiles-lazyload.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/parser/class-wpfiles-page-parser.php';

		//Compression & watermark
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/api/class-wpfiles-request.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/api/class-wpfiles-api.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-compression-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-compression.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-png-to-jpg.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-webp.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-resize.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-stats-hooks.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-stats.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-compression_requests.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/controllers/class-wpfiles-compression-controller.php';

		//Common
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/class-wpfiles-subscription.php';

		//Directory
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-directory.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/compression/class-wpfiles-directory-scanner.php';

		//Integrations
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/gutenberg/class-wpfiles-admin-gutenberg.php';

		//Svg support
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/svg/class-wpfiles-svg-tags.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/svg/class-wpfiles-svg-attributes.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/svg/class-wpfiles-svg-support.php';

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}
		
		if (!function_exists('wp_generate_attachment_metadata')) {
			require_once(ABSPATH . 'wp-admin/includes/image.php');
		}

		//Plugin updates
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/repositories/class-wpfiles-plugin-update.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wpfiles-admin.php';

		$this->loader = new Wp_Files_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 * Uses the Wp_Files_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function set_locale()
	{
		$plugin_i18n = new Wp_Files_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function define_admin_hooks()
	{
		$plugin_admin = new Wp_Files_Admin($this->get_plugin_name(), $this->get_version());

		//Action hook for loading script
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

		//Media hooks
		$this->media_hooks($plugin_admin);

		//Settings hooks
		$this->settings_hooks($plugin_admin);

		//Fires as an admin screen or script is being initialized.
		$this->loader->add_action('admin_init', $plugin_admin, 'admin_init');

		//Fires after WordPress has finished loading but before any headers are sent.
		$this->loader->add_action('init', $plugin_admin, 'init');

		//Cron schedules
		$this->loader->add_action('cron_schedules', $plugin_admin, 'cron_schedules');
		
		//Crons jobs
		$this->loader->add_action(WP_FILES_PREFIX . 'api_update_status', $plugin_admin, 'api_update_status');
		$this->loader->add_action(WP_FILES_PREFIX . 'delete_zip_files', $plugin_admin, 'deleteZipFiles');
		$this->loader->add_action(WP_FILES_PREFIX . 'feedback_notice_cron', $plugin_admin, 'feedback_notice_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'upgrade_hello_bar_cron', $plugin_admin, 'upgrade_hello_bar_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'usage_tracking_modal_cron', $plugin_admin, 'usage_tracking_modal_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'connect_account_notice_cron', $plugin_admin, 'connect_account_notice_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'post_usage_tracking_cron', $plugin_admin, 'post_usage_tracking_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'watermark_font_crons_cron', $plugin_admin, 'watermark_font_crons_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'upgrade_to_pro_cron', $plugin_admin, 'upgrade_to_pro_cron');
		$this->loader->add_action(WP_FILES_PREFIX . 'daily_cron', $plugin_admin, 'dailyCron');
		$this->loader->add_action(WP_FILES_PREFIX . 'clean_folders_cron', $plugin_admin, 'clean_folders_cron');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function define_public_hooks()
	{
		$plugin_public = new Wp_Files_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 * @since    1.0.0
	 * @return void
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 * @since     1.0.0
	 * @return    Wp_Files_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

	/**
	 * Media screen hooks
	 * @since     1.0.0
	 * @return    void
	*/
	public function media_hooks($plugin_admin)
	{
		$this->loader->add_action('rest_api_init', $plugin_admin, 'media_routes');

		$this->loader->add_action('rest_api_init', $plugin_admin, 'compression_routes');

		$this->loader->add_action('add_attachment', $plugin_admin, 'addAttachment');

		$this->loader->add_filter('wp_handle_upload_prefilter', $plugin_admin, 'uploadPreFilter');

		$this->loader->add_action('deleteAttachment', $plugin_admin, 'deleteAttachment');

		$this->loader->add_action('ajax_query_attachments_args', $plugin_admin, 'setQueryAttachmentsArgs', 20);

		$this->loader->add_action('mla_media_modal_query_final_terms', $plugin_admin, 'setQueryAttachmentsArgs', 20);

		$this->loader->add_action('posts_clauses', $plugin_admin, 'customizedPostsClauses', 10, 2);

		add_action( 'media_library_infinite_scrolling', '__return_true' );

		$this->loader->add_action('wpfiles_extend_fetch_count_where_query', $plugin_admin, 'extendFetchCountWhereQuery', 10, 1);

		$this->loader->add_filter( 'pre-upload-ui', $plugin_admin, 'upload_ui_media_new' );

		//Disabled for now because our server works only with https
		//$this->loader->add_action('validate_optimize_api_endpoint', $plugin_admin, 'validate_optimize_api_endpoint', 20);
	}

	/**
	 * Settings screens hooks
	 * @since     1.0.0
	 * @return    void
	*/
	public function settings_hooks($plugin_admin)
	{
		//Admin menus
		$this->loader->add_action('admin_menu', $plugin_admin, 'settingMenus', 11);

		//Fires in head section for all admin pages.
		$this->loader->add_action('admin_head', $plugin_admin, 'adminHead', 11);

		//routes
		$this->loader->add_action('rest_api_init', $plugin_admin, 'settings_routes');

		//Export wpFiles content
		$this->loader->add_action('admin_init', $plugin_admin, 'exportWpFilesContent');

		//Plugins list screen
		$this->loader->add_action('plugin_row_meta', $plugin_admin, 'plugin_row_meta', 10, 2);
		$this->loader->add_action('plugin_action_links_' . WP_FILES_BASENAME, $plugin_admin, 'plugin_action_links', 10);

		/**********Notices***********/
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'initial_trial_upgrade_notice');
		$this->loader->add_action( 'network_admin_notices', $plugin_admin, 'initial_trial_upgrade_notice' );

		$this->loader->add_action( 'admin_notices', $plugin_admin, 'free_to_pro_plugin_conversion_notice');
		$this->loader->add_action( 'network_admin_notices', $plugin_admin, 'free_to_pro_plugin_conversion_notice');
		
		// Prints a subscription validation issue notice in Media Library.
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'website_pro_to_free_notice');
		$this->loader->add_action( 'wp_ajax_update_api_status', $plugin_admin, 'update_api_status');

		// Plugin conflict notice.
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'show_plugin_conflict_notice' );
		$this->loader->add_action( 'activated_plugin', $plugin_admin, 'check_for_conflicts_cron' );
		$this->loader->add_action( 'deactivated_plugin', $plugin_admin, 'check_for_conflicts_cron' );

		//Account related notice
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'account_connect_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'cdn_suspended_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'account_payment_due_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'upgrade_to_pro_notice' );

		/**********General hooks***********/
		$this->loader->add_action( 'wp_ajax_dismiss_notice', $plugin_admin, 'dismiss_notice' );

		// Usage tracking notice/settings.
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'show_plugin_usage_tracking_notice' );
		$this->loader->add_action( 'wp_ajax_save_usage_tracking', $plugin_admin, 'save_usage_tracking' );

		//Account domain mismatch notice
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'show_plugin_domain_mismatch_notice' );

		//WPFiles rate notice
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'wpfiles_rate_notice' );
		
		//Plugin activation
		$this->loader->add_action( 'admin_init', $plugin_admin, 'redirect_after_installation' );

		//Fires once activated plugins have loaded.
		$this->loader->add_action('plugins_loaded', $plugin_admin, 'plugins_loaded');
	}
}
