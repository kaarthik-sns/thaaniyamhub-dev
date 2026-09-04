<?php
/**
 * The advance tab functionality of this plugin.
 *
 * Defines the sections of advance tab.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WCGS Advance class
 */
class WCGS_Advance {
	/**
	 * Specify the Advance tab for the WooGallery.
	 *
	 * @since    1.0.0
	 * @param string $prefix Define prefix wcgs_settings.
	 */
	public static function section( $prefix ) {
		WCGS::createSection(
			$prefix,
			array(
				'name'   => 'advance',
				'icon'   => 'sp_wgs-icon-advanced-tab',
				'title'  => __( 'Advanced', 'gallery-slider-for-woocommerce' ),
				'fields' => array(
					array(
						'type' => 'tabbed',
						'tabs' => array(
							array(
								'title'  => __( 'Advanced Controls', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-advanced-2',
								'fields' => array(
									array(
										'id'         => 'wcgs_data_remove',
										'type'       => 'checkbox',
										'title'      => __( 'Clean-up Data on Deletion', 'gallery-slider-for-woocommerce' ),
										'title_help' => __( 'Check this box if you would like Reno Product Gallery plugin to completely remove all of its data when the plugin is deleted.', 'gallery-slider-for-woocommerce' ),
									),
									array(
										'id'         => 'shortcode',
										'class'      => 'spwg_shortcode',
										'type'       => 'text',
										'shortcode'  => true,
										'title'      => __( 'Shortcode', 'gallery-slider-for-woocommerce' ),
										'desc'       => sprintf(
											/* translators: 1: start bold tag, 2: close bold tag, 3: start link tag, 4: close link tag. */
											__(
												'If the Product Gallery is not displaying automatically on your product pages when edited using page builders (%1$sDivi, Elementor, Bricks%2$s Builder, etc.), use this shortcode manually to render the Product Gallery. %3$sSee Instructions.%4$s',
												'gallery-slider-for-woocommerce'
											),
											'<b>',
											'</b>',
											'<a href="https://docs.renoproductgallery.com/guide/integrations/page-builder-compatibility" target="_blank"><b>',
											'</b></a>'
										),
										'attributes' => array(
											'readonly' => '',
										),
										'default'    => '[reno_product_gallery]',
									),
									array(
										'type'    => 'subheading',
										'content' => __( 'Assets (Styles & Scripts)', 'gallery-slider-for-woocommerce' ),
									),
									array(
										'id'         => 'enqueue_fancybox_css',
										'type'       => 'switcher',
										'title'      => __( 'FancyBox CSS', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enqueued', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Dequeued', 'gallery-slider-for-woocommerce' ),
										'text_width' => 100,
										'default'    => true,
									),
									array(
										'id'         => 'enqueue_fancybox_js',
										'type'       => 'switcher',
										'title'      => __( 'FancyBox JS', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enqueued', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Dequeued', 'gallery-slider-for-woocommerce' ),
										'text_width' => 100,
										'default'    => true,
									),
									array(
										'id'         => 'enqueue_swiper_css',
										'type'       => 'switcher',
										'title'      => __( 'Swiper CSS', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enqueued', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Dequeued', 'gallery-slider-for-woocommerce' ),
										'text_width' => 100,
										'default'    => true,
									),
									array(
										'id'         => 'enqueue_swiper_js',
										'type'       => 'switcher',
										'title'      => __( 'Swiper JS', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enqueued', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Dequeued', 'gallery-slider-for-woocommerce' ),
										'text_width' => 100,
										'default'    => true,
									),
								),
							),
							array(
								'title'  => __( 'Speed Optimization', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-gauge',
								'fields' => array(
									array(
										'id'      => 'optimize_notice',
										'type'    => 'notice',
										'style'   => 'normal',
										'class'   => 'wcgs-light-notice',
										'content' => sprintf(
											// translators: 1: start bold tag, 2: close bold tag, 3: start link tag, 4: close link tag.
											__( 'To improve the loading speed of your single product page with %3$sReno Product Gallery%4$s, please follow these %1$sguidelines%2$s. Additionally, you may refer to your caching plugin\'s recommendations for further optimization.', 'gallery-slider-for-woocommerce' ),
											'<a href="https://docs.renoproductgallery.com/guide/how-to/performance-and-speed#best-practices-to-speed-up-the-single-product-page" target="_blank"><b>',
											'</b></a>',
											'<b>',
											'</b>'
										),
									),
									array(
										'id'         => 'lazy_load_gallery',
										'type'       => 'checkbox',
										'title'      => __( 'Load the Product Gallery on the Visible Viewport', 'gallery-slider-for-woocommerce' ),
										'title_help' => __( 'Check this box if you would like Reno Product Gallery plugin to ensure the product gallery loads automatically when it enters the visible viewport', 'gallery-slider-for-woocommerce' ),
										'default'    => true,
									),
									array(
										'id'         => 'remove_default_wc_gallery',
										'type'       => 'checkbox',
										'title'      => __( 'Remove Default WooCommerce Gallery Scripts', 'gallery-slider-for-woocommerce' ),
										'title_help' => __( 'WooCommerce gallery assets are disabled because Reno Product Gallery is handling those functions.', 'gallery-slider-for-woocommerce' ),
										'default'    => array( 'lightbox', 'zoom', 'slider' ),
										'options'    => array(
											'lightbox' => __( 'Lightbox(PhotoSwipe) ', 'gallery-slider-for-woocommerce' ),
											'zoom'     => __( 'Zoom (Zoom)', 'gallery-slider-for-woocommerce' ),
											'slider'   => __( 'Slider(FlexSlider)', 'gallery-slider-for-woocommerce' ),
										),
									),
									array(
										'id'         => 'dequeue_single_product_css',
										'type'       => 'text',
										'title'      => __( 'Add handler to Dequeue Unnecessary CSS files', 'gallery-slider-for-woocommerce' ),
										'desc'       => __( 'Enter the CSS file handlers separated by comma. e.g., theme-style, plugin-style-xyz', 'gallery-slider-for-woocommerce' ),
										'title_help' => __( 'Enter the CSS file handles to remove, separated by commas. Caution: Incorrectly removing CSS files can break site styling. Only use this if you understand CSS handles.', 'gallery-slider-for-woocommerce' ),
										'default'    => '',
									),
									array(
										'id'         => 'dequeue_single_product_js',
										'type'       => 'text',
										'title'      => __( 'Add handler to Dequeue Unnecessary JS files ', 'gallery-slider-for-woocommerce' ),
										'desc'       => __( 'Enter the JS file handlers, separated by comma. e.g., theme-script, plugin-script-xyz', 'gallery-slider-for-woocommerce' ),
										'title_help' => __( 'Enter the JS file handles to remove, separated by commas. Caution: Incorrectly removing JS files can break site styling. Only use this if you understand JS handles.', 'gallery-slider-for-woocommerce' ),
										'default'    => '',
									),
								),
							),
							array(
								'title'  => __( 'Migration', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-tools_migration',
								'fields' => array(
									array(
										'id'      => 'optimize_notice',
										'type'    => 'notice',
										'style'   => 'normal',
										'class'   => 'wcgs-light-notice',
										'content' => __( 'Migrate galleries from the following plugins to <b>Reno Product Gallery</b>. The migration process will run in the background.', 'gallery-slider-for-woocommerce' ),
									),
									array(
										'id'      => 'woo-variation-gallery',
										'class'   => ' wcgs_migration_button woo-variation-gallery',
										'type'    => 'button_set',
										'title'   => 'Additional Variation Images Gallery for WooCommerce by Emran Ahmed',
										'desc'    => ' ',
										'options' => array(
											'' => __( 'Migrate Now', 'gallery-slider-for-woocommerce' ),
										),
									),
									array(
										'id'      => 'woo-product-gallery-slider',
										'class'   => ' wcgs_migration_button woo-product-gallery-slider',
										'type'    => 'button_set',
										'title'   => 'Product Gallery Slider for WooCommerce by Niloy - Codeixer',
										'desc'    => ' ',
										'options' => array(
											'' => __( 'Migrate Now', 'gallery-slider-for-woocommerce' ),
										),
									),
									array(
										'id'      => 'woo-product-variation-gallery',
										'class'   => ' wcgs_migration_button woo-product-variation-gallery',
										'type'    => 'button_set',
										'title'   => 'Variation Images Gallery for WooCommerce by RadiusTheme',
										'desc'    => ' ',
										'options' => array(
											'' => __( 'Migrate Now', 'gallery-slider-for-woocommerce' ),
										),
									),
									array(
										'id'      => 'iconic-woothumbs',
										'class'   => ' wcgs_migration_button iconic-woothumbs',
										'type'    => 'button_set',
										'title'   => 'WooThumbs for WooCommerce by IconicWP',
										'desc'    => ' ',
										'options' => array(
											'' => __( 'Migrate Now', 'gallery-slider-for-woocommerce' ),
										),
									),
									array(
										'id'      => 'woocommerce-additional-variation-images',
										'class'   => ' wcgs_migration_button woocommerce-additional-variation-images',
										'type'    => 'button_set',
										'title'   => 'WooCommerce Additional Variation Images by WooCommerce',
										'desc'    => ' ',
										'options' => array(
											'' => __( 'Migrate Now', 'gallery-slider-for-woocommerce' ),
										),
									),
								),
							),
							array(
								'title'  => __( 'Import & Export', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-tools_imp-exp',
								'fields' => array(
									array(
										'id'       => 'wcgs_what_export',
										'type'     => 'radio',
										'class'    => 'wcgs_what_export',
										'title'    => __( 'Choose What to Export', 'gallery-slider-for-woocommerce' ),
										'multiple' => false,
										'options'  => array(
											'global_setting' => __( 'Global Settings', 'gallery-slider-for-woocommerce' ),
											'all_layouts' => __( 'All Layout(s)', 'gallery-slider-for-woocommerce' ),
											'selected_layouts' => __( 'Selected Layout(s)', 'gallery-slider-for-woocommerce' ),
										),
										'default'  => 'all_layouts',
									),
									array(
										'id'          => 'wcgs_post',
										'class'       => 'wcgs_post_ids',
										'type'        => 'select',
										'title'       => ' ',
										'options'     => 'post',
										'chosen'      => true,
										'sortable'    => false,
										'multiple'    => true,
										'placeholder' => __( 'Choose Layout(s)', 'gallery-slider-for-woocommerce' ),
										'query_args'  => array(
											'post_type' => 'wcgs_layouts',
											'posts_per_page' => -1,
										),
										'dependency'  => array( 'wcgs_what_export', '==', 'selected_layouts', true ),

									),
									array(
										'id'       => 'export',
										'class'    => 'wcgs_export',
										'type'     => 'button_set',
										'title'    => ' ',
										'subtitle' => ' ',
										'options'  => array(
											'' => __( 'Export', 'gallery-slider-for-woocommerce' ),
										),
									),

									array(
										'class'    => 'wcgs_import',
										'type'     => 'custom_import',
										'title'    => __( 'Import JSON File to Upload', 'gallery-slider-for-woocommerce' ),
										'subtitle' => ' ',
									),
								),
							),
							array(
								'title'  => __( 'Additional CSS & JS', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-tools_add-css',
								'fields' => array(
									array(
										'id'       => 'wcgs_additional_css',
										'type'     => 'code_editor',
										'title'    => __( 'Additional CSS', 'gallery-slider-for-woocommerce' ),
										'settings' => array(
											'theme' => 'default',
											'mode'  => 'css',
										),
									),
									array(
										'id'       => 'wcgs_additional_js',
										'type'     => 'code_editor',
										'title'    => __( 'Additional JS', 'gallery-slider-for-woocommerce' ),
										'settings' => array(
											'theme' => 'default',
											'mode'  => 'js',
										),
									),
								),
							),
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
									array(
										'type'      => 'notice',
										'ignore_db' => true,
										'class'     => 'upgrade-to-pro-notice',
										'content'   => (
											WCGS::shop_page_tab()
										),
									),
								),
							),
						),
					),
				),
			)
		);
	}
}
