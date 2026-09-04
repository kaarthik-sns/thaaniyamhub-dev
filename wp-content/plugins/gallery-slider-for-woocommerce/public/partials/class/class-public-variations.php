<?php
/**
 * The product variations functionality of the plugin.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * Class WCGS_Variation_Gallery
 *
 * Handles gallery image metadata for WooCommerce product variations, including generating image URLs,
 * handling video order sorting, and building JSON data for the variation gallery.
 */
class WCGS_Public_Variations {

	/**
	 * Settings array
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Helper
	 *
	 * @var mixed
	 */
	private $helper;

	/**
	 * Constructor to initialize settings.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings = array() ) {
		$this->helper   = new WCGS_Public_Helper();
		$this->settings = $settings;
	}



	/**
	 * Get variation raw.
	 *
	 * @param  object $product Product.
	 * @return array
	 */
	private function get_variation_raw( $product ) {
		$variation_ids       = $product->get_children();
		$variation_array_raw = array();
		if ( ! empty( $variation_ids ) ) {
			foreach ( $variation_ids as $k => $variation_id ) {
				$variation_array_raw[ $k ]['id']         = $variation_id;
				$variation_array_raw[ $k ]['attributes'] = wc_get_product_variation_attributes( $variation_id );
			}
		}
		// Reindex the array.
		return $variation_array_raw;
	}

	/**
	 * Generates JSON data for product variations' images.
	 *
	 * @param object $product WooCommerce product.
	 * @return array JSON data structure for variation gallery images.
	 */
	public function wcgs_json_data( $product ) {
		$gallery = array();
		if ( ! $product ) {
			return;
		}
		$product_type = $product->get_type();
		$product_id   = $product->get_id();
		$slug_attr    = apply_filters( 'sp_woo_gallery_slider_use_slug_attr', true );
		if ( 'variable' === $product_type && $slug_attr ) {
			$variation_array_raw = $this->get_variation_raw( $product );
			if ( empty( $variation_array_raw ) ) {
				return false;
			}
			$default_gallery_ids           = $product->get_gallery_image_ids();
			$feature_image_id              = $product->get_image_id();
			$settings                      = $this->settings;
			$include_feature_image         = isset( $settings['include_feature_image_to_gallery'] ) ? $settings['include_feature_image_to_gallery'] : array( 'default_gl' );
			$include_variation_and_default = false;
			$video_order                   = isset( $settings['video_order'] ) ? $settings['video_order'] : 'usual';
			if ( empty( $include_feature_image ) ) {
				$include_feature_image = array();
			}
			$gallery_arrays  = array();
			$variation_array = array();
			if ( $variation_array_raw ) {
				foreach ( $variation_array_raw as $variation_array ) {
					$gallery = array();
					if ( is_array( $include_feature_image ) && in_array( 'variable_gl', $include_feature_image, true ) && $feature_image_id ) {
						array_push( $gallery, $this->helper->wcgs_image_meta( $feature_image_id, $settings ) );
					}
					$variation = $variation_array['id'];
					$image_id  = get_post_thumbnail_id( $variation );
					if ( $image_id ) {
						array_push( $gallery, $this->helper->wcgs_image_meta( $image_id, $settings ) );
					}
					$woo_gallery_slider = get_post_meta( $variation, 'woo_gallery_slider', true );
					$gallery_arr        = ! empty( $woo_gallery_slider ) && ! is_array( $woo_gallery_slider ) && strpos( $woo_gallery_slider, ']' ) !== false ? substr( $woo_gallery_slider, 1, -1 ) : $woo_gallery_slider;
					// ! empty( $woo_gallery_slider ) && ! is_array( ! $woo_gallery_slider ) && ? substr( $woo_gallery_slider, 1, -1 ) : '';

					$gallery_multiple = ! is_array( $woo_gallery_slider ) && strpos( $gallery_arr, ',' ) ? true : false;
					$gallery_multiple = ! $gallery_multiple && is_array( $woo_gallery_slider ) && count( $woo_gallery_slider ) > 0 ? true : $gallery_multiple;
					if ( $gallery_multiple ) {
						$count         = 1;
						$gallery_array = ! is_array( $gallery_arr ) ? explode( ',', $gallery_arr ) : $gallery_arr;
						foreach ( $gallery_array as $gallery_item ) {
							if ( 2 >= $count ) {
								array_push( $gallery, $this->helper->wcgs_image_meta( $gallery_item, $settings ) );
							}
							++$count;
						}
					} else {
						$gallery_array = $gallery_arr;
						if ( $gallery_array ) {
							array_push( $gallery, $this->helper->wcgs_image_meta( $gallery_array, $settings ) );
						}
					}
					if ( $include_variation_and_default ) {
						foreach ( $default_gallery_ids as $key => $gallery_image_id ) {
							array_push( $gallery, $this->helper->wcgs_image_meta( $gallery_image_id, $settings ) );
						}
						if ( empty( $gallery ) ) {
							array_push( $gallery, $this->helper->wcgs_image_meta( $feature_image_id, $settings ) );
						}
					}
					if ( $gallery ) {
						if ( 'video_come_last' === $video_order ) {
							usort( $gallery, array( $this->helper, 'wcgs_sort_by_has_key_add_last' ) );
						}

						array_push( $gallery_arrays, array( $variation_array['attributes'], $gallery ) );
					}
				}
				$gallery         = array();
				$variation_array = array();
				if ( is_array( $include_feature_image ) && in_array( 'default_gl', $include_feature_image, true ) && $feature_image_id ) {
					array_push( $gallery, $this->helper->wcgs_image_meta( $feature_image_id, $settings ) );
				} elseif ( 'default_gl' === $include_feature_image && $feature_image_id ) {
					array_push( $gallery, $this->helper->wcgs_image_meta( $feature_image_id, $settings ) );
				}
				foreach ( $default_gallery_ids as $key => $gallery_image_id ) {
					array_push( $gallery, $this->helper->wcgs_image_meta( $gallery_image_id, $settings ) );
				}
				if ( empty( $gallery ) ) {
					array_push( $gallery, $this->helper->wcgs_image_meta( $image_id, $settings ) );
				}
				if ( $gallery ) {
					if ( 'video_come_last' === $video_order ) {
						usort( $gallery, array( $this->helper, 'wcgs_sort_by_has_key_add_last' ) );
					}

					array_push( $gallery_arrays, array( $variation_array, $gallery ) );
				}
			}
			return $gallery_arrays;
		}
	}
}
