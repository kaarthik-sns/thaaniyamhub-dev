<?php
/**
 * The gallery tab functionality of this plugin.
 *
 * Defines the sections of gallery tab.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.
/**
 * WCGS Gallery class
 */
class WCGS_Gallery {
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
				'name'   => 'gallery',
				'icon'   => 'sp_wgs-icon-gallery-tab',
				'title'  => __( 'Gallery', 'gallery-slider-for-woocommerce' ),
				'fields' => array(
					array(
						'type' => 'tabbed',
						'tabs' => array(
							array(
								'title'  => __( 'Gallery Slider', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-gallery-slider-v3-01',
								'fields' => array(
									array(
										'id'         => 'autoplay',
										'type'       => 'switcher',
										'title'      => esc_html__( 'AutoPlay', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => false,
									),
									array(
										'id'         => 'autoplay_interval',
										'class'      => 'autoplay_interval',
										'type'       => 'slider',
										'title'      => __( 'AutoPlay Interval', 'gallery-slider-for-woocommerce' ),
										'default'    => 3000,
										'min'        => 0,
										'max'        => 12000,
										'step'       => 50,
										'unit'       => 'ms',
										'dependency' => array( 'autoplay|gallery_layout', '==|!=', 'true|grid', true ),
										'title_help' => sprintf(
											'<div class="wcgs-info-label">%s</div><div class="wcgs-short-content">%s</div>',
											__( 'AutoPlay Interval', 'gallery-slider-for-woocommerce' ),
											__( 'Set the autoplay delay or interval time, indicating the duration to pause between automatically cycling through slides. For example, 1000 milliseconds (ms) equal 1 second.', 'gallery-slider-for-woocommerce' )
										),
									),
									array(
										'id'         => 'autoplay_speed',
										'type'       => 'slider',
										'title'      => __( 'Slider Speed', 'gallery-slider-for-woocommerce' ),
										'default'    => 300,
										'min'        => 0,
										'max'        => 2000,
										'step'       => 50,
										'unit'       => 'ms',
										'title_help' => sprintf(
											'<div class="wcgs-info-label">%s</div><div class="wcgs-short-content">%s</div>',
											__( 'Slider Speed', 'gallery-slider-for-woocommerce' ),
											__( 'Set slider scrolling speed. For example, 1000 milliseconds (ms) equal 1 second.', 'gallery-slider-for-woocommerce' )
										),
										'dependency' => array( 'gallery_layout', '!=', 'grid', true ),
									),

									array(
										'id'         => 'slide_orientation',
										'type'       => 'select',
										'title'      => esc_html__( 'Slide Orientation', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/slide_orientation.svg" alt="' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '"></div><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/slider-orientations/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'options'    => array(
											'horizontal' => esc_html__( 'Horizontal', 'gallery-slider-for-woocommerce' ),
											'vertical'   => esc_html__( 'Vertical', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'horizontal',
									),
									array(
										'id'         => 'infinite_loop',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Infinite Loop', 'gallery-slider-for-woocommerce' ),
										'default'    => true,
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'dependency' => array( 'gallery_layout', '!=', 'hide_thumb', true ),
									),
									array(
										'id'      => 'fade_slide',
										'type'    => 'select',
										'title'   => __( 'Sliding Effect', 'gallery-slider-for-woocommerce' ),
										'options' => array(
											'slide' => __( 'Slide', 'gallery-slider-for-woocommerce' ),
											'flip'  => __( 'Flip', 'gallery-slider-for-woocommerce' ),
											'cube'  => __( 'Cube', 'gallery-slider-for-woocommerce' ),
											'fade'  => __( 'Fade (Pro)', 'gallery-slider-for-woocommerce' ),
										),
										'default' => 'slide',
									),
									array(
										'id'         => 'slider_height_type',
										'class'      => 'slider_height_type',
										'type'       => 'button_set',
										'title'      => __( 'Gallery Height', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'adaptive'  => __( 'Adaptive', 'gallery-slider-for-woocommerce' ),
											'max_image' => __( 'Max Image Height', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'max_image',
										'dependency' => array( 'gallery_layout', '!=', 'grid', true ),
									),
									array(
										'id'         => 'slider_height',
										'class'      => 'wcgs_pro_field',
										'type'       => 'dimensions_res',
										'title'      => __( 'Height', 'gallery-slider-for-woocommerce' ),
										'units'      => array( 'px' ),
										'default'    => array(
											'width'   => '500',
											'height'  => '500',
											'height2' => '500',
										),
										'dependency' => array( 'gallery_layout|slider_height_type', '!=|==', 'grid|fix_height', true ),
									),
									// array(
									// 'id'          => 'adaptive_height',
									// 'type'        => 'switcher',
									// 'title'       => esc_html__( 'Adaptive Height', 'gallery-slider-for-woocommerce' ),
									// 'title_video' => '<div class="wcgs-img-tag"><video autoplay loop muted playsinline><source src="https://plugins.svn.wordpress.org/gallery-slider-for-woocommerce/assets/visuals/adaptive-height.webm" type="video/webm"></video></div><div class="wcgs-info-label">' . __( 'Adaptive Height', 'gallery-slider-for-woocommerce' ) . '</div>',
									// 'text_on'     => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
									// 'text_off'    => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
									// 'text_width'  => 96,
									// 'default'     => false,
									// ),
									array(
										'id'         => 'accessibility',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Accessibility', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => true,
									),
									array(
										'id'         => 'slider_dir',
										'type'       => 'switcher',
										'title'      => esc_html__( 'RTL Mode', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => false,
									),
									array(
										'id'         => 'free_mode',
										'type'       => 'switcher',
										'title'      => __( 'Free Mode', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => true,
									),
									array(
										'id'         => 'mouse_wheel',
										'type'       => 'switcher',
										'title'      => __( 'Mouse Wheel', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => false,
									),
									array(
										'type'      => 'notice',
										'ignore_db' => true,
										'class'     => 'upgrade-to-pro-notice',
										'content'   => (
											WCGS::product_gallery_tab()
										),
									),
								),
							),
							array(
								'title'  => __( 'Navigation & Pagination', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-nav-n-pag-v',
								'fields' => array(
									array(
										'id'         => 'navigation',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Slider Navigation', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/slider-navigation.svg" alt="' . __( 'Slider Navigation', 'gallery-slider-for-woocommerce' ) . '"></div><div class="wcgs-info-label">' . __( 'Slider Navigation', 'gallery-slider-for-woocommerce' ) . '</div>',
										'text_on'    => esc_html__( 'Show', 'gallery-slider-for-woocommerce' ),
										'text_off'   => esc_html__( 'Hide', 'gallery-slider-for-woocommerce' ),
										'text_width' => 80,
										'default'    => true,
									),
									array(
										'id'         => 'navigation_icon',
										'type'       => 'button_set',
										'class'      => 'btn_icon',
										'title'      => esc_html__( 'Navigation Icon Style', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'angle'        => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-1"></i>',
											),
											'chevron'      => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-big"></i>',
											),
											'right_open'   => array(
												'option_name' => '<i class="sp_wgs-icon-right-open"></i>',
											),
											'right_open_1' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-right"></i>',
											),
											'right_open_3' => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-3"></i>',
											),
											'right_open_outline' => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-outline"></i>',
											),
											'angle_double' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-double-right"></i>',
											),
											'chevron_circle' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-circled-right"></i>',
											),
											'arrow'        => array(
												'option_name' => '<i class="sp_wgs-icon-right-big"></i>',
											),
											'right_outline' => array(
												'option_name' => '<i class="sp_wgs-icon-right-outline"></i>',
											),
											// 'long_arrow' => array(
											// 'option_name' => '<i class="sp_wgs-icon-left-thin"></i>',
											// 'pro_only' => true,
											// ),
											// 'arrow_circle' => array(
											// 'option_name' => '<i class="sp_wgs-icon-left-circled"></i>',
											// 'pro_only' => true,
											// ),
											// 'arrow_circle_o' => array(
											// 'option_name' => '<i class="sp_wgs-icon-left-circled-1"></i>',
											// 'pro_only' => true,
											// ),
										),
										'default'    => 'angle',
										'dependency' => array( 'navigation', '==', true ),
									),
									array(
										'id'         => 'navigation_position',
										'class'      => 'shop_video_icon_position',
										'type'       => 'image_select',
										'title'      => __( 'Navigation Position', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'center_center' => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/arrow-position/center-center.svg',
												'option_name' => __( 'Center Center', 'gallery-slider-for-woocommerce' ),
											),
											'bottom_left'  => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/arrow-position/bottom-left.svg',
												'option_name' => __( 'Bottom Left', 'gallery-slider-for-woocommerce' ),
											),
											'bottom_right' => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/arrow-position/bottom-right.svg',
												'option_name' => __( 'Bottom Right', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'bottom_center' => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/arrow-position/bottom-center.svg',
												'option_name' => __( 'Bottom Center', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'default'    => 'center_center',
										'dependency' => array( 'navigation', '==', 'true', true ),
									),
									array(
										'id'         => 'navigation_icon_size',
										'type'       => 'spinner',
										'title'      => esc_html__( 'Navigation Icon Size', 'gallery-slider-for-woocommerce' ),
										'dependency' => array( 'navigation', '==', true ),
										'default'    => 16,
										'unit'       => 'px',
									),
									array(
										'id'         => 'navigation_icon_color_group',
										'type'       => 'color_group',
										'title'      => esc_html__( 'Navigation Color', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'color'       => esc_html__( 'Color', 'gallery-slider-for-woocommerce' ),
											'hover_color' => esc_html__( 'Hover Color', 'gallery-slider-for-woocommerce' ),
											'bg_color'    => esc_html__( 'Background', 'gallery-slider-for-woocommerce' ),
											'hover_bg_color' => esc_html__( 'Hover Background', 'gallery-slider-for-woocommerce' ),
										),
										'dependency' => array( 'navigation', '==', true ),
										'default'    => array(
											'color'       => '#fff',
											'hover_color' => '#fff',
											'bg_color'    => 'rgba(0, 0, 0, .5)',
											'hover_bg_color' => 'rgba(0, 0, 0, .85)',
										),
									),
									array(
										'id'         => 'navigation_icon_radius',
										'type'       => 'spinner',
										'title'      => __( 'Navigation Background Radius', 'gallery-slider-for-woocommerce' ),
										'dependency' => array( 'navigation', '==', true ),
										'min'        => 0,
										'default'    => 0,
										'unit'       => 'px',
									),
									array(
										'id'         => 'navigation_visibility',
										'type'       => 'select',
										'title'      => esc_html__( 'Navigation Visibility', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'always' => esc_html__( 'Always', 'gallery-slider-for-woocommerce' ),
											'hover'  => esc_html__( 'On hover', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'hover',
										'dependency' => array( 'navigation', '==', true ),
									),
									array(
										'type'    => 'subheading',
										'content' => __( 'Pagination', 'gallery-slider-for-woocommerce' ),
									),
									array(
										'id'         => 'pagination',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Slider Pagination', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/slider-pagination.svg" alt="' . __( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ) . '"></div> <div class="wcgs-info-label">' . __( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ) . '</div>',
										'text_on'    => esc_html__( 'Show', 'gallery-slider-for-woocommerce' ),
										'text_off'   => esc_html__( 'Hide', 'gallery-slider-for-woocommerce' ),
										'text_width' => 80,
										'default'    => false,
									),
									array(
										'id'         => 'pagination_type',
										'class'      => 'pagination_type pro_desc',
										'type'       => 'image_select',
										'title'      => __( 'Pagination Style', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'bullets'     => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/bullets.svg',
												'option_name' => __( 'Bullets', 'gallery-slider-for-woocommerce' ),
											),
											'dynamic'     => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/dynamic.svg',
												'option_name' => __( 'Dynamic', 'gallery-slider-for-woocommerce' ),
											),
											'strokes'     => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/strokes.svg',
												'option_name' => __( 'Strokes', 'gallery-slider-for-woocommerce' ),
											),
											'numbers'     => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/numbers.svg',
												'option_name' => __( 'Numbers', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'progressbar' => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/progress-bar.svg',
												'option_name' => __( 'Progress Bar', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'dot-hopper'  => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/dot-hopper.svg',
												'option_name' => __( 'Dot Hopper', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'expanding-dot' => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/expanding-dot.svg',
												'option_name' => __( 'Expanding Dot', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'fraction'    => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/flexing.svg',
												'option_name' => __( 'Flexing Fraction', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'default'    => 'bullets',
										'dependency' => array( 'pagination', '==', true ),
									),
									array(
										'id'         => 'pagination_icon_color_group',
										'type'       => 'color_group',
										'title'      => esc_html__( 'Pagination Color', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'color'        => esc_html__( 'Color', 'gallery-slider-for-woocommerce' ),
											'active_color' => esc_html__( 'Active Color', 'gallery-slider-for-woocommerce' ),
										),
										'dependency' => array( 'pagination', '==', true ),
										'default'    => array(
											'color'        => 'rgba(115, 119, 121, 0.5)',
											'active_color' => 'rgba(115, 119, 121, 0.8)',
										),
									),
									array(
										'id'         => 'pagination_visibility',
										'type'       => 'select',
										'title'      => esc_html__( 'Pagination Visibility', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'always' => esc_html__( 'Always', 'gallery-slider-for-woocommerce' ),
											'hover'  => esc_html__( 'On hover', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'always',
										'dependency' => array( 'pagination', '==', true ),
									),
								),
							),
							array(
								'title'  => __( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-th-nav-01',
								'fields' => array(
									array(
										'id'         => 'thumbnailnavigation',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/thumbnails-navigation.svg" alt="' . __( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ) . '"></div><div class="wcgs-info-label">' . __( 'Thumbnails Navigation', 'gallery-slider-for-woocommerce' ) . '</div>',
										'text_on'    => esc_html__( 'Show', 'gallery-slider-for-woocommerce' ),
										'text_off'   => esc_html__( 'Hide', 'gallery-slider-for-woocommerce' ),
										'text_width' => 80,
										'default'    => true,
									),
									array(
										'id'         => 'thumb_nav_visibility',
										'type'       => 'select',
										'title'      => esc_html__( 'Thumbnails Navigation Visibility', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'always' => esc_html__( 'Always', 'gallery-slider-for-woocommerce' ),
											'hover'  => esc_html__( 'On hover', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'hover',
										'dependency' => array( 'thumbnailnavigation', '==', 'true', true ),
									),
									array(
										'id'         => 'thumbnailnavigation_style',
										'class'      => 'thumbnailnavigation_style',
										'type'       => 'image_select',
										'title_help' => '<div class="wcgs-info-label">' . __( 'Thumbnails Navigation Style', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'Stylize your thumbnail navigation using Inner, Outer, and Custom design.', 'gallery-slider-for-woocommerce' ) . '</div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/thumbnails-settings#how-to-customize-thumbnails-navigation" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/thumbnails-navigation-styles/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'title'      => __( 'Thumbnails Navigation Style', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'custom'      => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/custom.svg',
												'option_name' => __( 'Custom', 'gallery-slider-for-woocommerce' ),
											),
											'inner_right' => array(
												'image' => plugin_dir_url( __DIR__ ) . '../img/thumbs_navigation_right.svg',
												'option_name' => __( 'Inner Right', 'gallery-slider-for-woocommerce' ),
											),
											'outer'       => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/outer.svg',
												'option_name' => __( 'Outer', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'inner'       => array(
												'image'    => plugin_dir_url( __DIR__ ) . '../img/inner.svg',
												'option_name' => __( 'Inner', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),

										),
										'default'    => 'custom',
										'dependency' => array( 'thumbnailnavigation', '==', 'true', true ),
									),
									array(
										'id'         => 'thumbnailnavigation_icon',
										'class'      => 'btn_icon',
										'type'       => 'button_set',
										'title'      => esc_html__( 'Thumbnail Navigation Icon', 'gallery-slider-for-woocommerce' ),
										'dependency' => array( 'thumbnailnavigation', '==', 'true', true ),
										'options'    => array(
											'angle'        => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-1"></i>',
											),
											'chevron'      => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-big"></i>',
											),
											'right_open'   => array(
												'option_name' => '<i class="sp_wgs-icon-right-open"></i>',
											),
											'right_open_1' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-right"></i>',
											),
											'right_open_3' => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-3"></i>',
											),
											'right_open_outline' => array(
												'option_name' => '<i class="sp_wgs-icon-right-open-outline"></i>',
											),
											'angle_double' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-double-right"></i>',
											),
											'chevron_circle' => array(
												'option_name' => '<i class="sp_wgs-icon-angle-circled-right"></i>',
											),
											'arrow'        => array(
												'option_name' => '<i class="sp_wgs-icon-right-big"></i>',
											),
											'right_outline' => array(
												'option_name' => '<i class="sp_wgs-icon-right-outline"></i>',
											),
										),
										'default'    => 'angle',
									),
									array(
										'id'         => 'thumbnailnavigation_icon_size',
										'type'       => 'spinner',
										'unit'       => 'px',
										'title'      => esc_html__( 'Thumbnail Navigation Icon Size', 'gallery-slider-for-woocommerce' ),
										'default'    => 12,
										'dependency' => array( 'thumbnailnavigation', '==', 'true', true ),
									),
									array(
										'id'         => 'thumbnailnavigation_icon_color_group',
										'type'       => 'color_group',
										'title'      => esc_html__( 'Thumbnail Navigation Color', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'color'       => esc_html__( 'Color', 'gallery-slider-for-woocommerce' ),
											'hover_color' => esc_html__( 'Hover Color', 'gallery-slider-for-woocommerce' ),
											'bg_color'    => esc_html__( 'Background', 'gallery-slider-for-woocommerce' ),
											'hover_bg_color' => esc_html__( 'Hover Background', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => array(
											'color'       => '#fff',
											'hover_color' => '#fff',
											'bg_color'    => 'rgba(0, 0, 0, 0.5)',
											'hover_bg_color' => 'rgba(0, 0, 0, 0.8)',
										),
										'dependency' => array( 'thumbnailnavigation', '==', 'true', true ),
									),
								),
							),
							array(
								'title'  => __( 'Product Image', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-product-image-v2-01',
								'fields' => array(
									array(
										'id'      => 'image_sizes',
										'type'    => 'image_sizes',
										'title'   => esc_html__( 'Image Size', 'gallery-slider-for-woocommerce' ),
										'default' => 'woocommerce_single',
									),
									// array(
									// 'id'         => 'product_img_crop_size',
									// 'type'       => 'dimensions',
									// 'class'      => 'pro_only_field',
									// 'title'      => __( 'Custom Size', 'gallery-slider-for-woocommerce' ),
									// 'units'      => array(
									// 'Soft-crop (Pro)',
									// 'Hard-crop (Pro)',
									// ),
									// 'default'    => array(
									// 'width'  => '600',
									// 'height' => '400',
									// 'unit'   => 'Soft-crop',
									// ),
									// 'attributes' => array(
									// 'min' => 0,
									// ),
									// 'dependency' => array( 'image_sizes', '==', 'custom' ),
									// ),
									// array(
									// 'id'         => 'product_image_load_2x',
									// 'type'       => 'switcher',
									// 'class'      => 'pro_switcher wcgs_show_hide',
									// 'title'      => __( 'Load 2x Resolution Image in Retina Display', 'gallery-slider-for-woocommerce' ),
									// 'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
									// 'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
									// 'text_width' => 96,
									// 'default'    => false,
									// 'dependency' => array( 'image_sizes', '==', 'custom' ),
									// ),
									array(
										'id'      => 'wcgs_image_lazy_load',
										'type'    => 'button_set',
										'title'   => __( 'Lazy Load', 'gallery-slider-for-woocommerce' ),
										'options' => array(
											'false'    => __( 'Off', 'gallery-slider-for-woocommerce' ),
											'ondemand' => __( 'On Demand', 'gallery-slider-for-woocommerce' ),
										),
										'radio'   => true,
										'default' => 'ondemand',
									),
									array(
										'id'         => 'preloader',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Preloader', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => true,
									),
									array(
										'id'         => 'preloader_style',
										'type'       => 'select',
										'title'      => __( 'Preload Style', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'normal' => __( 'Normal', 'gallery-slider-for-woocommerce' ),
											'fade'   => __( 'Fade', 'gallery-slider-for-woocommerce' ),
											'gray'   => __( 'Gray', 'gallery-slider-for-woocommerce' ),
											'blur'   => __( 'Blur', 'gallery-slider-for-woocommerce' ),
										),
										'default'    => 'normal',
										'dependency' => array( 'preloader', '==', 'true' ),
									),
									array(
										'id'      => 'image_border_radius',
										'type'    => 'spinner',
										'title'   => __( 'Border Radius', 'gallery-slider-for-woocommerce' ),
										'default' => 0,
										'unit'    => 'px',
									),
									array(
										'id'      => 'grayscale',
										'type'    => 'select',
										'title'   => esc_html__( 'Image Mode', 'gallery-slider-for-woocommerce' ),
										'options' => array(
											'gray_off'     => esc_html__( 'Original', 'gallery-slider-for-woocommerce' ),
											'gray_always'  => esc_html__( 'Grayscale', 'gallery-slider-for-woocommerce' ),
											'gray_onhover' => esc_html__( 'Grayscale on hover', 'gallery-slider-for-woocommerce' ),
										),
										'default' => 'gray_off',
									),
								),
							),
							array(
								'title'  => __( 'Product Image Zoom', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-product-zoom-v3-01',
								'fields' => array(
									array(
										'id'         => 'zoom',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Enable Image Zoom', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => true,
									),
									array(
										'id'         => 'zoom_type',
										'class'      => 'zoom_type',
										'type'       => 'image_select',
										'title'      => __( 'Zoom Style', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'in_side'    => array(
												'image' => plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/zoom_default.svg',
												'option_name' => __( 'Default', 'gallery-slider-for-woocommerce' ),
											),
											'zoom_2x'    => array(
												'image' => plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/zoom_style_inside.svg',
												'option_name' => __( 'Inside (2x)', 'gallery-slider-for-woocommerce' ),
											),
											'right_side' => array(
												'image'    => plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/zoom_style_right_side.svg',
												'option_name' => __( 'Right Side', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
											'lens'       => array(
												'image'    => plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/zoom_style_magnify.svg',
												'option_name' => __( 'Magnific', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'title_help' => '<div class="wcgs-info-label">' . __( 'Zoom Style', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'This option indicates the visual or interactive approach used to magnify or enlarge product thumbnails.', 'gallery-slider-for-woocommerce' ) . '</div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/image-zoom-settings#how-to-apply-zoom-styles-for-product-images" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/zoom-styles/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'default'    => 'in_side',
										'dependency' => array( 'zoom', '==', 'true', true ),
									),
									array(
										'id'         => 'zoom_level',
										'type'       => 'slider',
										'class'      => 'pro_slider',
										'title'      => __( 'Zoom Scale', 'gallery-slider-for-woocommerce' ),
										'default'    => '2',
										'min'        => 1,
										'max'        => 10,
										'step'       => 0.1,
										'unit'       => 'x',
										'dependency' => array( 'zoom|zoom_type', '==|any', 'true|right_side,lens', true ),
									),
									array(
										'id'         => 'cursor_type',
										'type'       => 'button_set',
										'class'      => 'btn_icon pro_button_set',
										'title'      => __( 'Cursor Type', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'pointer'   => array(
												'option_name' => '<i class="sp_wgs-icon-hand-pointer-o"></i>',
											),
											'default'   => array(
												'option_name' => '<i class="sp_wgs-icon-mouse-pointer"></i>',
												'pro_only' => true,
											),
											'crosshair' => array(
												'option_name' => '<i class="sp_wgs-icon-plus-1"></i>',
												'pro_only' => true,
											),
											'zoom-in'   => array(
												'option_name' => '<i class="sp_wgs-icon-zoom-in-1"></i>',
												'pro_only' => true,
											),
										),
										'default'    => 'pointer',
										'dependency' => array( 'zoom|zoom_type', '==|any', 'true|right_side,lens', true ),
									),
									array(
										'id'         => 'lens_shape',
										'type'       => 'button_set',
										'class'      => 'pro_button_set',
										'title'      => __( 'Lens Shape', 'gallery-slider-for-woocommerce' ),

										'title_help' => '<div class="wcgs-info-label">' . __( 'Lens Shape', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'Choose a source from where you want to display the gallery images.', 'gallery-slider-for-woocommerce' ) . '</div>',
										'options'    => array(
											'circle' => array(
												'option_name' => __( 'Circle', 'gallery-slider-for-woocommerce' ),
											),
											'box'    => array(
												'option_name' => __( 'Box', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'radio'      => true,
										'default'    => 'circle',
										'dependency' => array( 'zoom|zoom_type', '==|==', 'true|lens', true ),
									),
									array(
										'id'         => 'lens_color',
										'type'       => 'color',
										'class'      => 'pro_color',
										'title'      => __( 'Lens Color', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/lens_color.svg" alt=""></div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/image-zoom-settings#how-to-choose-the-lens-color" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/lens-color/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'default'    => 'transparent',
										'dependency' => array( 'zoom|zoom_type', '==|==', 'true|right_side', true ),
									),
									array(
										'id'         => 'lens_border',
										'type'       => 'border',
										'class'      => 'pro_border',
										'title'      => __( 'Lens Border', 'gallery-slider-for-woocommerce' ),
										'color'      => true,
										'style'      => true,
										'all'        => true,
										'radius'     => false,
										'color2'     => false,
										'color3'     => false,
										'default'    => array(
											'color' => '#dddddd',
											'style' => 'solid',
											'all'   => 1,
										),
										'dependency' => array( 'zoom|zoom_type', '==|any', 'true|lens,right_side', true ),
									),
									array(
										'id'         => 'product_image_overlay',
										'type'       => 'button_set',
										'class'      => 'pro_button_set',
										'title'      => __( 'Product Image Overlay on Hover', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"><img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/product-image-overlay-hover.svg" alt=""></div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/image-zoom-settings#how-to-choose-product-image-overlay-style-on-hover" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/product-image-overlay-on-hover/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'options'    => array(
											'blur'         => array(
												'option_name' => __( 'Blur', 'gallery-slider-for-woocommerce' ),
											),
											'custom_color' => array(
												'option_name' => __( 'Custom Color', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'radio'      => true,
										'default'    => 'blur',
										'dependency' => array( 'zoom|zoom_type', '==|any', 'true|lens,right_side', true ),
									),
									array(
										'id'         => 'overlay_color',
										'type'       => 'color',
										'class'      => 'pro_color',
										'title'      => __( 'Image Overlay Color', 'gallery-slider-for-woocommerce' ),
										'default'    => '#fff',
										'dependency' => array( 'zoom|zoom_type|product_image_overlay', '==|any|==', 'true|lens,right_side|custom_color', true ),
									),
									array(
										'id'         => 'lens_opacity',
										'type'       => 'slider',
										'class'      => 'pro_slider',
										'title'      => __( 'Image Overlay opacity', 'gallery-slider-for-woocommerce' ),
										'default'    => '0.5',
										'min'        => 0,
										'max'        => 1,
										'step'       => .1,
										'unit'       => '',
										'dependency' => array( 'zoom|zoom_type|product_image_overlay', '==|any|==', 'true|lens,right_side|custom_color', true ),
									),
									array(
										'id'         => 'zoom_size_type',
										'type'       => 'button_set',
										'class'      => 'pro_button_set',
										'title'      => __( 'Zoom Window Size Type', 'gallery-slider-for-woocommerce' ),
										'options'    => array(
											'auto'   => array(
												'option_name' => __( 'Auto', 'gallery-slider-for-woocommerce' ),
											),
											'custom' => array(
												'option_name' => __( 'Custom', 'gallery-slider-for-woocommerce' ),
												'pro_only' => true,
											),
										),
										'radio'      => true,
										'default'    => 'auto',
										'dependency' => array( 'zoom|zoom_type', '==|==', 'true|right_side' ),
									),
									array(
										'id'          => 'zoom_size',
										'type'        => 'pro_dimensions',
										'title'       => __( 'Zoom Window Size', 'gallery-slider-for-woocommerce' ),
										'width_text'  => __( 'Width', 'gallery-slider-for-woocommerce' ),
										'height_text' => __( 'Height', 'gallery-slider-for-woocommerce' ),
										'title_help'  => '<div class="wcgs-info-label">' . __( 'Zoom Window Size', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'Adjust the zoom window size as per your need.', 'gallery-slider-for-woocommerce' ) . '</div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/image-zoom-settings#how-to-adjust-the-zoom-window-size-for-product-images" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/zoom-window-size/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'units'       => array( 'px' ),
										'default'     => array(
											'width'  => '400',
											'height' => '500',
										),
										'attributes'  => array(
											'min' => 0,
										),
										'dependency'  => array( 'zoom|zoom_type|zoom_size_type', '==|==|==', 'true|right_side|custom' ),
									),
									array(
										'id'         => 'zoom_window_distance',
										'type'       => 'slider',
										'class'      => 'pro_only_field pro_slider',
										'title'      => __( 'Zoom Window Distance', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-img-tag"> <img src="' . plugin_dir_url( __DIR__ ) . '/shapedplugin-framework/assets/images/help-visuals/zoom-window-distance.svg" alt=""></div><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/zoom-window-distance/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'default'    => 10,
										'unit'       => 'px',
										'dependency' => array( 'zoom|zoom_type', '==|==', 'true|right_side' ),
									),
									array(
										'id'         => 'mobile_zoom',
										'type'       => 'switcher',
										'title'      => esc_html__( 'Use Zoom for Mobile Devices', 'gallery-slider-for-woocommerce' ),
										'text_on'    => __( 'Enabled', 'gallery-slider-for-woocommerce' ),
										'text_off'   => __( 'Disabled', 'gallery-slider-for-woocommerce' ),
										'text_width' => 96,
										'default'    => false,
										'dependency' => array( 'zoom', '==', true ),
									),
									// array(
									// 'id'          => 'exclude_zoom_by_products',
									// 'class'       => 'exclude_zoom pro_only_field',
									// 'type'        => 'select',
									// 'title'       => __( 'Exclude Zoom by Product(s)', 'gallery-slider-for-woocommerce' ),
									// 'placeholder' => __( 'Select Products (Pro)', 'gallery-slider-for-woocommerce' ),
									// 'options'     => array(
									// '' => '',
									// ),
									// 'dependency'  => array( 'zoom|exclude_zoom_by_products_type', '==|==', 'true|from_product' ),
									// ),
									array(
										'id'          => 'exclude_zoom_by_category',
										'class'       => 'exclude_zoom pro_only_field',
										'type'        => 'select',
										'placeholder' => __( 'Select Categories (Pro)', 'gallery-slider-for-woocommerce' ),
										'title'       => __( 'Exclude Zoom by Category(s)', 'gallery-slider-for-woocommerce' ),
										'options'     => array(
											'' => '',
										),
										'dependency'  => array( 'zoom|exclude_zoom_by_products_type', '==|==', 'true|from_category' ),
									),
									array(
										'type'      => 'notice',
										'ignore_db' => true,
										'class'     => 'upgrade-to-pro-notice',
										'content'   => (
											WCGS::image_zoom_tab()
										),
									),
								),
							),
							array(
								'title'  => __( 'Product Video Gallery', 'gallery-slider-for-woocommerce' ),
								'icon'   => 'sp_wgs-icon-video-gallery-01',
								'fields' => array(
									array(
										'id'         => 'video_popup_place',
										'type'       => 'button_set',
										'title'      => __( 'Video Play Mode', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-info-label">' . __( 'Video Play Mode', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'This option refers to the specific behavior or settings related to how a video is played.', 'gallery-slider-for-woocommerce' ) . '</div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/adding-videos#how-to-set-video-play-mode-placement-related-videos" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/play-modes/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'options'    => array(
											'inline' => __( 'Inline', 'gallery-slider-for-woocommerce' ),
											'popup'  => __( 'Popup', 'gallery-slider-for-woocommerce' ),
										),
										'radio'      => true,
										'default'    => 'popup',
									),
									array(
										'id'      => 'video_icon',
										'class'   => 'shop_video_icon',
										'type'    => 'button_set',
										'title'   => __( 'Video Icon Style', 'gallery-slider-for-woocommerce' ),
										'options' => array(
											'play-01' => array(
												'option_name' => '<i class="sp_wgs-icon-play-01"></i>',
											),
											'play-02' => array(
												'option_name' => '<i class="sp_wgs-icon-play-02"></i>',
											),
											'play-03' => array(
												'option_name' => '<i class="sp_wgs-icon-play-03"></i>',
											),
											'play-04' => array(
												'option_name' => '<i class="sp_wgs-icon-play-04"></i>',
											),
											'play-05' => array(
												'option_name' => '<i class="sp_wgs-icon-play-05"></i>',
											),
											'play-06' => array(
												'option_name' => '<i class="sp_wgs-icon-play-06"></i>',
											),
											'play-07' => array(
												'option_name' => '<i class="sp_wgs-icon-play-07"></i>',
											),
											'play-08' => array(
												'option_name' => '<i class="sp_wgs-icon-play-08"></i>',
											),
											'play-09' => array(
												'option_name' => '<i class="sp_wgs-icon-play-09"></i>',
											),
										),
										'default' => 'play-01',
									),
									// array(
									// 'id'      => 'video_volume',
									// 'min'     => 0,
									// 'max'     => 1,
									// 'step'    => 0.1,
									// 'default' => 0.5,
									// 'type'    => 'slider',
									// 'class'   => 'pro_slider',
									// 'title'   => __( 'Video Volume', 'gallery-slider-for-woocommerce' ),
									// ),
									array(
										'id'         => 'video_order',
										'type'       => 'select',
										'title'      => __( 'Place of the Videos in Gallery Slider', 'gallery-slider-for-woocommerce' ),
										'title_help' => '<div class="wcgs-info-label">' . __( 'Place of the Videos in Gallery Slider', 'gallery-slider-for-woocommerce' ) . '</div><div class="wcgs-short-content">' . __( 'Determine where and when you want to display the video.', 'gallery-slider-for-woocommerce' ) . '</div><a class="wcgs-open-docs" href="https://docs.renoproductgallery.com/guide/how-to/adding-videos#how-to-set-video-play-mode-placement-related-videos" target="_blank">' . __( 'Open Docs', 'gallery-slider-for-woocommerce' ) . '</a><a class="wcgs-open-live-demo" href="https://demo.renoproductgallery.com/video-placement/" target="_blank">' . __( 'Live Demo', 'gallery-slider-for-woocommerce' ) . '</a>',
										'options'    => array(
											'usual' => __( 'Keep Videos in Default Position', 'gallery-slider-for-woocommerce' ),
											'video_come_last' => __( 'End of the Slider', 'gallery-slider-for-woocommerce' ),
											'video_come_first' => __( 'At Starting of the Slider (Pro)', 'gallery-slider-for-woocommerce' ),
										),
										'radio'      => true,
										'default'    => 'usual',
									),
									array(
										'type'      => 'notice',
										'ignore_db' => true,
										'class'     => 'upgrade-to-pro-notice',
										'content'   => (
											WCGS::video_gallery_tab()
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
