<?php
/**
 * The admin settings page of this plugin.
 *
 * Defines various settings ofWooGallery.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WCGS_Metabox.
 */
class WCGS_Metaboxs {
	/**
	 * Initialize the WooCommerce Settings page for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $prefix Define prefix wcgs_settings.
	 */
	public static function options( $prefix ) {
		if ( method_exists( 'WCGS', 'createMetabox' ) ) {
			WCGS::createMetabox(
				$prefix,
				array(
					'post_type'     => 'wcgs_layouts',
					'show_restore'  => false,
					'context'       => 'normal',
					'title'         => __( 'Gallery Settings', 'gallery-slider-for-woocommerce' ),
					'footer_credit' => sprintf(
					/* translators: 1: start strong tag, 2: close strong tag, 3: span tag start, 4: span tag end, 5: anchor tag start, 6: anchor tag ended. */
						__( 'Enjoying %1$sReno Product Gallery?%2$s Please rate us %3$s★★★★★%4$s %5$sWordPress.org.%6$s Your positive feedback will help us grow more. Thank you! 😊', 'gallery-slider-for-woocommerce' ),
						'<strong>',
						'</strong>',
						'<span class="spwpcp-footer-text-star">',
						'</span>',
						'<a href="https://wordpress.org/support/plugin/gallery-slider-for-woocommerce/reviews/" target="_blank">',
						'</a>'
					),
				)
			);
			WCGS_General::section( $prefix );
			WCGS_Gallery::section( $prefix );
			WCGS_Lightbox::section( $prefix );

			WCGS::createMetabox(
				'wcgs_builder_option',
				array(
					'title'            => __( 'Page Builders', 'gallery-slider-for-woocommerce' ),
					'post_type'        => 'wcgs_layouts',
					'context'          => 'side',
					'show_restore'     => false,
					'sp_lcp_shortcode' => false,
					'theme'            => 'light',
				)
			);

			WCGS::createSection(
				'wcgs_builder_option',
				array(
					'fields' => array(
						array(
							'type'      => 'shortcode',
							'shortcode' => false,
							'class'     => 'sp_tpro-admin-sidebar',
						),
					),
				)
			);

			$display_shortcode = 'wcgs_pro_notice';
			//
			// Create a metabox.
			//
			WCGS::createMetabox(
				$display_shortcode,
				array(
					'title'     => __( 'How To Use', 'gallery-slider-for-woocommerce' ),
					'post_type' => 'wcgs_layouts',
					'context'   => 'side',
					'theme'     => 'light',
				)
			);

			//
			// Create a section.
			//
			WCGS::createSection(
				$display_shortcode,
				array(
					'fields' => array(
						array(
							'type'      => 'shortcode',
							'class'     => 'splw-admin-sidebar',
							'shortcode' => 'pro_notice',
						),
					),
				)
			);
		}
	}
}
