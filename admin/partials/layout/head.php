<script type="text/javascript">
    // Open RM KB menu link in the new tab.
    jQuery( document ).ready( function( $ ) {
        $( "ul#adminmenu a[href$='<?php echo esc_url(WP_FILES_GO_URL); ?>/support']" ).attr( 'target', '_blank' );
    } );
</script>