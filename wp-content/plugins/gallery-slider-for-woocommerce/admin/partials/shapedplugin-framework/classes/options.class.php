<?php
/**
 * Framework options.class file.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Options' ) ) {
	/**
	 *
	 * Options Class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Options extends WCGS_Abstract {

		/**
		 * Unique
		 *
		 * @var string
		 */
		public $unique = '';
		/**
		 * Notice
		 *
		 * @var string
		 */
		public $notice = '';
		/**
		 * Abstract
		 *
		 * @var string
		 */
		public $abstract = 'options';
		/**
		 * Sections
		 *
		 * @var array
		 */
		public $sections = array();
		/**
		 * Options
		 *
		 * @var array
		 */
		public $options = array();
		/**
		 * Errors
		 *
		 * @var array
		 */
		public $errors = array();
		/**
		 * Pre_tabs
		 *
		 * @var array
		 */
		public $pre_tabs = array();
		/**
		 * Pre_fields
		 *
		 * @var array
		 */
		public $pre_fields = array();
		/**
		 * Pre_sections
		 *
		 * @var array
		 */
		public $pre_sections = array();


		/**
		 * Default Arguments.
		 *
		 * @var array
		 */
		public $args = array(

			// framework title.
			'framework_title'         => '',
			'framework_class'         => '', // menu settings.
			'menu_title'              => '',
			'menu_slug'               => '',
			'menu_type'               => 'menu',
			'menu_capability'         => 'manage_options',
			'menu_icon'               => null,
			'menu_position'           => null,
			'menu_hidden'             => false,
			'menu_parent'             => '', // menu extras.
			'show_bar_menu'           => false,
			'show_sub_menu'           => true,
			'show_network_menu'       => false,
			'show_in_customizer'      => false,
			'show_search'             => true,
			'show_reset_all'          => true,
			'show_reset_section'      => true,
			'show_footer'             => true,
			'show_all_options'        => true,
			'sticky_header'           => true,
			'save_defaults'           => true,
			'ajax_save'               => true, // admin bar menu settings.
			'admin_bar_menu_icon'     => '',
			'admin_bar_menu_priority' => 80, // footer.
			'footer_text'             => '',
			'footer_after'            => '',
			'footer_credit'           => '',

			// database model.
			'database'                => '', // options, transient, theme_mod, network.
			'transient_time'          => 0,

			// contextual help.
			'contextual_help'         => array(),
			'contextual_help_sidebar' => '',

			// others.
			'output_css'              => true,

			// theme.
			'theme'                   => 'dark',
			'class'                   => '',

			// external default values.
			'defaults'                => array(),

		);

		/**
		 * Run framework construct.
		 *
		 * @param  mixed $key key.
		 * @param  mixed $params params.
		 * @return void
		 */
		public function __construct( $key, $params = array() ) {

			$this->unique   = $key;
			$this->args     = apply_filters( "wcgs_{$this->unique}_args", wp_parse_args( $params['args'], $this->args ), $this );
			$this->sections = apply_filters( "wcgs_{$this->unique}_sections", $params['sections'], $this );

			// run only is admin panel options, avoid performance loss.
			$this->pre_tabs     = $this->pre_tabs( $this->sections );
			$this->pre_fields   = $this->pre_fields( $this->sections );
			$this->pre_sections = $this->pre_sections( $this->sections );

			$this->get_options();
			$this->set_options();
			$this->save_defaults();

			add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
			add_action( 'admin_bar_menu', array( &$this, 'add_admin_bar_menu' ), $this->args['admin_bar_menu_priority'] );
			add_action( 'wp_ajax_wcgs_' . $this->unique . '_ajax_save', array( &$this, 'ajax_save' ) );
			add_action( 'wp_ajax_wcgs_run_migration', array( $this, 'wcgs_run_migration_callback' ) );
			if ( ! empty( $this->args['show_network_menu'] ) ) {
				add_action( 'network_admin_menu', array( &$this, 'add_admin_menu' ) );
			}

			// wp enqeueu for typography and output css.
			parent::__construct();
		}

		/**
		 * Instance
		 *
		 * @param  mixed $key key.
		 * @param  mixed $params params.
		 * @return statement
		 */
		public static function instance( $key, $params = array() ) {

			return new self( $key, $params );
		}

		/**
		 * Pre_tabs
		 *
		 * @param  array $sections sections.
		 * @return array
		 */
		public function pre_tabs( $sections ) {

			$result  = array();
			$parents = array();
			$count   = 100;

			foreach ( $sections as $key => $section ) {
				if ( ! empty( $section['parent'] ) ) {
					$section['priority']             = ( isset( $section['priority'] ) ) ? $section['priority'] : $count;
					$parents[ $section['parent'] ][] = $section;
					unset( $sections[ $key ] );
				}
				++$count;
			}

			foreach ( $sections as $key => $section ) {
				$section['priority'] = ( isset( $section['priority'] ) ) ? $section['priority'] : $count;
				if ( ! empty( $section['id'] ) && ! empty( $parents[ $section['id'] ] ) ) {
					$section['subs'] = wp_list_sort( $parents[ $section['id'] ], array( 'priority' => 'ASC' ), 'ASC', true );
				}
				$result[] = $section;
				++$count;
			}
			return wp_list_sort( $result, array( 'priority' => 'ASC' ), 'ASC', true );
		}

		/**
		 * Pre_fields
		 *
		 * @param  mixed $sections sections.
		 * @return array
		 */
		public function pre_fields( $sections ) {

			$result = array();

			foreach ( $sections as $key => $section ) {
				if ( ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field ) {
						$result[] = $field;
					}
				}
			}

			return $result;
		}

		/**
		 * Pre_sections
		 *
		 * @param  mixed $sections section.
		 * @return array
		 */
		public function pre_sections( $sections ) {

			$result = array();

			foreach ( $this->pre_tabs as $tab ) {
				if ( ! empty( $tab['subs'] ) ) {
					foreach ( $tab['subs'] as $sub ) {
						$result[] = $sub;
					}
				}
				if ( empty( $tab['subs'] ) ) {
					$result[] = $tab;
				}
			}

			return $result;
		}

		/**
		 * Add admin bar menu.
		 *
		 * @param object $wp_admin_bar admin bar.
		 * @return void
		 */
		public function add_admin_bar_menu( $wp_admin_bar ) {

			if ( ! empty( $this->args['show_bar_menu'] ) && empty( $this->args['menu_hidden'] ) ) {

				global $submenu;

				$menu_slug = $this->args['menu_slug'];
				$menu_icon = ( ! empty( $this->args['admin_bar_menu_icon'] ) ) ? '<span class="wcgs-ab-icon ab-icon ' . $this->args['admin_bar_menu_icon'] . '"></span>' : '';

				$wp_admin_bar->add_node(
					array(
						'id'    => $menu_slug,
						'title' => $menu_icon . $this->args['menu_title'],
						'href'  => ( is_network_admin() ) ? network_admin_url( 'admin.php?page=' . $menu_slug ) : admin_url( 'admin.php?page=' . $menu_slug ),
					)
				);

				if ( ! empty( $submenu[ $menu_slug ] ) ) {
					foreach ( $submenu[ $menu_slug ] as $key => $menu ) {
						$wp_admin_bar->add_node(
							array(
								'parent' => $menu_slug,
								'id'     => $menu_slug . '-' . $key,
								'title'  => $menu[0],
								'href'   => ( is_network_admin() ) ? network_admin_url( 'admin.php?page=' . $menu[2] ) : admin_url( 'admin.php?page=' . $menu[2] ),
							)
						);
					}
				}

				if ( ! empty( $this->args['show_network_menu'] ) ) {
					$wp_admin_bar->add_node(
						array(
							'parent' => 'network-admin',
							'id'     => $menu_slug . '-network-admin',
							'title'  => $menu_icon . $this->args['menu_title'],
							'href'   => network_admin_url( 'admin.php?page=' . $menu_slug ),
						)
					);
				}
			}
		}
		/**
		 * Run_migration_callback
		 *
		 * @return void
		 */
		public function wcgs_run_migration_callback() {
			$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );
			if ( ! current_user_can( $capability ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'You do not have permission to perform this action.', 'gallery-slider-for-woocommerce' ),
					)
				);
			}
			$nonce = ( ! empty( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wcgs_options_nonce' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Nonce verification failed.', 'gallery-slider-for-woocommerce' ),
					)
				);
			}
			$offset         = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
			$limit          = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 50;
			$plugin         = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
			$migrated_count = 0;
			$args           = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'tax_query'      => array( // phpcs:ignore -- intentionally used to fetch only variable products.
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
			);

			$product_ids   = get_posts( $args );
			$total_fetched = count( $product_ids );

			foreach ( $product_ids as $product_id ) {
				$variations = get_posts(
					array(
						'post_type'      => 'product_variation',
						'post_status'    => array( 'private', 'publish' ),
						'posts_per_page' => -1,
						'post_parent'    => $product_id,
					)
				);

				foreach ( $variations as $variation ) {
					$variation_id = $variation->ID;

					if ( 'woocommerce-additional-variation-images' === $plugin ) { // WooCommerce.
						$old_gallery = get_post_meta( $variation_id, '_wc_additional_variation_images', true );
					}
					if ( 'iconic-woothumbs' === $plugin ) { // Iconic .
						$old_gallery = get_post_meta( $variation->ID, '_product_image_gallery', true );
					}
					if ( 'woo-product-variation-gallery' === $plugin ) {  // Radius theme.
						$old_gallery = get_post_meta( $variation_id, 'rtwpvg_images', true );
					}
					if ( 'woo-product-gallery-slider' === $plugin ) { // codeixer.
						$old_gallery = get_post_meta( $variation_id, 'wavi_value', true );
					}
					if ( 'woo-variation-gallery' === $plugin ) { // Emran Ahmed.
						$old_gallery = get_post_meta( $variation_id, 'woo_variation_gallery_images', true );
					}
					$current_gallery = get_post_meta( $variation_id, 'woo_gallery_slider', true );
					if ( empty( $current_gallery ) || '[]' === $current_gallery ) {
						$current_gallery = array();
					}
					if ( empty( $old_gallery ) ) {
						continue; // No old gallery data to migrate.
					}

					// Normalize both values to arrays.
					$old_ids     = is_array( $old_gallery ) ? $old_gallery : explode( ',', $old_gallery );
					$current_ids = is_array( $current_gallery ) ? $current_gallery : explode( ',', $current_gallery );

					// Merge and remove duplicates.
					$merged_ids = array_unique( array_filter( array_map( 'intval', array_merge( $current_ids, $old_ids ) ) ) );

					if ( ! empty( $merged_ids ) ) {
						update_post_meta( $variation_id, 'woo_gallery_slider', json_encode( array_values( $merged_ids ) ) );
						++$migrated_count;
					}
				}
			}
			// If we fetched less than the limit, it means we reached the end of the products.
			if ( $total_fetched !== $limit ) {
				// Delete cache on save option.
				$this->delete_products_variation_json_cache();
			}
			$response = array(
				'batch_migrated' => $migrated_count, // translators: %d is the number of variations migrated in the current batch.
				'message'        => sprintf( __( 'Migrated %d variation(s) in this batch.', 'gallery-slider-for-woocommerce' ), $migrated_count ),
				'continue'       => ( $total_fetched === $limit ), // More left to process?
			);

			wp_send_json_success( $response );
		}
		/**
		 * Ajax_save
		 *
		 * @return void
		 */
		public function ajax_save() {

			$result = $this->set_options( true );
			if ( ! $result ) {
				wp_send_json_error(
					array(
						'success' => false,
						'error'   => esc_html__(
							'Error while saving the changes.',
							'gallery-slider-for-woocommerce'
						),
					)
				);
			} else {
				// Delete cache on save option.
				$this->delete_products_variation_json_cache();

				wp_send_json_success(
					array(
						'success' => true,
						'notice'  => $this->notice,
						'errors'  => $this->errors,
					)
				);
			}
		}

		/**
		 * Purge all the transients associated with our plugin.
		 *
		 * @return void
		 */
		public function delete_products_variation_json_cache() {
			// Success.
			global $wpdb;
			if ( is_multisite() ) {
				$wp_sitemeta = $wpdb->get_blog_prefix( BLOG_ID_CURRENT_SITE ) . 'sitemeta';
				$wpdb->query( "DELETE FROM {$wp_sitemeta} WHERE `meta_key` LIKE ('%\spwg_product_variation_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
				$wpdb->query( "DELETE FROM {$wp_sitemeta} WHERE `meta_key` LIKE ('%\wcgsf_woo_gallery_%')" );// phpcs:ignore -- intentinally used to delete specific key.
			} else {
				$wp_options = $wpdb->prefix . 'options';
				$wpdb->query( "DELETE FROM {$wp_options} WHERE `option_name` LIKE ('%\_transient_spwg_product_variation_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
				$wpdb->query( "DELETE FROM {$wp_options} WHERE `option_name` LIKE ('%\_transient_wcgsf_woo_gallery_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
			}
		}

		/**
		 * Get default value
		 *
		 * @param  mixed $field field.
		 * @param  array $options options.
		 * @return mixed
		 */
		public function get_default( $field, $options = array() ) {

			$default = ( isset( $this->args['defaults'][ $field['id'] ] ) ) ? $this->args['defaults'][ $field['id'] ] : '';
			$default = ( isset( $field['default'] ) ) ? $field['default'] : $default;
			$default = ( isset( $options[ $field['id'] ] ) ) ? $options[ $field['id'] ] : $default;
			return $default;
		}

		/**
		 * Save defaults and set new fields value to main options.
		 *
		 * @return void
		 */
		public function save_defaults() {
			$tmp_options = $this->options;

			foreach ( $this->pre_fields as $field ) {
				if ( isset( $field['type'] ) && 'tabbed' === $field['type'] && empty( $tmp_options ) ) {
					$tabs = $field['tabs'];
					foreach ( $tabs as $fields ) {
						$fields = $fields['fields'];
						foreach ( $fields as $subfield ) {
							if ( isset( $subfield['id'] ) ) {
								$this->options[ $subfield['id'] ] = $this->get_default( $subfield );
							}
						}
					}
				} elseif ( isset( $field['id'] ) && ! empty( $field['id'] ) ) {
					$this->options[ $field['id'] ] = $this->get_default( $field, $this->options );
				}
			}

			if ( $this->args['save_defaults'] && empty( $tmp_options ) ) {
				$this->save_options( $this->options );
			}
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
		 * Set options.
		 *
		 * @param boolean $ajax true/false.
		 * @return bool
		 */
		public function set_options( $ajax = false ) {
			// Retrieve nonce.
			$nonce = '';
			if ( $ajax && ! empty( $_POST['nonce'] ) ) {
				// Nonce sent via AJAX request.
				$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			} elseif ( ! empty( $_POST[ 'wcgs_options_nonce' . $this->unique ] ) ) {
				// Nonce sent via standard form (with unique field key).
				$nonce = sanitize_text_field( wp_unslash( $_POST[ 'wcgs_options_nonce' . $this->unique ] ) );
			}

			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wcgs_options_nonce' ) ) {
				return false;
			}

			// XSS ok.
			// This "POST" requests is sanitizing in the below foreach. see #L331.
			$response = ( $ajax && ! empty( $_POST['data'] ) ) ? json_decode( wp_unslash( trim( $_POST['data'] ) ), true ) : wp_unslash( $_POST ); // phpcs:ignore
			$response = $this->sanitize_recursive( $response );

			// Set variables.
			$data       = array();
			$options    = ( ! empty( $response[ $this->unique ] ) ) ? $response[ $this->unique ] : array();
			$transient  = ( ! empty( $response['wcgs_transient'] ) ) ? $response['wcgs_transient'] : array();
			$importing  = false;
			$section_id = ( ! empty( $transient['section'] ) ) ? $transient['section'] : '';

			if ( ! empty( $transient['reset'] ) ) {
				foreach ( $this->pre_fields as $field ) {
					if ( ! empty( $field['id'] ) ) {
						if ( isset( $field['id'] ) ) {
							$data[ $field['id'] ] = $this->get_default( $field );
						}
					}
				}
				$this->notice = esc_html__( 'Default settings restored.', 'gallery-slider-for-woocommerce' );
			} elseif ( ! empty( $transient['reset_section'] ) && ! empty( $section_id ) ) {
				if ( ! empty( $this->pre_sections ) ) {
					foreach ( $this->pre_sections as $fields ) {
						if ( $fields['name'] === $section_id ) {
							foreach ( $fields['fields'] as $field ) {
								if ( 'tabbed' === $field['type'] ) {
									$tabs = $field['tabs'];
									foreach ( $tabs as $fields ) {
										$fields = $fields['fields'];
										foreach ( $fields as $field ) {
											if ( isset( $field['id'] ) ) {
												$data[ $field['id'] ] = $this->get_default( $field );
											}
										}
									}
								} elseif ( ! empty( $field['id'] ) ) {
										$data[ $field['id'] ] = $this->get_default( $field );
								}
							}
						}
					}
				}
				$data         = wp_parse_args( $data, $this->options );
				$this->notice = esc_html__( 'Default settings restored.', 'gallery-slider-for-woocommerce' );
			} else {
				// sanitize and validate.
				foreach ( $this->pre_fields as $field ) {
					if ( 'tabbed' === $field['type'] ) {
						$tabs = $field['tabs'];
						foreach ( $tabs as $fields ) {
							$fields = $fields['fields'];
							foreach ( $fields as $field ) {
								$field_id = isset( $field['id'] ) ? $field['id'] : '';
								// If field is ignored, skip it.
								if ( ! empty( $field['ignore_db'] ) ) {
									continue;
								}
								$field_value = isset( $options[ $field_id ] ) ? $options[ $field_id ] : '';
								// Ajax and Importing doing wp_unslash already.
								if ( ! $ajax && ! $importing ) {
									$field_value = wp_unslash( $field_value );
								}
								// Sanitize "post" request of field.
								if ( ! isset( $field['sanitize'] ) ) {
									if ( is_array( $field_value ) ) {
										$data[ $field_id ] = wp_kses_post_deep( $field_value );
									} else {
										$data[ $field_id ] = wp_kses_post( $field_value );
									}
								} elseif ( isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
									$data[ $field_id ] = call_user_func( $field['sanitize'], $field_value );
								} else {
									$data[ $field_id ] = $field_value;
								}

								// Validate "post" request of field.
								if ( isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {
									$has_validated = call_user_func( $field['validate'], $field_value );
									if ( ! empty( $has_validated ) ) {
										$data[ $field_id ]         = ( isset( $this->options[ $field_id ] ) ) ? $this->options[ $field_id ] : '';
										$this->errors[ $field_id ] = $has_validated;
									}
								}
							}
						}
					} elseif ( ! empty( $field['id'] ) ) {
						$field_id = $field['id'];
						// If field is ignored, skip it.
						if ( ! empty( $field['ignore_db'] ) ) {
							continue;
						}
						$field_value = isset( $options[ $field_id ] ) ? $options[ $field_id ] : '';
						// Ajax and Importing doing wp_unslash already.
						if ( ! $ajax && ! $importing ) {
							$field_value = wp_unslash( $field_value );
						}
						// Sanitize "post" request of field.
						if ( ! isset( $field['sanitize'] ) ) {
							if ( is_array( $field_value ) ) {
								$data[ $field_id ] = wp_kses_post_deep( $field_value );
							} else {
								$data[ $field_id ] = wp_kses_post( $field_value );
							}
						} elseif ( isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
							$data[ $field_id ] = call_user_func( $field['sanitize'], $field_value );
						} else {
							$data[ $field_id ] = $field_value;
						}

						// Validate "post" request of field.
						if ( isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {

							$has_validated = call_user_func( $field['validate'], $field_value );

							if ( ! empty( $has_validated ) ) {

								$data[ $field_id ]         = ( isset( $this->options[ $field_id ] ) ) ? $this->options[ $field_id ] : '';
								$this->errors[ $field_id ] = $has_validated;

							}
						}
					}
				}
			}

			$data = apply_filters( "wcgs_{$this->unique}_save", $data, $this );

			do_action( "wcgs_{$this->unique}_save_before", $data, $this );

			$this->options = $data;

			$this->save_options( $data );

			do_action( "wcgs_{$this->unique}_save_after", $data, $this );

			if ( empty( $this->notice ) ) {
				$this->notice = esc_html__( 'Settings saved.', 'gallery-slider-for-woocommerce' );
			}

			return true;
		}

		/**
		 * Save options database.
		 *
		 * @param  mixed $request Request.
		 * @return void
		 */
		public function save_options( $request ) {

			if ( 'transient' === $this->args['database'] ) {
				set_transient( $this->unique, $request, $this->args['transient_time'] );
			} elseif ( 'theme_mod' === $this->args['database'] ) {
				set_theme_mod( $this->unique, $request );
			} elseif ( 'network' === $this->args['database'] ) {
				update_site_option( $this->unique, $request );
			} else {
				update_option( $this->unique, $request );
			}

			do_action( "wcgs_{$this->unique}_saved", $request, $this );
		}

		/**
		 * Get options from database.
		 *
		 * @return mixed
		 */
		public function get_options() {

			if ( 'transient' === $this->args['database'] ) {
				$this->options = get_transient( $this->unique );
			} elseif ( 'theme_mod' === $this->args['database'] ) {
				$this->options = get_theme_mod( $this->unique );
			} elseif ( 'network' === $this->args['database'] ) {
				$this->options = get_site_option( $this->unique );
			} else {
				$this->options = get_option( $this->unique );
			}

			if ( empty( $this->options ) ) {
				$this->options = array();
			}

			return $this->options;
		}

		/**
		 * Wp api: admin menu.
		 *
		 * @return void
		 */
		public function add_admin_menu() {
			$args = $this->args;

			$menu_type       = $args['menu_type'] ?? '';
			$menu_parent     = $args['menu_parent'] ?? '';
			$menu_title      = $args['menu_title'] ?? '';
			$menu_capability = $args['menu_capability'] ?? 'manage_options';
			$menu_slug       = $args['menu_slug'] ?? '';
			$menu_icon       = $args['menu_icon'] ?? '';
			$menu_position   = $args['menu_position'] ?? null;
			$sub_menu_title  = $args['sub_menu_title'] ?? '';
			$menu_hidden     = $args['menu_hidden'] ?? false;

			$menu_capability = apply_filters( 'wcgs_ui_permission', $menu_capability );
			if ( 'submenu' === $menu_type ) {
				$menu_page = call_user_func( 'add_submenu_page', $menu_parent, $menu_title, $menu_title, $menu_capability, $menu_slug, array( &$this, 'add_options_html' ) );
			} else {
				$menu_page = call_user_func( 'add_menu_page', $menu_title, $menu_title, $menu_capability, $menu_slug, array( &$this, 'add_options_html' ), $menu_icon, $menu_position );

				if ( ! empty( $this->args['show_sub_menu'] ) && count( $this->pre_tabs ) > 1 ) {

					// create submenus.
					$tab_key = 1;
					foreach ( $this->pre_tabs as $section ) {

						call_user_func( 'add_submenu_page', $menu_slug, $section['title'], $section['title'], $menu_capability, $menu_slug . '#tab=' . $tab_key, '__return_null' );

						if ( ! empty( $section['subs'] ) ) {
							$tab_key += ( count( $section['subs'] ) - 1 );
						}

						++$tab_key;

					}

					remove_submenu_page( $menu_slug, $menu_slug );
				}

				if ( ! empty( $menu_hidden ) ) {
					remove_menu_page( $menu_slug );
				}
			}

			add_action( 'load-' . $menu_page, array( &$this, 'add_page_on_load' ) );
		}

		/**
		 * Add page on load
		 *
		 * @return void
		 */
		public function add_page_on_load() {

			if ( ! empty( $this->args['contextual_help'] ) ) {

				$screen = get_current_screen();

				foreach ( $this->args['contextual_help'] as $tab ) {
					$screen->add_help_tab( $tab );
				}

				if ( ! empty( $this->args['contextual_help_sidebar'] ) ) {
					$screen->set_help_sidebar( $this->args['contextual_help_sidebar'] );
				}
			}

			add_filter( 'admin_footer_text', array( &$this, 'add_admin_footer_text' ) );
		}

		/**
		 * Add admin footer text.
		 *
		 * @return void
		 */
		public function add_admin_footer_text() {

			$default = '';
			echo ! empty( $this->args['footer_credit'] ) ? wp_kses_post( $this->args['footer_credit'] ) : esc_html( $default );
		}

		/**
		 * Error check
		 *
		 * @param  mixed $sections Sections.
		 * @param  mixed $err error.
		 * @return statement
		 */
		public function error_check( $sections, $err = '' ) {

			if ( ! $this->args['ajax_save'] ) {

				if ( ! empty( $sections['fields'] ) ) {
					foreach ( $sections['fields'] as $field ) {
						if ( ! empty( $field['id'] ) ) {
							if ( array_key_exists( $field['id'], $this->errors ) ) {
								$err = '<span class="wcgs-label-error">!</span>';
							}
						}
					}
				}

				if ( ! empty( $sections['subs'] ) ) {
					foreach ( $sections['subs'] as $sub ) {
						$err = $this->error_check( $sub, $err );
					}
				}

				if ( ! empty( $sections['id'] ) && array_key_exists( $sections['id'], $this->errors ) ) {
					$err = $this->errors[ $sections['id'] ];
				}
			}

			return $err;
		}

		/**
		 * Option page html output.
		 *
		 * @return void
		 */
		public function add_options_html() {

			$has_nav       = ( count( $this->pre_tabs ) > 1 ) ? true : false;
			$show_all      = ( ! $has_nav ) ? ' wcgs-show-all' : '';
			$ajax_class    = ( $this->args['ajax_save'] ) ? ' wcgs-save-ajax' : '';
			$sticky_class  = ( $this->args['sticky_header'] ) ? ' wcgs-sticky-header' : '';
			$wrapper_class = ( $this->args['framework_class'] ) ? ' ' . $this->args['framework_class'] : '';
			$theme         = ( $this->args['theme'] ) ? ' wcgs-theme-' . $this->args['theme'] : '';
			$class         = ( $this->args['class'] ) ? ' ' . $this->args['class'] : '';

			echo '<div class="wcgs wcgs-options' . esc_attr( $theme . $class . $wrapper_class ) . '" data-slug="' . esc_attr( $this->args['menu_slug'] ) . '" data-unique="' . esc_attr( $this->unique ) . '">';

			$notice_class = ( ! empty( $this->notice ) ) ? ' wcgs-form-show' : '';
			$notice_text  = ( ! empty( $this->notice ) ) ? $this->notice : '';

			$kses_defaults = wp_kses_allowed_html( 'post' );
			$svg_args      = array(
				'svg'   => array(
					'class'           => true,
					'aria-hidden'     => true,
					'aria-labelledby' => true,
					'role'            => true,
					'xmlns'           => true,
					'width'           => true,
					'height'          => true,
					'viewbox'         => true,
				),
				'g'     => array( 'fill' => true ),
				'title' => array( 'title' => true ),
				'path'  => array(
					'd'    => true,
					'fill' => true,
				),
			);
			$allowed_tags  = array_merge( $kses_defaults, $svg_args );
			$error_class   = ( ! empty( $this->errors ) ) ? ' wcgs-form-show' : '';

			echo '<div class="wcgs-form-result wcgs-form-error' . esc_attr( $error_class ) . '">';
			if ( ! empty( $this->errors ) ) {
				foreach ( $this->errors as $error ) {
					echo '<i class="wcgs-label-error">!</i> ' . wp_kses_post( $error ) . '<br />';
				}
			}
			echo '</div>';

			echo '<div class="wcgs-container">';

			echo '<form method="post" action="" enctype="multipart/form-data" id="wcgs-form" autocomplete="off">';

			echo '<input type="hidden" class="wcgs-section-id" name="wcgs_transient[section]" value="1">';
			wp_nonce_field( 'wcgs_options_nonce', 'wcgs_options_nonce' . $this->unique );

			echo '<div class="wcgs-header' . esc_attr( $sticky_class ) . '">';
			echo '<div class="wcgs-header-inner">';
			if ( 'assign_layout' === $this->args['menu_slug'] ) {
				echo '<div class="wcgs-admin-header assign_layout_settings"><div class="wcgs-admin-logo"> <div class="wcgs-version">v' . esc_html( WOO_GALLERY_SLIDER_VERSION ) . '</div></div>';
			} else {
				echo '<div class="wcgs-admin-header"><div class="wcgs-admin-logo"> <div class="wcgs-version">v' . esc_html( WOO_GALLERY_SLIDER_VERSION ) . '</div></div>';
			}

			echo '<div class="wcgs-header-right">';

			echo ( $has_nav && $this->args['show_all_options'] ) ? '<div class="wcgs-expand-all" title="' . esc_html__( 'show all options', 'gallery-slider-for-woocommerce' ) . '"><i class="fa fa-outdent"></i></div>' : '';

			echo ( $this->args['show_search'] ) ? '<div class="wcgs-search"><input type="text" name="wcgs-search" placeholder="' . esc_html__( 'Search option(s)', 'gallery-slider-for-woocommerce' ) . '" autocomplete="off" /></div>' : '';

			echo '<div class="wcgs-buttons">';
			echo '<input type="submit" name="' . esc_attr( $this->unique ) . '[_nonce][save]" class="button button-primary wcgs-save' . esc_attr( $ajax_class ) . '" value="' . esc_html__( 'Save Settings', 'gallery-slider-for-woocommerce' ) . '" data-save="' . esc_html__( 'Saving...', 'gallery-slider-for-woocommerce' ) . '">';
			echo ( $this->args['show_reset_section'] ) ? '<input type="submit" name="wcgs_transient[reset_section]" class="button button-secondary wcgs-reset-section wcgs-confirm" value="' . esc_html__( 'Reset Tab', 'gallery-slider-for-woocommerce' ) . '" data-confirm="' . esc_html__( 'Are you sure to reset this section options?', 'gallery-slider-for-woocommerce' ) . '">' : '';
			echo '</div>';

			echo '</div>';

			echo '</div>';
			echo '</div>';
			echo '</div>';

			echo '<div class="wcgs-wrapper' . esc_attr( $show_all ) . '">';

			if ( $has_nav ) {
				echo '<div class="wcgs-nav wcgs-nav-options">';

				echo '<ul>';

				$tab_key = 1;

				foreach ( $this->pre_tabs as $tab ) {

					$tab_error = $this->error_check( $tab );
					$tab_icon  = ( ! empty( $tab['icon'] ) ) ? '<i class="' . $tab['icon'] . '"></i>' : '';

					if ( ! empty( $tab['subs'] ) ) {

						echo '<li class="wcgs-tab-depth-0">';

						echo '<a href="#tab=' . esc_attr( $tab_key ) . '" class="wcgs-arrow">' . wp_kses_post( $tab_icon . $tab['title'] . $tab_error ) . '</a>';

						echo '<ul>';

						foreach ( $tab['subs'] as $sub ) {

							$sub_error = $this->error_check( $sub );
							$sub_icon  = ( ! empty( $sub['icon'] ) ) ? '<i class="' . esc_attr( $sub['icon'] ) . '"></i>' : '';

							echo '<li class="wcgs-tab-depth-1"><a id="wcgs-tab-link-' . esc_attr( $tab_key ) . '" href="#tab=' . esc_attr( $tab_key ) . '">' . wp_kses( $sub_icon . $sub['title'] . $sub_error, $allowed_tags ) . '</a></li>';

							++$tab_key;
						}

						echo '</ul>';

						echo '</li>';

					} else {
						$tb_id_text = $tab['name'];
						echo '<li class="wcgs-tab-depth-0"><a id="wcgs-tab-link-' . esc_attr( $tb_id_text ) . '" href="#tab=' . esc_attr( $tb_id_text ) . '">' . wp_kses( $tab_icon . $tab['title'] . $tab_error, $allowed_tags ) . '</a></li>';

						++$tab_key;
					}
				}

				echo '</ul>';

				echo '</div>';

			}

			echo '<div class="wcgs-content">';

			echo '<div class="wcgs-sections">';

			$section_key = 1;

			foreach ( $this->pre_sections as $section ) {

				$onload       = ( ! $has_nav ) ? ' wcgs-onload' : '';
				$section_icon = ( ! empty( $section['icon'] ) ) ? '<i class="wcgs-icon ' . $section['icon'] . '"></i>' : '';
				$sc_text      = $section['name'];
				echo '<div id="wcgs-section-' . esc_attr( $sc_text ) . '" class="wcgs-section' . esc_attr( $onload ) . '">';
				echo ( $has_nav ) ? '<div class="wcgs-section-title"><h3>' . wp_kses( $section_icon . $section['title'], $allowed_tags ) . '</h3></div>' : '';
				echo ( ! empty( $section['description'] ) ) ? '<div class="wcgs-field wcgs-section-description">' . wp_kses_post( $section['description'] ) . '</div>' : '';

				if ( ! empty( $section['fields'] ) ) {

					foreach ( $section['fields'] as $field ) {

						$is_field_error = $this->error_check( $field );

						if ( ! empty( $is_field_error ) ) {
							$field['_error'] = $is_field_error;
						}
						if ( 'tabbed' === $field['type'] ) { // field type tabbed, no field id.
							$value = $this->options;
						} else {
							$value = ( ! empty( $field['id'] ) && isset( $this->options[ $field['id'] ] ) ) ? $this->options[ $field['id'] ] : '';
						}
						WCGS::field( $field, $value, $this->unique, 'options' );
					}
				} else {
					echo '<div class="wcgs-no-option wcgs-text-muted">' . esc_html__( 'No option provided by developer.', 'gallery-slider-for-woocommerce' ) . '</div>';
				}

				echo '</div>';

				++$section_key;
			}

			echo '</div>';

			echo '<div class="clear"></div>';

			echo '</div>';

			echo '<div class="wcgs-nav-background"></div>';

			echo '</div>';

			if ( ! empty( $this->args['show_footer'] ) ) {

				echo '<div class="wcgs-footer">';

				echo '<div class="wcgs-buttons">';
				echo '<input type="submit" name="wcgs_transient[save]" class="button button-primary wcgs-save' . esc_attr( $ajax_class ) . '" value="' . esc_html__( 'Save', 'gallery-slider-for-woocommerce' ) . '" data-save="' . esc_html__( 'Saving...', 'gallery-slider-for-woocommerce' ) . '">';
				echo ( $this->args['show_reset_section'] ) ? '<input type="submit" name="wcgs_transient[reset_section]" class="button button-secondary wcgs-reset-section wcgs-confirm" value="' . esc_html__( 'Reset Section', 'gallery-slider-for-woocommerce' ) . '" data-confirm="' . esc_html__( 'Are you sure to reset this section options?', 'gallery-slider-for-woocommerce' ) . '">' : '';
				echo ( $this->args['show_reset_all'] ) ? '<input type="submit" name="wcgs_transient[reset]" class="button button-secondary wcgs-warning-primary wcgs-reset-all wcgs-confirm" value="' . esc_html__( 'Reset All', 'gallery-slider-for-woocommerce' ) . '" data-confirm="' . esc_html__( 'Are you sure to reset all options?', 'gallery-slider-for-woocommerce' ) . '">' : '';
				echo '</div>';

				echo ( ! empty( $this->args['footer_text'] ) ) ? '<div class="wcgs-copyright">' . wp_kses_post( $this->args['footer_text'] ) . '</div>' : '';

				echo '<div class="clear"></div>';
				echo '</div>';

			}

			echo '</form>';

			echo '</div>';

			echo '<div class="clear"></div>';

			echo ( ! empty( $this->args['footer_after'] ) ) ? wp_kses_post( $this->args['footer_after'] ) : '';

			echo '</div>';
		}
	}
}
