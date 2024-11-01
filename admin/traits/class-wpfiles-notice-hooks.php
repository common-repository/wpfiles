<?php
trait Wp_Files_Notice_Hooks {

	/**
	 * List of wpfiles settings pages.
	 * @since 1.0.0
	 * @var array $plugin_pages
	 */
	public static $plugin_pages = array(
		'wpfiles_page_wpfiles-modules',
		'toplevel_page_wpfiles',
		'wpfiles_page_wpfiles-settings',
		'wpfiles_page_wpfiles-pro',
		'upload'
	);
	
    /**
	 * Shows Notice for free users, displays a discount coupon
	 * Initial trial upgrade notice 
	 * (WPFiles pages)
	 * @since 1.0.0
	 * @return void
	 */
	public function initial_trial_upgrade_notice() {

		// Return, If a pro user, or not super admin, or don't have the administrator.
		if (  Wp_Files_Subscription::is_pro() || ! is_admin() || ! is_super_admin()) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		// Return if notice is already dismissed.
		if ( get_site_option( WP_FILES_PREFIX . 'initial-trial-upgrade-notice' ) ) {
			return;
		}

		// Show it on WPFiles pages only.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		if(is_plugin_active( 'wpfiles/wpfiles.php' ) && WP_FILES_BASENAME == 'wpfiles/wpfiles.php' && in_array( $screen->id, self::$plugin_pages, true )) {

			$message = __( 'Thanks for installing WPFiles! %1$sGet a free trial of WPFiles Pro now%2$s and unlock true power of WPFiles. Grab it while it lasts.', 'wpfiles' );

			$upgrade_url = add_query_arg(
				array(
					'utm_source'   => 'wpfiles',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'wpfiles_initial_trial_upgrade_notice',
				),
				WP_FILES_GO_URL.'/pricing'
			);
	
			$this->loadTemplate('notices/initial-trial-upgrade-notice', [
				'upgrade_url' => $upgrade_url,
				'message' => $message,
			]);
			
		}
		
	}

    /**
	 * Display a admin notice about free to pro plugin conversion. 
	 * (WPFiles pages & plugins)
	 * @since 1.0.0
	 * @return void
	 */
	public function free_to_pro_plugin_conversion_notice() {
		
		//If free plugin is active enable notice
		if ( is_plugin_active( 'wpfiles/wpfiles.php' ) && WP_FILES_BASENAME == 'wpfiles/wpfiles.php' ) {
			delete_option(  WP_FILES_PREFIX . 'free-to-pro-plugin-conversion-notice' );
		}

		
	}

    /**
	 * Prints the subscription Validation issue notice
	 * Show notice when website convert pro to free
	 * (all pages)
	 * @since 1.0.0
	 * @return void
	 */
	public function website_pro_to_free_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'website-pro-to-free-notice' );
        
		if ( $dismissed ) {
			return;
		}
		
		//If account is not connected
		if(!Wp_Files_Subscription::is_active($this->settings)) {
			return;
		}

		$status = get_option( WP_FILES_PREFIX . 'website-pro-to-free-notice-value' );

		$current_domain = Wp_Files_Helper::getHostname(network_site_url());
		
		$website_id = $this->settings['site_status']['website']['id'];

		if ($status ) {
			$this->loadTemplate('notices/website-pro-to-free-notice', [
				'current_domain' => $current_domain,
				'website_id' => $website_id
			]);
		}

	}

	/**
	 * Update api status
	 * @since 1.0.0
	 * @return JSON
	 */
	public function update_api_status() {

		$settings = (array) Wp_Files_Settings::loadSettings();

        if($settings['api_key']) {

            $stats = new Wp_Files_Stats($settings);

            $response = $stats->update_api_status(true);

            if($response->success) {
                wp_send_json_success([
                    "message" => __("Api status updated", 'wpfiles')
                ]);
            } else {
                wp_send_json_error([
                    'message' => $response->message
                ]);
            }
            
        } else {
            wp_send_json_error([
                'message' => __("Please connect your WPFiles account", 'wpfiles')
            ]);
        }
		
	}

    /**
	 * Display plugin incompatibility notice.
	 * (WPFiles pages & plugins)
	 * @since 1.0.0
	 * @return void
	 */
	public function show_plugin_conflict_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'conflict-notice' );
        
		if ( $dismissed ) {
			return;
		}

		$plugins = get_option( WP_FILES_PREFIX . 'conflict_check' );

		// Have never checked before.
		if (false === $plugins || empty($plugins) || $plugins == null) {
			return;
		}

		// No conflicting plugins detected.
		if ( isset( $plugins ) && is_array( $plugins ) && empty( $plugins ) ) {
			return;
		}

		array_walk(
			$plugins,
			function( &$item ) {
				$item = '<strong>' . $item . '</strong>';
			}
		);
		
		// Show it on WPFiles pages only & plugin page.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		if(in_array( $screen->id, self::$plugin_pages, true ) || $screen->id == 'plugins') {
			$this->loadTemplate('notices/show-plugin-conflict-notice', [
				'plugins' => $plugins
			]);
		}
		
	}

	/**
	 * Account connect notice
	 * (WPFiles pages)
	 * Display message to remind user to connect your account.
	 * @since 1.0.0
	 * @return void
	 */
	public function account_connect_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'account-connect-notice' );
        
		if ( $dismissed ) {
			return;
		}

		// No need to print it if account is connected
		if ( Wp_Files_Subscription::is_active($this->settings) ) {
			return;
		}

		// Show it on WPFiles pages only.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		$translations = Wp_Files_i18n::getProTranslations();

		//Different messages for pro/free version
			
			$message = $translations['connect_account_free_notice_msg'];
			$link_1 = WP_FILES_GO_URL.'/register';
			$link_2 = WP_FILES_URL.'/user/dashboard?action=add-website&redirect='.get_site_url().'/wp-admin/admin.php?page=wpfiles';
		

		if ( ! empty( $screen ) && in_array( $screen->id, self::$plugin_pages, true ) ) {
			$this->loadTemplate('notices/account-connect-notice', [
				'message' => $message,
				'link_1' => $link_1,
				'link_2' => $link_2
			]);
		}
	}

	/**
	 * CDN suspended notice
	 * (all pages)
	 * Display message to remind user to resume your cdn feature.
	 * @since 1.0.0
	 * @return void
	 */
	public function cdn_suspended_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'cdn-suspended-notice' );
        
		if ( $dismissed ) {
			return;
		}

		// Don,t show if account is not connected
		if ( !$this->settings['api_key'] || !$this->settings['site_status']) {
			return;
		}

		$site_status = $this->settings['site_status'];

		if(isset($site_status['website']) && is_array($site_status['website']) && $site_status['website']['status'] == "suspended" && is_array($site_status['subscription']) && $site_status['subscription']['stripe_status'] != 'past_due') {
			$this->loadTemplate('notices/cdn-suspended-notice');
		}
	}

	/**
	 * Payment due notice
	 * (all pages)
	 * Display message to remind user to pay our pending payments.
	 * @since 1.0.0
	 * @return void
	 */
	public function account_payment_due_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'account-payment-due-notice' );
        
		if ( $dismissed ) {
			return;
		}

		//If account is not connected
		if(!Wp_Files_Subscription::is_active($this->settings)) {
			return;
		}

		$site_status = $this->settings['site_status'];

		if(isset($site_status['plan']['months'])) {
			$plan = ucfirst($site_status['plan']['name']).' Plan '.($site_status['plan']['months'] > 1 ? __('Yearly', 'wpfiles') : __('Monthly', 'wpfiles'));
		} else {
			$plan = ucfirst($site_status['plan']['name']).' Plan ';
		}

		if(isset($site_status['website']) && is_array($site_status['subscription']) && $site_status['subscription']['stripe_status'] == 'past_due') {
			$this->loadTemplate('notices/account-payment-due-notice', [
				'plan' => $plan
			]);
		}
	}
	
	/**
	 * Website status change to free
	 * (WPFiles pages & plugins)
	 * Display message to remind user your website has been convert to free for some reason.
	 * @since 1.0.0
	 * @return void
	 */
	public function upgrade_to_pro_notice() {

		// Display only in backend for administrators.
		if (Wp_Files_Subscription::is_pro() || ! is_admin() || ! is_super_admin() ) {
			return;
		}
		
		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}

		$dismissed = get_option( WP_FILES_PREFIX . 'upgrade-to-pro-notice' );
        
		if ( $dismissed ) {
			return;
		}

		$site_status = $this->settings['site_status']; 

		if(Wp_Files_Subscription::is_active($this->settings) && isset($site_status['website']) && is_array($site_status['website']) && $site_status['website']['was_pro'] == 1 && $site_status['is_free'] == 1 && isset($site_status['is_trial_used']) && $site_status['is_trial_used'] > 0) {
			$message = __('Renew your subscription to unlock all the features of the Media library along with blazing fast WPFiles built-in CDN to quickly skyrocket your website speed by optimizing your images on the fly and serving them from over %1$s locations around the globe.', 'wpfiles');
			$button_text = __('Renew subscription', 'wpfiles');
		} else if(Wp_Files_Subscription::is_active($this->settings) && isset($site_status['is_trial_used']) && $site_status['is_trial_used'] > 0) {	
			//simple upgrade if trial used
			$message = __('Upgrade to Pro to unlock all the features of the Media library along with blazing fast WPFiles built-in CDN to quickly skyrocket your website speed by optimizing your images on the fly and serving them from over %1$s locations around the globe.', 'wpfiles');
			$button_text = __('Upgrade to Pro', 'wpfiles');
		} else {
			//upgrade to pro with trial (trial not used & is free)
			$message = __('Start a free trial to unlock all the features of the Media library along with blazing fast WPFiles built-in CDN to quickly skyrocket your website speed by optimizing your images on the fly and serving them from over %1$s locations around the globe.', 'wpfiles');
			$button_text = __('Start free trial', 'wpfiles');
		}

		// Show it on WPFiles pages only & plugin page.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		if(in_array( $screen->id, self::$plugin_pages, true ) || $screen->id == 'plugins') {
			$this->loadTemplate('notices/upgrade-to-pro-notice', [
				'message' => $message,
				'button_text' => $button_text
			]);
		}
	}

	/**
	 * Display plugin usage tracking notice.
	 * (WPFiles pages)
	 * @since 1.0.0
	 * @return void
	 */
	public function show_plugin_usage_tracking_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}
		
		$dismissed = get_option( WP_FILES_PREFIX . 'usage-tracking-notice-hide' );
        
		if ( $dismissed || $this->settings['usage_tracking'] == 1) {
			return;
		}

		// Show it on WPFiles pages only.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		if(in_array( $screen->id, self::$plugin_pages, true )) {
			$this->loadTemplate('notices/plugin-usage-tracking-notice', [
				'username' => Wp_Files_Helper::getUserName()
			]);
		}
	}

	/**
	 * Display plugin domain mismatch notice.
	 * (all pages)
	 * @since 1.0.0
	 * @return void
	 */
	public function show_plugin_domain_mismatch_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}
		
		$dismissed = get_option( WP_FILES_PREFIX . 'domain-mismatch-hide' );
        
		if ( $dismissed) {
			return;
		}

		$domain = get_option( WP_FILES_PREFIX . 'domain-mismatch' );
		
		if($domain && $domain != Wp_Files_Helper::getHostname(network_site_url())) {
			$this->loadTemplate('notices/account-domain-mismatch-notice', [
				'new_domain' => network_site_url(),
				'old_domain' => $domain
			]);
		}
	}

	/**
	 * Display WPFiles rate notice.
	 * (WPFiles pages)
	 * @since 1.0.0
	 * @return void
	 */
	public function wpfiles_rate_notice() {

		// Display only in backend for administrators.
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		//Don't display notice on installer page
		if(!get_option(WP_FILES_PREFIX . 'install-hide')) {
			return;
		}
		
		$dismissed = get_option( WP_FILES_PREFIX . 'rate-notice-hide' );
        
		if ( $dismissed) {
			return;
		}

		//If already done
		$already_done = get_option( WP_FILES_PREFIX . 'rate-notice-already-done' );

		if ( $already_done) {
			return;
		}

		// Show it on WPFiles pages only.
		$screen = function_exists('get_current_screen') ? get_current_screen() : false;

		if(in_array( $screen->id, self::$plugin_pages, true )) {
			$this->loadTemplate('notices/wpfiles-rate-notice');
		}
		
	}
	
	/**
	 * Dismiss notices according to types.
	 * @since 1.0.0
	 * @return void
	 */
	public function dismiss_notice() {
		
		if(isset($_POST['delete']) && $_POST['delete']) {
			delete_option( trim(sanitize_text_field($_POST['notice'])));
		} else {
			update_option( trim(sanitize_text_field($_POST['notice'])), true );
		}
		
		wp_send_json_success();
	}

	/**
     * Check for plugin conflicts cron.
     * @since 1.0.0
     * @param string $deactivated  Holds the slug of activated/deactivated plugin.
	 * @return void
     */
	public function check_for_conflicts_cron( $deactivated = '' ) {

		$conflicting_plugins = array(
			'ewww-image-optimizer/ewww-image-optimizer.php',
			'imagify/imagify.php',
			'resmushit-image-optimizer/resmushit.php',
			'shortpixel-image-optimizer/wp-shortpixel.php',
			'tiny-compress-images/tiny-compress-images.php',
			'folders/folders.php',
			'filebird-pro/filebird.php',
			'filebird/filebird.php',
			'real-media-library/index.php',
			'real-media-library-lite/index.php',
			'wp-smush-pro/wp-smush.php',
			'enable-media-replace/enable-media-replace.php',
			'happyfiles/happyfiles.php',
			'wp-media-folder/wp-media-folder.php',
			'media-library-plus/media-library-plus.php',
			'wp-media-manager-lite/wp-media-manager-lite.php'
		);

		$plugins = get_plugins();

		$active_plugins = array();

		foreach ( $conflicting_plugins as $plugin ) {
            
			if ( ! array_key_exists( $plugin, $plugins ) ) {
				continue;
			}

			if ( ! is_plugin_active( $plugin ) ) {
				continue;
			}

			// Deactivation of the plugin in process.
			if ( doing_action( 'deactivated_plugin' ) && $deactivated === $plugin ) {
				continue;
			}

			$active_plugins[] = $plugins[ $plugin ]['Name'];
			
		}

		//Update conflict plugins
		update_option( WP_FILES_PREFIX . 'conflict_check', $active_plugins );
		
		//Delete dismiss notice for plugin conflicts
		delete_option( WP_FILES_PREFIX . 'conflict-notice' );
		
	}
}
?>
