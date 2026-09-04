<?php
/**
 * Settings Manager class.
 *
 * @package Thaaniyam\ReferFriend\Settings
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles saving, loading, and defaults for the plugin configuration.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Settings cache.
	 *
	 * @var array
	 */
	private array $settings = array();

	/**
	 * Constructor. Loads settings from the database.
	 */
	public function __construct() {
		$this->load();
	}

	/**
	 * Load settings from db.
	 *
	 * @return void
	 */
	public function load(): void {
		$db_settings = get_option( TRAF_OPTION_KEY, array() );
		if ( ! is_array( $db_settings ) ) {
			$db_settings = array();
		}
		$this->settings = array_merge( self::get_defaults(), $db_settings );
	}

	/**
	 * Retrieve a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting isn't found.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Retrieve all settings.
	 *
	 * @return array
	 */
	public function get_all(): array {
		return $this->settings;
	}

	/**
	 * Update settings.
	 *
	 * @param array $new_settings Settings array.
	 * @return bool True if updated, false otherwise.
	 */
	public function update( array $new_settings ): bool {
		$this->settings = array_merge( $this->settings, $new_settings );
		return update_option( TRAF_OPTION_KEY, $this->settings );
	}

	/**
	 * Get the default plugin settings.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			// General settings.
			'enabled'                 => '1',
			'cookie_expiry'           => 1,
			'param_name'              => 'ref',

			// Referrer Reward.
			'referrer_reward_type'    => 'percent',
			'referrer_reward_value'   => 10,
			'referrer_coupon_expiry'  => 30,
			'email_subject'           => __( 'Your Refer a Friend reward is here!', 'thaaniyam-refer-a-friend' ),
			'email_content'           => __( 'Hi {referrer_name},<br><br>Thank you for referring your friend {friend_email}! They have successfully completed their first purchase.<br><br>As a thank you, here is a discount coupon code for <strong>{discount_value}</strong> off your next order:<br><br><span style="font-size: 20px; font-weight: bold; background: #F7F3EA; color: #2c2a29; border: 1px solid #7A9E22; padding: 12px 24px; display: inline-block; border-radius: 8px; letter-spacing: 1.5px; font-family: monospace;">{coupon_code}</span><br><br>This coupon is valid for {expiry_days} days. Happy shopping!<br><br>Warm regards,<br>Thaaniyam Team', 'thaaniyam-refer-a-friend' ),

			// Referee Reward.
			'referee_reward_type'     => 'percent',
			'referee_reward_value'    => 10,
			'referee_discount_label'  => __( 'Referral Discount', 'thaaniyam-refer-a-friend' ),
		);
	}
}
