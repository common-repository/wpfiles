<?php
class Wp_Files_LazyLoad
{
	/**
	 * Settings
	 * @since 1.0.0
	 */
	private $settings = null;

	/**
	 * Page parser.
	 * @since 1.0.0
	 * @var Wp_Files_PageParser $parser
	*/
	protected $parser;

	/**
	 * Excluded classes list.
	 * @since 1.0.0
	 * @var array
	 */
	private $excluded_classes = array(
		'no-lazyload',
		'skip-lazy',
		'rev-slidebg',
		'soliloquy-preload',
	);

	use Wp_Files_LazyLoad_Hooks, Wp_Files_LazyLoad_Utilities;

	/**
	 * Constructor
	 * @since 1.0.0
	 * @var object $parser
	 * @var array
	 */
	public function __construct($parser)
	{
		$this->settings = Wp_Files_Settings::loadSettings();

		$this->parser = $parser;

		//Parsing content hooks 
		$this->parsingHooks();

		if (is_admin() || !$this->settings['lazy_load']) {
			$this->parser->off( 'lazy_load' );
			return;
		} else {
			$this->parser->on( 'lazy_load' );
		}

		$this->hooks();
		
		if ($this->settings['lazy_media_type'] === "all" || (isset( $this->settings['lazy_media_types'] ) && is_array($this->settings['lazy_media_types']) && in_array('iframe', $this->settings['lazy_media_types'])) ) {
			$this->parser->on( 'iframes' );
		}
	}

	/**
	 * Parse image for Lazy load.
	 * @since 1.0.0
	 * @param string $source Image URL.
	 * @param string $img  Image tag
	 * @param string $type ['img', 'source' or 'iframe'] Default: img
	 * @return string
	*/
	public function parseImage($source, $img, $type = 'img')
	{
		if ($this->isAmp()) {
			return $img;
		}

		if (apply_filters('wpfiles_skip_image_from_lazy_load', false, $source, $img)) {
			return $img;
		}

		$isGravatar = false !== strpos($source, 'gravatar.com');

		$path = wp_parse_url($source, PHP_URL_PATH);

		$extension  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		$extension  = 'jpg' === $extension ? 'jpeg' : $extension;

		$iframe = 'iframe' === substr($img, 1, 6);

		if (!$isGravatar && !in_array($extension, array('jpeg', 'gif', 'png', 'svg', 'webp'), true) && !$iframe) {
			return $img;
		}

		if (!in_array($extension, $this->settings['lazy_media_types']) && $this->settings['lazy_media_type'] === "custom") {
			return $img;
		}

		if ($iframe && !in_array('iframe', $this->settings['lazy_media_types']) && $this->settings['lazy_media_type'] === "custom") {
			return $img;
		}

		if (empty($source) || ($iframe && apply_filters('wpfiles_skip_iframe_from_lazy_load', false, $source))) {
			return $img;
		}

		if ($iframe && esc_url_raw($source) !== $source) {
			return $img;
		}

		if ($this->hasExcludedClassOrId($img)) {
			return $img;
		}

		if (false !== strpos($img, 'data-skip-lazy')) {
			return $img;
		}

		$newImage = $img;

		$attributes = array('src', 'sizes');

		foreach ($attributes as $attribute) {
			$attr = Wp_Files_PageParser::getAttribute($newImage, $attribute);
			if ($attr) {
				Wp_Files_PageParser::removeAttribute($newImage, $attribute);
				Wp_Files_PageParser::addAttribute($newImage, "data-{$attribute}", $attr);
			}
		}

		$newImage = preg_replace('/<(.*?)(srcset=)(.*?)>/i', '<$1data-$2$3>', $newImage);

		if ('source' === $type) {
			return $newImage;
		}

		$class = Wp_Files_PageParser::getAttribute($newImage, 'class');
		if ($class) {
			$class .= ' lazyload';
		} else {
			$class = 'lazyload';
		}

		Wp_Files_PageParser::removeAttribute($newImage, 'class');

		Wp_Files_PageParser::addAttribute($newImage, 'class', apply_filters('wp_files_lazy_load_classes', $class));

		Wp_Files_PageParser::addAttribute($newImage, 'src', 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

		if (!$iframe && !$this->settings['disable_no_script']) {
			$newImage .= '<noscript>' . $img . '</noscript>';
		}

		return $newImage;
	}

}
