<?php

/**
 * WPFiles page parsing 
 * CDN implementation
 * Lazy loading implementation
 */
class Wp_Files_PageParser
{
    /**
     * Check lazy load module status.
     * @since    1.0.0
     * @var bool $lazy_load
     */
    private $lazy_load = true;

    /**
     * Check CDN module status.
     * @since    1.0.0
     * @var bool $cdn
     */
    private $cdn = true;

    /**
     * For process background images.
     * @since    1.0.0
     * @var bool $background_images
     */
    private $background_images = false;

    

    /**
     * Instance of LazyLoad Class Repository.
     * @since    1.0.0
     * @var object $lazyLoadInstance
     */
    private $lazyLoadInstance;

    /**
     * Start parsing point.
     * @var object $cdn_instance
     * @var object $lazyLoadInstance
     * @since    1.0.0
     */
    public function init($cdn_instance, $lazyLoadInstance)
    {
        
        
        $this->lazyLoadInstance = $lazyLoadInstance;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_admin() || is_customize_preview()) {
            return;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        if ($this->isPageBuilder()) {
            return;
        }

        if ($this->isSmartcrawlChecker()) {
            return;
        }

        add_action(
            'template_redirect',
            function () {
                ob_start(array($this, 'parseImage'));
            },
            1
        );
    }

    /**
     * Process images from current buffer content.
     * @param string $content Current buffer content.
     * @since 1.0.0
     * @return string
     */
    public function parseImage($content)
    {
        if (!$this->cdn && !$this->lazy_load) {
            return $content;
        }

        if (is_customize_preview()) {
            return $content;
        }

        if (empty($content)) {
            return $content;
        }

        $content = $this->processImages($content);

        if ($this->background_images) {
            $content = $this->processBackgroundImages($content);
        }

        return $content;
    }

    /**
     * Process all images within <img> tags.
     * @param string $content Current buffer content.
     * @since 1.0.0
     * @return string
     */
    private function processImages($content)
    {
        $images = $this->getImagesFromContent($content);

        if (empty($images)) {
            return $content;
        }

        foreach ($images[0] as $key => $img) {

            $img_src   = $images['src'][$key];

            $new_image = $img;

            

            if ($this->lazy_load && !apply_filters('wp_files_should_skip_parse', false)) {
                $new_image = $this->lazyLoadInstance->parseImage($img_src, $new_image, $images['type'][$key]);
            }

            $content = str_replace($img, $new_image, $content);

        }

        return $content;
    }

    /**
     * Get image tags from page content.
     * @since 1.0.0
     * @param string $content
     * @return array
     */
    public function getImagesFromContent($content)
    {
        $images = array();

        if (preg_match('/(?=<body).*<\/body>/is', $content, $body)) {
            $content = $body[0];
        }

        if (preg_match_all('/<(?P<type>img|source|iframe)\b(?>\s+(?:src=[\'"](?P<src>[^\'"]*)[\'"]|srcset=[\'"](?P<srcset>[^\'"]*)[\'"])|[^\s>]+|\s+)*>/is', $content, $images)) {
            foreach ($images as $key => $unused) {
                if (is_numeric($key) && $key > 0) {
                    unset($images[$key]);
                }
            }
        }

        return $images;
    }

    /**
     * SmartCrawl
     * SEO checker that scans posts and pages for readability and keyword density and makes suggestions for optimizing your content.
     * Compatibility with this plugin.
     * Do not process page on analysis.
     * @since 1.0.0
     * @return boolean
    */
    private function isSmartcrawlChecker()
    {
        $analysis = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

        if (!is_null($analysis) && 'wds-analysis-recheck' === $analysis) {
            return true;
        }

        if (null !== filter_input(INPUT_GET, 'wds-frontend-check', FILTER_UNSAFE_RAW)) {
            return true;
        }

        return false;
    }

    /**
     * Check it for page builders.
     * @since 1.0.0
     * @return bool
    */
    private function isPageBuilder()
    {
        // Beaver Builder
        if (null !== filter_input(INPUT_GET, 'fl_builder')) {
            return true;
        }

        // Oxygen Builder
        if (null !== filter_input(INPUT_GET, 'ct_builder')) {
            return true;
        }

        if (defined('SHOW_CT_BUILDER') && SHOW_CT_BUILDER) {
            return true;
        }

        // Thrive Theme Builder
        if (null !== filter_input(INPUT_GET, 'tve') && null !== filter_input(INPUT_GET, 'tcbf')) {
            return true;
        }

        // Tatsu fully visual page builder
        if (null !== filter_input(INPUT_GET, 'tatsu')) {
            return true;
        }

        // BuddyBoss
        if (function_exists('bbp_is_ajax') && bbp_is_ajax()) {
            return true;
        }

        return false;
    }

    /**
     * Background images
     * @since 1.0.0
     * @param string $content 
     * @return string
     */
    private function processBackgroundImages($content)
    {
        $images = self::getBackgroundImages($content);

        if (empty($images)) {
            return $content;
        }

        // Resolve duplicate entries.
        $elements = array_unique($images[0]);

        $urls     = array_unique($images['img_url']);

        if (count($elements) === count($urls)) {
            $images[0]         = $elements;
            $images['img_url'] = $urls;
        }

        foreach ($images[0] as $key => $img) {

            $img_src   = $images['img_url'][$key];

            $new_image = $img;

            
            
            $content = str_replace($img, $new_image, $content);
        }

        return $content;
    }

    /**
     * Get background images from content.
     * @since 1.0.0
     * @param string $content
     * @return array
     */
    private static function getBackgroundImages($content)
    {
        $images = array();

        if (preg_match_all('/(?:background-image:\s*?url\(\s*[\'"]?(?P<img_url>.*?[^)\'"]+)[\'"]?\s*\))/i', $content, $images)) {
            foreach ($images as $key => $unused) {
                if (is_numeric($key) && $key > 0) {
                    unset($images[$key]);
                }
            }
        }

        //Confirm image don't start and end with &quot;.
        $images['img_url'] = array_map(
            function ($img) {

                $quotes = apply_filters('wp_files_background_image_quotes', array('&#034;', '&quot;', '&apos;', '&#039;', '&#939;'));

                $img = trim($img);

                if (in_array(substr($img, 0, 6), $quotes, true)) {
                    $img = substr($img, 6);
                }

                if (in_array(substr($img, -6), $quotes, true)) {
                    $img = substr($img, 0, -6);
                }

                return $img;
            },

            $images['img_url']
        );

        return $images;
    }

    /**
     * Fetch URLs from a string of content.
     * @since 1.0.0
     * @param string $content Content.
     * @return array
     */
    public static function getLinksFromContent($content)
    {
        $images = array();
        preg_match_all('/(?:https?[^\s\'"]*)/is', $content, $images);
        return $images;
    }

    /**
     * Remove attributes from selected tag.
     * @since 1.0.0
     * @param string $element 
     * @param string $attribute
     * @return void
     */
    public static function removeAttribute(&$element, $attribute)
    {
        $element = preg_replace('/' . $attribute . '=[\'"](.*?)[\'"]/i', '', $element);
    }

    /**
     * Get attribute from the HTML element.
     * @since 1.0.0
     * @param string $element 
     * @param string $name
     * @return string
     */
    public static function getAttribute($element, $name)
    {
        preg_match("/{$name}=['\"]([^'\"]+)['\"]/is", $element, $value);
        return isset($value['1']) ? $value['1'] : '';
    }

    /**
     * Add attribute to selected tag.
     * @since 1.0.0
     * @param string $element 
     * @param string $name 
     * @param string $value
     * @return void
     */
    public static function addAttribute(&$element, $name, $value = null)
    {
        $closing = false === strpos($element, '/>') ? '>' : ' />';

        $quotes  = false === strpos($element, '"') ? '\'' : '"';

        if (!is_null($value)) {
            $element = rtrim($element, $closing) . " {$name}={$quotes}{$value}{$quotes}{$closing}";
        } else {
            $element = rtrim($element, $closing) . " {$name}{$closing}";
        }

    }

    /**
     * OFF parser for selected modules.
     * @since 1.0.0
     * @param string $module 
     * @return void
     */
    public function off($module)
    {
        if (!in_array($module, array('cdn', 'lazy_load'), true)) {
            return;
        }

        $this->$module = false;
    }

    /**
     * Enable parser for selected modules.
     * @since 1.0.0
     * @param string $module 
     * @return void
     */
    public function on($module)
    {
        if (!in_array($module, array('cdn', 'lazy_load', 'iframes', 'background_images'), true)) {
            return;
        }

        $this->$module = true;
    }
}
