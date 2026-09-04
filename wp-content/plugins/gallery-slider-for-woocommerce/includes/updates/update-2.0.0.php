<?php
/**
 * This file is used to update the plugin to version 2.0.0
 *
 * @package    Woo_Gallery_Slide
 * @subpackage Woo_Gallery_Slide/includes
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

update_option( 'woo_gallery_slider_version', '2.0.0' );
update_option( 'woo_gallery_slider_db_version', '2.0.0' );
// Update old option to new options.
$old_settings                                      = get_option( 'wcgs_settings' );
$active_thumbnail_border                           = isset( $old_settings['border_width_for_active_thumbnail'] ) ? $old_settings['border_width_for_active_thumbnail'] : '';
$old_settings['border_normal_width_for_thumbnail'] = array(
	'color'  => isset( $active_thumbnail_border['color'] ) ? $active_thumbnail_border['color'] : '#ddd',
	'color3' => isset( $active_thumbnail_border['color3'] ) ? $active_thumbnail_border['color3'] : '#0085BA',
	'all'    => isset( $active_thumbnail_border['all'] ) ? $active_thumbnail_border['all'] : 2,
	'radius' => 0,
);
$thumbnails_space                                  = isset( $old_settings['thumbnails_space'] ) ? $old_settings['thumbnails_space'] : '6';
$old_settings['thumbnails_sliders_space']          = array(
	'width'  => $thumbnails_space,
	'height' => $thumbnails_space,
);

update_option( 'wcgs_settings', $old_settings );
