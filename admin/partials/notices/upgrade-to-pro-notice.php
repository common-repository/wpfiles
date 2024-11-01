<div id="wpfiles-upgrade-to-pro-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    $params['message'],
                    WP_FILES_CDN_POPS
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="<?php echo esc_url( WP_FILES_GO_URL.'/pricing' ); ?>" class="wpfiles-notice-act button-primary" target="_blank">
            <?php esc_html_e( $params['button_text'], 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-upgrade-to-pro-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'upgrade-to-pro-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>