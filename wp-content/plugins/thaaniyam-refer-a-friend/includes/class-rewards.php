<?php
/**
 * Rewards Manager class.
 *
 * @package Thaaniyam\ReferFriend\Rewards
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend\Rewards;

use Thaaniyam\ReferFriend\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles referral cookies, cart discounts, order metadata mapping, and coupon generation.
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
	 * Register rewards hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( '1' !== $this->settings->get( 'enabled', '1' ) ) {
			return;
		}

		// Track and save referral parameter.
		add_action( 'template_redirect', array( $this, 'check_referral_cookie' ) );

		// Apply referee cart discount.
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_referee_discount' ), 10, 1 );

		// Save referrer details during checkout order creation.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_referrer_to_order' ), 10, 2 );

		// Trigger referrer rewards upon completed order status.
		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger_referrer_reward' ), 10, 1 );

		// Customize referral coupon label in checkout/cart.
		add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'custom_coupon_label' ), 10, 2 );

		// Block self-referral coupon usage.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_referral_coupon' ), 10, 3 );
	}

	/**
	 * Check for referral query parameters and set the tracking cookie.
	 *
	 * @return void
	 */
	public function check_referral_cookie(): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$param = $this->settings->get( 'param_name', 'ref' );
		if ( empty( $_GET[ $param ] ) ) {
			return;
		}

		$ref_val     = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
		$referrer_id = 0;

		// Try resolving as user ID.
		if ( is_numeric( $ref_val ) ) {
			$user = get_userdata( (int) $ref_val );
			if ( $user ) {
				$referrer_id = $user->ID;
			}
		}

		// Try resolving as username.
		if ( ! $referrer_id ) {
			$user = get_user_by( 'login', $ref_val );
			if ( $user ) {
				$referrer_id = $user->ID;
			}
		}

		if ( ! $referrer_id ) {
			return;
		}

		// Self-referral protection.
		if ( is_user_logged_in() && get_current_user_id() === $referrer_id ) {
			return;
		}

		// Set the tracking cookie & session timestamp.
		$expiry_days   = max( 1, (int) $this->settings->get( 'cookie_expiry', 1 ) );
		$expires_at    = time() + ( $expiry_days * DAY_IN_SECONDS );
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';

		setcookie(
			'thaaniyam_ref_referrer',
			(string) $referrer_id,
			$expires_at,
			COOKIEPATH,
			$cookie_domain,
			is_ssl(),
			true
		);

		// Also store in WC Session with strict expiry timestamp.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'thaaniyam_ref_referrer', $referrer_id );
			WC()->session->set( 'thaaniyam_ref_expiry', $expires_at );
		}
	}

	/**
	 * Retrieve active referrer ID if tracking has not expired.
	 *
	 * @return int Referrer User ID or 0 if expired/absent.
	 */
	public function get_active_referrer_id(): int {
		$referrer_id = 0;
		$expires_at  = 0;

		if ( function_exists( 'WC' ) && WC()->session ) {
			$expires_at = (int) WC()->session->get( 'thaaniyam_ref_expiry' );
		}

		if ( isset( $_COOKIE['thaaniyam_ref_referrer'] ) ) {
			$referrer_id = (int) $_COOKIE['thaaniyam_ref_referrer'];
		} elseif ( function_exists( 'WC' ) && WC()->session && $expires_at > 0 && time() <= $expires_at ) {
			$referrer_id = (int) WC()->session->get( 'thaaniyam_ref_referrer' );
		}

		if ( $expires_at > 0 && time() > $expires_at ) {
			$this->clear_referral_tracking();
			return 0;
		}

		return $referrer_id;
	}

	/**
	 * Clear active referral cookies and WC session keys.
	 *
	 * @return void
	 */
	public function clear_referral_tracking(): void {
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
		setcookie( 'thaaniyam_ref_referrer', '', time() - 3600, COOKIEPATH, $cookie_domain, is_ssl(), true );
		unset( $_COOKIE['thaaniyam_ref_referrer'] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'thaaniyam_ref_referrer', null );
			WC()->session->set( 'thaaniyam_ref_expiry', null );
		}
	}

	/**
	 * Apply referee reward discount to cart if the referral cookie is present.
	 *
	 * @param \WC_Cart $cart WooCommerce cart object.
	 * @return void
	 */
	public function apply_referee_discount( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$type = $this->settings->get( 'referee_reward_type', 'none' );
		if ( 'none' === $type ) {
			return;
		}

		// Require user to be logged in to receive referral discount.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$referrer_id = $this->get_active_referrer_id();

		if ( ! $referrer_id ) {
			return;
		}

		// Verify referrer user exists.
		if ( ! get_userdata( $referrer_id ) ) {
			return;
		}

		// Do not apply discount if referrer is logged in.
		if ( is_user_logged_in() && get_current_user_id() === $referrer_id ) {
			return;
		}

		// Do not apply discount if referee has already completed orders (not a first purchase).
		if ( is_user_logged_in() ) {
			$customer_orders = wc_get_orders( array(
				'customer' => get_current_user_id(),
				'status'   => array( 'completed', 'processing' ),
				'limit'    => 1,
			) );
			if ( ! empty( $customer_orders ) ) {
				return;
			}
		}

		// Prevent double discounting if they already applied the referral coupon code
		$applied_coupons = $cart->get_applied_coupons();
		if ( ! empty( $applied_coupons ) ) {
			foreach ( $applied_coupons as $code ) {
				$coupon_id = wc_get_coupon_id_by_code( $code );
				if ( $coupon_id ) {
					$coupon = new \WC_Coupon( $coupon_id );
					if ( $coupon->get_meta( '_traf_referrer_id' ) ) {
						return; // Skip applying automatic cart fee since coupon is already active!
					}
				}
			}
		}

		$value = (float) $this->settings->get( 'referee_reward_value', 0 );
		if ( $value <= 0 ) {
			return;
		}

		$discount = 0.0;
		$label    = $this->settings->get( 'referee_discount_label', __( 'Referral Discount', 'thaaniyam-refer-a-friend' ) );
		$subtotal = (float) $cart->get_subtotal();

		if ( 'percent' === $type ) {
			$discount = $subtotal * ( $value / 100 );
		} elseif ( 'fixed' === $type ) {
			$discount = min( $value, $subtotal );
		}

		if ( $discount > 0 ) {
			$cart->add_fee( $label, -$discount, true );
		}
	}

	/**
	 * Link the referrer ID to the order metadata on checkout.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $data  Checkout POST data.
	 * @return void
	 */
	public function save_referrer_to_order( \WC_Order $order, array $data ): void {
		$referrer_id = $this->get_active_referrer_id();

		if ( $referrer_id && get_userdata( $referrer_id ) ) {
			$order->update_meta_data( '_thaaniyam_referrer_id', $referrer_id );
		}
	}

	/**
	 * Trigger the reward workflow when an order is completed.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 * @return void
	 */
	public function trigger_referrer_reward( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Extract referrer user ID.
		$referrer_id = (int) $order->get_meta( '_thaaniyam_referrer_id' );
		
		// Fallback: Check if a sharing coupon was used
		if ( ! $referrer_id ) {
			$used_coupons = $order->get_coupon_codes();
			if ( ! empty( $used_coupons ) ) {
				foreach ( $used_coupons as $code ) {
					$coupon_id = wc_get_coupon_id_by_code( $code );
					if ( $coupon_id ) {
						$coupon = new \WC_Coupon( $coupon_id );
						$ref_id = (int) $coupon->get_meta( '_traf_referrer_id' );
						if ( $ref_id ) {
							$referrer_id = $ref_id;
							// Save it to order metadata for future reference
							$order->update_meta_data( '_thaaniyam_referrer_id', $referrer_id );
							break;
						}
					}
				}
			}
		}

		if ( ! $referrer_id ) {
			return;
		}

		// Avoid duplicate rewards.
		if ( '1' === $order->get_meta( '_thaaniyam_referral_rewarded' ) ) {
			return;
		}

		$referrer = get_userdata( $referrer_id );
		if ( ! $referrer ) {
			return;
		}

		// Double-check referral logs to ensure this order wasn't already processed.
		$existing = get_posts( array(
			'post_type'   => 'thaaniyam_referral',
			'post_status' => 'any',
			'meta_query'  => array(
				array(
					'key'     => '_referee_order_id',
					'value'   => $order_id,
					'compare' => '=',
				),
			),
		) );
		if ( ! empty( $existing ) ) {
			return;
		}

		// Generate the unique discount coupon.
		$coupon_code = $this->create_referrer_coupon( $referrer_id, $order );

		// Mark order metadata.
		$order->update_meta_data( '_thaaniyam_referral_rewarded', '1' );
		$order->save();

		// Record the successful referral in Custom Post Type.
		$reward_amount = $this->get_formatted_reward_amount();
		$referee_email = $order->get_billing_email();

		$referral_post_id = wp_insert_post( array(
			'post_title'  => sprintf( 'Referral for %s (Order #%s)', $referee_email, $order_id ),
			'post_status' => 'publish',
			'post_type'   => 'thaaniyam_referral',
		) );

		if ( $referral_post_id ) {
			update_post_meta( $referral_post_id, '_referrer_id', $referrer_id );
			update_post_meta( $referral_post_id, '_referee_email', $referee_email );
			update_post_meta( $referral_post_id, '_referee_order_id', $order_id );
			if ( $coupon_code ) {
				update_post_meta( $referral_post_id, '_reward_coupon_code', $coupon_code );
				update_post_meta( $referral_post_id, '_reward_amount', $reward_amount );
			}
		}

		// Trigger reward email to the referrer if a coupon was actually created.
		if ( $coupon_code ) {
			$emails = new \Thaaniyam\ReferFriend\Emails\Manager( $this->settings );
			$emails->send_reward_email( $referrer, $referee_email, $coupon_code );
		}
	}

	/**
	 * Programmatically create a WooCommerce coupon for the referrer.
	 *
	 * @param int       $referrer_id Referrer User ID.
	 * @param \WC_Order $order       Triggering referee order.
	 * @return string|null Coupon code if created, null otherwise.
	 */
	private function create_referrer_coupon( int $referrer_id, \WC_Order $order ): ?string {
		$type = $this->settings->get( 'referrer_reward_type', 'none' );
		if ( 'none' === $type ) {
			return null;
		}

		$value = (float) $this->settings->get( 'referrer_reward_value', 0 );
		if ( $value <= 0 ) {
			return null;
		}

		$referrer = get_userdata( $referrer_id );
		if ( ! $referrer ) {
			return null;
		}

		// Generate random password code.
		$code = 'REF-' . strtoupper( wp_generate_password( 4, false ) ) . '-' . strtoupper( wp_generate_password( 4, false ) );

		// Instantiate WooCommerce Coupon.
		$coupon           = new \WC_Coupon();
		$wc_discount_type = ( 'fixed' === $type ) ? 'fixed_cart' : 'percent';

		$coupon->set_code( $code );
		$coupon->set_discount_type( $wc_discount_type );
		$coupon->set_amount( $value );

		// Set limits and restrict by referrer email to prevent abuse.
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( $referrer->user_email ) );

		// Expiry date mapping.
		$expiry_days = (int) $this->settings->get( 'referrer_coupon_expiry', 30 );
		$expiry_date = new \WC_DateTime();
		$expiry_date->add( new \DateInterval( 'P' . $expiry_days . 'D' ) );
		$coupon->set_date_expires( $expiry_date );

		$coupon->update_meta_data( '_traf_reward_coupon', '1' );
		$coupon->set_description( sprintf( 'Referral reward coupon for referring %s (Order #%s)', $order->get_billing_email(), $order->get_id() ) );
		$coupon->save();

		// Set post_author to 0 to make it site-wide (not restricted by vendor)
		wp_update_post( array(
			'ID'          => $coupon->get_id(),
			'post_author' => 0,
		) );

		return $code;
	}

	/**
	 * Get human readable formatting of the reward value.
	 *
	 * @return string
	 */
	public function get_formatted_reward_amount(): string {
		$type  = $this->settings->get( 'referrer_reward_type', 'none' );
		$value = (float) $this->settings->get( 'referrer_reward_value', 0 );
		if ( 'none' === $type || $value <= 0 ) {
			return __( 'No Reward', 'thaaniyam-refer-a-friend' );
		}
		if ( 'percent' === $type ) {
			return $value . '%';
		}
		return html_entity_decode( strip_tags( wc_price( $value ) ) );
	}

	/**
	 * Get or create a permanent sharing coupon for a referrer user.
	 *
	 * @param int $referrer_id Referrer User ID.
	 * @return string Coupon code.
	 */
	public function get_or_create_sharing_coupon( int $referrer_id ): string {
		$coupon_code = get_user_meta( $referrer_id, '_traf_sharing_coupon_code', true );
		if ( $coupon_code ) {
			// Check if coupon still exists in WooCommerce.
			$coupon_id = wc_get_coupon_id_by_code( $coupon_code );
			if ( $coupon_id ) {
				return $coupon_code;
			}
		}

		// Generate new unique coupon code.
		$user = get_userdata( $referrer_id );
		if ( ! $user ) {
			return '';
		}
		$sanitized_username = preg_replace( '/[^A-Za-z0-9]/', '', $user->user_login );
		$code = 'REF-' . strtoupper( $sanitized_username ) . '-' . strtoupper( wp_generate_password( 4, false ) );

		// Create WooCommerce Coupon.
		$type  = $this->settings->get( 'referee_reward_type', 'none' );
		$value = (float) $this->settings->get( 'referee_reward_value', 0 );
		if ( 'none' === $type || $value <= 0 ) {
			// Fallback defaults: 10% discount.
			$type  = 'percent';
			$value = 10.0;
		}

		$coupon           = new \WC_Coupon();
		$wc_discount_type = ( 'fixed' === $type ) ? 'fixed_cart' : 'percent';

		$coupon->set_code( $code );
		$coupon->set_discount_type( $wc_discount_type );
		$coupon->set_amount( $value );
		$coupon->set_individual_use( true );
		
		// Sharing coupon is reusable, so no usage limit, but we track who created it.
		$coupon->update_meta_data( '_traf_referrer_id', $referrer_id );
		$coupon->set_description( sprintf( 'Sharing coupon for referrer: %s', $user->user_email ) );
		$coupon->save();

		// Set post_author to 0 to make it site-wide (not restricted by vendor)
		wp_update_post( array(
			'ID'          => $coupon->get_id(),
			'post_author' => 0,
		) );

		// Save in user meta.
		update_user_meta( $referrer_id, '_traf_sharing_coupon_code', $code );

		return $code;
	}

	public function custom_coupon_label( string $label, \WC_Coupon $coupon ): string {
		// Check if it's a sharing coupon (Friend Referral)
		if ( $coupon->get_meta( '_traf_referrer_id' ) ) {
			return sprintf( __( 'Friend Referral: (%s)', 'thaaniyam-refer-a-friend' ), $coupon->get_code() );
		}

		// Check if it's a reward coupon (Referrer Reward)
		if ( $coupon->get_meta( '_traf_reward_coupon' ) ) {
			return sprintf( __( 'Referral Reward: (%s)', 'thaaniyam-refer-a-friend' ), $coupon->get_code() );
		}

		return $label;
	}

	/**
	 * Prevent a user from applying their own referral sharing coupon to their cart.
	 *
	 * @param bool       $is_valid Existing validity value.
	 * @param \WC_Coupon $coupon   Coupon object.
	 * @param mixed      $discount Discount context object.
	 * @return bool
	 * @throws \Exception When user tries to use their own referral coupon.
	 */
	public function validate_referral_coupon( bool $is_valid, \WC_Coupon $coupon, $discount ): bool {
		if ( ! is_user_logged_in() ) {
			return $is_valid;
		}

		$referrer_id = (int) $coupon->get_meta( '_traf_referrer_id' );
		if ( $referrer_id && $referrer_id === get_current_user_id() ) {
			throw new \Exception( __( 'You cannot use your own referral sharing coupon.', 'thaaniyam-refer-a-friend' ) );
		}

		return $is_valid;
	}
}
