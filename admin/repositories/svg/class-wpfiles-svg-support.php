<?php 
/**
 * You will find all related about SVG support.
 */
class WpFiles_svg_support {

    /**
     * The sanitizer
     * @since 1.0.0
     * @var \enshrined\svgSanitize\Sanitizer
    */
    protected $sanitizer;

    /**
     * Containing svg related hooks
     * @since 1.0.0
     * @return void
    */
    function __construct() {
        $this->sanitizer = new enshrined\svgSanitize\Sanitizer();
        $this->sanitizer->minify( true );
        add_filter( 'wp_get_attachment_image_src', array( $this, 'setDimensions' ), 10, 4 );
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'uploadPrefilterForSvg' ) );
        add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepareAttachmentForJs' ), 10, 3 );
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'resolveMimeTypeSvg' ), 75, 4 );
        add_filter( 'admin_post_thumbnail_html', array( $this, 'resolveFeaturedImage' ), 10, 3 );
        add_filter( 'wp_get_attachment_metadata', array( $this, 'fixMetadataIssues' ), 10, 2 );
        add_filter( 'upload_mimes', array( $this, 'allowSvg' ) );
        add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'disable_svg_srcset' ), 10, 4 );
        add_action( 'get_image_tag', array( $this, 'overrideImageTag' ), 10, 6 );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'skipSvgRegeneration' ), 10, 2 );
    }

    /**
     * Polyfill for `str_ends_with()` function added in PHP 8.0.
     * @since 1.0.0
     * Performs a case-sensitive check indicating if
     * the haystack ends with needle.
     * @param string $haystack The string to search in.
     * @param string $needle   The substring to search for in the `$haystack`.
     * @return bool True if `$haystack` ends with `$needle`, otherwise false.
     */
    protected function str_ends_with( $haystack, $needle ) {
        if ( function_exists( 'str_ends_with' ) ) {
            return str_ends_with( $haystack, $needle );
        }

        if ( '' === $haystack && '' !== $needle ) {
            return false;
        }

        $len = strlen( $needle );
        return 0 === substr_compare( $haystack, $needle, -$len, $len );
    }

    /**
     * Allow SVG Uploads
     * @since 1.0.0
     * @param $mimes
     * @return mixed
     */
    public function allowSvg( $mimes ) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Sanitize the SVG
     * @since 1.0.0
     * @param $file
     * @return bool|int
     */
    protected function sanitize( $file ) {

        $dirty = file_get_contents( $file );

        // Is the SVG gzipped? If so we try and decode the string
        if ( $is_zipped = $this->isGzip( $dirty ) ) {
            $dirty = gzdecode( $dirty );

            // If decoding fails, bail as we're not secure
            if ( $dirty === false ) {
                return false;
            }
        }

        /**
         * Load extra filters to allow devs to access the safe tags and attrs by themselves.
         */
        $this->sanitizer->setAllowedTags( new WpFiles_svg_tags() );
        $this->sanitizer->setAllowedAttrs( new WpFiles_svg_attributes() );

        $clean = $this->sanitizer->sanitize( $dirty );

        if ( $clean === false ) {
            return false;
        }

        // If we were gzipped, we need to re-zip
        if ( $is_zipped ) {
            $clean = gzencode( $clean );
        }

        file_put_contents( $file, $clean );

        return true;
    }

    /**
     * Get SVG size from the width/height or viewport.
     * @since 1.0.0
     * @param string|false $svg The file path to where the SVG file should be, false otherwise.
     * @return array|bool
     */
    protected function getSvgDimensions( $svg ) {

        $svg    = @simplexml_load_file( $svg );

        $width  = 0;

        $height = 0;

        if ( $svg ) {

            $attributes = $svg->attributes();

            if ( isset( $attributes->viewBox ) ) {
                $sizes = explode( ' ', $attributes->viewBox );
                if ( isset( $sizes[2], $sizes[3] ) ) {
                    $viewbox_width  = floatval( $sizes[2] );
                    $viewbox_height = floatval( $sizes[3] );
                }
            }

            if ( isset( $attributes->width, $attributes->height ) && is_numeric( (float) $attributes->width ) && is_numeric( (float) $attributes->height ) && ! $this->str_ends_with( (string) $attributes->width, '%' ) && ! $this->str_ends_with( (string) $attributes->height, '%' ) ) {
                $$attributeWidth  = floatval( $attributes->width );
                $attributeHeight = floatval( $attributes->height );
            }

            /**
             * Check which attributes we use first for image dimensions.
             * Default to using the params in the viewbox attribute but
             * that can be overridden using this filter if you would prefer to use
             * the width and height attributes.
             * @param {bool} $false If the width & height attributes should be used first. Default false.
             * @param {string} $svg The file path to the SVG.
             * @return {bool} If we should use the width & height attributes first or not.
             */
            $useWidthHeight = (bool) apply_filters( 'safe_svg_useWidthHeight_attributes', false, $svg );

            if ( $useWidthHeight ) {
                if ( isset( $$attributeWidth, $attributeHeight ) ) {
                    $width  = $$attributeWidth;
                    $height = $attributeHeight;
                } elseif ( isset( $viewbox_width, $viewbox_height ) ) {
                    $width  = $viewbox_width;
                    $height = $viewbox_height;
                }
            } else {
                if ( isset( $viewbox_width, $viewbox_height ) ) {
                    $width  = $viewbox_width;
                    $height = $viewbox_height;
                } elseif ( isset( $$attributeWidth, $attributeHeight ) ) {
                    $width  = $$attributeWidth;
                    $height = $attributeHeight;
                }
            }

            if ( ! $width && ! $height ) {
                return false;
            }
        }

        return array(
            'width'       => $width,
            'height'      => $height,
            'orientation' => ( $width > $height ) ? 'landscape' : 'portrait'
        );
    }

    /**
     * Filters the attachment data prepared for JavaScript to add the sizes array to the response
     * @since 1.0.0
     * @param array $response 
     * @param int|object $attachment
     * @param array $meta 
     * @return array
     */
    public function prepareAttachmentForJs( $response, $attachment, $meta ) {

        if ( $response['mime'] == 'image/svg+xml' ) {
            $dimensions = $this->getSvgDimensions( get_attached_file( $attachment->ID ) );

            if ( $dimensions ) {
                $response = array_merge( $response, $dimensions );
            }

            $possible_sizes = apply_filters( 'image_size_names_choose', array(
                'full'      => __( 'Full Size' ),
                'thumbnail' => __( 'Thumbnail' ),
                'medium'    => __( 'Medium' ),
                'large'     => __( 'Large' ),
            ) );

            $sizes = array();

            foreach ( $possible_sizes as $size => $label ) {

                $default_height = 2000;

                $default_width  = 2000;

                if ( 'full' === $size && $dimensions ) {
                    $default_height = $dimensions['height'];
                    $default_width  = $dimensions['width'];
                }

                $sizes[ $size ] = array(
                    'height'      => get_option( "{$size}_size_w", $default_height ),
                    'width'       => get_option( "{$size}_size_h", $default_width ),
                    'url'         => $response['url'],
                    'orientation' => 'portrait',
                );
            }

            $response['sizes'] = $sizes;

            $response['icon']  = $response['url'];
        }

        return $response;
    }

    /**
     * If the featured image is an SVG we wrap it in an SVG class so we can apply our CSS fix.
     * @since 1.0.0
     * @param string $content 
     * @param int $post_id 
     * @param int $thumbnail_id 
     * @return string
     */
    public function resolveFeaturedImage( $content, $post_id, $thumbnail_id ) {

        $mime = get_post_mime_type( $thumbnail_id );

        if ( 'image/svg+xml' === $mime ) {
            $content = sprintf( '<span class="svg">%s</span>', $content );
        }

        return $content;
    }

    /**
     * Override the default height and width string on an SVG
     * @since 1.0.0
     * @param string $html HTML content for the image.
     * @param int $id
     * @param string $alt 
     * @param string $title
     * @param string $align 
     * @param string|array $size 
     * Default 'medium'.
     * @return mixed
     */
    function overrideImageTag( $html, $id, $alt, $title, $align, $size ) {

        $mime = get_post_mime_type( $id );

        if ( 'image/svg+xml' === $mime ) {
            if ( is_array( $size ) ) {
                $width  = $size[0];
                $height = $size[1];
            } elseif ( 'full' == $size && $dimensions = $this->getSvgDimensions( get_attached_file( $id ) ) ) {
                $width  = $dimensions['width'];
                $height = $dimensions['height'];
            } else {
                $width  = get_option( "{$size}_size_w", false );
                $height = get_option( "{$size}_size_h", false );
            }

            if ( $height && $width ) {
                $html = str_replace( 'width="1" ', sprintf( 'width="%s" ', $width ), $html );
                $html = str_replace( 'height="1" ', sprintf( 'height="%s" ', $height ), $html );
            } else {
                $html = str_replace( 'width="1" ', '', $html );
                $html = str_replace( 'height="1" ', '', $html );
            }

            $html = str_replace( '/>', ' role="img" />', $html );
        }

        return $html;
    }

    /**
     * Skip regenerating SVGs
     * @since 1.0.0
     * @param int $attachment_id 
     * @param string $file 
     * @return mixed
     */
    function skipSvgRegeneration( $metadata, $attachment_id ) {

        $mime = get_post_mime_type( $attachment_id );

        if ( 'image/svg+xml' === $mime ) {

            $additional_image_sizes = wp_get_additional_image_sizes();

            $svg_path               = get_attached_file( $attachment_id );

            $upload_dir             = wp_upload_dir();

            $relative_path = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $svg_path );
            
            $filename      = basename( $svg_path );

            $dimensions = $this->getSvgDimensions( $svg_path );

            if ( ! $dimensions ) {
                return $metadata;
            }

            $metadata = array(
                'width'  => intval( $dimensions['width'] ),
                'height' => intval( $dimensions['height'] ),
                'file'   => $relative_path
            );

            // Might come handy to create the sizes array too - But it's not needed for this workaround! Always links to original svg-file => Hey, it's a vector graphic! ;)
            $sizes = array();
            foreach ( get_intermediate_image_sizes() as $s ) {
                $sizes[ $s ] = array( 'width' => '', 'height' => '', 'crop' => false );

                if ( isset( $additional_image_sizes[ $s ]['width'] ) ) {
                    // For theme-added sizes
                    $sizes[ $s ]['width'] = intval( $additional_image_sizes[ $s ]['width'] );
                } else {
                    // For default sizes set in options
                    $sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
                }

                if ( isset( $additional_image_sizes[ $s ]['height'] ) ) {
                    // For theme-added sizes
                    $sizes[ $s ]['height'] = intval( $additional_image_sizes[ $s ]['height'] );
                } else {
                    // For default sizes set in options
                    $sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
                }

                if ( isset( $additional_image_sizes[ $s ]['crop'] ) ) {
                    // For theme-added sizes
                    $sizes[ $s ]['crop'] = intval( $additional_image_sizes[ $s ]['crop'] );
                } else {
                    // For default sizes set in options
                    $sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
                }

                $sizes[ $s ]['file']      = $filename;

                $sizes[ $s ]['mime-type'] = $mime;
            }

            $metadata['sizes'] = $sizes;
        }

        return $metadata;
    }

    /**
     * Filters the attachment meta data.
     * @since 1.0.0
     * @param array|bool $data 
     * @param int $post_id 
     */
    function fixMetadataIssues( $data, $post_id ) {

        if ( is_wp_error( $data ) ) {
            $data = wp_generate_attachment_metadata( $post_id, get_attached_file( $post_id ) );
            wp_update_attachment_metadata( $post_id, $data );
        }

        return $data;
    }

    /**
     * Check if the file is an SVG, if so handle appropriately
     * @since 1.0.0
     * @param $file
     * @return mixed
     */
    public function uploadPrefilterForSvg( $file ) {

        if ( ! isset( $file['tmp_name'] ) ) {
            return $file;
        }

        $file_name   = isset( $file['name'] ) ? $file['name'] : '';

        $wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file_name );

        $type        = ! empty( $wp_filetype['type'] ) ? $wp_filetype['type'] : '';

        if ( $type === 'image/svg+xml' ) {
            if ( ! $this->sanitize( $file['tmp_name'] ) ) {
                $file['error'] = __( "Sorry, this file couldn't be sanitized so for security reasons wasn't uploaded",
                    'wpfiles' );
            }
        }

        return $file;
    }

    /**
     * Disable the creation of srcset on SVG images.
     * @since 1.0.0
     * @param array $image_meta The image meta data.
     * @param int[]  $size_array    
     * @param string $image_src    
     * @param int    $attachment_id 
     */
    public function disable_svg_srcset( $image_meta, $size_array, $image_src, $attachment_id ) {

        if ( $attachment_id && 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
            $image_meta['sizes'] = array();
        }

        return $image_meta;
    }

    /**
     * Fixes the issue in WordPress 4.7 being unable to correctly identify SVGs
     * @since 1.0.0
     * @param null $data
     * @param null $file
     * @param null $filename
     * @param null $mimes
     * @return null
     */
    public function resolveMimeTypeSvg( $data = null, $file = null, $filename = null, $mimes = null ) {

        $ext = isset( $data['ext'] ) ? $data['ext'] : '';

        if ( strlen( $ext ) < 1 ) {
            $exploded = explode( '.', $filename );
            $ext      = strtolower( end( $exploded ) );
        }

        if ( $ext === 'svg' ) {
            $data['type'] = 'image/svg+xml';
            $data['ext']  = 'svg';
        } elseif ( $ext === 'svgz' ) {
            $data['type'] = 'image/svg+xml';
            $data['ext']  = 'svgz';
        }

        return $data;
    }

    /**
     * Check if the contents are gzipped
     * @since 1.0.0
     * @param $contents
     * @return bool
     */
    protected function isGzip( $contents ) {
        if ( function_exists( 'mb_strpos' ) ) {
            return 0 === mb_strpos( $contents, "\x1f" . "\x8b" . "\x08" );
        } else {
            return 0 === strpos( $contents, "\x1f" . "\x8b" . "\x08" );
        }
    }

    /**
     * Filters the image src result.
     * If the image size doesn't exist, set a default size of 100 for width and height
     * @since 1.0.0
     * @param array|false $image 
     * @param int $attachment_id
     * @param string|array $size 
     * @param bool $icon 
     * @return array
     */
    public function setDimensions( $image, $attachment_id, $size, $icon ) {

        if ( get_post_mime_type( $attachment_id ) === 'image/svg+xml' ) {
            $dimensions = $this->getSvgDimensions( get_attached_file( $attachment_id ) );

            if ( $dimensions ) {
                $image[1] = $dimensions['width'];
                $image[2] = $dimensions['height'];
            } else {
                $image[1] = 100;
                $image[2] = 100;
            }
        }

        return $image;
    }

}