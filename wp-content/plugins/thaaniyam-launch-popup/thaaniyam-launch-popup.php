<?php
/**
 * Plugin Name:       Thaaniyam Launch Popup
 * Description:       Display a premium launch offer popup for WooCommerce visitors to promote website launch discounts.
 * Version:           1.0.0
 * Author:            Searchnscore Solution PVT LTD
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       thaaniyam-launch-popup
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Thaaniyam\LaunchPopup
 */

declare( strict_types=1 );

namespace Thaaniyam\LaunchPopup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'TLP_VERSION', '1.0.0' );
define( 'TLP_PLUGIN_FILE', __FILE__ );
define( 'TLP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TLP_OPTION_KEY', 'tlp_settings' );

/**
 * Main plugin bootstrap class.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Require all plugin class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once TLP_PLUGIN_DIR . 'includes/class-settings.php';
		require_once TLP_PLUGIN_DIR . 'includes/class-admin.php';
		require_once TLP_PLUGIN_DIR . 'includes/class-frontend.php';
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( TLP_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( TLP_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialise sub-components after all plugins are loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// Load textdomain.
		load_plugin_textdomain(
			'thaaniyam-launch-popup',
			false,
			dirname( plugin_basename( TLP_PLUGIN_FILE ) ) . '/languages'
		);

		// Boot admin.
		$settings = new Settings\Manager();
		$admin    = new Admin\Manager( $settings );
		$admin->init();

		// Boot frontend.
		$frontend = new Frontend\Manager( $settings );
		$frontend->init();
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Set default options if none exist.
		if ( ! get_option( TLP_OPTION_KEY ) ) {
			$defaults = Settings\Manager::get_defaults();
			update_option( TLP_OPTION_KEY, $defaults, false );
		}
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Nothing to clean up on deactivation.
	}
}

// Bootstrap.
Plugin::instance();
