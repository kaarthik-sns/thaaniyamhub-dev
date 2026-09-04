<?php
/**
 * This file is used to update the plugin to version 2.2.1
 *
 * @since      2.2.1
 * @package    Woo_Gallery_Slide
 * @subpackage Woo_Gallery_Slide/includes
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

update_option( 'woo_gallery_slider_version', '2.2.1' );
update_option( 'woo_gallery_slider_db_version', '2.2.1' );

// If the transient exists, delete it.
if ( get_transient( 'wcgs_plugins' ) ) {
	delete_transient( 'wcgs_plugins' );
}
