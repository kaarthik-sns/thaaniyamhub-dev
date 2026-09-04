<?php

/**
 * WCFM plugin controllers
 *
 * Plugin Memberships Payment Controller
 *
 * @author 		WC Lovers
 * @package 	wcfmvm/controllers
 * @version   1.0.0
 */

class WCFMvm_Memberships_Payment_Controller {

	public function __construct() {
		global $WCFM, $WCFMu;

		$this->processing();
	}

	public function processing() {
		global $WCFM, $WCFMvm, $wpdb, $wcfm_membership_payment_form_data;

		$wcfm_membership_payment_form_data = array();
		parse_str($_POST['wcfm_membership_payment_form'], $wcfm_membership_payment_form_data);

		$wcfm_membership_payment_form_data = wc_clean($wcfm_membership_payment_form_data);

		$wcfm_membership_payment_messages = get_wcfmvm_membership_payment_messages();
		$has_error = false;

		if (isset($wcfm_membership_payment_form_data['member_id']) && !empty($wcfm_membership_payment_form_data['member_id'])) {
			$member_id 			= absint($wcfm_membership_payment_form_data['member_id']);
			
			if ($member_id && !$this->is_valid_member_id( $member_id )) {
				echo '{"status": false, "message": "' . esc_html__( 'Not a valid member', 'wc-multivendor-membership' ) . '"}';
				die;
			}

			$wcfm_membership	= get_user_meta($member_id, 'temp_wcfm_membership', true);
			$paymode 			= wc_clean($_POST['paymode']);

			if ($wcfm_membership) {
				$subscription 	= (array) get_post_meta($wcfm_membership, 'subscription', true);
				$is_free 		= isset($subscription['is_free']);

				/**
				 * This controller finalises the subscription and registers the vendor WITHOUT
				 * contacting any payment gateway, so it must only ever complete:
				 *
				 *   - Free memberships (nothing is due), or
				 *   - Paid memberships paid with an *offline* method the admin has enabled
				 *     (e.g. Bank Transfer) that is reconciled outside the site.
				 *
				 * Online gateways (PayPal, Stripe) must NOT be completed here -- they are
				 * finalised only after the gateway returns a verified IPN (see
				 * ipn/wcfmvm-handle-pp-ipn.php and ipn/wcfmvm-handle-stripe-sca-*-ipn.php).
				 * Completing them here would let a member activate a paid vendor membership
				 * without ever paying (CVE-2026-12967). The paymode is also validated against
				 * the admin allow-list so a disabled method cannot be forced via a direct
				 * request (Broken Access Control).
				 */
				if ($is_free) {
					// Free plan: payment mode is irrelevant, normalise it.
					$paymode = 'free';
				} else {
					$enabled_payment_methods = get_wcfm_membership_enabled_payment_methods();
					$offline_payment_methods = get_wcfm_membership_offline_payment_methods();

					if (!in_array($paymode, $enabled_payment_methods, true) || !in_array($paymode, $offline_payment_methods, true)) {
						echo '{"status": false, "message": "' . esc_html($wcfm_membership_payment_messages['invalid_payment_method']) . '"}';
						die;
					}
				}

				update_user_meta($member_id, 'wcfm_membership_paymode', $paymode);
				$required_approval = get_post_meta($wcfm_membership, 'required_approval', true) ? get_post_meta($wcfm_membership, 'required_approval', true) : 'no';

				if ($required_approval != 'yes') {
					$has_error = $WCFMvm->register_vendor($member_id);
					$WCFMvm->store_subscription_data($member_id, $paymode, '', 'free_subscription', 'Completed', '');
				} else {
					$WCFMvm->send_approval_reminder_admin($member_id);
				}

				// Reset Membership Session
				if (WC()->session && WC()->session->get('wcfm_membership')) {
					WC()->session->__unset('wcfm_membership');
				}

				if (!$has_error) {
					echo '{"status": true, "message": "' . esc_html($wcfm_membership_payment_messages['subscription_success']) . '", "redirect": "' . esc_url(apply_filters('wcfm_registration_thankyou_url', add_query_arg('vmstep', 'thankyou', get_wcfm_membership_url()))) . '"}';
				} else {
					echo '{"status": false, "message": "' . esc_html($wcfm_membership_payment_messages['subscription_failed']) . '"}';
				}
			} else {
				echo '{"status": false, "message": "' . esc_html($wcfm_membership_payment_messages['no_memberid']) . '"}';
			}
		} else {
			echo '{"status": false, "message": "' . esc_html($wcfm_membership_payment_messages['no_memberid']) . '"}';
		}

		die;
	}

	protected function is_valid_member_id( $member_id ) {
		$is_valid_member = false;

		if ((get_current_user_id() == $member_id) && wcfm_is_allowed_membership()) {
			$is_valid_member = true;
		}

		return $is_valid_member;
	}
}
