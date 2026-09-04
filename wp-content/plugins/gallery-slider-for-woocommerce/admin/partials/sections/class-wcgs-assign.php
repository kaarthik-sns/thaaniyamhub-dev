<?php
/**
 * The Assign Gallery functionality of this plugin.
 *
 * Defines the sections of Assign Gallery.
 *
 * @package    Woo_Gallery_Slider_Pro
 * @subpackage Woo_Gallery_Slider_Pro/admin
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WCGS_Assign
 */
class WCGS_Assign {
	/**
	 * Specify the Assign Gallery for the Woo Gallery.
	 *
	 * @since    1.0.0
	 * @param string $prefix Define prefix wcgs_settings.
	 */
	public static function section( $prefix ) {
		WCGS::createSection(
			$prefix,
			array(
				'name'   => 'assign_layout',
				'title'  => __( 'Gallery', 'gallery-slider-for-woocommerce' ),
				'fields' => array(
					array(
						'type'    => 'submessage',
						'content' => 'Assign product gallery layout based on category or product. <a href="https://docs.renoproductgallery.com/guide/assign-layout" target="_blank">See Docs</a>',
					),
					array(
						'id'                     => 'assign_layout_data',
						'class'                  => 'assign_layout_data',
						'type'                   => 'group',
						'max'                    => 1,
						'button_title'           => __( 'Add New', 'gallery-slider-for-woocommerce' ),
						'accordion_title_prefix' => __( 'Assign Layout:', 'gallery-slider-for-woocommerce' ),
						'accordion_title_number' => true,
						'fields'                 => array(
							array(
								'id'      => 'assign_product_from',
								'type'    => 'select',
								'title'   => __( 'Filter Products', 'gallery-slider-for-woocommerce' ),
								'options' => array(
									'category'          => __( 'Category(s)', 'gallery-slider-for-woocommerce' ),
									'specific_products' => __( 'Specific Product(s)', 'gallery-slider-for-woocommerce' ),
								),
								'default' => 'category',
								'class'   => 'chosen',
							),
							array(
								'id'          => 'assign_product_terms',
								'class'       => 'assign_product_terms',
								'type'        => 'select',
								'title'       => __( 'Specific Category(s)', 'gallery-slider-for-woocommerce' ),
								'help'        => __( 'Choose the product category term(s).', 'gallery-slider-for-woocommerce' ),
								'options'     => 'categories',
								'query_args'  => array(
									'post_type' => 'product',
									'taxonomy'  => 'product_cat',
								),
								'chosen'      => true,
								'ajax'        => true,
								'multiple'    => true,
								'placeholder' => __( 'Choose term(s)', 'gallery-slider-for-woocommerce' ),
								'attributes'  => array(
									'style' => 'min-width: 250px;',
								),
								'dependency'  => array( 'assign_product_from', '==', 'category' ),
							),
							array(
								'id'          => 'specific_product',
								'class'       => 'wcgs_specific_product',
								'type'        => 'select',
								'title'       => __( 'Specific Product(s)', 'gallery-slider-for-woocommerce' ),
								'options'     => 'posts',
								'query_args'  => array(
									'post_type'      => 'product',
									'posts_per_page' => 10,
								),
								'ajax'        => true,
								'chosen'      => true,
								'sortable'    => false,
								'multiple'    => true,
								'placeholder' => __( 'Choose Product', 'gallery-slider-for-woocommerce' ),
								'dependency'  => array( 'assign_product_from', '==', 'specific_products' ),
							),
							array(
								'id'            => 'layout',
								'class'         => 'wcgs_layout',
								'type'          => 'select',
								'title'         => __( 'Select Layout', 'gallery-slider-for-woocommerce' ),
								'help'          => __( 'Choose the products to display. Sort the products position by drag & drop.', 'gallery-slider-for-woocommerce' ),
								'options'       => 'posts',
								'query_args'    => array(
									'post_type'      => 'wcgs_layouts',
									'posts_per_page' => '10',
								),
								'ajax'          => true,
								'multiple'      => false,
								'placeholder'   => __( 'Choose Layout', 'gallery-slider-for-woocommerce' ),
								'empty_message' => 'No product gallery layout is created. Please <a href="' . admin_url( 'post-new.php?post_type=wcgs_layouts' ) . '" target="_blank">create</a> a layout first.',
							),
						),
					),
				),
			)
		);
	}
}
