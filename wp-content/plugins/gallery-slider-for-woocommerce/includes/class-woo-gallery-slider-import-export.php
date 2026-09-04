<?php
/**
 * Custom import export.
 *
 * @link http://shapedplugin.com
 * @since 3.0.0
 *
 * @package Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Custom import export.
 */
class Woo_Gallery_Slider_Import_Export {
	/**
	 * Export
	 *
	 * @param  mixed $layout_ids Export shortcode ids.
	 * @return object
	 */
	public function export( $layout_ids ) {
		$export = array();
		if ( 'global_settings' === $layout_ids ) {
			$export['global_settings'] = get_option( 'wcgs_settings' );
			$export['metadata']        = array(
				'version' => WOO_GALLERY_SLIDER_VERSION,
				'date'    => gmdate( 'Y/m/d' ),
			);
			return $export;
		} elseif ( ! empty( $layout_ids ) ) {
			$post_in    = 'all_layouts' === $layout_ids ? '' : $layout_ids;
			$args       = array(
				'post_type'        => 'wcgs_layouts',
				'post_status'      => array( 'inherit', 'publish' ),
				'orderby'          => 'modified',
				'suppress_filters' => 1, // wpml, ignore language filter.
				'posts_per_page'   => -1,
				'post__in'         => $post_in,
			);
			$shortcodes = get_posts( $args );
			if ( ! empty( $shortcodes ) ) {
				foreach ( $shortcodes as $shortcode ) {
					$accordion_export = array(
						'title'       => sanitize_text_field( $shortcode->post_title ),
						'original_id' => absint( $shortcode->ID ),
						'meta'        => array(),
					);
					foreach ( get_post_meta( $shortcode->ID ) as $metakey => $value ) {
						$accordion_export['meta'][ $metakey ] = $value[0];
					}
					$export['layout'][] = $accordion_export;

					unset( $accordion_export );
				}
				$export['metadata'] = array(
					'version' => WOO_GALLERY_SLIDER_VERSION,
					'date'    => gmdate( 'Y/m/d' ),
				);
			}
			return $export;
		}
	}
	/**
	 * Retrieve all field IDs and their sanitize callbacks from a given metabox.
	 *
	 * @param string $metabox_id The ID of the metabox.
	 * @return array List of field ID and sanitize callback pairs.
	 */
	public function sp_get_metabox_field_ids_with_sanitizers( $metabox_id ) {
		if ( ! class_exists( 'WCGS' ) ) {
			return array();
		}
		$sections = WCGS::$args['sections'][ $metabox_id ] ?? null;
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return array();
		}
		$field_data = array();
		foreach ( $sections as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				if ( isset( $field['id'] ) ) {
					$field_data[] = array(
						'id'       => $field['id'],
						'sanitize' => $field['sanitize'] ?? null,
					);
				}
			}
		}
		return $field_data;
	}
	/**
	 * Export tabs by ajax.
	 *
	 * @return void
	 */
	public function export_shortcode() {
		$nonce = ( ! empty( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcgs_options_nonce' ) ) {
			die();
		}

		$_capability = apply_filters( 'wcgs_import_export_capability', 'manage_options' );
		if ( ! current_user_can( $_capability ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'You do not have permission to export.', 'gallery-slider-for-woocommerce' ) ) );
		}

		$layout_ids = '';
		if ( isset( $_POST['wcgs_ids'] ) ) {
			$layout_ids = is_array( $_POST['wcgs_ids'] ) ? wp_unslash( array_map( 'absint', $_POST['wcgs_ids'] ) ) : sanitize_text_field( wp_unslash( $_POST['wcgs_ids'] ) );
		}
		$export = $this->export( $layout_ids );

		if ( is_wp_error( $export ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html( $export->get_error_message() ),
				),
				400
			);
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			echo wp_json_encode( $export, JSON_PRETTY_PRINT );
			die;
		}
		wp_send_json( $export, 200 );
	}

	/**
	 * Import
	 *
	 * @param  array $shortcodes Import shortcode array.
	 * @throws \Exception Error massage.
	 * @return object
	 */
	public function import( $shortcodes ) {
		$layouts = get_posts(
			array(
				'post_type'      => 'wcgs_layouts',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids', // Only return post IDs.
			)
		);
		$errors  = array();
		if ( empty( $layouts ) ) {
			$shortcode        = $shortcodes[0];
			$index            = 0;
			$errors[ $index ] = array();
			$new_tabs_id      = 0;
			try {
				$new_tabs_id = wp_insert_post(
					array(
						'post_title'  => isset( $shortcode['title'] ) ? sanitize_text_field( $shortcode['title'] ) : '',
						'post_status' => 'publish',
						'post_type'   => 'wcgs_layouts',
					),
					true
				);

				if ( is_wp_error( $new_tabs_id ) ) {
					throw new Exception( $new_tabs_id->get_error_message() );
				}

				if ( isset( $shortcode['meta'] ) && is_array( $shortcode['meta'] ) ) {
					foreach ( $shortcode['meta'] as $key => $value ) {
						if ( 'wcgs_metabox' === $key ) {

							$value = str_replace( '{#ID#}', $new_tabs_id, $value );
							if ( is_serialized( $value ) ) {
								$value = unserialize( $value, array( 'allowed_classes' => false ) ); //phpcs:ignore -- no class allowed.
							}
							$sanitize_value = $this->sanitize_recursive( $value, $key );

							update_post_meta(
								$new_tabs_id,
								$key,
								$sanitize_value
							);

						}
					}
				}
			} catch ( Exception $e ) {
				array_push( $errors[ $index ], $e->getMessage() );
				// If there was a failure somewhere, clean up.
				wp_trash_post( $new_tabs_id );
			}
			// If no errors, remove the index.
			if ( ! count( $errors[ $index ] ) ) {
				unset( $errors[ $index ] );
			}

			// External modules manipulate data here.
			do_action( 'sp_wcgs_imported', $new_tabs_id );
		}
		$errors = reset( $errors );
		return isset( $errors[0] ) ? new WP_Error( 'import_wcgs_error', $errors[0] ) : $shortcodes;
	}

	/**
	 * Sanitize and process metabox form data.
	 *
	 * @param  string $metabox_key Unique metabox identifier.
	 * @param  array  $request_data Data submitted via the form ($_POST or similar).
	 * @return array Sanitized metabox data.
	 */
	public function sanitize_and_collect_metabox_data( $metabox_key, $request_data ) {
		$sanitized_data = array();

		// Retrieve the list of fields with their respective sanitization callbacks.
		$metabox_fields = $this->sp_get_metabox_field_ids_with_sanitizers( $metabox_key );

		foreach ( $metabox_fields as $field ) {
			// Ensure the field has a valid ID.
			if ( empty( $field['id'] ) ) {
				continue;
			}

			$field_id    = sanitize_key( $field['id'] );
			$field_value = isset( $request_data[ $field_id ] ) ? $request_data[ $field_id ] : '';

			// If a custom sanitizer function is provided, use it.
			if ( ! empty( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
				$sanitized_data[ $field_id ] = call_user_func( $field['sanitize'], $field_value );
			} elseif ( is_array( $field_value ) ) {
				$sanitized_data[ $field_id ] = wp_kses_post_deep( $field_value );
			} else {
				$sanitized_data[ $field_id ] = $field_value ? wp_kses_post( $field_value ) : null;
			}
		}

		return $sanitized_data;
	}

	/**
	 * Recursively sanitize all options.
	 *
	 * @param mixed  $data field data.
	 * @param string $key_context Context of the key, used for special cases.
	 * @return mixed Sanitized data.
	 */
	public function sanitize_recursive( $data, $key_context = '' ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : $key;
				$sanitized[ $sanitized_key ] = $this->sanitize_recursive( $value, $sanitized_key );
			}
			return $sanitized;
		}

		if ( is_object( $data ) ) {
			return $this->sanitize_recursive( (array) $data, $key_context );
		}

		if ( is_string( $data ) ) {
			// Special case: CSS fields.
			if ( 'wcgs_additional_css' === $key_context || 'wcgs_additional_js' === $key_context ) {
				// Strip tags to avoid <script>, but keep CSS syntax intact.
				return wp_strip_all_tags( $data );
			}
			return sanitize_text_field( $data );
		}

		if ( is_int( $data ) ) {
			return intval( $data );
		}

		if ( is_float( $data ) ) {
			return floatval( $data );
		}

		if ( is_bool( $data ) ) {
			return (bool) $data;
		}

		return null;
	}

	/**
	 * Import Tabs by ajax.
	 *
	 * @return void
	 */
	public function import_shortcode() {
		$nonce = ( ! empty( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcgs_options_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error: Nonce verification has failed. Please try again.', 'gallery-slider-for-woocommerce' ) ), 401 );
		}

		$_capability = apply_filters( 'wcgs_import_export_capability', 'manage_options' );
		if ( ! current_user_can( $_capability ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'You do not have permission to import.', 'gallery-slider-for-woocommerce' ) ) );
		}

		// Don't worry sanitize after JSON decode below.
		$data         = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '';//phpcs:ignore
		if ( ! $data ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nothing to import.', 'gallery-slider-for-woocommerce' ),
				),
				400
			);
		}

		// Decode JSON with error checking.
		$decoded_data = json_decode( $data, true );
		if ( is_string( $decoded_data ) ) {
			$decoded_data = json_decode( $decoded_data, true );
		}

		// Check for JSON errors.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid JSON data.', 'gallery-slider-for-woocommerce' ),
				),
				400
			);
		}

		$shortcodes = isset( $decoded_data['layout'] ) ? wp_kses_post_deep( $decoded_data['layout'] ) : array();

		if ( empty( $shortcodes ) ) {
			$global_settings = isset( $decoded_data['global_settings'] ) ? $this->sanitize_recursive( $decoded_data['global_settings'] ) : array();
			// Update global settings if available.
			if ( ! empty( $global_settings ) ) {
				update_option( 'wcgs_settings', $global_settings );
			}

			$status = array(
				'message' => __( 'Global settings imported successfully.', 'gallery-slider-for-woocommerce' ),
				'import'  => 'global_settings',
			);
		} else {
			// Validate expected structure.
			if ( ! isset( $decoded_data['layout'] ) || ! is_array( $decoded_data['layout'] ) ) {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Invalid shortcode data structure.', 'gallery-slider-for-woocommerce' ),
					),
					400
				);
			}

			$status = array(
				'message' => __( 'Nothing to import.', 'gallery-slider-for-woocommerce' ),
			);

			$status = $this->import( $shortcodes );

			if ( is_wp_error( $status ) ) {
				wp_send_json_error(
					array(
						'message' => esc_html( $status->get_error_message() ),
					),
					400
				);
			}
		}

		wp_send_json_success( $status, 200 );
	}
}
