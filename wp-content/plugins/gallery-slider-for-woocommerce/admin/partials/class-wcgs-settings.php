<?php
/**
 * The admin settings page of this plugin.
 *
 * Defines various settings of WooGallery.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WCGS Settings class
 */
class WCGS_Settings {
	/**
	 * Initialize the WooCommerce Settings page for the admin area.
	 *
	 * @since    1.0.0
	 * @param string $prefix Define prefix wcgs_settings.
	 */
	public static function options( $prefix ) {
		WCGS::createOptions(
			$prefix,
			array(
				'framework_title'    => '',
				'framework_class'    => 'wcgs-settings',
				'class'              => 'wcgs-preloader',
				'menu_title'         => __( 'Global Settings', 'gallery-slider-for-woocommerce' ),
				'menu_type'          => 'submenu',
				'menu_parent'        => 'wpgs-settings',
				'menu_slug'          => 'wpgs-settings',
				'show_reset_section' => true,
				'show_search'        => false,
				'show_all_options'   => false,
				'theme'              => 'light',
				'show_footer'        => false,
				'sticky_header'      => true,
				'show_sub_menu'      => false,
				'footer_credit'      => sprintf(
					/* translators: 1: start strong tag, 2: close strong tag, 3: span tag start, 4: span tag end, 5: anchor tag start, 6: anchor tag ended. */
					__( 'Enjoying %1$sReno Product Gallery?%2$s Please rate us %3$s★★★★★%4$s %5$sWordPress.org.%6$s Your positive feedback will help us grow more. Thank you! 😊', 'gallery-slider-for-woocommerce' ),
					'<strong>',
					'</strong>',
					'<span class="spwpcp-footer-text-star">',
					'</span>',
					'<a href="https://wordpress.org/support/plugin/gallery-slider-for-woocommerce/reviews/" target="_blank">',
					'</a>'
				),
				'footer_after'       => "<div id='BuyProPopupContent' style='display: none;'>
				<div class='wcgs-popup-content'><div class='pro-image-tag'><span class='pro-icon'><img src='" . plugin_dir_url( __DIR__ ) . 'img/go-pro-icon.svg' . "'></span></div><h2> " . sprintf(
					/* translators: 1: start strong tag, 2: close strong tag. */
					__( 'Upgrade to %1$sReno Product Gallery Pro%2$s', 'gallery-slider-for-woocommerce' ),
					'<strong>',
					'</strong>'
				) . '</h2><h3>' . __( 'To unlock this feature, simply upgrade to Pro!', 'gallery-slider-for-woocommerce' ) . "</h3><p class='wcgs-popup-p'>" . sprintf(
					/* translators: 1: start strong tag, 2: close strong tag. */
					__( 'Take your online shop\'s product page experience to the next level with many premium features and %1$sBoost Sales!%2$s', 'gallery-slider-for-woocommerce' ),
					'<strong>',
					'</strong>'
				) . " 🚀</p><p><a href='" . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . "' target='_blank' class='btn'>" . __( 'Upgrade to Pro Now', 'gallery-slider-for-woocommerce' ) . '</a></p></div></div>',
			)
		);

		// <div id="myOnPageContent" style="display: none;"> <div class="wcgs-popup-content">
		// <h2> <Upgrade to <strong>WooGallery Pro</strong></h2>

		// <p> Take your online shop product page experience to the next
		// level ton of premium features and Boost Sales! 🚀</p> <p><a target="_blank" href=' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . ' class="btn">Get the Pro version</a></p></div> </div>
		WCGS_General::section( $prefix );
		WCGS_Gallery::section( $prefix );
		WCGS_Lightbox::section( $prefix );
		// WCGSP_Shoppage::section( $prefix );
		WCGS_Advance::section( $prefix );
		// WCGS_Help::section( $prefix );
	}
}
