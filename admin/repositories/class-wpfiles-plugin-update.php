<?php
/**
 * In this class, you will find all Auto Update related to WPFiles.
 */
class Wp_Files_Plugin_Update
{
  /**
   * WPFiles updates versions
   * @since 1.0.0
   */
	private static $updates = [
		'1.0.1'  => 'updates/update-1.0.1.php'
	];

  /**
   * Settings
   * @var $settings
   * @since 1.0.0
   */
  private $settings = null;
  
  /**
     * Set settings
     * @since 1.0.0
     * @param  mixed $settings
     * @return void
  */
  public function __construct($settings)
  {
      $this->settings = $settings;
  }
  
  /**
     * Update installer info
     * @since 1.0.0
     * @return void
  */
  public function init() {

      

      $this->installNewVersion();
  }

  /**
     * Install new version db changes if exist
     * @since 1.0.0
     * @return void
  */
	public function installNewVersion() {

		$installed_version = get_option( 'wpfiles_version', '1.0.0' );

		// Maybe it's the first install.
		if ( ! $installed_version ) {
			return;
		}

		if ( version_compare( $installed_version, WP_FILES_VERSION, '<' ) ) {
			$this->do_updates();
		}
	}

	/**
     * Perform updates
     * @since 1.0.0
     * @return void
  */
	public function do_updates() {
		$installed_version = get_option( 'wpfiles_version', '1.0.0' );

		foreach ( self::$updates as $version => $path ) {
			if ( version_compare( $installed_version, $version, '<' ) ) {
				include WP_FILES_PLUGIN_DIR . $path;
				update_option( 'wpfiles_version', $version );
			}
		}

		update_option( 'wpfiles_version', WP_FILES_VERSION );
	}
  
}
