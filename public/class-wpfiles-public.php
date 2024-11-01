<?php

/**
 * The public-facing functionality of the plugin.
 * @link       https://wpfiles.io
 * @since      1.0.0
 * @package    WPFiles
 * @subpackage WPFiles/public
 */

/**
 * The public-facing functionality of the plugin.
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 * @package    WPFiles
 * @subpackage WPFiles/public
 */
class Wp_Files_Public
{

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
	 * The cdn object of this plugin
	 * Load all images from cdn
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $cdn    The cdn object of this plugin.
	 */
	private $cdn;

	/**
	 * The settings object of this plugin
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $settings  
	 */
	private $settings;

	/**
	 * Initialize the class and set its properties.
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;

		$this->version = $version;

		$this->settings = Wp_Files_Settings::loadSettings();

		$this->cdn = new Wp_Files_Cdn_Controller();
		
		$this->cdn->init();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wpfiles-public.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		/**
		 * This function is provided for demonstration purposes only.
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Files_Loader as all of the hooks are defined
		 * in that particular class.
		 * The Wp_Files_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wpfiles-public.min.js', array('jquery'), $this->version, false);

		if($this->settings['gutenberg_support']) {
			$this->gutenberg_gallery_block(); //Gutenberg gallery block
		}
	}

	/**
	 * Gutenberg gallery block
	 * @since 1.0.0
	 * Lightbox for Gallery & Image Block
	 * Masonry for Gallery & Image Block
	 */
	public function gutenberg_gallery_block()
	{
		wp_register_style($this->plugin_name . 'baguettebox-css', plugin_dir_url(__FILE__) . 'js/baguetteBox/baguetteBox.min.css', [], $this->version);

		wp_register_script($this->plugin_name . 'baguettebox', plugin_dir_url(__FILE__) . 'js/baguetteBox/baguetteBox.min.js', [], $this->version, true);

		//wp_enqueue_style($this->plugin_name . 'masonry', plugin_dir_url(__FILE__) . 'js/masonry/masonry.css', array(), $this->version, 'all');

		//wp_register_script($this->plugin_name . 'masonry-imagesloaded', plugin_dir_url(__FILE__) . 'js/masonry/imagesloaded.pkgd.min.js', [], $this->version, true);

		//wp_register_script($this->plugin_name . 'masonry-load', plugin_dir_url(__FILE__) . 'js/masonry/masonry.pkgd.min.js', [], $this->version, true);

		/**
		 * Filters the CSS selector of baguetteBox.js
		 * @since 1.0.0
		 * @param string  $value  The CSS selector to a gallery (or galleries) containing a tags
		 */

		$baguettebox_selector = apply_filters('baguettebox_selector', '.wpfiles-gallery-lightbox-block,:not(.wpfiles-gallery-lightbox-block)>.wp-block-image,.wp-block-media-text,.gallery,.wp-block-coblocks-gallery-masonry,.wp-block-coblocks-gallery-stacked,.wp-block-coblocks-gallery-collage,.wp-block-coblocks-gallery-offset,.wp-block-coblocks-gallery-stacked');

		/**
		 * Filters the image files filter of baguetteBox.js
		 * @since 1.0.0
		 * @param string  $value  The RegExp Pattern to match image files. Applied to the a.href attribute
		 */
		$baguettebox_filter = apply_filters('baguettebox_filter',  '/.+\.(gif|jpe?g|png|webp|svg|avif|heif|heic|tif?f|)($|\?)/i');

		wp_add_inline_script($this->plugin_name . 'baguettebox', 'window.addEventListener("load", function() {baguetteBox.run("' . $baguettebox_selector . '",{captions:function(t){var e=t.parentElement.classList.contains("wp-block-image")?t.parentElement.querySelector("figcaption"):t.parentElement.parentElement.querySelector("figcaption,dd");return!!e&&e.innerHTML},filter:' . $baguettebox_filter . '});});');

		//wp_add_inline_script($this->plugin_name . 'masonry-load', 'jQuery(".wpfiles-gallery-masonry-block").imagesLoaded(function() {jQuery(".wpfiles-gallery-masonry-block").masonry({itemSelector: ".blocks-gallery-item"});});');

		if (has_block('wpfiles/block-wpfiles-gallery')) {

			//Lightbox
			wp_enqueue_script($this->plugin_name . 'baguettebox');
			wp_enqueue_style($this->plugin_name . 'baguettebox-css');

			//Masonry
			//wp_enqueue_script($this->plugin_name . 'masonry-imagesloaded');
			//wp_enqueue_script($this->plugin_name . 'masonry-load');
		}
	}
}
