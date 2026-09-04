<?php
/**
 * The product images.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * Default value trait
 */
trait WCGS_Public_Default {
	/**
	 * Inactive Image effect
	 *
	 * @var string
	 */
	public $inactive_thumbnails_effect = 'none';
	/**
	 * Navigation icon
	 *
	 * @var string
	 */
	public $navigation_icon = 'angle';
	/**
	 * Navigation icon size
	 *
	 * @var integer
	 */
	public $navigation_icon_size = 16;
	/**
	 * Vertical_thumbs_width
	 *
	 * @var int
	 */
	public $vertical_thumbs_width = 20;
	/**
	 * Navigation icon color
	 *
	 * @var integer
	 */
	public $navigation_icon_color = '#fff';
	/**
	 * Navigation icon hover color
	 *
	 * @var integer
	 */
	public $navigation_icon_hover_color = '#fff';
	/**
	 * Navigation icon background color
	 *
	 * @var integer
	 */
	public $navigation_icon_bg_color = 'rgba(0, 0, 0, 0.5)';
	/**
	 * Navigation_icon_radius
	 *
	 * @var string
	 */
	public $navigation_icon_radius = '0';
	/**
	 * Navigation icon size background hover color
	 *
	 * @var integer
	 */
	public $navigation_icon_hover_bg_color = 'rgba(0, 0, 0, 0.85)';
	/**
	 * Navigation left icon
	 *
	 * @var integer
	 */
	public $navigation_left_icon = '\f104';
	/**
	 * Navigation right icon
	 *
	 * @var integer
	 */
	public $navigation_right_icon = '\f105';
	/**
	 * Gallery_layout
	 *
	 * @var string
	 */
	public $gallery_layout = 'horizontal';
	/**
	 * Image border radius
	 *
	 * @var int
	 */
	public $image_border_radius = 0;
	/**
	 * Thumbnails_sliders_space
	 *
	 * @var array
	 */
	public $thumbnails_sliders_width = 6;
	/**
	 * Thumbnail_style
	 *
	 * @var string
	 */
	public $thumbnail_style = 'border_around';
	/**
	 * Thumbnails_sliders_height
	 *
	 * @var int
	 */
	public $thumbnails_sliders_height = 6;
	/**
	 * Thumbnail navigation icon
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon = 'angle';
	/**
	 * Thumbnail navigation left icon
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_left_icon = '\f104';
	/**
	 * Thumbnail navigation right icon
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_right_icon = '\f105';
	/**
	 * Thumbnail navigation icon size
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon_size = 12;
	/**
	 * Thumbnail hover effect
	 *
	 * @var int
	 */
	public $thumbnails_hover_effect = 'none';
	/**
	 * Thumbnail navigation icon color
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon_color = '#fff';
	/**
	 * Thumbnail navigation icon hover color
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon_hover_color = '#fff';
	/**
	 * Pagination type
	 *
	 * @var string
	 */
	public $pagination_type = 'bullets';

	/**
	 * Thumbnail navigation icon background color
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon_bg_color = 'rgba(0, 0, 0, 0.5)';
	/**
	 * Thumbnail navigation icon hover background color
	 *
	 * @var integer
	 */
	public $thumbnailnavigation_icon_hover_bg_color = 'rgba(0, 0, 0, 0.8)';

	/**
	 * Pagination icon icon color
	 *
	 * @var integer
	 */
	public $pagination_icon_color = 'rgba(115, 119, 121, 0.5)';
	/**
	 * Pagination icon active color
	 *
	 * @var integer
	 */
	public $pagination_icon_active_color = 'rgba(115, 119, 121, 0.8)';
	/**
	 * Lightbox icon
	 *
	 * @var integer
	 */
	public $lightbox_icon = 'search_plus';
	/**
	 * Lightbox icon color
	 *
	 * @var integer
	 */
	public $lightbox_icon_color = '#1e1e1e';
	/**
	 * Lightbox icon hover color
	 *
	 * @var integer
	 */
	public $lightbox_icon_hover_color = '#1e1e1e';
	/**
	 * Lightbox icon background color
	 *
	 * @var integer
	 */
	public $lightbox_icon_bg_color = '#ffffff';
	/**
	 * Lightbox icon hover background color
	 *
	 * @var integer
	 */
	public $lightbox_icon_hover_bg_color = '#ffffff';
	/**
	 * Lightbox background
	 *
	 * @var integer
	 */
	public $lightbox_bg = '#1e1e1e';
	/**
	 * Lightbox icon size
	 *
	 * @var integer
	 */
	public $lightbox_icon_size = 13;
	/**
	 * Lightbox caption size
	 *
	 * @var integer
	 */
	public $lightbox_caption_size = 14;
	/**
	 * WCGS additional css
	 *
	 * @var integer
	 */
	public $wcgs_additional_css = '';
}
