<div id="wpfiles-account-connect-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    esc_html__(
                        $params['message'],
                        'wpfiles'
                    ),
                    '<a target="_blank" href="'.$params['link_1'].'" data-message="%s">',
                    '</a>'
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="<?php echo esc_url($params['link_2']); ?>" class="button button-primary">
            <?php esc_html_e( 'Connect account', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-account-connect-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'account-connect-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>