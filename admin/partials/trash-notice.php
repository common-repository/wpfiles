<div class="notice notice-warning wpfiles-notice">
    <ul>
        <li><strong>Media Trash Button</strong>
            <?php
            echo wp_kses_post(sprintf(__(' : In %1$s, Add the following one line. %2$s', 'media-trash-button'), $params['wp_config_link_html'], $params['wp_define_html']));
            ?>
        </li>
    </ul>
</div>