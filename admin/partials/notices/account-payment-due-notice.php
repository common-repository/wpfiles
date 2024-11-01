<div id="wpfiles-account-payment-due-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    esc_html__(
                        'Your WPFiles %1$s payment is pending. Please %2$sclick here%3$s to make a payment otherwise your WPFiles PRO account will be converted to FREE and all the websites as well. If you think this is an error get in touch with our %4$ssupport team%5$s.',
                        'wpfiles'
                    ),
                    $params['plan'],
                    '<a target="_blank" href="'.WP_FILES_URL.'/user/subscription?action=payment-due" data-message="%s">',
                    '</a>',
                    '<a href="'.WP_FILES_GO_URL.'/support" target="_blank">',
                    '</a>'
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a target="_blank" href="<?php echo esc_url( WP_FILES_URL.'/user/subscription?action=payment-due' ); ?>" class="button button-primary">
            <?php esc_html_e( 'Pay now', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-account-payment-due-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'account-payment-due-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>