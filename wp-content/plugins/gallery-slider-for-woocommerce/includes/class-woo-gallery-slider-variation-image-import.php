<?php
/**
 * WooGallery Custom variation images import/export functionality.
 *
 * This class handles the import and export of custom gallery images for WooCommerce products.
 * It integrates with WooCommerce's native CSV import/export system to include gallery images.
 *
 * @since      2.2.1
 * @package    Woo_Gallery_Slide
 * @subpackage Woo_Gallery_Slide/includes
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Gallery_Slide_Export_Import' ) ) {
	/**
	 * Woo_Gallery_Slide_Export_Import Class
	 *
	 * Handles the import and export of custom gallery images for WooCommerce products.
	 *
	 * @since 2.2.1
	 */
	class Woo_Gallery_Slide_Export_Import {

		/**
		 * Export type for WooCommerce product export.
		 *
		 * @since 2.2.1
		 * @var string
		 */
		private $export_type = 'product';

		/**
		 * Column identifier for WooCommerce product export/import.
		 *
		 * @since 2.2.1
		 * @var string
		 */
		private $column_id = 'woo_gallery_slider';

		/**
		 * Singleton instance of the class.
		 *
		 * @since 2.2.1
		 * @var Woo_Gallery_Slide_Export_Import
		 */
		protected static $_instance = null;

		/**
		 * Constructor for the class.
		 *
		 * Sets up the necessary hooks and initializes the class.
		 *
		 * @since 2.2.1
		 * @return void
		 */
		protected function __construct() {
			$this->import_export_hooks();
		}

		/**
		 * Get the singleton instance of the class.
		 *
		 * @since 2.2.1
		 * @return Woo_Gallery_Slide_Export_Import The singleton instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * This function will be override the hook `woocommerce_product_export_skip_meta_keys`
		 *
		 * @param string $meta_keys meta keys to skip.
		 * @return array
		 */
		public function skip_woo_gallery_slider_meta_in_export( $meta_keys ) {
			$meta_keys[] = 'woo_gallery_slider';
			return $meta_keys;
		}
		/**
		 * Set up the necessary hooks for the class.
		 *
		 * @since 2.2.1
		 * @return void
		 */
		public function import_export_hooks() {
			// Export hooks.
			add_filter(
				"woocommerce_product_export_{$this->export_type}_default_columns",
				array( $this, 'export_column_name' )
			);
			// Remove the column.
			add_filter( 'woocommerce_product_export_skip_meta_keys', array( $this, 'skip_woo_gallery_slider_meta_in_export' ), 10, 1 );
			add_filter(
				"woocommerce_product_export_{$this->export_type}_column_{$this->column_id}",
				array( $this, 'export_column_data' ),
				10,
				3
			);
			// Import hooks.
			add_filter(
				'woocommerce_csv_product_import_mapping_options',
				array( $this, 'import_column_name' )
			);

			add_filter(
				'woocommerce_csv_product_import_mapping_default_columns',
				array( $this, 'default_import_column_name' )
			);

			add_action(
				'woocommerce_product_import_inserted_product_object',
				array( $this, 'process_product_gallery_image_import' ),
				10,
				2
			);
			/**
			 * Action hook that fires after the export/import class is loaded.
			 *
			 * @since 2.2.1
			 * @param Woo_Gallery_Slide_Export_Import $this The class instance.
			 */
			do_action( 'woo_gallery_export_import_loaded', $this );
		}

		/**
		 * Add the gallery images column to the WooCommerce product export.
		 *
		 * @since 2.2.1
		 * @param array $columns The existing columns.
		 * @return array The modified columns.
		 */
		public function export_column_name( $columns ) {
			// Add our custom column to the export columns.
			$columns[ $this->column_id ] = esc_html__( 'Reno Product Gallery Variation Images', 'gallery-slider-for-woocommerce' );
			return $columns;
		}

		/**
		 * Provide the data for the gallery images column in the export.
		 *
		 * @since 2.2.1
		 * @param string     $value      The column value.
		 * @param WC_Product $product    The product being exported.
		 * @param string     $column_id  The column identifier.
		 * @return string The formatted gallery images data.
		 */
		public function export_column_data( $value, $product, $column_id ) {
			$product_id = $product->get_id();
			// Get the gallery images for this product.
			$gallery_images = get_post_meta( $product_id, 'woo_gallery_slider', true );

			// If there are no gallery images, return an empty string.
			if ( empty( $gallery_images ) ) {
				return '';
			}

			// Decode the gallery images if they are stored as JSON.
			if ( is_string( $gallery_images ) && is_array( json_decode( $gallery_images, true ) ) ) {
				$gallery_images = json_decode( $gallery_images );
			}

			// Ensure we have an array of image IDs.
			if ( is_array( $gallery_images ) ) {
				/**
				 * Filter the raw gallery images before processing.
				 *
				 * @since 2.2.1
				 * @param array     $gallery_images  The gallery image IDs.
				 * @param WC_Product $product        The product being exported.
				 */
				$gallery_images = (array) apply_filters( 'sp_woo_gallery_raw_exported_images', $gallery_images, $product );

				// Remove any empty values and reset array keys.
				$gallery_images = array_values( array_filter( $gallery_images ) );
			} else {
				// If we don't have an array, initialize an empty one.
				$gallery_images = array();
			}
			// Convert attachment IDs to URLs.
			$images = array();
			foreach ( $gallery_images as $image_id ) {
				$image = wp_get_attachment_image_src( $image_id, 'full' );
				if ( $image ) {
					$images[] = $image[0]; // Get the URL from the image data.
				}
			}
			/**
			 * Filter the gallery image URLs before exporting.
			 *
			 * @since 2.2.1
			 * @param array $images     The gallery image URLs.
			 * @param int   $product_id The product ID.
			 */
			$images = apply_filters( 'sp_woo_gallery_exported_images', $images, $product_id );

			// Join the image URLs with commas.
			return implode( ',', array_values( array_filter( $images ) ) );
		}

		/**
		 * Add the gallery images column to the import mapping options.
		 *
		 * @since 2.2.1
		 * @param array $columns The existing columns.
		 * @return array The modified columns.
		 */
		public function import_column_name( $columns ) {
			// Add our custom column to the import mapping options.
			$columns[ $this->column_id ] = esc_html__( 'Reno Product Gallery Variation Images', 'gallery-slider-for-woocommerce' );
			return $columns;
		}

		/**
		 * Define the default column name for import mapping.
		 *
		 * @since 2.2.1
		 * @param array $columns The existing default columns.
		 * @return array The modified default columns.
		 */
		public function default_import_column_name( $columns ) {
			// Map the human-readable column name to our column ID.
			$columns[ esc_html__( 'Reno Product Gallery Variation Images', 'gallery-slider-for-woocommerce' ) ] = $this->column_id;
			return $columns;
		}

		/**
		 * Process the import data for a product.
		 *
		 * @since 2.2.1
		 * @param WC_Product $product The product being imported.
		 * @param array      $data    The import data.
		 * @return void
		 */
		public function process_product_gallery_image_import( $product, $data ) {
			$product_id = $product->get_id();
			// Check if our column exists in the import data.
			if ( isset( $data[ $this->column_id ] ) && ! empty( $data[ $this->column_id ] ) ) {
				$woo_variation_images = array();
				// Split the comma-separated list of image URLs.
				$raw_gallery_images = (array) explode( ',', $data[ $this->column_id ] );
				// Remove any empty values and reset array keys.
				$raw_gallery_images = array_values( array_filter( $raw_gallery_images ) );

				/**
				 * Filter the raw gallery image URLs before processing.
				 *
				 * @since 2.2.1
				 * @param array $raw_gallery_images The raw gallery image URLs.
				 * @param int   $product_id        The product ID.
				 * @param array $data              The import data.
				 * @param string $column_id        The column identifier.
				 */
				$raw_gallery_images = (array) apply_filters(
					'sp_woo_gallery_raw_imported_images',
					$raw_gallery_images,
					$product_id,
					$data,
					$this->column_id
				);

				// Convert URLs to attachment IDs.
				foreach ( $raw_gallery_images as $url ) {
					$attachment_id = $this->get_attachment_id_from_url( trim( $url ), $product_id );
					if ( $attachment_id ) {
						$woo_variation_images[] = $attachment_id;
					}
				}

				// Save the gallery images to the product.
				update_post_meta( $product_id, 'woo_gallery_slider', json_encode( array_values( array_filter( $woo_variation_images ) ) ) );
			}
		}

		/**
		 * Get the attachment ID from a URL.
		 *
		 * If the URL doesn't correspond to an existing attachment, the image will be downloaded and created.
		 *
		 * @since 2.2.1
		 * @param string $url        The image URL.
		 * @param int    $product_id The product ID.
		 * @return int The attachment ID or 0 on failure.
		 * @throws Exception If there's an error processing the image.
		 */
		public function get_attachment_id_from_url( $url, $product_id ) {
			// If the URL is empty, return 0.
			if ( empty( $url ) ) {
				return 0;
			}

			$id         = 0;
			$upload_dir = wp_upload_dir( null, false );
			$base_url   = $upload_dir['baseurl'] . '/';

			// Check if the image is already in the WordPress uploads directory.
			if ( false !== strpos( $url, $base_url ) || false === strpos( $url, '://' ) ) {
				// Get the file path relative to the uploads directory.
				$file = str_replace( $base_url, '', $url );

				// Search for the attachment by meta data.
				$args = array(
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore -- WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'OR',
						array(
							'key'     => '_wp_attached_file',
							'value'   => '^' . $file,
							'compare' => 'REGEXP',
						),
						array(
							'key'     => '_wp_attached_file',
							'value'   => '/' . $file,
							'compare' => 'LIKE',
						),
						array(
							'key'     => '_wc_attachment_source',
							'value'   => '/' . $file,
							'compare' => 'LIKE',
						),
					),
				);
			} else {
				// This is an external URL, so search by source.
				$args = array(
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore -- WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'value' => $url,
							'key'   => '_wc_attachment_source',
						),
					),
				);
			}

			// Get the matching attachment IDs.
			$ids = get_posts( $args );

			// If there are matches, use the first one.
			if ( $ids ) {
				$id = current( $ids );
			}

			// If no matching attachment was found and this is a remote URL, download it.
			if ( ! $id && stristr( $url, '://' ) ) {
				try {
					// Download the image using WooCommerce's helper function.
					$upload = wc_rest_upload_image_from_url( $url );

					if ( is_wp_error( $upload ) ) {
						throw new Exception( $upload->get_error_message(), 400 );
					}

					// Create an attachment from the uploaded image.
					$id = wc_rest_set_uploaded_image_as_attachment( $upload, $product_id );

					// Verify that the attachment is an image.
					if ( ! wp_attachment_is_image( $id ) ) {
						throw new Exception(
							sprintf(
							/* translators: %s: image URL */
								__( 'Not able to attach "%s".', 'gallery-slider-for-woocommerce' ),
								$url
							),
							400
						);
					}

					// Save the source URL for future reference.
					update_post_meta( $id, '_wc_attachment_source', $url );
				} catch ( Exception $e ) {
					// Log the error but continue with the import.
					return 0;
				}
			}
			// If we still don't have an ID, report the error.
			if ( ! $id ) {
				// translators: %s: URL of the image that could not be used.
				throw new Exception( sprintf( __( 'Unable to use image "%s".', 'gallery-slider-for-woocommerce' ), $url ), 400 ); // phpcs:ignore -- WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return $id;
		}
	}

	// Initialize the class.
	Woo_Gallery_Slide_Export_Import::instance();
}
