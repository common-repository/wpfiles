<?php
trait Wp_Files_LazyLoad_Hooks
{
    /**
     * Lazyload related hooks
     * @since 1.0.0
     * @return void
     */
    public function hooks() {

        // Disable WP native lazy load.
		add_filter('wp_lazy_loading_enabled', '__return_false');

		add_action('wp_head', array($this, 'addInlineStyles'));

        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'), 99);

        if (defined('WP_FILES_ASYNC_LAZY') && WP_FILES_ASYNC_LAZY) {
			add_filter('script_loader_tag', array($this, 'async_load'), 10, 2);
		}

		add_filter('wp_kses_allowed_html', array($this, 'addLazyLoadAttributes'));

        add_filter('wp_files_should_skip_parse', array($this, 'maybeSkipParse'));

		if (!in_array('content', $this->settings['lazy_output_location'])) {
			add_filter('the_content', array($this, 'excludeFromLazyLoading'), 100);
		}

		if (!in_array('thumbnail', $this->settings['lazy_output_location'])) {
			add_filter('post_thumbnail_html', array($this, 'excludeFromLazyLoading'), 100);
		}

		if (!in_array('gravatars', $this->settings['lazy_output_location'])) {
			add_filter('get_avatar', array($this, 'excludeFromLazyLoading'), 100);
		}

		if (!in_array('widget', $this->settings['lazy_output_location'])) {
			add_action('dynamic_sidebar_before', array($this, 'filterSidebarContentStart'), 0);
			add_action('dynamic_sidebar_after', array($this, 'filterSidebarContentEnd'), 1000);
		}
    }

	/**
     * Lazyload parsing hooks
     * @since 1.0.2
     * @return void
     */
    public function parsingHooks() {
		// Compatibility
		add_filter('wpfiles_skip_image_from_lazy_load', array($this, 'skipLazyLoad'), 10, 3);
		
		add_filter('wpfiles_skip_image_from_lazy_load', array($this, 'lazyLoadCompat'), 10, 3);

		add_filter('wpfiles_skip_image_from_lazy_load', array($this, 'trpTranslationEditor'));

		add_filter('envira_gallery_indexable_images', array($this, 'addNoLazyloadClass'));

		add_filter('wpfiles_skip_iframe_from_lazy_load', array($this, 'excludeRecaptchaIframe'), 10, 2);

		add_action('give_donation_form_top', array($this, 'givewpSkipImageLazyLoad'), 0);
	}

    /**
	 * Inline styles
	 * @since 1.0.0
	 * @return void
	 */
	public function addInlineStyles()
	{
		if ($this->isAmp()) {
			return;
		}
		// Fix for poorly coded themes that do not remove the no-js in the HTML class.
        ?>
		<script>
			document.documentElement.className = document.documentElement.className.replace('no-js', 'js');
		</script>
		<?php
		if (!$this->settings['lazy_animation_type'] || 'none' === $this->settings['lazy_animation_type']) {
			return;
		}
		// Spinner.
		if ('spinner' === $this->settings['lazy_animation_type']) {
			$lazy_loader = WP_FILES_PLUGIN_URL . 'admin/images/lazyload/' . $this->settings['lazyload_active_spinner'];
			if (isset($this->settings['lazyload_active_spinner']) && 'manual' == $this->settings['lazyload_active_spinner']) {
				$lazy_loader = wp_get_attachment_image_src($this->settings['lazyload_attachment_id'], 'full');
				$lazy_loader = $lazy_loader[0];
			}
			$background = 'rgba(255, 255, 255, 0)';
		} else {

			// Placeholder.
			$lazy_loader = WP_FILES_PLUGIN_URL . 'admin/images/placeholders/' . $this->settings['lazyload_active_placeholder'];
			
			if($this->settings['lazyload_active_placeholder'] == "placeholder-1.png") {
				$background = $this->settings['lazyload_bg_color_1'];
			} else if($this->settings['lazyload_active_placeholder'] == "placeholder-2.png") {
				$background = $this->settings['lazyload_bg_color_2'];
			} else {
				$background = $this->settings['lazyload_bg_color_3'];
			}
	
			if (isset($this->settings['lazyload_active_placeholder']) && 'manual' == $this->settings['lazyload_active_placeholder']) {
				
				$lazy_loader = wp_get_attachment_image_src((int) $this->settings['lazyload_placeholder_attachment_id'], 'full');

				// Can't find a loader on multisite? Try main site.
				if (!$lazy_loader && is_multisite()) {
					switch_to_blog(1);
					$lazy_loader = wp_get_attachment_image_src((int) $this->settings['lazyload_placeholder_attachment_id'], 'full');
					restore_current_blog();
				}

				$lazy_loader = $lazy_loader[0];
			}
		}

		$fade_in = isset($this->settings['lazy_animation_duration']) ? $this->settings['lazy_animation_duration'] : 0;

		$delay  = isset($this->settings['lazy_animation_delay']) ? $this->settings['lazy_animation_delay'] : 0;
		
		?>
		<style>
            figure.wp-block-image img.lazyloading {
				min-width: 150px;
			}
			.no-js img.lazyload {
				display: none;
			}
			<?php if ('fadein' === $this->settings['lazy_animation_type']) : ?>.lazyload,
			.lazyloading {
				opacity: 0;
			}
			.lazyloaded {
				opacity: 1;
				transition: opacity <?php echo esc_html($fade_in); ?>ms;
				transition-delay: <?php echo esc_html($delay); ?>ms;
			}
			<?php else : ?>.lazyload {
				opacity: 0;
			}
			.lazyloading {
				border: 0 !important;
				opacity: 1;
				background: <?php echo esc_attr($background); ?> url('<?php echo esc_url($lazy_loader); ?>') no-repeat center !important;
				background-size: 16px auto !important;
				min-width: 16px;
			}
			<?php endif; ?>
		</style>
        <?php
	}

    /**
	 * Include JS files
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueueAssets()
	{
		if ($this->isAmp()) {
			return;
		}

		$script = WP_FILES_PLUGIN_URL . 'public/js/lazyload/wpfiles-lazy-load.min.js';

		if ($this->settings['native_lazy_loading']) {
			$script = WP_FILES_PLUGIN_URL . 'public/js/lazyload/wpfiles-lazy-load-native.min.js';
		}

		$footer = $this->settings['lazy_script_location'] == "footer" ? true : false;

		wp_enqueue_script(
			'wpfiles-lazy-load',
			$script,
			array(),
			WP_FILES_VERSION,
			$footer
		);

		$this->addDiviSupport();

		$this->addMasonrySupport();

		$this->addSoliloquySupport();

		if (defined('WP_FILES_LAZY_LOAD_AVADA') && WP_FILES_LAZY_LOAD_AVADA) {
			$this->addAvadaSupport();
		}
	}

    /**
	 * Async load the lazy load scripts.
	 * @since 1.0.0
	 * @param string $scriptTag   
	 * @param string $scriptRegisteredHandler
	 * @return string
	 */
	public function async_load($scriptTag, $scriptRegisteredHandler)
	{
		if ('wpfiles-lazy-load' === $scriptRegisteredHandler) {
			return str_replace(' src', ' async="async" src', $scriptTag);
		}

		return $scriptTag;
	}

    /**
	 * Add lazy load attributes
	 * @since 1.0.0
	 * @param array $allowedPostTags
	 * @return mixed
	 */
	public function addLazyLoadAttributes($allowedPostTags)
	{
		if (!isset($allowedPostTags['img'])) {
			return $allowedPostTags;
		}

		$attributes = array(
			'data-src'    => true,
			'data-srcset' => true,
			'data-sizes'  => true,
		);

		$imgAttributes = array_merge($allowedPostTags['img'], $attributes);

		$allowedPostTags['img'] = $imgAttributes;

		return $allowedPostTags;
	}

    /**
	 * See if we need to skip parsing of some pages.
	 * @since 1.0.0
	 * @param bool $skip
	 * @return bool
	 */
	public function maybeSkipParse($skip)
	{
		// Don't lazy load for previews, feeds, embeds.
		if (is_feed() || is_preview() || is_embed()) {
			$skip = true;
		}

		if ($this->skipPostTypes() || $this->isExludedUri()) {
			$skip = true;
		}

		return $skip;
	}

	/**
	 * Get images from content and add exclusion class.
	 * @since 1.0.0
	 * @param string $pageContent
	 * @return string
	 */
	public function excludeFromLazyLoading($pageContent)
	{
		$images = $this->parser->getImagesFromContent($pageContent);

		if (empty($images)) {
			return $pageContent;
		}

		foreach ($images[0] as $key => $img) {
			$newImage = $img;

			// Add .no-lazyload class.
			$class = Wp_Files_PageParser::getAttribute($newImage, 'class');

			if ($class) {
				Wp_Files_PageParser::removeAttribute($newImage, 'class');
				$class .= ' no-lazyload';
			} else {
				$class = 'no-lazyload';
			}

			Wp_Files_PageParser::addAttribute($newImage, 'class', $class);

			/**
			 * Filters the no-lazyload image.
			 * @since 1.0.0
			 * @param string $text The image that can be filtered.
			 */
			$newImage = apply_filters('wp_files_filter_no_lazyload_image', $newImage);

			$pageContent = str_replace($img, $newImage, $pageContent);
		}

		return $pageContent;
	}

	/**
	 * Sidebar: Buffer content.
	 * @since 1.0.0
	 * @return void
	 */
	public function filterSidebarContentStart()
	{
		ob_start();
	}

	/**
	 * Process buffered
	 * @since 1.0.0
	 * @return void
	 */
	public function filterSidebarContentEnd()
	{
		$pageContent = ob_get_clean();
		echo $this->excludeFromLazyLoading($pageContent);
		unset($pageContent);
	}

	/**
	 * Compatibility checks.
	 * @since 1.0.0
	 * @param bool $skip Default: false.
	 * @param string $url
	 * @param string $img Image.
	 * @return bool
	 */
	public function lazyLoadCompat($skip, $url, $img)
	{
		// Compatibility with Slider Revolution's lazy loading.
		if (false !== strpos($img, '/revslider/') && false !== strpos($img, 'data-lazyload')) {
			return true;
		}

		// If attributes are set by another plugin then avoid conflicts.
		if (false !== strpos($img, 'data-src')) {
			return true;
		}

		//  JetPack compatibility with lazy loading.
		if (false !== strpos($img, 'jetpack-lazy-image')) {
			return true;
		}

		// Essential compatibility with grid lazy loading.
		if (false !== strpos($img, 'data-lazysrc')) {
			return true;
		}

		return $skip;
	}

	/**
	 * Disables on Translate Press translate editor
	 * @since 1.0.0
	 * @param bool $skip Default: false.
	 * @return bool
	 */
	public function trpTranslationEditor($skip)
	{
		if (!isset($_GET['trp-edit-translation']) || !class_exists('\TRP_Translate_Press')) {
			return $skip;
		}

		return true;
	}

	/**
	 * Envira Gallery: Do not lazy load images
	 * @since 1.0.0
	 * @param bool $lazyload Default: false.
	 * @param string $url  
	 * @param string $image
	 * @return bool
	 */
	public function skipLazyLoad($lazyload, $url, $image)
	{
		$classes = Wp_Files_PageParser::getAttribute($image, 'class');
		return false !== strpos($classes, 'envira-lazy');
	}

	/**
	 * Envira Galleries will use a noscript tag with images. 
	 * WPFiles can't filter the DOM tree.
	 * So we will add a no-lazyload class to every image
	 * @since 1.0.0
	 * @param string $images string of img tags that will go inside nocscript element.
	 * @return string
	 */
	public function addNoLazyloadClass($images)
	{
		$parsed = (new Wp_Files_PageParser())->getImagesFromContent($images);

		if (empty($parsed)) {
			return $images;
		}

		foreach ($parsed[0] as $img) {

			$original = $img;

			$class = Wp_Files_PageParser::getAttribute($img, 'class');

			if (!$class) {
				Wp_Files_PageParser::addAttribute($img, 'class', 'no-lazyload');
			} else {
				Wp_Files_PageParser::addAttribute($img, 'class', $class . ' no-lazyload');
			}

			$images = str_replace($original, $img, $images);
		}

		return $images;
	}

	/**
	 * Skip ReCaptcha iframes from lazy loading.
	 * @since 1.0.0
	 * @param bool   $skip  Default: false.
	 * @param string $src Iframe url.
	 * @return bool
	 */
	public function excludeRecaptchaIframe($skip, $src)
	{
		return false !== strpos($src, 'recaptcha/api');
	}

	/**
	 * Skip GiveWP forms images from lazy loading.
	 * @since 1.0.0
	 * @return void
	 */
	public function givewpSkipImageLazyLoad()
	{
		add_filter('wp_files_should_skip_parse', '__return_true');
	}
	
}
