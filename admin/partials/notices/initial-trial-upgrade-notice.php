<div id="wpfiles-initial-trial-upgrade-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
   <div class="wpfiles-notice__logo">
        <img
            src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>"
            srcset="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?> 2x"
            alt="<?php esc_html_e( 'WPFiles Icon', 'wpfiles' ); ?>"
        >   
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php printf( esc_html( $params['message'] ), '<strong>', '</strong>' ); ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="<?php echo esc_url( $params['upgrade_url'] ); ?>" class="wpfiles-notice-act button-primary" target="_blank">
            <?php esc_html_e( 'Try WPFiles Pro Free', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-initial-trial-upgrade-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'initial-trial-upgrade-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>