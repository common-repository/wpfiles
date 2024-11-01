<div id="wpfiles-free-to-pro-plugin-conversion-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    esc_html__(
                        'WPFiles Lite version was deactivated successfully.',
                        'wpfiles'
                    )
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-free-to-pro-plugin-conversion-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'free-to-pro-plugin-conversion-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>