<?php

/**
 * Page builder class.
 * Responsible for displaying a UI (Media folders) in the Page builders (Media library).
 * @since 1.0.0
 */

class Wp_Files_Page_Builder
{
    /**
     * Plugin name
     * @since 1.0.0
     * @var object $plugin_name
     */
    private $plugin_name;

    /**
     * version
     * @since 1.0.0
     * @var object $version
     */
    private $version;

    /**
     * Settings
     * @since 1.0.0
     * @var object $settings
     */
    private $settings;

    
    /**
     * Constructor.
     * @since 1.0.0
     * @return void
     */
    public function __construct($settings, $plugin_name, $version)
    {
        $this->settings = $settings;

        $this->plugin_name = $plugin_name;

        $this->version = $version;
    }

    /**
     * Hooks.
     * @since 1.0.0
     * @return void
     */
    public function hooks()
    {
        
    }

    

    

    

    

    

    

    

    

    

    
    
    

    

}
