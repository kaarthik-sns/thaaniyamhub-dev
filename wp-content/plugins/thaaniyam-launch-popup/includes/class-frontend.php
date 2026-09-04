<?php
/**
 * Frontend manager — enqueues assets and injects the popup template.
 *
 * @package Thaaniyam\LaunchPopup\Frontend
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\LaunchPopup\Frontend;

use Thaaniyam\LaunchPopup\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles everything related to the frontend launch popup.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings manager.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register all frontend hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only render if popup is enabled.
		if ( '1' !== $this->settings->get( 'enabled', '0' ) ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'register_frontend_hooks' ) );
	}

	/**
	 * Register hooks if the current page matches the targeting rules.
	 *
	 * @return void
	 */
	public function register_frontend_hooks(): void {
		if ( $this->is_coupon_expired() ) {
			return;
		}

		if ( ! $this->should_display_on_page() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
	}

	/**
	 * Determine if current page matches the display rules.
	 *
	 * @return bool
	 */
	private function should_display_on_page(): bool {
		$rule = $this->settings->get( 'display_rules', 'all' );
		if ( 'all' === $rule ) {
			return true;
		}

		$selected_ids = $this->settings->get( 'specified_pages', array() );
		if ( ! is_array( $selected_ids ) ) {
			$selected_ids = array();
		}
		$selected_ids = array_map( 'intval', $selected_ids );

		// Resolve current page/post ID.
		$current_id = (int) get_queried_object_id();

		// Handle WooCommerce shop page check.
		if ( is_post_type_archive( 'product' ) && class_exists( 'WooCommerce' ) ) {
			$shop_page_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_page_id > 0 ) {
				$current_id = $shop_page_id;
			}
		}

		$is_matched = in_array( $current_id, $selected_ids, true );

		if ( 'include' === $rule ) {
			return $is_matched;
		}

		if ( 'exclude' === $rule ) {
			return ! $is_matched;
		}

		return true;
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'tlp-google-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'tlp-popup-style',
			TLP_PLUGIN_URL . 'assets/css/popup.css',
			array(),
			(string) filemtime( TLP_PLUGIN_DIR . 'assets/css/popup.css' )
		);

		wp_enqueue_script(
			'tlp-popup-script',
			TLP_PLUGIN_URL . 'assets/js/popup.js',
			array(),
			(string) filemtime( TLP_PLUGIN_DIR . 'assets/js/popup.js' ),
			true
		);

		// Pass PHP config to JS.
		wp_localize_script(
			'tlp-popup-script',
			'tlpConfig',
			array(
				'delay'            => (int) $this->settings->get( 'delay', 2 ),
				'couponCode'      => esc_html( (string) $this->settings->get( 'coupon_code', 'LAUNCH10' ) ),
				'cooldownEnabled'  => ( '1' === $this->settings->get( 'cooldown_enabled', '1' ) ),
				'userId'           => get_current_user_id(), // 0 for guests, user ID for logged-in users.
			)
		);
	}

	/**
	 * Render and output popup HTML.
	 *
	 * @return void
	 */
	public function render_popup(): void {
		$title       = wp_kses_post( (string) $this->settings->get( 'title', '' ) );
		$description = wp_kses_post( (string) $this->settings->get( 'description', '' ) );
		$coupon_code = esc_html( (string) $this->settings->get( 'coupon_code', '' ) );
		$cta_text    = esc_html( (string) $this->settings->get( 'cta_text', '' ) );

		// Resolve CTA URL based on destination page settings.
		$cta_page = $this->settings->get( 'cta_page', 'custom' );
		$cta_url  = '';

		if ( 'custom' === $cta_page ) {
			$cta_url = (string) $this->settings->get( 'cta_url', '' );
		} elseif ( 'home' === $cta_page ) {
			$cta_url = home_url( '/' );
		} elseif ( 'shop' === $cta_page && class_exists( 'WooCommerce' ) ) {
			$cta_url = get_permalink( wc_get_page_id( 'shop' ) );
		} elseif ( 'cart' === $cta_page && class_exists( 'WooCommerce' ) ) {
			$cta_url = get_permalink( wc_get_page_id( 'cart' ) );
		} elseif ( 'checkout' === $cta_page && class_exists( 'WooCommerce' ) ) {
			$cta_url = get_permalink( wc_get_page_id( 'checkout' ) );
		} elseif ( strpos( $cta_page, 'page_' ) === 0 ) {
			$page_id = (int) substr( $cta_page, 5 );
			$cta_url = get_permalink( $page_id );
		}

		if ( ! $cta_url ) {
			$cta_url = home_url( '/' );
		}

		$cta_url     = esc_url( (string) $cta_url );
		$image_url   = esc_url( (string) $this->settings->get( 'image_url', '' ) );
		if ( ! $image_url ) {
			$image_url = TLP_PLUGIN_URL . 'assets/images/ribbon-cutting.png';
		}

		$expiry_date_formatted = '';
		if ( ! empty( $coupon_code ) && class_exists( 'WC_Coupon' ) ) {
			$coupon = new \WC_Coupon( $coupon_code );
			if ( $coupon->get_id() > 0 ) {
				$expiry_date = $coupon->get_date_expires();
				if ( $expiry_date ) {
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_format' );
					$expiry_date_formatted = $expiry_date->date_i18n( $date_format . ' ' . $time_format );
				}
			}
		}

		// Locate and include the HTML template.
		$template_path = TLP_PLUGIN_DIR . 'templates/popup-template.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Check if the configured coupon has expired.
	 *
	 * @return bool True if expired, false otherwise.
	 */
	private function is_coupon_expired(): bool {
		$coupon_code = $this->settings->get( 'coupon_code', '' );
		if ( empty( $coupon_code ) || ! class_exists( 'WC_Coupon' ) ) {
			return false;
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( $coupon->get_id() > 0 ) {
			$expiry_date = $coupon->get_date_expires();
			if ( $expiry_date ) {
				if ( $expiry_date->getTimestamp() < time() ) {
					return true;
				}
			}
		}

		return false;
	}
}
