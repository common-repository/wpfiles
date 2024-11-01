<div id="wpfiles-cdn-suspended-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
   <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    esc_html__(
                        'Your WPFiles CDN has been suspended due to incomplete payment. Please %1$sclick here to recharge%2$s your balance. If you think this is an error get in touch with our %3$ssupport team%4$s.',
                        'wpfiles'
                    ),
                    '<a target="_blank" href="'.WP_FILES_URL.'/user/subscription?action=recharge-balance" data-message="%s">',
                    '</a>',
                    '<a href="'.WP_FILES_GO_URL.'/support" target="_blank">',
                    '</a>'
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="<?php echo WP_FILES_URL.'/user/subscription?action=recharge-balance' ?>" class="button button-primary">
            <?php esc_html_e( 'Recharge', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-cdn-suspended-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'cdn-suspended-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>