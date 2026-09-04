<?php
/**
 * Framework metabox class.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

if ( ! class_exists( 'WCGS_Metabox' ) ) {
	/**
	 *
	 * Metabox Class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Metabox extends WCGS_Abstract {
		/**
		 * Unique
		 *
		 * @var string
		 */
		public $unique = '';
		/**
		 * Abstract
		 *
		 * @var string
		 */
		public $abstract = 'metabox';
		/**
		 * Pre_fields
		 *
		 * @var array
		 */
		public $pre_fields = array();
		/**
		 * Sections
		 *
		 * @var array
		 */
		public $sections = array();
		/**
		 * Post_type
		 *
		 * @var array
		 */
		public $post_type = array();
		/**
		 * Post formats
		 *
		 * @var array
		 */
		public $post_formats = array();
		/**
		 * Page_templates
		 *
		 * @var array
		 */
		public $page_templates = array();

		/**
		 * Args
		 *
		 * @var array
		 */
		public $args = array(
			'title'              => '',
			'post_type'          => 'post',
			'data_type'          => 'serialize',
			'context'            => 'advanced',
			'priority'           => 'default',
			'exclude_post_types' => array(),
			'page_templates'     => '',
			'post_formats'       => '',
			'show_reset'         => false,
			'show_restore'       => false,
			'enqueue_webfont'    => true,
			'async_webfont'      => false,
			'output_css'         => true,
			'nav'                => 'normal',
			'theme'              => 'dark',
			'class'              => '',
			'defaults'           => array(),
		);

		/**
		 * Run framework construct.
		 *
		 * @param  mixed $key key.
		 * @param  mixed $params params.
		 * @return void
		 */
		public function __construct( $key, $params = array() ) {
			$this->unique         = $key;
			$this->args           = apply_filters( "wcgs_{$this->unique}_args", wp_parse_args( $params['args'], $this->args ), $this );
			$this->sections       = apply_filters( "wcgs_{$this->unique}_sections", $params['sections'], $this );
			$this->post_type      = ( is_array( $this->args['post_type'] ) ) ? $this->args['post_type'] : array_filter( (array) $this->args['post_type'] );
			$this->post_formats   = ( is_array( $this->args['post_formats'] ) ) ? $this->args['post_formats'] : array_filter( (array) $this->args['post_formats'] );
			$this->page_templates = ( is_array( $this->args['page_templates'] ) ) ? $this->args['page_templates'] : array_filter( (array) $this->args['page_templates'] );
			$this->pre_fields     = $this->pre_fields( $this->sections );

			add_action( 'add_meta_boxes', array( &$this, 'add_meta_box' ) );
			add_action( 'save_post', array( &$this, 'save_meta_box' ) );
			add_action( 'edit_attachment', array( &$this, 'save_meta_box' ) );

			if ( ! empty( $this->page_templates ) || ! empty( $this->post_formats ) || ! empty( $this->args['class'] ) ) {
				foreach ( $this->post_type as $post_type ) {
					add_filter( 'postbox_classes_' . $post_type . '_' . $this->unique, array( &$this, 'add_metabox_classes' ) );
				}
			}

			// wp enqueue for typography and output css.
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
		 * Add metabox classes
		 *
		 * @param  string $classes  Class name.
		 * @return  string
		 */
		public function add_metabox_classes( $classes ) {

			global $post;

			if ( ! empty( $this->post_formats ) ) {

				$saved_post_format = ( is_object( $post ) ) ? get_post_format( $post ) : false;
				$saved_post_format = ( ! empty( $saved_post_format ) ) ? $saved_post_format : 'default';

				$classes[] = 'wcgs-post-formats';

				// Sanitize post format for standard to default.
				if ( ( $key = array_search( 'standard', $this->post_formats ) ) !== false ) {
					$this->post_formats[ $key ] = 'default';
				}

				foreach ( $this->post_formats as $format ) {
					$classes[] = 'wcgs-post-format-' . $format;
				}

				if ( ! in_array( $saved_post_format, $this->post_formats ) ) {
					$classes[] = 'wcgs-metabox-hide';
				} else {
					$classes[] = 'wcgs-metabox-show';
				}
			}

			if ( ! empty( $this->page_templates ) ) {

				$saved_template = ( is_object( $post ) && ! empty( $post->page_template ) ) ? $post->page_template : 'default';

				$classes[] = 'wcgs-page-templates';

				foreach ( $this->page_templates as $template ) {
					$classes[] = 'wcgs-page-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', strtolower( $template ) );
				}

				if ( ! in_array( $saved_template, $this->page_templates ) ) {
					$classes[] = 'wcgs-metabox-hide';
				} else {
					$classes[] = 'wcgs-metabox-show';
				}
			}

			if ( ! empty( $this->args['class'] ) ) {
				$classes[] = $this->args['class'];
			}

			return $classes;
		}

		/**
		 * Add metabox.
		 *
		 * @param  string $post_type The post type.
		 * @return void
		 */
		public function add_meta_box( $post_type ) {
			if ( ! in_array( $post_type, $this->args['exclude_post_types'] ) ) {
				add_meta_box( $this->unique, $this->args['title'], array( &$this, 'add_meta_box_content' ), $this->post_type, $this->args['context'], $this->args['priority'], $this->args );
			}
		}

		/**
		 * Get default value.
		 *
		 * @param array $field The field.
		 * @return string
		 */
		public function get_default( $field ) {

			$default = ( isset( $field['default'] ) ) ? $field['default'] : '';
			$default = ( isset( $this->args['defaults'][ $field['id'] ] ) ) ? $this->args['defaults'][ $field['id'] ] : $default;

			return $default;
		}

		/**
		 *  Get meta value.
		 *
		 * @param  array $field The field.
		 * @return mixed The meta value.
		 */
		public function get_meta_value( $field ) {

			global $post;

			$value = null;

			if ( is_object( $post ) && ! empty( $field['id'] ) ) {

				if ( 'serialize' !== $this->args['data_type'] ) {
					$meta  = get_post_meta( $post->ID, $field['id'] );
					$value = ( isset( $meta[0] ) ) ? $meta[0] : null;
				} else {
					$meta  = get_post_meta( $post->ID, $this->unique, true );
					$value = ( isset( $meta[ $field['id'] ] ) ) ? $meta[ $field['id'] ] : null;
				}
			} elseif ( 'tabbed' === $field['type'] ) {
				$value = get_post_meta( $post->ID, $this->unique, true );
			}

			$default = ( isset( $field['id'] ) ) ? $this->get_default( $field ) : '';
			$value   = ( isset( $value ) ) ? $value : $default;

			return $value;
		}

		/**
		 * Add metabox content.
		 *
		 * @param  object $post  The post object.
		 * @param  string $callback The callback.
		 * @return void
		 */
		public function add_meta_box_content( $post, $callback ) {
			global $post;
			$has_nav   = ( count( $this->sections ) > 1 && 'side' !== $this->args['context'] ) ? true : false;
			$show_all  = ( ! $has_nav ) ? ' wcgs-show-all' : '';
			$post_type = ( is_object( $post ) ) ? $post->post_type : '';
			$errors    = ( is_object( $post ) ) ? get_post_meta( $post->ID, '_wcgs_errors_' . $this->unique, true ) : array();
			$errors    = ( ! empty( $errors ) ) ? $errors : array();
			$theme     = ( $this->args['theme'] ) ? ' wcgs-theme-' . $this->args['theme'] : '';
			$nav_type  = ( 'inline' === $this->args['nav'] ) ? 'inline' : 'normal';

			if ( is_object( $post ) && ! empty( $errors ) ) {
				delete_post_meta( $post->ID, '_wcgs_errors_' . $this->unique );
			}

			wp_nonce_field( 'wcgs_metabox_nonce', 'wcgs_metabox_nonce' . $this->unique );

			echo '<div class="wcgs wcgs-metabox' . esc_attr( $theme ) . '">';

			echo '<div class="wcgs-wrapper' . esc_attr( $show_all ) . '">';
			if ( 'wcgs_layouts' === $post_type && $has_nav ) {
				// Header start.
				echo '<div class="wcgs-header">';
				echo '<div class="wcgs-header-inner">';
					echo '<div class="wcgs-admin-header"><div class="wcgs-admin-logo"> <div class="wcgs-version">v' . esc_html( WOO_GALLERY_SLIDER_VERSION ) . '</div> </div>';
						echo '<div class="wcgs-header-right">';
							// echo '<a href="" class="support_button wcgs-help-text"><i class="sp_wgs-icon-help-tab"></i> Support</a>';
							echo ' <div class="wcgs-support-area">
							<span class="support_button"><i class="sp_wgs-icon-help-tab"></i>Support</span>
							<div class="wcgs-help-text wcgs-help-text wcgs-support"><div class="wcgs-info-label">Documentation</div>Check out our documentation and more information about what you can do with the Reno Product Gallery.<a class="wcgs-open-docs browser-docs" href="https://docs.renoproductgallery.com/" target="_blank">Browse Docs</a><div class="wcgs-info-label">Need Help or Missing a Feature?</div>Feel free to get help from our friendly support team or request a new feature if needed. We appreciate your suggestions to make the plugin better.<a class="wcgs-open-docs support" href="https://shapedplugin.com/create-new-ticket/" target="_blank">Get Help</a><a class="wcgs-open-docs feature-request" href="https://shapedplugin.com/contact-us/" target="_blank">Request a Feature</a></div></span></div>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
				echo '</div>';
				// Header end.
			}
			if ( $has_nav ) {

				echo '<div class="wcgs-nav wcgs-nav-' . esc_attr( $nav_type ) . ' wcgs-nav-metabox">';

				echo '<ul>';

				$tab_key = 0;

				foreach ( $this->sections as $section ) {

					if ( ! empty( $section['post_type'] ) && ! in_array( $post_type, array_filter( (array) $section['post_type'] ) ) ) {
						continue;
					}
					$tab_error = ( ! empty( $errors['sections'][ $tab_key ] ) ) ? '<i class="wcgs-label-error wcgs-error">!</i>' : '';
					$tab_icon  = ( ! empty( $section['icon'] ) ) ? '<i class="wcgs-tab-icon ' . esc_attr( $section['icon'] ) . '"></i>' : '';

					echo '<li><a data-section="' . esc_attr( $this->unique ) . '_' . esc_attr( $tab_key ) . '" href="#">' . wp_kses_post( $tab_icon . $section['title'] . $tab_error ) . '</a></li>';

					++$tab_key;
				}

				echo '</ul>';

				echo '</div>';

			}

			echo '<div class="wcgs-content">';

			echo '<div class="wcgs-sections">';

			$section_key = 0;

			foreach ( $this->sections as $section ) {

				if ( ! empty( $section['post_type'] ) && ! in_array( $post_type, array_filter( (array) $section['post_type'] ) ) ) {
					continue;
				}

				$section_onload = ( ! $has_nav ) ? ' wcgs-onload' : '';
				$section_class  = ( ! empty( $section['class'] ) ) ? ' ' . $section['class'] : '';
				$section_title  = ( ! empty( $section['title'] ) ) ? $section['title'] : '';
				$section_icon   = ( ! empty( $section['icon'] ) ) ? '<i class="wcgs-section-icon ' . esc_attr( $section['icon'] ) . '"></i>' : '';

				// echo '<div class="wcgs-section hidden' . esc_attr( $section_onload . $section_class ) . '">';
				echo '<div id="wcgs-section-' . esc_attr( $this->unique ) . '_' . esc_attr( $section_key ) . '" class="wcgs-section hidden' . esc_attr( $section_onload . $section_class ) . '" >';

				echo ( $section_title || $section_icon ) ? '<div class="wcgs-section-title"><h3>' . wp_kses_post( $section_icon . $section_title ) . '</h3></div>' : '';
				echo ( ! empty( $section['description'] ) ) ? '<div class="wcgs-field wcgs-section-description">' . wp_kses_post( $section['description'] ) . '</div>' : '';

				if ( ! empty( $section['fields'] ) ) {

					foreach ( $section['fields'] as $field ) {

						if ( ! empty( $field['id'] ) && ! empty( $errors['fields'][ $field['id'] ] ) ) {
							$field['_error'] = $errors['fields'][ $field['id'] ];
						}
						if ( ! empty( $field['id'] ) ) {
							$field['default'] = $this->get_default( $field );
						}
						WCGS::field( $field, $this->get_meta_value( $field ), $this->unique, 'metabox' );
					}
				} else {
					echo '<div class="wcgs-no-option">' . esc_html__( 'No data available.', 'gallery-slider-for-woocommerce' ) . '</div>';
				}

				echo '</div>';

				++$section_key;

			}

			echo '</div>';

			if ( ! empty( $this->args['show_restore'] ) || ! empty( $this->args['show_reset'] ) ) {

				echo '<div class="wcgs-sections-reset">';
				echo '<label>';
				echo '<input type="checkbox" name="' . esc_attr( $this->unique ) . '[_reset]" />';
				echo '<span class="button wcgs-button-reset">' . esc_html__( 'Reset', 'gallery-slider-for-woocommerce' ) . '</span>';
				echo '<span class="button wcgs-button-cancel">' . sprintf( '<small>( %s )</small> %s', esc_html__( 'update post', 'gallery-slider-for-woocommerce' ), esc_html__( 'Cancel', 'gallery-slider-for-woocommerce' ) ) . '</span>';
				echo '</label>';
				echo '</div>';
			}

			echo '</div>';

			echo ( $has_nav && 'normal' === $nav_type ) ? '<div class="wcgs-nav-background"></div>' : '';
			echo '<div class="clear"></div>';

			echo '</div>';

			echo '</div>';
			// Popup content.
			echo "<div id='BuyProPopupContent' style='display: none;'>
			<div class='wcgs-popup-content'>";
				echo '
				<h3>' . esc_html__( 'Create unlimited custom product gallery layouts and assign them to specific products and categories.', 'gallery-slider-for-woocommerce' ) . "</h3>
				<p class='wcgs-popup-p'>";
					printf(
						/* translators: 1: start strong tag, 2: close strong tag. */
						esc_html__( 'Take your online shop\'s product page experience to the next level with many premium features and %1$sBoost Sales!%2$s%3$s', 'gallery-slider-for-woocommerce' ),
						'<strong>',
						'</strong>',
						'<span class="pro-boost-icon">🚀</span>'
					);
				echo " 🚀</p>
				<p>
					<a href='" . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . "' target='_blank' class='btn'>" . esc_html__( 'Upgrade to Pro Now', 'gallery-slider-for-woocommerce' ) . '</a>
				</p>
			</div>
			</div>';
		}

		/**
		 * Delete cache on save option.
		 *
		 * @return void
		 */
		public function delete_products_variation_json_cache() {
			// Success.
			global $wpdb;
			if ( is_multisite() ) {
				$wp_sitemeta = $wpdb->get_blog_prefix( BLOG_ID_CURRENT_SITE ) . 'sitemeta';
				$wpdb->query( "DELETE FROM {$wp_sitemeta} WHERE `meta_key` LIKE ('%\spwg_product_variation_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
				$wpdb->query( "DELETE FROM {$wp_sitemeta} WHERE `meta_key` LIKE ('%\wcgsf_woo_gallery_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
			} else {
				$wp_options = $wpdb->prefix . 'options';
				$wpdb->query( "DELETE FROM {$wp_options} WHERE `option_name` LIKE ('%\_transient_spwg_product_variation_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
				$wpdb->query( "DELETE FROM {$wp_options} WHERE `option_name` LIKE ('%\_transient_wcgsf_woo_gallery_%')" ); // phpcs:ignore -- intentinally used to delete specific key.
			}
		}

		/**
		 * Save metabox.
		 *
		 * @param  int $post_id The post ID.
		 * @return int
		 */
		public function save_meta_box( $post_id ) {
			$count    = 1;
			$data     = array();
			$errors   = array();
			$noncekey = 'wcgs_metabox_nonce' . $this->unique;
			$nonce    = ( ! empty( $_POST[ $noncekey ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ $noncekey ] ) ) : '';

			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! wp_verify_nonce( $nonce, 'wcgs_metabox_nonce' ) ) {
				return $post_id;
			}

			// XSS ok.
			// No worries, This "POST" requests is sanitizing in the below foreach.
			$request = ( ! empty( $_POST[ $this->unique ] ) ) ? wp_unslash( $_POST[ $this->unique ] ) : array(); // phpcs:ignore 

			if ( ! empty( $request ) ) {

				foreach ( $this->sections as  $section ) {

					if ( ! empty( $section['fields'] ) ) {

						foreach ( $section['fields'] as $field ) {

							if ( 'tabbed' === $field['type'] ) {
								$tabs = $field['tabs'];
								foreach ( $tabs as $fields ) {
									$fields = $fields['fields'];
									foreach ( $fields as $field ) {
										$field_id = isset( $field['id'] ) ? $field['id'] : '';
										if ( ! empty( $field['ignore_db'] ) ) {
											continue;
										}
										$field_value = isset( $request[ $field_id ] ) ? $request[ $field_id ] : '';

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

												$errors['sections'][ $count ]  = true;
												$errors['fields'][ $field_id ] = $has_validated;
												$data[ $field_id ]             = $this->get_meta_value( $field );
											}
										}
									}
								}
							} elseif ( ! empty( $field['id'] ) ) {

								$field_id = $field['id'];
								if ( ! empty( $field['ignore_db'] ) ) {
									continue;
								}
								$field_value = isset( $request[ $field_id ] ) ? $request[ $field_id ] : '';

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

										$errors['sections'][ $count ]  = true;
										$errors['fields'][ $field_id ] = $has_validated;
										$data[ $field_id ]             = $this->get_meta_value( $field );

									}
								}
							}
						}
					}

					++$count;

				}
			}

			$data = apply_filters( "wcgs_{$this->unique}_save", $data, $post_id, $this );

			do_action( "wcgs_{$this->unique}_save_before", $data, $post_id, $this );

			if ( empty( $data ) || ! empty( $request['_reset'] ) ) {

				if ( 'serialize' !== $this->args['data_type'] ) {
					foreach ( $data as $key => $value ) {
						delete_post_meta( $post_id, $key );
					}
				} else {
					delete_post_meta( $post_id, $this->unique );
				}
			} else {

				if ( 'serialize' !== $this->args['data_type'] ) {
					foreach ( $data as $key => $value ) {
						update_post_meta( $post_id, $key, $value );
					}
				} else {
					update_post_meta( $post_id, $this->unique, $data );
				}

				if ( ! empty( $errors ) ) {
					update_post_meta( $post_id, '_wcgs_errors_' . $this->unique, $errors );
				}
			}

			do_action( "wcgs_{$this->unique}_saved", $data, $post_id, $this );
			$this->delete_products_variation_json_cache();
			do_action( "wcgs_{$this->unique}_save_after", $data, $post_id, $this );
		}
	}
}
