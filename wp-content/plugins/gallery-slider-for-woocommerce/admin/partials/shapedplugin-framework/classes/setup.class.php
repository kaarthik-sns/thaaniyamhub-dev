<?php
/**
 * Framework setup.class file.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS' ) ) {
	/**
	 *
	 * Setup Class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS {
		/**
		 * Version framework version is 2.1.0
		 *
		 * @var string
		 */
		public static $version = WOO_GALLERY_SLIDER_VERSION;
		/**
		 * Dir.
		 *
		 * @var string
		 */
		public static $dir = null;
		/**
		 * Url.
		 *
		 * @var string
		 */
		public static $url = null;
		/**
		 * Init.
		 *
		 * @var array
		 */
		public static $inited = array();
		/**
		 * Field.
		 *
		 * @var array
		 */
		public static $fields = array();
		/**
		 * Args.
		 *
		 * @var array
		 */
		public static $args = array(
			'options' => array(),
		);

		/**
		 * Shortcode instances.
		 *
		 * @var array
		 */
		public static $shortcode_instances = array();

		/**
		 * Init
		 *
		 * @return void
		 */
		public static function init() {

			// init action.
			do_action( 'wcgs_init' );

			// set constants.
			self::constants();

			// include files.
			self::includes();

			add_action( 'after_setup_theme', array( 'WCGS', 'setup' ) );
			add_action( 'init', array( 'WCGS', 'setup' ) );
			add_action( 'switch_theme', array( 'WCGS', 'setup' ) );
			add_action( 'admin_enqueue_scripts', array( 'WCGS', 'add_admin_enqueue_scripts' ), 20 );
		}

		/**
		 * Setup
		 *
		 * @return void
		 */
		public static function setup() {

			// setup options.
			$params = array();
			if ( ! empty( self::$args['options'] ) ) {
				foreach ( self::$args['options'] as $key => $value ) {
					if ( ! empty( self::$args['sections'][ $key ] ) && ! isset( self::$inited[ $key ] ) ) {

						$params['args']       = $value;
						$params['sections']   = self::$args['sections'][ $key ];
						self::$inited[ $key ] = true;
						WCGS_Options::instance( $key, $params );
						if ( ! empty( $value['show_in_customizer'] ) ) {
							self::$args['customize_options'][ $key ] = $value;
							self::$inited[ $key ]                    = null;
						}
					}
				}
			}
			// Setup metabox option framework.
			$params = array();
			if ( class_exists( 'WCGS_Metabox' ) && ! empty( self::$args['metabox_options'] ) ) {
				foreach ( self::$args['metabox_options'] as $key => $value ) {
					if ( ! empty( self::$args['sections'][ $key ] ) && ! isset( self::$inited[ $key ] ) ) {
							$params['args']       = $value;
							$params['sections']   = self::$args['sections'][ $key ];
							self::$inited[ $key ] = true;
							WCGS_Metabox::instance( $key, $params );
					}
				}
			}

			do_action( 'wcgs_loaded' );
		}

		/**
		 * Create Options
		 *
		 * @param  mixed $id ID.
		 * @param  mixed $args Args.
		 * @return void
		 */
		public static function createOptions( $id, $args = array() ) {

			self::$args['options'][ $id ] = $args;
		}
		/**
		 * Create metabox option.
		 *
		 * @param  mixed $id
		 * @param  mixed $args
		 * @return void
		 */
		public static function createMetabox( $id, $args = array() ) {
			self::$args['metabox_options'][ $id ] = $args;
		}
		/**
		 * Create section.
		 *
		 * @param  mixed $id ID.
		 * @param  mixed $sections Sections.
		 * @return void
		 */
		public static function createSection( $id, $sections ) {
			self::$args['sections'][ $id ][] = $sections;
			self::set_used_fields( $sections );
		}

		/**
		 * Constants
		 *
		 * @return void
		 */
		public static function constants() {

			// we need this path-finder code for set URL of framework.
			$dirname        = wp_normalize_path( dirname( __DIR__ ) );
			$theme_dir      = wp_normalize_path( get_parent_theme_file_path() );
			$plugin_dir     = wp_normalize_path( WP_PLUGIN_DIR );
			$located_plugin = ( preg_match( '#' . self::sanitize_dirname( $plugin_dir ) . '#', self::sanitize_dirname( $dirname ) ) ) ? true : false;
			$directory      = ( $located_plugin ) ? $plugin_dir : $theme_dir;
			$directory_uri  = ( $located_plugin ) ? WP_PLUGIN_URL : get_parent_theme_file_uri();
			$foldername     = str_replace( $directory, '', $dirname );
			$protocol_uri   = ( is_ssl() ) ? 'https' : 'http';
			$directory_uri  = set_url_scheme( $directory_uri, $protocol_uri );

			self::$dir = $dirname;
			self::$url = $directory_uri . $foldername;
		}

		/**
		 * Include plugin files
		 *
		 * @param  mixed $file file.
		 * @param  mixed $load load.
		 * @return array
		 */
		public static function include_plugin_file( $file, $load = true ) {

			$path     = '';
			$file     = ltrim( $file, '/' );
			$override = apply_filters( 'wcgs_override', 'wcgs-override' );

			if ( file_exists( get_parent_theme_file_path( $override . '/' . $file ) ) ) {
				$path = get_parent_theme_file_path( $override . '/' . $file );
			} elseif ( file_exists( get_theme_file_path( $override . '/' . $file ) ) ) {
				$path = get_theme_file_path( $override . '/' . $file );
			} elseif ( file_exists( self::$dir . '/' . $override . '/' . $file ) ) {
				$path = self::$dir . '/' . $override . '/' . $file;
			} elseif ( file_exists( self::$dir . '/' . $file ) ) {
				$path = self::$dir . '/' . $file;
			}

			if ( ! empty( $path ) && ! empty( $file ) && $load ) {

				global $wp_query;

				if ( is_object( $wp_query ) && function_exists( 'load_template' ) ) {

					load_template( $path, true );

				} else {

					require_once $path;

				}
			} else {

				return self::$dir . '/' . $file;

			}
		}

		/**
		 * Is active plugin
		 *
		 * @param  mixed $file file.
		 * @return statement
		 */
		public static function is_active_plugin( $file = '' ) {

			return in_array( $file, (array) get_option( 'active_plugins', array() ) );
		}

		/**
		 * Sanitize dirname.
		 *
		 * @param  mixed $dirname dirname.
		 * @return statement
		 */
		public static function sanitize_dirname( $dirname ) {

			return preg_replace( '/[^A-Za-z]/', '', $dirname );
		}

		/**
		 * Set plugin url.
		 *
		 * @param  mixed $file file.
		 * @return string
		 */
		public static function include_plugin_url( $file ) {
			return WOO_GALLERY_SLIDER_URL . 'admin/partials/shapedplugin-framework/' . ltrim( $file, '/' );
		}

		/**
		 * General includes.
		 *
		 * @return void
		 */
		public static function includes() {

			// Includes helpers.
			self::include_plugin_file( 'functions/actions.php' );
			self::include_plugin_file( 'functions/deprecated.php' );
			self::include_plugin_file( 'functions/helpers.php' );
			self::include_plugin_file( 'functions/sanitize.php' );
			self::include_plugin_file( 'functions/validate.php' );

			// Includes free version classes.
			self::include_plugin_file( 'classes/abstract.class.php' );
			self::include_plugin_file( 'classes/fields.class.php' );
			self::include_plugin_file( 'classes/options.class.php' );
			self::include_plugin_file( 'classes/metabox-options.class.php' );
		}

		/**
		 * Include field.
		 *
		 * @param  mixed $type type.
		 * @return void
		 */
		public static function maybe_include_field( $type = '' ) {

			if ( ! class_exists( 'WCGS_Field_' . $type ) && class_exists( 'WCGS_Fields' ) ) {
				self::include_plugin_file( 'fields/' . $type . '/' . $type . '.php' );
			}
		}

		/**
		 * Get all of fields.
		 *
		 * @param  mixed $sections sections.
		 * @return void
		 */
		public static function set_used_fields( $sections ) {

			if ( ! empty( $sections['fields'] ) ) {

				foreach ( $sections['fields'] as $field ) {

					if ( ! empty( $field['fields'] ) ) {
						self::set_used_fields( $field );
					}

					if ( ! empty( $field['tabs'] ) ) {
						self::set_used_fields( array( 'fields' => $field['tabs'] ) );
					}

					if ( ! empty( $field['accordions'] ) ) {
						self::set_used_fields( array( 'fields' => $field['accordions'] ) );
					}

					if ( ! empty( $field['type'] ) ) {
						self::$fields[ $field['type'] ] = $field;
					}
				}
			}
		}

		/**
		 * Enqueue admin and fields styles and scripts.
		 *
		 * @return void
		 */
		public static function add_admin_enqueue_scripts() {
			$current_screen = get_current_screen();
			// The submenu screen base is prefixed with the sanitized parent menu title, so derive it instead of hardcoding it.
			$assign_layout_base = get_plugin_page_hookname( 'assign_layout', 'wpgs-settings' );
			if ( is_object( $current_screen ) && 'toplevel_page_wpgs-settings' === $current_screen->base || $assign_layout_base === $current_screen->base || 'wcgs_layouts' === $current_screen->post_type ) {
				// check for developer mode.
				$min = ( apply_filters( 'wcgs_dev_mode', false ) || WP_DEBUG ) ? '' : '.min';

				// admin utilities.
				wp_enqueue_media();

				// wp color picker.
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );

				// framework core styles.
				wp_enqueue_style( 'wcgs', self::include_plugin_url( 'assets/css/wcgs' . $min . '.css' ), array(), self::$version, 'all' );

				// rtl styles.
				if ( is_rtl() ) {
					wp_enqueue_style( 'wcgs-rtl', self::include_plugin_url( 'assets/css/wcgs-rtl' . $min . '.css' ), array(), self::$version, 'all' );
				}

				// framework core scripts.
				wp_enqueue_script( 'wcgs-plugins', self::include_plugin_url( 'assets/js/wcgs-plugins' . $min . '.js' ), array(), self::$version, true );
				wp_enqueue_script( 'wcgs', self::include_plugin_url( 'assets/js/wcgs' . $min . '.js' ), array( 'wcgs-plugins' ), self::$version, true );

				wp_localize_script(
					'wcgs',
					'wcgs_vars',
					array(
						'color_palette' => apply_filters( 'wcgs_color_palette', array() ),
						'i18n'          => array(
							'confirm'             => esc_html__( 'Are you sure?', 'gallery-slider-for-woocommerce' ),
							'reset_notification'  => esc_html__( 'Restoring options.', 'gallery-slider-for-woocommerce' ),
							'import_notification' => esc_html__( 'Importing options.', 'gallery-slider-for-woocommerce' ),
							// Translators: %s represents the minimum number of characters required.
							'typing_text'         => esc_html__( 'Please enter %s or more characters', 'gallery-slider-for-woocommerce' ),
							'searching_text'      => esc_html__( 'Searching...', 'gallery-slider-for-woocommerce' ),
							'no_results_text'     => esc_html__( 'No results found.', 'gallery-slider-for-woocommerce' ),
						),
					)
				);

				// load admin enqueue scripts and styles.
				$enqueued = array();

				if ( ! empty( self::$fields ) ) {
					foreach ( self::$fields as $field ) {
						if ( ! empty( $field['type'] ) ) {
							$classname = 'WCGS_Field_' . $field['type'];
							self::maybe_include_field( $field['type'] );
							if ( class_exists( $classname ) && method_exists( $classname, 'enqueue' ) ) {
								$instance = new $classname( $field );
								if ( method_exists( $classname, 'enqueue' ) ) {
									$instance->enqueue();
								}
								unset( $instance );
							}
						}
					}
				}

				do_action( 'wcgs_enqueue' );

			}
		}

		/**
		 * Add a new framework field.
		 *
		 * @param  mixed $field Field.
		 * @param  mixed $value value.
		 * @param  mixed $unique unique id.
		 * @param  mixed $where Where.
		 * @param  mixed $parent parent.
		 * @return void
		 */
		public static function field( $field = array(), $value = '', $unique = '', $where = '', $parent = '' ) {

			// Check for unallow fields.
			if ( ! empty( $field['_notice'] ) ) {

				$field_type = $field['type'];

				$field = array();
				/* translators: %s: content field */
				$field['content'] = sprintf( esc_html__( 'Ooops! This field type (%s) can not be used here, yet.', 'gallery-slider-for-woocommerce' ), '<strong>' . $field_type . '</strong>' );
				$field['type']    = 'notice';
				$field['style']   = 'danger';

			}

			$depend     = '';
			$hidden     = '';
			$unique     = ( ! empty( $unique ) ) ? $unique : '';
			$class      = ( ! empty( $field['class'] ) ) ? ' ' . $field['class'] : '';
			$is_pseudo  = ( ! empty( $field['pseudo'] ) ) ? ' wcgs-pseudo-field' : '';
			$field_type = ( ! empty( $field['type'] ) ) ? $field['type'] : '';

			if ( ! empty( $field['dependency'] ) ) {

				$dependency      = $field['dependency'];
				$hidden          = ' hidden';
				$data_controller = '';
				$data_condition  = '';
				$data_value      = '';
				$data_global     = '';

				if ( is_array( $dependency[0] ) ) {
					$data_controller = implode( '|', array_column( $dependency, 0 ) );
					$data_condition  = implode( '|', array_column( $dependency, 1 ) );
					$data_value      = implode( '|', array_column( $dependency, 2 ) );
					$data_global     = implode( '|', array_column( $dependency, 3 ) );
				} else {
					$data_controller = ( ! empty( $dependency[0] ) ) ? $dependency[0] : '';
					$data_condition  = ( ! empty( $dependency[1] ) ) ? $dependency[1] : '';
					$data_value      = ( ! empty( $dependency[2] ) ) ? $dependency[2] : '';
					$data_global     = ( ! empty( $dependency[3] ) ) ? $dependency[3] : '';
				}

				$depend .= ' data-controller="' . $data_controller . '"';
				$depend .= ' data-condition="' . $data_condition . '"';
				$depend .= ' data-value="' . $data_value . '"';
				$depend .= ( ! empty( $data_global ) ) ? ' data-depend-global="true"' : '';

			}

			if ( ! empty( $field_type ) ) {

				echo '<div class="wcgs-field wcgs-field-' . esc_attr( $field_type . $is_pseudo . $class . $hidden ) . '"' . wp_kses_post( $depend ) . '>';

				if ( ! empty( $field['title'] ) ) {
					$subtitle = ( ! empty( $field['subtitle'] ) ) ? '<p class="wcgs-text-subtitle">' . $field['subtitle'] . '</p>' : '';
					// $title_help = ( ! empty( $field['title_help'] ) ) ? '<span class="wcgs-help wcgs-title-help"><span class="wcgs-help-text">' . $field['title_help'] . '</span><span class="sp_wgs-icon-question-circle"></span></span>' : '';
					$title_help = '';
					if ( ! empty( $field['title_help'] ) || ! empty( $field['title_video'] ) ) {
						$help_text       = ! empty( $field['title_help'] ) ? $field['title_help'] : '';
						$icon_type       = ! empty( $field['title_video'] ) ? 'video_info.svg' : 'info.svg';
						$video_help_attr = ! empty( $field['title_video'] ) ? ' title-video-help' : '';
						$title_help      = sprintf(
							'<span class="wcgs-help wcgs-title-help">
							<span class="wcgs-help-text ' . esc_attr( $video_help_attr ) . '">%s</span><span class="tooltip-icon"><img src="%s"></span></span>',
							$help_text . ( ! empty( $field['title_video'] ) ? $field['title_video'] : '' ),
							self::include_plugin_url( 'assets/images/' . $icon_type )
						);
					}
					echo '<div class="wcgs-title"><h4>' . $field['title'] . $title_help . '</h4>' . wp_kses_post( $subtitle ) . '</div>'; // phpcs:ignore -- no need to escape title, since we don't take any user input for title.
				}

				echo ( ! empty( $field['title'] ) ) ? '<div class="wcgs-fieldset">' : '';

				$value = ( ! isset( $value ) && isset( $field['default'] ) ) ? $field['default'] : $value;
				$value = ( isset( $field['value'] ) ) ? $field['value'] : $value;

				self::maybe_include_field( $field_type );

				$classname = 'WCGS_Field_' . $field_type;

				if ( class_exists( $classname ) ) {
					$instance = new $classname( $field, $value, $unique, $where, $parent );
					$instance->render();
				} else {
					echo '<p>' . esc_html__( 'This field class is not available!', 'gallery-slider-for-woocommerce' ) . '</p>';
				}
			} else {
					echo '<p>' . esc_html__( 'This type is not found!', 'gallery-slider-for-woocommerce' ) . '</p>';
			}

			echo ( ! empty( $field['title'] ) ) ? '</div>' : '';
			echo '<div class="clear"></div>';
			echo '</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for General tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function general_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Unlock the Full Potential of Product Image & Video Galleries with Pro</h2>
				<p>
					Enhance product pages with powerful galleries, videos, and premium controls.
				</p>
				<div class="features-wrapper general-tab">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> <a href="https://renoproductgallery.com/#layout-tab" target="_blank">16+ ready-made product gallery</a> layouts & modern designs</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Assign <a href="https://renoproductgallery.com/assign-and-manage-layouts/" target="_blank">different gallery layouts</a> per product or category</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Advanced image & video product gallery system</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Add <a href="https://renoproductgallery.com/additional-variation-gallery/" target="_blank">unlimited variation images</a> for each product variation</li>
						<li class="long-list-item"><i class="sp_wgs-icon-feature-list-checkmark"></i> <a href="https://demo.renoproductgallery.com/product/hooded-track-jacket/" target="_blank"> Product video gallery</a> support from multiple popular video sources (YouTube, Vimeo, Facebook, Wistia, Dailymotion, self-hosted & more)</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Show <a href="https://renoproductgallery.com/product-featured-video/" target="_blank">featured product videos</a> on Shop & Archive pages</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> <a href="https://demo.renoproductgallery.com/product-category/video-autoplay/" target="_blank">Video autoplay</a> or lazy-loaded videos for better performance</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Advanced thumbnail styling & navigation controls</li>
						</ul>
						<ul class="features-list second-column">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Multiple slider effects with smooth animations</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Premium <a href="https://renoproductgallery.com/product-image-zoom/" target="_blank">zoom effects</a> with full customization</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> <a href="https://renoproductgallery.com/product-image-lightbox/" target="_blank">Modern product lightbox</a> with 30+ customization options</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Mobile-optimized layouts with touch-friendly sliders</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Lazy loading & smart performance optimization</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Advanced responsive and styling controls</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Priority, top-notch Pro support</li>
					</ul>
				</div>

				<p class="special-bonus">
					<strong>Special Bonus:</strong> As a Reno Product Gallery Lite user, you’ll receive an exclusive
    				<span class="highlight-discount">50% discount</span> <strong>on a lifetime Pro upgrade.</strong>
				</p>

				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for product gallery tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function product_gallery_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Additional Product Gallery Slider Options Available in Pro</h2>
				<p>
					Get full design control for a professional product gallery slider.
				</p>
				<div class="features-wrapper">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Fixed product gallery height for a consistent layout
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Fully customizable gallery navigation & pagination
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Advanced thumbnail navigation customizations 
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Tons of customization options for a fully personalized product gallery.
						</li>
					</ul>
				</div>

				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for image zoom tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function image_zoom_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Advanced Product Image Zoom</h2>
				<p>
				Give your customers a closer, more confident buying experience with a powerful product image zoom.
				</p>
				<div class="features-wrapper">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Control zoom scale for precise magnification
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Enable mouse wheel zoom for smoother interaction
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Choose from 4 cursor styles
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize zoom lens color & border
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Show product image overlay on hover
						</li>
						</ul>
						<ul class="features-list second-column">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Adjust image overlay color & opacity
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Configure zoom window size, type & distance
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize zoom window border & box shadow
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Exclude Zoom for specific products when needed.
						</li>
					</ul>
				</div>

				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for video gallery tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function video_gallery_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Product Videos in the Gallery</h2>
				<p>
					Add product videos to your galleries for a richer product presentation.
				</p>
				<div class="features-wrapper">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Choose from 10+ beautiful video play icon styles
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Upload and use your own video play icons
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Precisely control video icon position, size, and colors
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Enable auto-play for instant product engagement
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Loop videos for uninterrupted viewing
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Manage self-hosted video player controls
						</li>
					</ul>
					<ul class="features-list second-column">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize self-hosted player appearance
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Adjust player text and font colors
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Control video player background colors
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize video playback progress colors
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Style progress bar background for better visibility
						</li>
						</li><li><i class="sp_wgs-icon-feature-list-checkmark"></i> Configure YouTube video player controls.
						</li>
					</ul>
				</div>

				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for lightbox tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function lightbox_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Advanced Product Image Lightbox</h2>
				<p>
				Give customers a larger, distraction-free view of your products with a powerful lightbox.
				</p>
				<div class="features-wrapper">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize lightbox overlay for a sleek viewing experience
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Show or hide lightbox icons for a cleaner UI
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Enable slideshow mode for smooth media browsing
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Toggle thumbnails gallery visibility with ease
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Add quick-access thumbnails navigation
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Choose stylish thumbnail layouts
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Enable media download with one click
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Zoom, rotate, and interact using transform controls.
						</li>
					</ul>
				</div>

				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}

		/**
		 * Render the "Upgrade to Pro" section markup for shop page tab.
		 *
		 * @return string HTML content for the upgrade section.
		 */
		public static function shop_page_tab() {
			return '<div class="wcgs-upgrade-to-pro-section">
				<h2>Get premium features that enhance product gallery and drive more conversions.</h2>
				<p>
					By upgrading to Reno Product Gallery Pro, you can get access to numerous shop page video features and boost sales, including:
				</p>
				<div class="features-wrapper">
					<ul class="features-list">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Display product featured videos directly on the Shop page
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Play videos inline or in a popup/lightbox
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Choose from 10+ beautiful video play icon styles
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Adjust icon size, color, and position to match your shop design
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Customize video background overlay color
						</li>
						</ul>
						<ul class="features-list second-column">
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Auto-play videos for instant attention
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Enable video looping for continuous engagement
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Show or hide video player controls as needed
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Control video aspect ratio for perfect display
						</li>
						<li><i class="sp_wgs-icon-feature-list-checkmark"></i> Optimized video playback on mobile devices
						</li>
					</ul>
				</div>
				<a href="' . esc_url( WOO_GALLERY_SLIDER_PRO_LINK ) . '" target="_blank" class="upgrade-btn">Upgrade to Pro</a>
				<a href="https://renoproductgallery.com/#features" target="_blank" class="see-full-features">See Full Features <i class="sp_wgs-icon-up-right-arrow"></i></a>
			</div>';
		}
	}

	WCGS::init();
}
