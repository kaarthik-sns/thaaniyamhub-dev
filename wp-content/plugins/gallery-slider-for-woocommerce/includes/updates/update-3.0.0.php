<?php
/**
 * This file is used to update the plugin to version 3.0.0
 *
 * @since      3.0.0
 * @package    Woo_Gallery_Slide
 * @subpackage Woo_Gallery_Slide/includes
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

update_option( 'woo_gallery_slider_version', '3.0.0' );
update_option( 'woo_gallery_slider_db_version', '3.0.0' );


// Update old option to new options.
$old_settings                       = get_option( 'wcgs_settings' );
$adaptive_height                    = isset( $old_settings['adaptive_height'] ) ? $old_settings['adaptive_height'] : false;
$old_settings['slider_height_type'] = $adaptive_height ? 'adaptive' : 'max_image';
$old_settings['slider_height']      = array(
	'width'   => '500',
	'height'  => '500',
	'height2' => '500',
);
update_option( 'wcgs_settings', $old_settings );

// Update old layout meta to new layout meta.
$wcgs_layouts = get_posts(
	array(
		'post_type'      => 'wcgs_layouts',
		'posts_per_page' => 999,
		'fields'         => 'ids',
	)
);
if ( ! empty( $wcgs_layouts ) ) {
	foreach ( $wcgs_layouts as $layout ) {
		$layout_meta = get_post_meta( $layout, 'wcgs_metabox', true );
		if ( ! empty( $layout_meta ) ) {
			$adaptive_height                   = isset( $layout_meta['adaptive_height'] ) ? $layout_meta['adaptive_height'] : false;
			$layout_meta['slider_height_type'] = $layout_meta ? 'adaptive' : 'max_image';
			$layout_meta['slider_height']      = array(
				'width'   => '500',
				'height'  => '500',
				'height2' => '500',
			);
			update_post_meta( $layout, 'wcgs_metabox', $layout_meta );
		}
	}
}
