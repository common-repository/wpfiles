<?php
/**
 * You will find all related about SVG support.
 */
class WpFiles_svg_attributes extends \enshrined\svgSanitize\data\AllowedAttributes {
	/**
     * Returns an array of attributes
     * @since 1.0.0
     * @return array
    */
	public static function getAttributes() {
		return apply_filters( 'svg_allowed_attributes', parent::getAttributes() );
	}
}