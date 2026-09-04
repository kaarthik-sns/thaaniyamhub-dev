<?php
/**
 * The Helper class for Public functionality.
 *
 * @package    Woo_Gallery_Slider_pro
 * @subpackage Woo_Gallery_Slider_pro/public
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * Helper class for Public functionality.
 *
 * This class contains methods for handling frontend operations.
 *
 * @since 3.0.4
 */
class WCGS_Public_Helper {


	/**
	 * Assigns layout based on product and category settings
	 *
	 * @param  object $product Product.
	 * @return array Array of assigned layouts.
	 */
	public function get_assigns_layout( $product ) {
		$current_product_id = $product->get_id();
		// Retrieve assigns data with a default empty array.
		$assigns_data = get_option( 'wcgs_assigns', array() );

		// return if no assign layout data exists.
		$assign_layout_data = ! empty( $assigns_data['assign_layout_data'] ) ? $assigns_data['assign_layout_data'] : array();

		if ( empty( $assign_layout_data ) ) {
			return;
		}

		// Get current product and its categories.
		$product_categories = wp_get_post_terms( $current_product_id, 'product_cat' );

		// Extract category IDs, handling potential errors.
		$category_ids = is_wp_error( $product_categories ) || empty( $product_categories )
		? array() : wp_list_pluck( $product_categories, 'term_id' );

		// Find assigned layouts.
		$assigned_layout = $this->find_assigned_layouts( $assign_layout_data, $current_product_id, $category_ids );

		return $assigned_layout;
	}
	/**
	 * Minify output
	 *
	 * @param  string $html output.
	 * @return statement
	 */
	public function minify_output( $html ) {
		$html = preg_replace( '/<!--(?!s*(?:[if [^]]+]|!|>))(?:(?!-->).)*-->/s', '', $html );
		$html = str_replace( array( "\r\n", "\r", "\n", "\t" ), '', $html );
		while ( stristr( $html, '  ' ) ) {
			$html = str_replace( '  ', ' ', $html );
		}
		return $html;
	}
	/**
	 * Find assigned layouts based on product and category criteria.
	 *
	 * @param array $assign_layout_data Layout assignment data.
	 * @param int   $current_product_id Current product ID.
	 * @param array $category_ids Current product's category IDs.
	 * @return array Assigned layout IDs
	 */
	public function find_assigned_layouts( $assign_layout_data, $current_product_id, $category_ids ) {
		$assigned_layouts = array();

		foreach ( $assign_layout_data as $assign_layout ) {
			$assign_product_from = $assign_layout['assign_product_from'] ?? '';
			$layout              = ! empty( $assign_layout['layout'] ) ? $assign_layout['layout'] : '';

			// Skip if no valid layout or assignment method.
			if ( empty( $layout ) || empty( $assign_product_from ) ) {
				continue;
			}
			// Check specific products.
			if ( 'specific_products' === $assign_product_from ) {
				$specific_products = $assign_layout['specific_product'] ?? array();
				if ( in_array( $current_product_id, $specific_products ) ) {
					$assigned_layouts[] = $layout;
				}
			} elseif ( 'category' === $assign_product_from ) {
				// Check product categories.
				$assign_product_terms = $assign_layout['assign_product_terms'] ?? array();
				$common_cat_ids       = array_intersect( $category_ids, $assign_product_terms );
				if ( ! empty( $common_cat_ids ) ) {
					$assigned_layouts[] = $layout;
				}
			}
		}
		return $assigned_layouts;
	}

	/**
	 * Recursively merges user-defined arguments with default values.
	 *
	 * @param mixed $args User-defined arguments. Can be an array or a string.
	 * @param mixed $defaults Default values. Can be an array or a string.
	 * @return array The merged array of user-defined arguments and default values.
	 */
	public function wp_parse_args_recursive( $args, $defaults ) {
		// Merge the arrays recursively.
		$parsed_args = $defaults;

		foreach ( $defaults as $key => $default ) {
			// If both the default and the argument value are arrays, merge them recursively.
			if ( isset( $defaults[ $key ] ) && is_array( $default ) && isset( $args[ $key ] ) ) {
				$parsed_args[ $key ] = $this->wp_parse_args_recursive( $args[ $key ], $default );
			} else {
				// Otherwise, override the default with the argument value.
				$parsed_args[ $key ] = isset( $args[ $key ] ) ? $args[ $key ] : $default;
			}
		}

		return $parsed_args;
	}

	/**
	 * Comparison function to sort based on any other key's presence within the inner arrays.
	 *
	 * @param  array $a array.
	 * @param  array $b array.
	 * @return array
	 */
	public function wcgs_sort_by_has_key_add_last( $a, $b ) {
		// Ensure both values are arrays to prevent TypeError.
		$a = is_array( $a ) ? $a : array();
		$b = is_array( $b ) ? $b : array();

		$has_key_a = array_key_exists( 'video', $a );
		$has_key_b = array_key_exists( 'video', $b );

		if ( $has_key_a && ! $has_key_b ) {
			return 1; // $a comes after $b
		} elseif ( ! $has_key_a && $has_key_b ) {
			return -1; // $a comes before $b
		} else {
			return 0; // $a and $b are equal
		}
	}

	/**
	 * Custom set transient
	 *
	 * @param  mixed $cache_key Key.
	 * @param  mixed $data json data.
	 * @param  mixed $time expiration time.
	 * @return void
	 */
	public function spwg_set_transient( $cache_key, $data, $time ) {
		if ( ! is_admin() ) {
			if ( is_multisite() ) {
				set_site_transient( 'site_' . get_current_blog_id() . $cache_key, $data, $time );
			} else {
				set_transient( $cache_key, $data, $time );
			}
		}
	}

	/**
	 * Custom set transient
	 *
	 * @param  mixed $cache_key Key.
	 * @return json
	 */
	public function spwg_get_transient( $cache_key ) {
		$cached_data = '';
		if ( is_multisite() ) {
			$cached_data = get_site_transient( 'site_' . get_current_blog_id() . $cache_key );
		} else {
			$cached_data = get_transient( $cache_key );
		}
		return $cached_data;
	}

	/**
	 * Image meta data.
	 *
	 * @param  int   $image_id image.
	 * @param  array $settings settings.
	 * @return array
	 */
	public function wcgs_image_meta( $image_id, $settings ) {
		if ( ! $image_id ) {
			return null;
		}
		$image_size    = isset( $settings['image_sizes'] ) ? $settings['image_sizes'] : 'woocommerce_single';
		$thumb_size    = isset( $settings['thumbnails_sizes'] ) ? $settings['thumbnails_sizes'] : 'thumbnail';
		$image_url     = wp_get_attachment_url( $image_id );
		$image_caption = wp_get_attachment_caption( $image_id );
		$image_alt     = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		// Thumb crop size.
		$thumb_width    = isset( $settings['thumb_crop_size']['width'] ) ? $settings['thumb_crop_size']['width'] : '';
		$image_full_src = wp_get_attachment_image_src( $image_id, 'full' );
		$sized_thumb    = wp_get_attachment_image_src( $image_id, $thumb_size );
		$sized_image    = wp_get_attachment_image_src( $image_id, $image_size );
		$video_url      = get_post_meta( $image_id, 'wcgs_video', true );
		if ( ! empty( $image_url ) ) {
				$result = array(
					'url'         => $sized_image[0],
					'full_url'    => $image_url,
					'thumb_url'   => ! empty( $sized_thumb[0] ) && $sized_thumb[0] ? $sized_thumb[0] : '',
					'cap'         => isset( $image_caption ) && ! empty( $image_caption ) ? esc_attr( $image_caption ) : '',
					'thumbWidth'  => $sized_thumb[1],
					'thumbHeight' => $sized_thumb[2],
					'imageWidth'  => $sized_image[1],
					'imageHeight' => $sized_image[2],
					'alt_text'    => $image_alt,
					'id'          => $image_id,
				);
				if ( ! empty( $video_url ) ) {
					// Replace 'shorts/' by 'watch?v=' in the video URL.
					$video_url       = str_replace( 'shorts/', 'watch?v=', $video_url );
					$result['video'] = $video_url;
				}

				return $result;
		}
	}
}
