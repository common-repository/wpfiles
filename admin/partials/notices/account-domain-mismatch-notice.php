<div id="wpfiles-account-domain-mismatch-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <b>
                <?php
                    printf(
                        esc_html__(
                            'Is %1$s a new website?',
                            'wpfiles'
                        ),
                        '<a target="_blank" href="'.$params['new_domain'].'" data-message="%s">'.$params['new_domain'].'</a>'
                    );
                ?>
            </b>
            <?php
                printf(
                    esc_html__(
                        'We detected that your website changed from %1$s to %2$s so your WPFiles account was automatically disconnected, you will have to re-connect your account for your new website.',
                        'wpfiles'
                    ),
                    '<a target="_blank" href="'.$params['old_domain'].'" data-message="%s">'.$params['old_domain'].'</a>',
                    '<a target="_blank" href="'.$params['new_domain'].'" data-message="%s">'.$params['new_domain'].'</a>',
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="javascript:void(0)" class="button button-primary connect-account">
            <?php esc_html_e( 'Connect account', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-account-domain-mismatch-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'domain-mismatch-hide' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>