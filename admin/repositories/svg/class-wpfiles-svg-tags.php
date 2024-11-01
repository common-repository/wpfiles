<?php
/**
 * You will find all related about SVG support.
 */
class WpFiles_svg_tags extends \enshrined\svgSanitize\data\AllowedTags {

	/**
	 * Returns an array of tags
	 * @since 1.0.0
	 * @return array
	*/
	public static function getTags() {
		return apply_filters( 'svg_allowed_tags', parent::getTags() );
	}
}