<?php
/**
 * The gallery tab functionality of this plugin.
 *
 * Defines the sections of gallery tab.
 *
 * @package    Woo_Gallery_Slider_Pro
 * @subpackage Woo_Gallery_Slider_Pro/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WCGSP_Lightbox
 */
class WCGSP_Shoppage {
	/**
	 * Specify the Gallery tab for the WooGallery.
	 *
	 * @since    1.0.0
	 * @param string $prefix Define prefix wcgs_settings.
	 */
	public static function section( $prefix ) {
		WCGS::createSection(
			$prefix,
			array(
				'name'   => 'shop_page_video',
				'icon'   => 'sp_wgs-icon-video-01-1',
				'title'  => __( 'Shop Page Video', 'gallery-slider-for-woocommerce' ),
				'fields' => array(
					array(
						'id'      => 'shop_page_video_notice',
						'class'   => 'shop_page_video_notice wcgs-light-notice',
						'type'    => 'notice',
						'style'   => 'normal',
						'content' => sprintf(
							/* translators: 1: start link and strong tag, 2: close link and strong tag, 3: start link and strong tag, 4: close link and strong tag. */
							__( 'Want to show multiple types of %1$s Product Featured Videos with AutoPlay%2$s on the %3$sShop/Archive Page%4$s and speed up customer decision-making? %5$sUpgrade to Pro!%6$s See the %7$savailable options.%8$s', 'gallery-slider-for-woocommerce' ),
							'<a href="https://demo.renoproductgallery.com/product-category/video-autoplay/" target="_blank"><strong>',
							'</strong></a>',
							'<a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/" target="_blank"><strong>',
							'</strong></a>',
							'<a href="https://renoproductgallery.com/pricing/?ref=143" target="_blank" class="btn"><strong>',
							'</strong></a>',
							'<a href="https://docs.renoproductgallery.com/guide/shop-page-video" target="_blank" class="btn"><strong>',
							'</strong></a>'
						),
					),
				),
			)
		);
	}
}
