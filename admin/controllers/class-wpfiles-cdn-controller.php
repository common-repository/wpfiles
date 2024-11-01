<?php
/**
 * CDN management
 */
class Wp_Files_Cdn_Controller
{
    /**
     * WPFiles page parser that is used by CDN and Lazy load modules
     * @since    1.0.0
     * @access   private
     * @var      object    $parser
     */
    private $parser;

    /**
     * Cdn class instance
     * @since    1.0.0
     * @access   private
     * @var      object $cdnInstance
     */
    private $cdnInstance;

    /**
     * Class constructor
     * @since    1.0.0
     * @access   public
     * @return void
     */
    public function __construct()
    {
        $this->parser = new Wp_Files_PageParser();
    }

    /**
     * Process CDN
     * @since    1.0.0
     * @access   public
     * @return void
     */
    public function init()
    {
        $this->cdnInstance = new Wp_Files_Cdn($this->parser);

        $this->lazyLoadInstance = new Wp_Files_LazyLoad($this->parser);

        $this->parser->init(
            $this->cdnInstance,
            $this->lazyLoadInstance
        );
    }
}
