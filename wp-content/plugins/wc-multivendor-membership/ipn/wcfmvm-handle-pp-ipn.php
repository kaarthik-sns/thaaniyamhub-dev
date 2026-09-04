<?php

class wcfmvm_paypal_ipn_handler {

	public $last_error;                 // holds the last error encountered
	public $ipn_log = false;            // bool: log IPN results to text file?
	public $ipn_response;               // holds the IPN response from paypal
	public $ipn_data = array();         // array contains the POST values for IPN
	public $fields = array();           // array holds the fields to submit to paypal
	public $sandbox_mode = false;
	public $paypal_email = '';
	public $paypal_url = '';
	public $post_string = '';

	function __construct() {
		$this->paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
		$this->last_error = '';
		$this->ipn_response = '';
		
		$wcfm_membership_options = get_option( 'wcfm_membership_options', array() );
 		$membership_payment_settings = array();
		if( isset( $wcfm_membership_options['membership_payment_settings'] ) ) $membership_payment_settings = $wcfm_membership_options['membership_payment_settings'];
		$this->paypal_email = ( isset($membership_payment_settings['paypal_email']) && $membership_payment_settings['paypal_email'] ) ? $membership_payment_settings['paypal_email'] : '';
		$this->sandbox_mode = isset( $membership_payment_settings['paypal_sandbox'] ) ? true : false;
	}

	/**
	 * Claim a transaction id before processing it - the atomic replay guard.
	 *
	 * See wcfmvm_claim_transaction(): a get_option() / update_option() pair let two
	 * concurrent IPN retries both process the same payment.
	 *
	 * @param string $txn_id Gateway transaction id.
	 * @return bool True when this request claimed the transaction.
	 */
	public function claim_transaction( $txn_id ) {
		return wcfmvm_claim_transaction( 'paypal', $txn_id );
	}

	/**
	 * Release a claim taken by claim_transaction().
	 *
	 * PayPal re-sends an IPN for the same transaction when it was not accepted the
	 * first time - a payment that had not cleared yet, or a misconfigured receiver
	 * email - so a transaction that was NOT processed has to become claimable again.
	 *
	 * @param string $txn_id Gateway transaction id.
	 */
	public function release_transaction( $txn_id ) {
		wcfmvm_release_transaction( 'paypal', $txn_id );
	}

	function wcfmvm_validate_and_create_membership() {
		$txn_id = isset( $this->ipn_data['txn_id'] ) ? $this->ipn_data['txn_id'] : '';

		// Prevent Replay Attacks
		if ( ! empty( $txn_id ) && ! $this->claim_transaction( $txn_id ) ) {
			wcfmvm_create_log( 'Transaction ' . $txn_id . ' already processed. Aborting to prevent replay.' );
			return false;
		}

		$processed = $this->process_membership_ipn();

		// Nothing was done with this transaction, let PayPal retry it.
		if ( ! $processed && ! empty( $txn_id ) ) {
			$this->release_transaction( $txn_id );
		}

		return $processed;
	}

  function process_membership_ipn() {
  	global $WCFM, $WCFMvm, $wpdb;

		// Check Product Name , Price , Currency , Receivers email ,
		$error_msg = "";

		// Read the IPN and validate
		$gross_total = $this->ipn_data['mc_gross'];
		$transaction_type = $this->ipn_data['txn_type'];
		$txn_id = isset($this->ipn_data['txn_id']) ? $this->ipn_data['txn_id'] : '';
		$payment_status = isset($this->ipn_data['payment_status']) ? $this->ipn_data['payment_status'] : '';

		// Check receiver email
		$receiver_email = isset($this->ipn_data['receiver_email']) ? strtolower(trim($this->ipn_data['receiver_email'])) : '';
		$business_email = isset($this->ipn_data['business']) ? strtolower(trim($this->ipn_data['business'])) : '';
		$configured_email = strtolower(trim($this->paypal_email));
		
		if ( empty( $configured_email ) ) {
			wcfmvm_create_log('PayPal Email is not configured. Aborting the process.');
			return false;
		}
		
		if ( $receiver_email != $configured_email && $business_email != $configured_email ) {
			wcfmvm_create_log('PayPal Receiver Email mismatch. Aborting the process.');
			return false;
		}
			
		//Check payment status
		if (!empty($payment_status)) {
			if ($payment_status == "Denied") {
				wcfmvm_create_log("Payment status for this transaction is DENIED. You denied the transaction... most likely a cancellation of an eCheque. Nothing to do here.");
				return false;
			}
			if ($payment_status == "Canceled_Reversal") {
				wcfmvm_create_log("This is a dispute closed notification in your favour. The plugin will not do anyting.");
				return true;
			}
			if ($payment_status != "Completed" && $payment_status != "Processed" && $payment_status != "Refunded" && $payment_status != "Reversed") {
				$error_msg .= 'Funds have not been cleared yet. Transaction will be processed when the funds clear!';
				wcfmvm_create_log($error_msg);
				return false;
			}
		}

		//Check txn type
		if ($transaction_type == "new_case") {
			wcfmvm_create_log('This is a dispute case. Nothing to do here.');
			return true;
		}
			
		$member_id = urldecode($this->ipn_data['custom']);
		$this->ipn_data['custom'] = $member_id;
		wcfmvm_create_log('Member ID: ' . $member_id);
			
		//Handle refunds
		if ( $gross_total < 0 ) {
			// This is a refund or reversal
			wcfmvm_create_log('This is a refund notification. Refund amount: '.$gross_total);
			return true;
		}
		if (isset($this->ipn_data['reason_code']) && $this->ipn_data['reason_code'] == 'refund') {
			wcfmvm_create_log('This is a refund notification. Refund amount: '.$gross_total);
			return true;            
		}

		if (($transaction_type == "subscr_signup")) {
			wcfmvm_create_log('Subscription signup IPN received... (handled by the subscription IPN handler)');

			$wcfm_membership = get_user_meta( $member_id, 'temp_wcfm_membership', true );
			
			// Subscription Price and Currency Checks
			$membership_id = absint($wcfm_membership);
			if ( $membership_id ) {
				$subscription      = (array) get_post_meta( $membership_id, 'subscription', true );
				$subscription_type = isset( $subscription['subscription_type'] ) ? $subscription['subscription_type'] : 'one_time';
				
				if ( $subscription_type != 'one_time' ) {
					// The subscription request PayPal turned into this profile was built and
					// submitted CLIENT SIDE, so every profile parameter the member's browser
					// posted has to be validated against the plan - not just the recurring
					// amount, also the periods and the recur count, otherwise a member can
					// subscribe at the right price but on a tampered interval, or slip in a
					// trial leg the plan does not have.
					//
					// The expected values mirror wcfm_memberships_payment_paypal(), which
					// builds the request: `a3` is the plan's billing amount with membership
					// tax applied (`mc_amount3` here), `p3 t3` come back as `period3`, the
					// optional trial leg as `mc_amount1` / `period1`, and `srt` as
					// `recur_times`. (There is no `subscription_amt` key on a plan - reading
					// one made every recurring signup fall back to 1 and fail the check.)
					$decimals             = wc_get_price_decimals();
					$billing_amt          = isset( $subscription['billing_amt'] ) ? floatval( $subscription['billing_amt'] ) : 0;
					$billing_period       = isset( $subscription['billing_period'] ) ? $subscription['billing_period'] : '1';
					$billing_period_type  = isset( $subscription['billing_period_type'] ) ? $subscription['billing_period_type'] : 'M';
					$billing_period_count = isset( $subscription['billing_period_count'] ) ? absint( $subscription['billing_period_count'] ) : 1;
					$trial_period         = isset( $subscription['trial_period'] ) ? $subscription['trial_period'] : '';
					$trial_period_type    = isset( $subscription['trial_period_type'] ) ? $subscription['trial_period_type'] : 'M';
					$trial_amt            = isset( $subscription['trial_amt'] ) ? $subscription['trial_amt'] : '0';
					if ( !empty( $trial_period ) && empty( $trial_amt ) ) {
						$trial_amt = 1;
					}

					$payment_currency = strtoupper(get_woocommerce_currency());
					$payment_currency = apply_filters( 'wcfm_membership_payment_currency', $payment_currency );

					$subscription_amt = wc_format_decimal( wcfmvm_membership_tax_price( $billing_amt ), $decimals );
					$mc_amount3 = wc_format_decimal( isset( $this->ipn_data['mc_amount3'] ) ? floatval( $this->ipn_data['mc_amount3'] ) : 0, $decimals );
					$mc_currency = isset( $this->ipn_data['mc_currency'] ) ? strtoupper( $this->ipn_data['mc_currency'] ) : '';

					if ( $subscription_amt !== $mc_amount3 ) {
						wcfmvm_create_log('Subscription fee AMOUNT mismatch. Expected: ' . $subscription_amt . ' Received: ' . $mc_amount3 . ' Aborting.');
						return false;
					}

					if ( $payment_currency != $mc_currency ) {
						wcfmvm_create_log('Subscription fee CURRENCY mismatch. Expected: ' . $payment_currency . ' Received: ' . $mc_currency . ' Aborting.');
						return false;
					}

					$expected_period3 = strtoupper( trim( $billing_period . ' ' . $billing_period_type ) );
					$ipn_period3 = isset( $this->ipn_data['period3'] ) ? strtoupper( trim( $this->ipn_data['period3'] ) ) : '';
					if ( $expected_period3 !== $ipn_period3 ) {
						wcfmvm_create_log('Subscription BILLING PERIOD mismatch. Expected: ' . $expected_period3 . ' Received: ' . $ipn_period3 . ' Aborting.');
						return false;
					}

					$ipn_period1 = isset( $this->ipn_data['period1'] ) ? strtoupper( trim( $this->ipn_data['period1'] ) ) : '';
					if ( !empty( $trial_period ) ) {
						$expected_a1 = wc_format_decimal( wcfmvm_membership_tax_price( floatval( $trial_amt ) ), $decimals );
						$ipn_amount1 = wc_format_decimal( isset( $this->ipn_data['mc_amount1'] ) ? floatval( $this->ipn_data['mc_amount1'] ) : 0, $decimals );
						$expected_period1 = strtoupper( trim( $trial_period . ' ' . $trial_period_type ) );
						if ( ( $expected_period1 !== $ipn_period1 ) || ( $expected_a1 !== $ipn_amount1 ) ) {
							wcfmvm_create_log('Subscription TRIAL mismatch. Expected: ' . $expected_a1 . ' / ' . $expected_period1 . ' Received: ' . $ipn_amount1 . ' / ' . $ipn_period1 . ' Aborting.');
							return false;
						}
					} elseif ( $ipn_period1 !== '' ) {
						wcfmvm_create_log('Subscription has a TRIAL leg (' . $ipn_period1 . ') but the plan has none. Aborting.');
						return false;
					}

					if ( $billing_period_count > 1 ) {
						$ipn_recur_times = isset( $this->ipn_data['recur_times'] ) ? absint( $this->ipn_data['recur_times'] ) : 0;
						if ( $ipn_recur_times !== $billing_period_count ) {
							wcfmvm_create_log('Subscription RECUR COUNT mismatch. Expected: ' . $billing_period_count . ' Received: ' . $ipn_recur_times . ' Aborting.');
							return false;
						}
					}
				}
			}

			if( $wcfm_membership ) {
				update_user_meta( $member_id, 'wcfm_membership_paymode', 'paypal' );
				update_user_meta( $member_id, 'wcfm_paypal_subscription_id', $this->ipn_data['subscr_id'] );
				$required_approval = get_post_meta( $wcfm_membership, 'required_approval', true ) ? get_post_meta( $wcfm_membership, 'required_approval', true ) : 'no';
				if( $required_approval != 'yes' ) {
					$WCFMvm->register_vendor( $member_id );
				} else {
					$wcfm_is_send_approval_reminder_admin = get_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', true );
					if( !$wcfm_is_send_approval_reminder_admin ) {
						$WCFMvm->send_approval_reminder_admin( $member_id );
						update_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', 'yes' );
					}
				}
			}
			$WCFMvm->store_subscription_data( $member_id, 'paypal_subs', $this->ipn_data['subscr_id'], $transaction_type, 'Completed', $this->post_string );
			
			//wcfmvm_handle_subsc_signup_stand_alone( $member_id, $this->ipn_data );
			return true;
		} else if (($transaction_type == "subscr_cancel") || ($transaction_type == "subscr_eot")) {
			// Code to handle the IPN for subscription cancellation
			$wcfm_membership_id = get_user_meta( $member_id, 'wcfm_membership', true );
			wcfm_log('Subscription cancellation PayPal IPN received...');
			wcfm_log( "Membership Expiry by PayPal :: " . $member_id . " <=> " . $wcfm_membership_id . " <=> " . $transaction_type );
			// Ledger first: the cancellation cleans the profile metas up, and this row
			// must not re-create wcfm_subscription_profile_id after that.
			$WCFMvm->store_subscription_data( $member_id, 'paypal_subs', $this->ipn_data['subscr_id'], $transaction_type, 'Cancelled', $this->post_string );
			$WCFMvm->wcfmvm_vendor_membership_cancel( $member_id, $wcfm_membership_id );
			return true;
		} else if ($transaction_type == "subscr_failed") {
			// A failed collection is NOT a cancellation: PayPal retries it (the request
			// sets `sra`) and sends subscr_cancel / subscr_eot when it finally gives up.
			// Tearing the membership down here demoted the vendor days before the retry
			// succeeded - the renewal then arrived for a member who no longer had a
			// membership to renew.
			$subscr_id = isset( $this->ipn_data['subscr_id'] ) ? $this->ipn_data['subscr_id'] : '';
			wcfm_log( 'Subscription payment failed PayPal IPN received... member ' . $member_id . ' (' . $subscr_id . '), awaiting PayPal retry or cancellation.' );
			if ( $subscr_id ) {
				$WCFMvm->store_subscription_data( $member_id, 'paypal_subs', $subscr_id, $transaction_type, 'Failed', $this->post_string );
			}
			return true;
		} else if ($transaction_type == "subscr_payment") {
			// A recurring collection: the profile's first charge right after signup, or
			// a renewal of a later cycle.
			$subscr_id = isset( $this->ipn_data['subscr_id'] ) ? $this->ipn_data['subscr_id'] : '';
			$wcfm_membership = get_user_meta( $member_id, 'temp_wcfm_membership', true );

			if ( $wcfm_membership ) {
				// First collection while registration is still pending - the payment IPN
				// overtook the signup IPN. Activate exactly like the signup path does.
				update_user_meta( $member_id, 'wcfm_membership_paymode', 'paypal' );
				if ( $subscr_id ) {
					update_user_meta( $member_id, 'wcfm_paypal_subscription_id', $subscr_id );
				}
				$required_approval = get_post_meta( $wcfm_membership, 'required_approval', true ) ? get_post_meta( $wcfm_membership, 'required_approval', true ) : 'no';
				if( $required_approval != 'yes' ) {
					$WCFMvm->register_vendor( $member_id );
				} else {
					$wcfm_is_send_approval_reminder_admin = get_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', true );
					if( !$wcfm_is_send_approval_reminder_admin ) {
						$WCFMvm->send_approval_reminder_admin( $member_id );
						update_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', 'yes' );
					}
				}
				$WCFMvm->store_subscription_data( $member_id, 'paypal', $txn_id, $transaction_type, $payment_status, $this->post_string );
			} else {
				$membership_id = absint( get_user_meta( $member_id, 'wcfm_membership', true ) );
				if ( !$membership_id ) {
					wcfmvm_create_log( 'Recurring payment ' . $txn_id . ' received for member ' . $member_id . ' who holds no membership. Needs manual attention.' );
					return false;
				}

				// The collection must come from the profile this member subscribed with.
				$profile_id = get_user_meta( $member_id, 'wcfm_subscription_profile_id', true );
				if ( !$profile_id ) {
					$profile_id = get_user_meta( $member_id, 'wcfm_paypal_subscription_id', true );
				}
				if ( $profile_id && $subscr_id && ( $profile_id !== $subscr_id ) ) {
					wcfmvm_create_log( 'Recurring payment ' . $txn_id . ' is for profile ' . $subscr_id . ' but member ' . $member_id . ' subscribed with profile ' . $profile_id . '. Aborting.' );
					return false;
				}

				// The profile's first collection arrives right after the signup IPN has
				// already registered the member and scheduled the period it pays for -
				// only the ledger row is due for it. Later collections are renewals.
				$prior_payments = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcfm_membership_subscription WHERE vendor_id = %d AND membership_id = %d AND transaction_type IN ( 'subscr_payment', 'subscription_renewal' )", $member_id, $membership_id ) );

				if ( !$prior_payments ) {
					$WCFMvm->store_subscription_data( $member_id, 'paypal', $txn_id, $transaction_type, $payment_status, $this->post_string );
				} else {
					// A renewal collects the plan's recurring amount - the trial, if any,
					// was the first collection.
					$subscription = (array) get_post_meta( $membership_id, 'subscription', true );
					$decimals     = wc_get_price_decimals();
					$billing_amt  = isset( $subscription['billing_amt'] ) ? floatval( $subscription['billing_amt'] ) : 0;
					$expected_amt = wc_format_decimal( wcfmvm_membership_tax_price( $billing_amt ), $decimals );
					$received_amt = wc_format_decimal( floatval( $this->ipn_data['mc_gross'] ), $decimals );
					$payment_currency = strtoupper(get_woocommerce_currency());
					$payment_currency = apply_filters( 'wcfm_membership_payment_currency', $payment_currency );
					$mc_currency = isset( $this->ipn_data['mc_currency'] ) ? strtoupper( $this->ipn_data['mc_currency'] ) : '';

					if ( $expected_amt !== $received_amt ) {
						wcfmvm_create_log( 'Renewal AMOUNT mismatch for member ' . $member_id . '. Expected: ' . $expected_amt . ' Received: ' . $received_amt . ' Aborting.' );
						return false;
					}
					if ( $payment_currency != $mc_currency ) {
						wcfmvm_create_log( 'Renewal CURRENCY mismatch for member ' . $member_id . '. Expected: ' . $payment_currency . ' Received: ' . $mc_currency . ' Aborting.' );
						return false;
					}

					$renewed = $WCFMvm->wcfmvm_membership_renewal( $member_id, array(
						'paymode'             => 'paypal',
						'transaction_id'      => $txn_id,
						'transaction_details' => $this->post_string,
						'membership_id'       => $membership_id,
					) );
					if ( !$renewed ) {
						return false;
					}
				}
			}
		} else {
			$cart_items = array();
			wcfmvm_create_log('Transaction Type: Buy Now/Subscribe');
			$item_number = $this->ipn_data['item_number'];
			$item_name = $this->ipn_data['item_name'];
			$quantity = $this->ipn_data['quantity'];
			$mc_gross = $this->ipn_data['mc_gross'];
			$mc_currency = $this->ipn_data['mc_currency'];

			$current_item = array(
					'item_number' => $item_number,
					'item_name' => $item_name,
					'quantity' => $quantity,
					'mc_gross' => $mc_gross,
					'mc_currency' => $mc_currency,
			);

			array_push($cart_items, $current_item);
			
			$wcfm_membership = get_user_meta( $member_id, 'temp_wcfm_membership', true );

			$membership_id = absint($wcfm_membership);
			if ( $membership_id ) {
				$subscription			= (array) get_post_meta( $membership_id, 'subscription', true );
				$subscription_type		= isset( $subscription['subscription_type'] ) ? $subscription['subscription_type'] : 'one_time';

				if ( $subscription_type == 'one_time' ) {
					// The payment form posts the amount with membership tax applied, so the
					// expected total has to include it too.
					$one_time_amt		= isset( $subscription['one_time_amt'] ) ? floatval($subscription['one_time_amt']) : 1;
					$one_time_amt		= wc_format_decimal( wcfmvm_membership_tax_price( $one_time_amt ), wc_get_price_decimals() );
					$payment_currency 	= strtoupper(get_woocommerce_currency());
					$payment_currency 	= apply_filters( 'wcfm_membership_payment_currency', $payment_currency );

					if ( $one_time_amt !== wc_format_decimal( floatval( $mc_gross ), wc_get_price_decimals() ) ) {
						wcfmvm_create_log('Membership fee AMOUNT mismatch. Check you paypal transaction to verify. Aborting the process.');
						return false;
					}

					if ( $payment_currency != $mc_currency ) {
						wcfmvm_create_log('Membership fee CURRENCY mismatch. Check you paypal transaction to verify. Aborting the process.');
						return false;
					}
				}
			}

			if( $wcfm_membership ) {
				update_user_meta( $member_id, 'wcfm_membership_paymode', 'paypal' );
				$required_approval = get_post_meta( $wcfm_membership, 'required_approval', true ) ? get_post_meta( $wcfm_membership, 'required_approval', true ) : 'no';
				if( $required_approval != 'yes' ) {
					$WCFMvm->register_vendor( $member_id );
				} else {
					$wcfm_is_send_approval_reminder_admin = get_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', true );
					if( !$wcfm_is_send_approval_reminder_admin ) {
						$WCFMvm->send_approval_reminder_admin( $member_id );
						update_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', 'yes' );
					}
				}
			}
			$WCFMvm->store_subscription_data( $member_id, 'paypal', $txn_id, $transaction_type, $payment_status, $this->post_string );
		}

		/*** Do Post payment operation and cleanup ***/
		//Save the transaction data
		wcfmvm_create_log('Saving transaction data to the database table.');
		$this->ipn_data['gateway'] = 'paypal';
		$this->ipn_data['status'] = $this->ipn_data['payment_status'];
		wcfmvm_create_log('Transaction data saved.');
		
		//Trigger the PayPal IPN processed action hook (so other plugins can can listen for this event).
		do_action('wcfmvm_paypal_ipn_processed', $this->ipn_data);
		
		do_action('wcfmvm_payment_ipn_processed', $this->ipn_data);

		// The transaction was claimed before processing started, see
		// wcfmvm_validate_and_create_membership().
		return true;
	}

	function wcfmvm_validate_ipn() {
		//Generate the post string from the _POST vars aswell as load the _POST vars into an arry
		$post_string = '';
		foreach ($_POST as $field=>$value) {
			$this->ipn_data["$field"] = $value;
			$post_string .= $field.'='.urlencode(stripslashes($value)).'&';
		}

		$this->post_string = $post_string;
		wcfmvm_create_log('Post string : '. $this->post_string);

		//IPN validation check
		if($this->validate_ipn_using_remote_post()) {
			//We can also use an alternative validation using the validate_ipn_using_curl() function
			return true;
		} else {
			return false;
		}
  }

	function validate_ipn_using_remote_post() {
		wcfmvm_create_log( 'Checking if PayPal IPN response is valid');
		
		// Get received values from post data
		$validate_ipn = array( 'cmd' => '_notify-validate' );
		$validate_ipn += wc_clean( wp_unslash( $_POST ) );

		// Send back post vars to paypal
		$params = array(
						'body'        => $validate_ipn,
						'timeout'     => 60,
						'httpversion' => '1.1',
						'compress'    => false,
						'decompress'  => false,
						'user-agent'  => 'WCFM - WooCommerce Multivendor Membership',
		);

		// Post back to get a response.
		$connection_url = $this->sandbox_mode ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
		wcfmvm_create_log('Connecting to: ' . $connection_url);
		$response = wp_safe_remote_post( $connection_url, $params );

		//The following two lines can be used for debugging
		//wcfmvm_create_log( 'IPN Request: ' . print_r( $params, true ) );
		//wcfmvm_create_log( 'IPN Response: ' . print_r( $response, true ) );

		// Check to see if the request was valid.
		if ( ! is_wp_error( $response ) && strstr( $response['body'], 'VERIFIED' ) ) {
			wcfmvm_create_log('IPN successfully verified.');
			return true;
		}

		// Invalid IPN transaction. Check the log for details.
		wcfmvm_create_log('IPN validation failed.');
		if ( is_wp_error( $response ) ) {
			wcfmvm_create_log('Error response: ' . $response->get_error_message());
		}
		return false;        
	}
}

// Start of IPN handling (script execution)
$ipn_handler_instance = new wcfmvm_paypal_ipn_handler();

// Validate the IPN
if ($ipn_handler_instance->wcfmvm_validate_ipn()) {
	wcfmvm_create_log('Creating product Information to send.');

	if(!$ipn_handler_instance->wcfmvm_validate_and_create_membership()) {
		wcfmvm_create_log('IPN product validation failed.');
	}
}
wcfmvm_create_log('Paypal class finished.');