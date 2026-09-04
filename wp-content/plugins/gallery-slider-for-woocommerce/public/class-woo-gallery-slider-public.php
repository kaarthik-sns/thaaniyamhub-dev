<?php
/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @link       https://shapedplugin.com/
 * @since      1.0.0
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WooGallery Public class
 */
class Woo_Gallery_Slider_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Slider settings
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array $settings The settings of Slider
	 */
	private $settings;
	/**
	 * Helper functions.
	 *
	 * @var function
	 */
	private $helper;
	/**
	 * Slider settings
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array $settings The settings of Slider
	 */
	private $json_data = array();
	/**
	 * Is_settings_updated
	 *
	 * @var bool
	 */
	private $is_settings_updated = false;
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->settings    = get_option( 'wcgs_settings' );

		spl_autoload_register( array( $this, 'autoload_class' ) );
		spl_autoload_register( array( $this, 'autoload_trait' ) );
		$this->helper = new WCGS_Public_Helper();
		add_filter( 'blocksy:woocommerce:product-view:use-default', array( $this, 'wcgs_product_slider_view' ) );
		add_action( 'activated_plugin', array( $this, 'redirect_help_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_script' ), 100 );
		// Add specific CSS class by filter .
		add_filter( 'body_class', array( $this, 'wcgs_body_class' ), 100 );

		// Register primary shortcode [reno_product_gallery] since the Reno Product Gallery rebrand.
		add_shortcode( 'reno_product_gallery', array( $this, 'wcgs_woocommerce_show_product_images' ) );
		// Legacy shortcode [woogallery] kept for backward compatibility (since version 2.2.3).
		add_shortcode( 'woogallery', array( $this, 'wcgs_woocommerce_show_product_images' ) );
		// Deprecated shortcode [wcgs_gallery_slider] since version 2.2.3 (use [reno_product_gallery] instead).
		add_shortcode(
			'wcgs_gallery_slider',
			function () {
				return do_shortcode( '[reno_product_gallery]' );
			}
		);
		// Remove default WooCommerce gallery lightbox,zoom,slider scripts.
		add_action( 'wp', array( $this, 'wcgs_remove_woo_gallery' ) );
		add_action(
			'elementor/preview/enqueue_styles',
			function () {
				wp_enqueue_style( 'wcgs-elementor', WOO_GALLERY_SLIDER_URL . 'public/css/elementor-widget.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );
			}
		);
	}

	/**
	 * Remove Woo gallery lightbox zoom slider.
	 *
	 * @return void
	 */
	public function wcgs_remove_woo_gallery() {

		$remove_default_wc_gallery = isset( $this->settings['remove_default_wc_gallery'] ) ? $this->settings['remove_default_wc_gallery'] : array( 'lightbox', 'zoom', 'slider' );
		// Remove default WooCommerce gallery lightbox,zoom,slider.
		if ( is_array( $remove_default_wc_gallery ) ) {
			if ( in_array( 'lightbox', $remove_default_wc_gallery, true ) ) {
				remove_theme_support( 'wc-product-gallery-lightbox' );
			}
			if ( in_array( 'zoom', $remove_default_wc_gallery, true ) ) {
				remove_theme_support( 'wc-product-gallery-zoom' );
			}
			if ( in_array( 'slider', $remove_default_wc_gallery, true ) ) {
				remove_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}

	/**
	 * Woo-gallery-slider main class.
	 *
	 * @param  mixed $classes class name.
	 * @return string
	 */
	public function wcgs_body_class( $classes ) {
		if ( is_product() ) {
			$classes = array_merge( $classes, array( 'wcgs-gallery-slider' ) );
		}
		return $classes;
	}

	/**
	 * This function has been used for doing compatible with Blocksy Theme.
	 *
	 * @return true
	 */
	public function wcgs_product_slider_view() {
		if ( is_singular( 'product' ) ) {
			return true;
		}
	}

	/**
	 * Autoload class files on demand
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $class requested class name.
	 */
	private function autoload_class( $class ) {
		$name = explode( '_', $class );
		if ( isset( $name[2] ) ) {
			$class_name        = strtolower( $name[2] );
			$spto_config_paths = array( 'partials' );
			foreach ( $spto_config_paths as $sptp_path ) {
				$filename = plugin_dir_path( __FILE__ ) . '/' . $sptp_path . '/class/class-public-' . $class_name . '.php';
				if ( file_exists( $filename ) ) {
					require_once $filename;
				}
			}
		}
	}

	/**
	 * Autoload trait files on demand
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $trait requested class name.
	 */
	private function autoload_trait( $trait ) {
		$name = explode( '_', $trait );
		if ( isset( $name[2] ) ) {
			$trait_name        = strtolower( $name[2] );
			$spto_config_paths = array( 'partials' );
			foreach ( $spto_config_paths as $sptp_path ) {
				$filename = plugin_dir_path( __FILE__ ) . '/' . $sptp_path . '/trait/trait-public-' . $trait_name . '.php';
				if ( file_exists( $filename ) ) {
					require_once $filename;
				}
			}
		}
	}

	/**
	 * Remove woocommerce_show_product_images and add wcgs function
	 *
	 * @since 1.0.0
	 */
	public function remove_gallery_and_product_images() {
		if ( is_product() ) {
			add_filter( 'wc_get_template', array( $this, 'wpgs_gallery_template_part_override' ), 99, 2 );
		}
	}

	/**
	 * Gallery template part override
	 *
	 * @param  string $template template.
	 * @param  string $template_name template name.
	 * @return string
	 */
	public function wpgs_gallery_template_part_override( $template, $template_name ) {
		if ( 'single-product/product-image.php' !== $template_name ) {
			return $template;
		}
		global $product;
		$old_template = $template;
		if ( 'single-product/product-image.php' === $template_name && $product && ( has_post_thumbnail( $product->get_id() ) || count( $product->get_gallery_image_ids() ) > 0 ) ) {
			$template = WOO_GALLERY_SLIDER_PATH . '/public/partials/product-images.php';
		}
		return $template;
	}
	/**
	 * Redirect after active
	 *
	 * @param string $plugin The plugin help page.
	 */
	public function redirect_help_page( $plugin ) {
		if ( WOO_GALLERY_SLIDER_BASENAME === $plugin && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpgs-help' ) );
			exit();
		}
	}

	/**
	 * When variation change this method do the work
	 *
	 * @param object $product product id.
	 */
	public function wcgs_json_data( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$product_id     = $product->get_id();
			$variation_data = $this->helper->spwg_get_transient( 'spwg_product_variation_' . $product_id );
			if ( ! $variation_data ) {
				$data           = new WCGS_Public_Variations( $this->settings );
				$variation_data = $data->wcgs_json_data( $product );
				$this->helper->spwg_get_transient( 'spwg_product_variation_' . $product_id, $variation_data, WOO_GALLERY_SLIDER_TRANSIENT_EXPIRATION );
			}
			$this->json_data = $variation_data;
		}
		return $this->json_data;
	}

	/**
	 * WCGS product image area method.
	 *
	 * @since 1.0.0
	 */
	public function wcgs_woocommerce_show_product_images() {
		ob_start();
		include WOO_GALLERY_SLIDER_PATH . '/public/partials/product-images.php';
		return ob_get_clean();
	}

	/**
	 * Dequeue scripts for oceanwp theme support.
	 *
	 * @return void
	 */
	public function dequeue_script() {
		if ( is_singular( 'product' ) ) {
			// Dequeue single product css files.
			$dequeue_single_product_css = ! empty( $this->settings['dequeue_single_product_css'] ) ? $this->settings['dequeue_single_product_css'] : '';
			if ( ! empty( $dequeue_single_product_css ) ) {
				$disabled_styles = array_map( 'trim', explode( ',', $dequeue_single_product_css ) );
				foreach ( $disabled_styles as $style ) {
					wp_dequeue_style( $style );
				}
			}
			// Dequeue single product js files.
			$dequeue_single_product_js = ! empty( $this->settings['dequeue_single_product_js'] ) ? $this->settings['dequeue_single_product_js'] : '';
			if ( ! empty( $dequeue_single_product_js ) ) {
				$disabled_js = array_map( 'trim', explode( ',', $dequeue_single_product_js ) );
				foreach ( $disabled_js as $js_handle ) {
					wp_dequeue_script( $js_handle );
				}
			}
			// Dequeue OceanWP lightbox.
			wp_dequeue_script( 'oceanwp-lightbox' );
			if ( apply_filters( 'wcgs_dequeue_magnific_popup', true ) ) {
				wp_dequeue_script( 'magnific-popup' );
			}
		}
	}


	/**
	 * Update with Assigned Layout data.
	 *
	 * @param  object $product product.
	 * @return void
	 */
	private function assigns_layout( $product ) {

		$this->is_settings_updated = true;
		$assigned_layout           = $this->helper->get_assigns_layout( $product );
		// Apply first matching layout.
		if ( ! empty( $assigned_layout ) ) {
			$this->apply_assigned_layout( $assigned_layout[0] );
		}
	}
	/**
	 * Apply the assigned layout settings.
	 *
	 * @param int $layout_id Layout post ID.
	 */
	private function apply_assigned_layout( $layout_id ) {
		$assigned_options = get_post_meta( $layout_id, 'wcgs_metabox', true );
		if ( ! empty( $assigned_options ) ) {
			$this->settings = $this->helper->wp_parse_args_recursive( $assigned_options, $this->settings );
		}
	}
	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woo_Gallery_Slider_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woo_Gallery_Slider_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( is_singular( 'product' ) ) {
			global $post;
			$product_id = $post->ID;
			$product    = wc_get_product( $product_id );
			if ( ! $this->is_settings_updated ) {
				$this->assigns_layout( $product );
			}
			$dynamic_css          = new WCGS_Public_Style( $this->settings );
			$settings             = $this->settings;
			$enqueue_fancybox_js  = isset( $settings['enqueue_fancybox_js'] ) ? $settings['enqueue_fancybox_js'] : true;
			$enqueue_swiper_js    = isset( $settings['enqueue_swiper_js'] ) ? $settings['enqueue_swiper_js'] : true;
			$enqueue_fancybox_css = isset( $settings['enqueue_fancybox_css'] ) ? $settings['enqueue_fancybox_css'] : true;
			$enqueue_swiper_css   = isset( $settings['enqueue_swiper_css'] ) ? $settings['enqueue_swiper_css'] : true;
			$lazy_load_gallery    = isset( $settings['lazy_load_gallery'] ) ? $settings['lazy_load_gallery'] : '1';
			$custom_js            = isset( $settings['wcgs_additional_js'] ) ? $settings['wcgs_additional_js'] : '';
			$custom_js            = isset( $settings['wcgs_additional_js'] ) ? $settings['wcgs_additional_js'] : '';

			wp_enqueue_style( 'sp_wcgs-fontello-fontende-icons', plugin_dir_url( __FILE__ ) . 'css/fontello.min.css', array(), $this->version, 'all' );

			if ( $enqueue_swiper_css ) {
				wp_enqueue_style( 'wcgs-swiper', plugin_dir_url( __FILE__ ) . 'css/swiper.min.css', array(), $this->version, 'all' );
			}
			if ( $enqueue_fancybox_css ) {
				wp_enqueue_style( 'wcgs-fancybox', plugin_dir_url( __FILE__ ) . 'css/fancybox.min.css', array(), $this->version, 'all' );
			}
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woo-gallery-slider-public.min.css', array(), $this->version, 'all' );
			wp_enqueue_style( 'sp_wcgs-fontello-icons', WOO_GALLERY_SLIDER_URL . 'admin/css/fontello.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );

			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/woo-gallery-slider-public.min.js',
				array( 'jquery' ),
				$this->version,
				array(
					'in_footer' => true,
					'strategy'  => 'async',
				)
			);
			if ( ! empty( $custom_js ) ) {
				wp_add_inline_script( $this->plugin_name, $custom_js );
			}
			$dynamic_inline_css = $this->helper->minify_output( $dynamic_css::wcgs_stylesheet_include() );
			wp_add_inline_style( $this->plugin_name, $dynamic_inline_css );
			// Update the json_data.
			$this->wcgs_json_data( $product );

			wp_localize_script(
				$this->plugin_name,
				'wcgs_object',
				array(
					'wcgs_data'             => $this->json_data,
					'wcgs_settings'         => $this->settings,
					'wcgs_other_variations' => apply_filters( 'wcgs_other_variations', '.spswp-shop-variations' ), // To exclude other variations like related products variations.
					'wcgs_body_font_size'   => apply_filters( 'wcgs_body_font_size', '14' ),
					'wcgs_public_url'       => plugin_dir_url( __FILE__ ),
					'lazy_load_gallery'     => $lazy_load_gallery,
				)
			);
		}
	}
}
