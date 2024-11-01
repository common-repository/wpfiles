<?php
trait Wp_Files_LazyLoad_Utilities
{
    /**
	 * Determine whether it is an AMP page.
	 * @since 1.0.0
	 * @return bool Whether AMP.
	 */
	private function isAmp()
	{
		return function_exists('is_amp_endpoint') && is_amp_endpoint();
	}

	/**
	 * Support for plugins that use the masonry grid system
	 * @since 1.0.0
	 * @return void
	*/
	private function addMasonrySupport()
	{
		if (!function_exists('has_block')) {
			return;
		}

		if (!has_block('blockgallery/masonry') && !has_block('coblocks/gallery-masonry')) {
			return;
		}

		$js = "var e = jQuery( '.wp-block-coblocks-gallery-masonry ul' );";
		
		if (has_block('blockgallery/masonry')) {
			$js = "var e = jQuery( '.wp-block-blockgallery-masonry ul' );";
		}

		$blockGalleryCompat = "jQuery(document).on('lazyloaded', function(){{$js} if ('function' === typeof e.masonry) e.masonry();});";

		wp_add_inline_script('wpfiles-lazy-load', $blockGalleryCompat);
	}

	/**
	 * Fusion gallery support for Avada theme
	 * @since 1.0.0
	 * @return void
	 */
	private function addAvadaSupport()
	{
		if (!defined('FUSION_BUILDER_VERSION')) {
			return;
		}

		$js = "var e = jQuery( '.fusion-gallery' );";

		$blockGalleryCompat = "jQuery(document).on('lazyloaded', function(){{$js} if ('function' === typeof e.isotope) e.isotope();});";

		wp_add_inline_script('wpfiles-lazy-load', $blockGalleryCompat);
	}

	/**
	 * Divi support for lazyload support.
	 * @since 1.0.0
	 * @return void
	 */
	private function addDiviSupport()
	{
		if (!defined('ET_BUILDER_THEME') || !ET_BUILDER_THEME) {
			return;
		}

		$script = "function rw() { Waypoint.refreshAll(); } window.addEventListener( 'lazybeforeunveil', rw, false); window.addEventListener( 'lazyloaded', rw, false);";

		wp_add_inline_script('wpfiles-lazy-load', $script);
	}

	/**
	 * Soliloquy support lazy loading.
	 * @since 1.0.0
	 * @return void
	 */
	private function addSoliloquySupport()
	{
		if (!function_exists('soliloquy')) {
			return;
		}

		$js = "var e = jQuery( '.soliloquy-image:not(.lazyloaded)' );";

		$soliloquy = "jQuery(document).on('lazybeforeunveil', function(){{$js}e.each(function(){lazySizes.loader.unveil(this);});});";

		wp_add_inline_script('wpfiles-lazy-load', $soliloquy);
	}

	/**
	 * Skip it, if not enabled.
	 * @since 1.0.0
	 * @return bool
	 */
	private function skipPostTypes()
	{
		if($this->settings['lazy_post_type'] == "custom") {
			if (!is_array($this->settings['lazy_post_types']) || empty($this->settings['lazy_post_types'])) {
				return true;
			}
	
			$blogIsFrontpage = ('posts' === get_option('show_on_front') && !is_multisite()) ? true : false;
	
			if (is_front_page() && !in_array('front-page', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (is_home() && !in_array('blogs', $this->settings['lazy_post_types']) && !$blogIsFrontpage) {
				return true;
			} elseif (is_page() && !in_array('pages', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (is_single() && !in_array('posts', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (is_archive() && !in_array('archives', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (is_category() && !in_array('categories', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (is_tag() && !in_array('tags', $this->settings['lazy_post_types'])) {
				return true;
			} elseif (self::skipCustomPostTypes(get_post_type())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Exclude uri from lazy loading
	 * @since 1.0.0
	 * @return bool
	 */
	private function isExludedUri()
	{
		if (!isset($this->settings['lazy_disable_urls']) || empty($this->settings['lazy_disable_urls'])) {
			return false;
		}

		$requestUri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		$uriPattern = array_filter($this->settings['lazy_disable_urls']);
		
		$uriPattern = implode('|', $uriPattern);

		if (preg_match("#{$uriPattern}#i", $requestUri)) {
			return true;
		}

		return false;
	}

	/**
	 * Check images for Class|ID.
	 * @since 1.0.0
	 * @param string $img.
	 * @return bool
	*/
	private function hasExcludedClassOrId($img)
	{
		$imageClasses = Wp_Files_PageParser::getAttribute($img, 'class');

		$imageClasses = explode(' ', $imageClasses);

		$imageId      = '#' . Wp_Files_PageParser::getAttribute($img, 'id');

		if (in_array($imageId, (array)$this->settings['lazy_disable_classes'])) {
			return true;
		}

		foreach ($imageClasses as $class) {

			if (in_array($class, $this->excluded_classes, true)) {
				return true;
			}

			if (in_array(".{$class}", (array)$this->settings['lazy_disable_classes'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Skip custom post type that are added in settings.
	 * @since 1.0.0
	 * @param string $postType
	 * @return bool
	 */
	private function skipCustomPostTypes($postType)
	{
		if($this->settings['lazy_post_type'] == "custom") {
			$cpts = Wp_Files_Helper::getPostTypes();

			if (in_array($postType, $cpts) && is_array($this->settings['lazy_post_types']) && !in_array($postType, $this->settings['lazy_post_types'])) {
				return true;
			}
		}

		return false;
	}
}
