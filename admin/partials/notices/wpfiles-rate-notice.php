<div id="wpfiles-rate-notice-parent" data-message="<?php esc_attr_e( 'Validating...', 'wpfiles' ); ?>" class="notice wpfiles-notice notice-warning">
    <div class="wpfiles-notice__logo">
        <img src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>" alt="WPFiles Icon" srcset="">
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php
            printf(
                esc_html__(
                    'Hopefully you are loving WPFiles so far. Could you please rate WPFiles %1$s&#9733;&#9733;&#9733;&#9733;&#9733;%2$s on %3$sWordPress.org%4$s to help us spread the word. Thank you from the WPFiles team ♥',
                    'wpfiles'
                ),
                '<a target="_blank" href="'.WP_FILES_GO_URL.'/rate-wp">',
                '</a>',
                '<a target="_blank" href="'.WP_FILES_GO_URL.'/rate-wp">',
                '</a>'
            );
            ?>  
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a target="_blank" href="<?php echo WP_FILES_GO_URL.'/rate-wp' ;?>" class="button button-primary">
            <?php echo __( 'Rate now', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" id="wpfiles-rate-notice-already-done" class="button button-secondary" data-notice="<?php echo  WP_FILES_PREFIX . 'rate-notice-already-done' ?>">
            <?php echo __( 'Already did', 'wpfiles' ); ?>
        </a>
        <a href="javascript:void(0)" class="wpfiles-notice__link_subdued" id="wpfiles-rate-notice" data-notice="<?php echo  WP_FILES_PREFIX . 'rate-notice-hide' ?>">
            <?php echo __( 'Later', 'wpfiles' ); ?>
        </a>
    </div>
</div>