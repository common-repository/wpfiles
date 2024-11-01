<div class="notice wpfiles-notice notice-warning" id="wpfiles-dismiss-usage-tracking-notice-parent">
   <div class="wpfiles-notice__logo">
        <img
            src="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?>"
            srcset="<?php echo esc_url( WP_FILES_PLUGIN_URL . '/admin/images/settings/logo-icon.svg' ); ?> 2x"
            alt="<?php esc_html_e( 'WPFiles Icon', 'wpfiles' ); ?>"
        >
    </div>
    <div class="wpfiles-notice__content">
        <div class="wpfiles-notice__content__wrapper">
            <?php esc_html_e( __( ucfirst($params['username']).', in order to make your WPFiles experience better and secure we need to collect some non-sensitive usage data.', 'wpfiles' ), 'wpfiles' ); ?>
            <div>
                <div class="wf-permissions">
                    <div class="wf-permissions__title" id="wpfiles-toggle-usage-tracking-content"><?php echo __( 'What permissions are being granted?', 'wpfiles' ); ?></div>
                    <div class="wf-permissions__wrapper">
                        <div class="wf-permissions__block">
                            <div class="wf-permissions__block__icon"><span class="wpfiles-icon wpfiles-icon-user"></span></div>
                            <div class="wf-permissions__block__details">
                                <div class="wf-permissions__block__title"><?php echo __( 'Your profile overview', 'wpfiles' ); ?></div>
                                <div class="wf-permissions__block__sub"><?php echo __( 'Name, email and geo', 'wpfiles' ); ?></div>
                            </div>
                        </div>
                        <div class="wf-permissions__block">
                            <div class="wf-permissions__block__icon"><span class="wpfiles-icon wpfiles-icon-wordpress"></span></div>
                            <div class="wf-permissions__block__details">
                                <div class="wf-permissions__block__title"><?php echo __( 'Your site overview', 'wpfiles' ); ?></div>
                                <div class="wf-permissions__block__sub"><?php echo __( 'Site address, WordPress version and tech stack', 'wpfiles' ); ?></div>
                            </div>
                        </div>
                        <div class="wf-permissions__block">
                            <div class="wf-permissions__block__icon"><span class="wpfiles-icon wpfiles-icon-unplug"></span></div>
                            <div class="wf-permissions__block__details">
                                <div class="wf-permissions__block__title"><?php echo __( 'Current plugins events', 'wpfiles' ); ?></div>
                                <div class="wf-permissions__block__sub"><?php echo __( 'Activation, deactivation and uninstall', 'wpfiles' ); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="wpfiles-notice__actions">
        <a href="#" id="wpfiles-usage-tracking_submit" class="button button-primary">
            <?php esc_html_e( 'Allow & Continue', 'wpfiles' ); ?>
        </a>
        <a href="#" id="wpfiles-dismiss-usage-tracking-notice" class="wpfiles-notice__link_subdued" data-notice="<?php echo  WP_FILES_PREFIX . 'usage-tracking-notice-hide' ?>">
            <?php esc_html_e( 'Dismiss', 'wpfiles' ); ?>
        </a>
    </div>
</div>