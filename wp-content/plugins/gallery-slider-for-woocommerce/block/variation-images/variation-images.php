<?php
/**
 * The product variations block functionality of the plugin.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/block/variation-images
 */

use Automattic\WooCommerce\Admin\Features\ProductBlockEditor\BlockRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Class WCGS_Variation_Image_Block_Editor
 *
 * Handles the registration of custom variation image block,
 * integration with WooCommerce product block editor,
 * and REST API interaction for saving and retrieving variation images.
 */
class WCGS_Variation_Image_Block_Editor {

	/**
	 * Meta key used for storing variation gallery image IDs.
	 */
	const META_KEY = 'woo_gallery_slider';

	/**
	 * WCGS_Variation_Image_Block_Editor constructor.
	 *
	 * Hooks into WordPress and WooCommerce to add custom functionality.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_variation_images_block' ) );
		add_filter( 'woocommerce_block_template_area_product-form_after_add_block_product-variation-images-section', array( $this, 'inject_variation_images_block' ), 100 );
		add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'append_variation_images_to_rest_response' ), 10, 3 );
		add_action( 'woocommerce_rest_insert_product_variation_object', array( $this, 'save_variation_images_from_rest_request' ), 10, 2 );
	}

	/**
	 * Registers the custom variation images block for the product block editor.
	 */
	public function register_variation_images_block() {
		if ( isset( $_GET['page'] ) && 'wc-admin' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			BlockRegistry::get_instance()->register_block_type_from_metadata(
				WOO_GALLERY_SLIDER_PATH . 'block/variation-images/build'
			);
		}
	}

	/**
	 * Adds the custom variation images block to the product block editor.
	 *
	 * @param mixed $variation_section Variation section object from WooCommerce block editor.
	 */
	public function inject_variation_images_block( $variation_section ) {
		if ( $variation_section ) {
			$variation_section->add_block(
				array(
					'id'         => 'shapedplugin-variation-images',
					'order'      => 40,
					'blockName'  => 'shapedplugin/variation-images',
					'attributes' => array(
						'variation_images' => self::META_KEY,
					),
				)
			);
		}
	}

	/**
	 * Adds variation image data to the REST API response for product variations.
	 *
	 * @param WP_REST_Response $response REST API response object.
	 * @param WC_Product       $variation WooCommerce product variation object.
	 * @param WP_REST_Request  $request REST API request object.
	 * @return WP_REST_Response Modified REST response.
	 */
	public function append_variation_images_to_rest_response( WP_REST_Response $response, WC_Product $variation, WP_REST_Request $request ) {
		if ( ! FeaturesUtil::feature_is_enabled( 'product_block_editor' ) ) {
			return $response;
		}

		if ( $request->get_param( 'context' ) !== 'edit' ) {
			return $response;
		}

		$meta_value = get_post_meta( $variation->get_id(), self::META_KEY, true );
		$meta_value = strpos( $meta_value, ']' ) !== false ? substr( $meta_value, 1, -1 ) : $meta_value;
		if ( ! is_string( $meta_value ) || empty( $meta_value ) ) {
			$response->data[ self::META_KEY ] = array();
			return $response;
		}

		$image_ids = array_filter( explode( ',', $meta_value ), 'is_numeric' );

		$images = array_values(
			array_filter(
				array_map(
					function ( $image_id ) {
						$url = wp_get_attachment_image_url( $image_id );
						if ( ! $url ) {
								return null;
						}
						return array(
							'id'  => (int) $image_id,
							'url' => $url,
						);
					},
					$image_ids
				)
			)
		);

		$response->data[ self::META_KEY ] = $images;

		return $response;
	}

	/**
	 * Saves variation images data from the REST API request.
	 *
	 * @param WC_Product      $variation WooCommerce product variation object.
	 * @param WP_REST_Request $request REST API request object.
	 */
	public function save_variation_images_from_rest_request( WC_Product $variation, WP_REST_Request $request ) {
		if ( ! FeaturesUtil::feature_is_enabled( 'product_block_editor' ) ) {
			return;
		}

		$variation_images = $request->get_param( self::META_KEY );

		if ( is_null( $variation_images ) ) {
			update_post_meta( $variation->get_id(), self::META_KEY, '' );
			return;
		}

		$image_ids = wp_list_pluck( $variation_images, 'id' );

		$meta_value = implode( ',', array_filter( $image_ids, 'is_numeric' ) );

		update_post_meta( $variation->get_id(), self::META_KEY, $meta_value );
	}
}
new WCGS_Variation_Image_Block_Editor();
