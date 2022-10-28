<?php
/**
 * Shortcode template
 *
 */
function eamm_template( $atts, $content=null ){
	$atts = shortcode_atts(
		array(
			'title' => 'DEBUG INFORMATION',
		),
		$atts
	);
	$title = (is_null($content) || strlen($content)==0)? esc_html($atts['title']): esc_html($content);
	$out = '<h2>'.$title.'</h2>';
	return $out;
}
add_shortcode('eamm_template', 'eamm_template');

