<div class="notice wpfiles-notice notice-warning" id="wpfiles-plugin-conflict-notice-parent" id="wpfiles-conflict-notice">
   <div class="wpfiles-notice__logo">
        <img
            src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>"
            srcset="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?> 2x"
            alt="<?php esc_html_e( 'WPFiles Icon', 'wpfiles' ); ?>"
        >
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php esc_html_e( 'You have multiple WordPress media library and image optimization plugins installed. For best results use only one plugin at a time for the same task. Following plugins may cause unpredictable behavior and issues with WPFiles:', 'wpfiles' ); ?>
            <div style="margin-top: 4px;">
                <?php echo wp_kses_post( join( '<br>', $params['plugins'] ) ); ?>
            </div>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Manage Plugins', 'wpfiles' ); ?>
        </a>
        <a href="#" id="wpfiles-plugin-conflict-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'conflict-notice' ?>" class="wpfiles-notice__link_subdued">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>