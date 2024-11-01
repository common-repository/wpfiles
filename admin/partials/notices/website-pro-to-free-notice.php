<div id="wpfiles-website-pro-to-free-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
                printf(
                    esc_html__(
                        '%1$s'.$params['current_domain'].'%2$s has been converted to Free so Pro features have been disabled for now. If you think this is an error, run a %3$sre-check%4$s or get in touch with our %5$ssupport team%6$s.',
                        'wpfiles'
                    ),
                    '<b>',
                    '</b>',
                    '<a href="javascript:void(0)" id="wpfiles-revalidate-member" data-message="%s">',
                    '</a>',
                    '<a href="'.WP_FILES_GO_URL.'/support" target="_blank">',
                    '</a>'
                );
            ?>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a target="_blank" href="<?php echo WP_FILES_URL; ?>/user/dashboard?action=edit-website&id=<?php esc_html_e( $params['website_id'], 'wpfiles' ); ?>" class="wpfiles-notice-act button-primary">
            <?php esc_html_e( 'Change to Pro', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-website-pro-to-free-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'website-pro-to-free-notice' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>