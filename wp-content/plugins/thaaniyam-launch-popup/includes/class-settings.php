<?php
/**
 * Settings manager — stores and retrieves plugin options.
 *
 * @package Thaaniyam\LaunchPopup\Settings
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\LaunchPopup\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages all plugin settings stored under TLP_OPTION_KEY.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Cached settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Retrieve all settings, merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$saved       = get_option( TLP_OPTION_KEY, array() );
			$this->cache = wp_parse_args( $saved, self::get_defaults() );
		}
		return $this->cache;
	}

	/**
	 * Return default values for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'          => '1',
			'title'            => __( "🚀 We're Live!", 'thaaniyam-launch-popup' ),
			'description'      => __( 'Celebrate our launch with an exclusive discount on your first order.', 'thaaniyam-launch-popup' ),
			'coupon_code'      => 'LAUNCH10',
			'cta_text'         => __( 'Shop Now', 'thaaniyam-launch-popup' ),
			'cta_page'         => 'custom',
			'cta_url'          => home_url( '/shop' ),
			'image_url'        => '',
			'delay'            => 2,
			'cooldown_enabled' => '1',
			'display_rules'    => 'all',
			'specified_pages'  => array(),
		);
	}

	/**
	 * Get WooCommerce coupons as key => value options.
	 *
	 * @return array<string, string>
	 */
	public static function get_woocommerce_coupons(): array {
		if ( ! class_exists( 'WooCommerce' ) && ! post_type_exists( 'shop_coupon' ) ) {
			return array();
		}

		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $coupons as $coupon ) {
			$code             = $coupon->post_title;
			$options[ $code ] = $code;
		}

		return $options;
	}

	/**
	 * Get all WordPress pages as ID => Title pairs.
	 *
	 * @return array<int, string>
	 */
	public static function get_all_pages(): array {
		$pages = get_pages(
			array(
				'sort_column'  => 'post_title',
				'sort_order'   => 'ASC',
				'post_status'  => 'publish',
			)
		);

		$options = array();
		foreach ( $pages as $page ) {
			$options[ (int) $page->ID ] = $page->post_title;
		}

		return $options;
	}

	/**
	 * Get list of page options for CTA link.
	 *
	 * @return array<string, string>
	 */
	public static function get_cta_page_options(): array {
		$options = array(
			'custom' => __( 'Custom URL (Enter below)', 'thaaniyam-launch-popup' ),
			'home'   => __( 'Homepage', 'thaaniyam-launch-popup' ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$options['shop'] = __( 'WooCommerce Shop Page', 'thaaniyam-launch-popup' );
			}
			$cart_id = wc_get_page_id( 'cart' );
			if ( $cart_id > 0 ) {
				$options['cart'] = __( 'WooCommerce Cart Page', 'thaaniyam-launch-popup' );
			}
			$checkout_id = wc_get_page_id( 'checkout' );
			if ( $checkout_id > 0 ) {
				$options['checkout'] = __( 'WooCommerce Checkout Page', 'thaaniyam-launch-popup' );
			}
		}

		// Add all regular pages.
		foreach ( self::get_all_pages() as $id => $title ) {
			$options[ 'page_' . $id ] = $title;
		}

		return $options;
	}

	/**
	 * Return registered field definitions used by Admin and Settings API.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields(): array {
		$coupon_options = self::get_woocommerce_coupons();

		if ( ! empty( $coupon_options ) ) {
			$coupon_field = array(
				'label'   => __( 'Coupon Code', 'thaaniyam-launch-popup' ),
				'type'    => 'select',
				'default' => 'LAUNCH10',
				'options' => array_merge( array( '' => __( 'Select a coupon...', 'thaaniyam-launch-popup' ) ), $coupon_options ),
			);
		} else {
			$coupon_field = array(
				'label'       => __( 'Coupon Code', 'thaaniyam-launch-popup' ),
				'type'        => 'text',
				'default'     => 'LAUNCH10',
				'placeholder' => 'LAUNCH10',
				'description' => __( 'No active WooCommerce coupons found. Creating one in Marketing -> Coupons will list it here.', 'thaaniyam-launch-popup' ),
			);
		}

		return array(
			'enabled'     => array(
				'label'   => __( 'Enable Popup', 'thaaniyam-launch-popup' ),
				'type'    => 'checkbox',
				'default' => '1',
			),
			'title'       => array(
				'label'       => __( 'Popup Title', 'thaaniyam-launch-popup' ),
				'type'        => 'text',
				'default'     => "🚀 We're Live!",
				'placeholder' => "🚀 We're Live!",
			),
			'description' => array(
				'label'       => __( 'Popup Description', 'thaaniyam-launch-popup' ),
				'type'        => 'textarea',
				'default'     => 'Celebrate our launch with an exclusive discount on your first order.',
				'placeholder' => 'Enter a short description...',
			),
			'coupon_code' => $coupon_field,
			'cta_text'    => array(
				'label'       => __( 'CTA Button Text', 'thaaniyam-launch-popup' ),
				'type'        => 'text',
				'default'     => 'Shop Now',
				'placeholder' => 'Shop Now',
			),
			'cta_page'    => array(
				'label'       => __( 'CTA Button Link Destination', 'thaaniyam-launch-popup' ),
				'type'        => 'select',
				'default'     => 'custom',
				'options'     => self::get_cta_page_options(),
			),
			'cta_url'     => array(
				'label'       => __( 'Custom CTA URL', 'thaaniyam-launch-popup' ),
				'type'        => 'url',
				'default'     => '',
				'placeholder' => 'https://example.com/shop',
				'description' => __( 'Only used if "Custom URL" is selected as the link destination above.', 'thaaniyam-launch-popup' ),
			),
			'image_url'   => array(
				'label'   => __( 'Popup Image', 'thaaniyam-launch-popup' ),
				'type'    => 'image',
				'default' => '',
			),
			'delay'       => array(
				'label'       => __( 'Delay Before Showing (seconds)', 'thaaniyam-launch-popup' ),
				'type'        => 'number',
				'default'     => 2,
				'min'         => 0,
				'max'         => 60,
				'placeholder' => '2',
			),
			'cooldown_enabled' => array(
				'label'       => __( 'Enable 7-Day Cooldown', 'thaaniyam-launch-popup' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'description' => __( 'If checked, the popup will show once every 7 days per visitor. Uncheck to show on every page load.', 'thaaniyam-launch-popup' ),
			),
			'display_rules' => array(
				'label'   => __( 'Where to Show Popup', 'thaaniyam-launch-popup' ),
				'type'    => 'select',
				'default' => 'all',
				'options' => array(
					'all'     => __( 'Show on all pages', 'thaaniyam-launch-popup' ),
					'exclude' => __( 'Show on all pages EXCEPT specified pages', 'thaaniyam-launch-popup' ),
					'include' => __( 'Show ONLY on specified pages', 'thaaniyam-launch-popup' ),
				),
			),
			'specified_pages' => array(
				'label'       => __( 'Specified Pages', 'thaaniyam-launch-popup' ),
				'type'        => 'page_multiselect',
				'default'     => array(),
				'description' => __( 'Search and select pages to target or exclude.', 'thaaniyam-launch-popup' ),
			),
		);
	}
}
