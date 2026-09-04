<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/includes
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * WooGallery class
 */
class Woo_Gallery_Slider {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woo_Gallery_Slider_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		if ( defined( 'WOO_GALLERY_SLIDER_VERSION' ) ) {
			$this->version = WOO_GALLERY_SLIDER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'gallery-slider-for-woocommerce';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$active_plugins = get_option( 'active_plugins' );
		foreach ( $active_plugins as $active_plugin ) {
			$_temp = strpos( $active_plugin, 'woo-gallery-slider.php' );
			if ( $_temp ) {
				add_filter( 'plugin_action_links_' . $active_plugin, array( $this, 'add_settings_links' ) );
			}
		}
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 10 );
		if ( class_exists( 'WC_PRODUCT_VIDEO_GALLERY_RENDERING' ) ) {
			remove_action( 'plugins_loaded', 'nickx_remove_woo_hooks' );
		}
	}

	/**
	 * Create settings, Pro link at plugins bottom.
	 *
	 * @since 2.0.0
	 * @param string $links links provided by WordPress.
	 */
	public function add_settings_links( $links ) {
		$new_links = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wpgs-settings' ), esc_html__( 'Settings', 'gallery-slider-for-woocommerce' ) );

		array_unshift( $links, $new_links );

		$links['go_pro'] = sprintf( '<a href="%s" target="_blank" style="%s">%s</a>', esc_url( WOO_GALLERY_SLIDER_PRO_LINK ), 'color:#1dab87;font-weight:bold', __( 'Go Pro!', 'gallery-slider-for-woocommerce' ) );

		return $links;
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woo_Gallery_Slider_Loader. Orchestrates the hooks of the plugin.
	 * - Woo_Gallery_Slider_Admin. Defines all hooks for the admin area.
	 * - Woo_Gallery_Slider_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-woo-gallery-slider-loader.php';

		require_once plugin_dir_path( __DIR__ ) . 'admin/help-page/help.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-woo-gallery-slider-admin.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-woo-gallery-slider-import-export.php';

		/**
		 * ShapedPlugin framework
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/partials/shapedplugin-framework/classes/setup.class.php';

		/**
		 * Admin review notice.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/partials/class-wgs-admin-notices.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-woo-gallery-slider-public.php';
		// Handles the import and export of custom gallery images for WooCommerce products.
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-woo-gallery-slider-variation-image-import.php';
		// WC block for adding variation images in Product editor beta.
		require_once plugin_dir_path( __DIR__ ) . 'block/variation-images/variation-images.php';
		$this->loader = new Woo_Gallery_Slider_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woo_Gallery_Slider_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'init', $plugin_admin, 'wcgs_layouts_post_type' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'woocommerce_product_after_variable_attributes', $plugin_admin, 'woocommerce_add_gallery_product_variation', 10, 3 );
		$this->loader->add_action( 'woocommerce_save_product_variation', $plugin_admin, 'woocommerce_save_gallery_product_variation', 10, 2 );
		$this->loader->add_action( 'save_post', $plugin_admin, 'spwg_product_variation_transient_data_clear' );
		$this->loader->add_filter( 'attachment_fields_to_edit', $plugin_admin, 'wcgs_add_media_custom_field', 99, 2 );
		$this->loader->add_filter( 'admin_body_class', $plugin_admin, 'wcgs_admin_body_class' );
		$this->loader->add_filter( 'update_footer', $plugin_admin, 'wcgs_footer_version', 11 );
		$this->loader->add_filter( 'edit_attachment', $plugin_admin, 'wcgs_add_media_custom_field_save' );
		// Export Import Ajax call.
		$import_export = new Woo_Gallery_Slider_Import_Export( $this->get_plugin_name(), $this->get_version() );
		add_action( 'wp_ajax_wcgs_export_layouts', array( $import_export, 'export_shortcode' ) );
		add_action( 'wp_ajax_wcgs_import_layouts', array( $import_export, 'import_shortcode' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Woo_Gallery_Slider_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 80 );

		$this->loader->add_filter( 'wc_get_template', $plugin_public, 'wpgs_gallery_template_part_override', 99, 2 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woo_Gallery_Slider_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
