<?php
/**
 * Emails Manager class.
 *
 * @package Thaaniyam\ReferFriend\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend\Emails;

use Thaaniyam\ReferFriend\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares and sends HTML referral reward emails.
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
	 * Format and send the reward email.
	 *
	 * @param \WP_User $referrer      The user who referred.
	 * @param string   $referee_email Email of the friend.
	 * @param string   $coupon_code   Generated coupon.
	 * @return bool True on success, false otherwise.
	 */
	public function send_reward_email( \WP_User $referrer, string $referee_email, string $coupon_code ): bool {
		$subject = $this->settings->get( 'email_subject', __( 'Your Refer a Friend reward is here!', 'thaaniyam-refer-a-friend' ) );
		$content = $this->settings->get( 'email_content', '' );

		// Resolve reward display value.
		$type  = $this->settings->get( 'referrer_reward_type', 'none' );
		$value = (float) $this->settings->get( 'referrer_reward_value', 0 );
		$discount_value = '';
		if ( 'percent' === $type ) {
			$discount_value = $value . '%';
		} elseif ( 'fixed' === $type ) {
			$discount_value = html_entity_decode( strip_tags( wc_price( $value ) ) );
		}

		$expiry_days = (string) $this->settings->get( 'referrer_coupon_expiry', 30 );

		// Replace placeholders in subject and content.
		$placeholders = array(
			'{referrer_name}'  => $referrer->display_name,
			'{friend_email}'   => $referee_email,
			'{coupon_code}'    => $coupon_code,
			'{discount_value}' => $discount_value,
			'{expiry_days}'    => $expiry_days,
		);

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );

		// Load HTML email template.
		$template_path = TRAF_PLUGIN_DIR . 'templates/email-reward-template.php';
		$email_body    = '';

		if ( file_exists( $template_path ) ) {
			ob_start();
			// Pass variables to template scope.
			$email_title   = $subject;
			$email_message = $content;
			include $template_path;
			$email_body = ob_get_clean();
		} else {
			$email_body = $content;
		}

		// Prepare mail parameters.
		$to      = $referrer->user_email;
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Use WooCommerce email filters or default wp_mail.
		return wp_mail( $to, $subject, $email_body, $headers );
	}

	/**
	 * Send the referral invitation email.
	 *
	 * @param string $referrer_name Name of the person referring.
	 * @param string $friend_email  Target email.
	 * @param string $ref_link      The sharing link.
	 * @param int    $product_id    The shared product ID.
	 * @param string $custom_message Custom message.
	 * @return bool
	 */
	public function send_invite_email( string $referrer_name, string $friend_email, string $ref_link, int $product_id = 0, string $custom_message = '', string $sharing_coupon = '' ): bool {
		$site_name = get_bloginfo( 'name' );
		
		// Determine display subject.
		$subject = sprintf( __( '%s has sent you a special invitation to shop at %s!', 'thaaniyam-refer-a-friend' ), $referrer_name, $site_name );
		
		// Retrieve reward discount information.
		$referee_reward_type  = $this->settings->get( 'referee_reward_type', 'none' );
		$referee_reward_value = (float) $this->settings->get( 'referee_reward_value', 0 );
		$reward_text          = '';

		if ( 'none' !== $referee_reward_type && $referee_reward_value > 0 ) {
			if ( 'percent' === $referee_reward_type ) {
				$reward_text = $referee_reward_value . '%';
			} elseif ( 'fixed' === $referee_reward_type ) {
				$reward_text = html_entity_decode( strip_tags( wc_price( $referee_reward_value ) ) );
			}
		}

		// Build content body.
		$message = '';
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			$item_name = $product ? $product->get_name() : __( 'a product', 'thaaniyam-refer-a-friend' );
			$message .= sprintf(
				__( 'Hi there,<br><br>Your friend <strong>%s</strong> thought you would love this product: <strong>%s</strong> at %s.<br><br>', 'thaaniyam-refer-a-friend' ),
				esc_html( $referrer_name ),
				esc_html( $item_name ),
				esc_html( $site_name )
			);

			// Embed product featured image if available.
			if ( $product ) {
				$image_id  = $product->get_image_id();
				$image_src = wp_get_attachment_image_src( $image_id, 'medium' );
				if ( ! empty( $image_src[0] ) ) {
					$message .= sprintf(
						'<div style="text-align: center; margin: 24px 0;">' .
						'<img src="%s" alt="%s" style="max-width: 220px; height: auto; border-radius: 12px; border: 1px solid #e2dbcd; box-shadow: 0 4px 15px rgba(44, 42, 41, 0.08); display: inline-block;">' .
						'</div><br>',
						esc_url( $image_src[0] ),
						esc_attr( $item_name )
					);
				}
			}
		} else {
			$message .= sprintf(
				__( 'Hi there,<br><br>Your friend <strong>%s</strong> has invited you to check out %s.<br><br>', 'thaaniyam-refer-a-friend' ),
				esc_html( $referrer_name ),
				esc_html( $site_name )
			);
		}

		if ( ! empty( $reward_text ) ) {
			$message .= sprintf(
				__( 'Use their special referral link below and you will automatically receive a <strong>%s discount</strong> on your purchase!<br><br>', 'thaaniyam-refer-a-friend' ),
				esc_html( $reward_text )
			);
		}

		if ( ! empty( $custom_message ) ) {
			$message .= '<blockquote style="background: #ffffff; border-left: 4px solid #7A9E22; padding: 12px 18px; margin: 20px 0; font-style: italic; color: #706b69;">';
			$message .= '"' . esc_html( $custom_message ) . '"';
			$message .= '</blockquote>';
		}

		// Center-aligned coupon code block.
		if ( ! empty( $sharing_coupon ) ) {
			$message .= '<div style="text-align: center; margin: 24px 0;">';
			$message .= '<p style="font-size: 13px; color: #706b69; margin-bottom: 8px; font-weight: 600;">' . esc_html__( 'Use coupon code at checkout:', 'thaaniyam-refer-a-friend' ) . '</p>';
			$message .= sprintf(
				'<div style="display: inline-block; background-color: #ffffff; border: 1px solid #2c2a29; padding: 12px 30px; font-family: monospace; font-size: 18px; font-weight: 700; color: #2c2a29; letter-spacing: 2px; text-transform: uppercase; border-radius: 4px;">%s</div>',
				esc_html( $sharing_coupon )
			);
			$message .= '</div>';
		}

		// Dynamic Call-To-Action button.
		$message .= '<div style="text-align: center; margin: 30px 0;">';
		$message .= sprintf(
			'<a href="%s" style="background-color: #7A9E22; color: #ffffff !important; text-decoration: none !important; font-weight: 700; padding: 14px 28px; border-radius: 8px; display: inline-block; box-shadow: 0 4px 10px rgba(122,158,34,0.2); letter-spacing: 0.5px;">%s</a>',
			esc_url( $ref_link ),
			__( 'Shop Now & Claim Discount', 'thaaniyam-refer-a-friend' )
		);
		$message .= '</div>';

		// Load HTML email template.
		$template_path = TRAF_PLUGIN_DIR . 'templates/email-reward-template.php';
		$email_body    = '';

		if ( file_exists( $template_path ) ) {
			ob_start();
			$email_title   = $subject;
			$email_message = $message;
			include $template_path;
			$email_body = ob_get_clean();
		} else {
			$email_body = $message;
		}

		// Prepare mail parameters.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		return wp_mail( $friend_email, $subject, $email_body, $headers );
	}
}
