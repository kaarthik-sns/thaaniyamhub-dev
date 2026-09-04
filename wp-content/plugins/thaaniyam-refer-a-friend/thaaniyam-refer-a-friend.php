<?php
/**
 * Plugin Name:       Thaaniyam Refer a Friend
 * Description:       A premium Refer a Friend referral program for WooCommerce. Reward referrers with dynamic coupons and referees with cart discounts.
 * Version:           1.0.0
 * Author:            Searchnscore Solution PVT LTD
 * License:           GPL-2.0-or-later
 * Text Domain:       thaaniyam-refer-a-friend
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Thaaniyam\ReferFriend
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'TRAF_VERSION', '1.0.0' );
define( 'TRAF_PLUGIN_FILE', __FILE__ );
define( 'TRAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TRAF_OPTION_KEY', 'traf_settings' );

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
		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( TRAF_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( TRAF_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialise the plugin after other plugins are loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->load_dependencies();

		// Load textdomain.
		load_plugin_textdomain(
			'thaaniyam-refer-a-friend',
			false,
			dirname( plugin_basename( TRAF_PLUGIN_FILE ) ) . '/languages'
		);

		// Boot components.
		$settings = new Settings\Manager();
		
		// Boot Admin panel & custom post types.
		$admin = new Admin\Manager( $settings );
		$admin->init();

		// Boot Rewards handler (cookie mapping, coupon generation).
		$rewards = new Rewards\Manager( $settings );
		$rewards->init();

		// Boot Frontend (My Account, Single Product integration).
		$frontend = new Frontend\Manager( $settings );
		$frontend->init();
	}

	/**
	 * Require all plugin class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once TRAF_PLUGIN_DIR . 'includes/class-settings.php';
		require_once TRAF_PLUGIN_DIR . 'includes/class-admin.php';
		require_once TRAF_PLUGIN_DIR . 'includes/class-emails.php';
		require_once TRAF_PLUGIN_DIR . 'includes/class-rewards.php';
		require_once TRAF_PLUGIN_DIR . 'includes/class-frontend.php';
	}

	/**
	 * Notice displayed if WooCommerce is not installed or active.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		?>
		<div class="error">
			<p><?php esc_html_e( 'Thaaniyam Refer a Friend requires WooCommerce to be installed and active.', 'thaaniyam-refer-a-friend' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Set default options if none exist.
		if ( ! get_option( TRAF_OPTION_KEY ) ) {
			require_once TRAF_PLUGIN_DIR . 'includes/class-settings.php';
			$defaults = Settings\Manager::get_defaults();
			update_option( TRAF_OPTION_KEY, $defaults, false );
		}
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Flush rewrite rules on deactivation since we use custom My Account endpoints.
		flush_rewrite_rules();
	}
}

// Bootstrap.
Plugin::instance();
